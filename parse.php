<?php
/**
 * @author Alina Vinogradova (xvinog00@stud.fit.vutbr.cz)
 * parse.php - syntax analysis and XML code generator for IPPcode23 language
 * Project 1 for IPP university course 2022/23
 */


require 'src/scanner.php';
ini_set('display_errors', 'stderr');
$input = fopen('php://stdin', 'r');

# Parser errors
const eParameter = 10;    # Error - bad parameter
const eHeader = 21;       # Error - bad header
const eOpcode = 22;       # Error - bad opcode
const eOther = 23;        # Error - other lexical or syntax
const eOK = 0;            # No error - success

# Tokens
const tHeader = 0;        # Token Header
const tOpcode = 1;        # Token Operator code
const tVar    = 2;        # Token Variable
const tConst  = 3;        # Token Constant
const tLabel  = 4;        # Token Label
const tType   = 5;        # Token Type
const tEOF    = 6;        # Token End of file

$cnt;                     # Instruction counter
$XMLcode;                 # XML code output

$token = array();

# Instructions
$instructions = array(
    0 => "MOVE",
    1 => "CREATEFRAME",
    2 => "PUSHFRAME",
    3 => "POPFRAME",
    4 => "DEFVAR",
    5 => "CALL",
    6 => "RETURN",
    7 => "PUSHS",
    8 => "POPS",
    9 => "ADD",
    10 => "SUB",
    11 => "MUL",
    12 => "IDIV",
    13 => "LT",
    14 => "GT",
    15 => "EQ",
    16 => "AND",
    17 => "OR",
    18 => "NOT",
    19 => "INT2CHAR",
    20 => "STRI2INT",
    21 => "READ",
    22 => "WRITE",
    23 => "CONCAT",
    24 => "STRLEN",
    25 => "GETCHAR",
    26 => "SETCHAR", 
    27 => "TYPE",
    28 => "LABEL",
    29 => "JUMP",
    30 => "JUMPIFEQ",
    31 => "JUMPIFNEQ",
    32 => "EXIT",
    # Debug
    33 => "DPRINT", 
    34 => "BREAK"
);

function put_xml($code){
    global $XMLcode;
    $XMLcode .= $code;
}

function exit_error($message, $code){
    if($code != eOK) fprintf(STDERR, $message."\n");
    exit($code);
}

function check_argument($expected, $token){
    global $instructions;
    if(sizeof($token) - 1 > $expected){
        exit_error("Error: wrong argument count at ".$instructions[$token[0][1]], eOther);
    } else return eOK;
}

function is_eof($token){
    return ($token[0][0] == tEOF);
}

function process_special_char($string){
    $string = preg_replace("~[&]~", "&amp;", $string);
    $string = preg_replace("~[>]~", "&gt;", $string);
    $string = preg_replace("~[<]~", "&lt;", $string);
    return $string;
}

function process_const($const){
    $result = array();
    if(preg_match("~^int@[+|-]?\d+~", $const)){
        $result[0] = preg_replace("~^int@~", '', $const);
        $result[1] = "int";
    } elseif(preg_match("~^string@~", $const)){
        if(preg_match("~\\\\(?!\d{3})~", $const)){
            exit_error("Error: bad constant argument", eOther);
        }
        $result[0] = preg_replace("~^string@~", '', $const);
        $result[1] = "string";
    } elseif(preg_match("~^bool@(true|false)~", $const)){
        $result[0] = preg_replace("~^bool@~", '', $const);
        $result[1] = "bool";
    } elseif(preg_match("~^nil@nil$~", $const)){
        $result[0] = preg_replace("~^nil@~", '', $const);
        $result[1] = "nil";
    } else exit_error("Error: wrong const type", eOther);

    if(preg_match("~([&]|[>]|[<])~", $result[0])){
        $result[0] = process_special_char($result[0]);
    }

    return $result;
}

function get_var($token){
    if(preg_match("~([&]|[>]|[<])~", $token)){
        $token = process_special_char($token);
    }
    return $token;
}

function get_symb($token, $type){
    $result = array();

    if($type == tVar) {
        $result[0] = get_var($token);
        $result[1] = tVar;
    } else $result = process_const($token);
    return $result; 
}

function parse(){
    global $token;
    global $XMLcode;
    global $argc;
    global $argv;

    $shortopt = "h";
    $longopt = array("help");
    $options = getopt($shortopt, $longopt);

    // print_r($options);
    // print($argc."\n");
    // print_r($argv);

    if(array_key_exists("help", $options) || array_key_exists("h", $options)) {
        if($argc == 2 && ($argv[1] == "-h" || $argv[1] == "--help")) {
            echo "parse.php help:\n";
            echo "\tthis script reads the source code in IPPcode23 from standard input\n";
            echo "\tchecks the code for lexical and syntactic correctness\n";
            echo "\tand prints it to standard XML representation of the program according to the specification\n\n";
            echo "usage:\n";
            echo "\t--help\t\t\t\tprints this message\n";
            echo "\tparse.php <input_file\t\tforward input from file\n";
            exit(eOK);
        } else exit_error("Error: bad parameter given", eParameter);
    }

    $XMLcode = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"."\n";

    $token = get_token();
    check_header();

    while(!is_eof($token = get_token())){
        process_token($token);
    }

    put_xml("</program>\n");
    echo $XMLcode;
}

function check_header(){
    global $token;
    global $header;
    global $XMLcode;

    if($token[0][0] == tHeader && $header == 0){
        put_xml("<program language=\"IPPcode23\">"."\n");
        return eOK;
    } else exit_error("Error: wrong header", eHeader);
}

function process_token($token){
    global $instructions;
    global $cnt;
    global $XMLcode;
    $argcnt = 0;

    if($token[0][0] != tOpcode) exit_error("Error: wrong instruction", eOpcode);

    $inst = $instructions[$token[0][1]];
    
    switch ($inst) {
        # 0 arguments instructions
        case 'CREATEFRAME':
        case 'PUSHFRAME':
        case 'POPFRAME':    
        case 'RETURN':
        case 'BREAK':
            check_argument(0, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">"."\n");
            break;
        # 1 argument instructions
        case 'DEFVAR':  
        case 'POPS':
            # Argument is tVar
            check_argument(1, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            
            if($token[1][0] == tVar){
                $var = get_var($token[1][1]);
                $argcnt++;
                put_xml("\t\t<arg$argcnt type=\"var\">$var</arg$argcnt>\n");
            } else exit_error("Error: wrong argument at $inst", eOther);
            break;
        case 'PUSHS':   
        case 'WRITE':   
        case 'EXIT':    
        case 'DPRINT':
            # Argument is symbol: tVar ot tConst
            check_argument(1, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            
            if($token[1][0] == tVar || $token[1][0] == tConst){
                $symbol = get_symb($token[1][1], $token[1][0]);
                $argcnt++;

                if($symbol[1] == tVar) {
                    put_xml("\t\t<arg$argcnt type=\"var\">$symbol[0]</arg$argcnt>\n");
                } else {
                    put_xml("\t\t<arg$argcnt type=\"$symbol[1]\">$symbol[0]</arg$argcnt>\n");
                }
            } else exit_error("Error: wrong argument at $inst", eOther);
            break;
        case 'CALL':   
        case 'LABEL':   
        case 'JUMP': 
            # Argument is tLabel
            check_argument(1, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            if($token[1][0] == tLabel){
                $label = $token[1][1];
                $argcnt++;
                if(preg_match("~^[_a-zA-Z-$&%*!?][a-zA-Z0-9-$&%!?]*$~", $label)){
                    put_xml("\t\t<arg$argcnt type=\"label\">".$label."</arg$argcnt>\n");
                } else {
                    exit_error("Error: wrong label name at $inst", eOther);
                }
            } else exit_error("Error: wrong argument at $inst", eOther);
            break;
        case 'INT2CHAR':
        case 'STRLEN':
        case 'TYPE':
        case 'MOVE':
        case 'NOT':
            # 2 arguments: tVar and symbol (therefore is either tVar or tConst)
            check_argument(2, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            if($token[1][0] == tVar){
                $var = get_var($token[1][1]);
                $argcnt++;
                put_xml("\t\t<arg$argcnt type=\"var\">$var</arg$argcnt>\n");
            } else exit_error("Error: wrong argument at $inst", eOther);

            if($token[2][0] == tVar || $token[2][0] == tConst){
                $symbol = get_symb($token[2][1], $token[2][0]);
                $argcnt++;
                if($symbol[1] == tVar){
                    put_xml("\t\t<arg$argcnt type=\"var\">$symbol[0]</arg$argcnt>\n");
                } else {
                    put_xml("\t\t<arg$argcnt type=\"$symbol[1]\">$symbol[0]</arg$argcnt>\n");
                }
            } else exit_error("Error: wrong argument at $inst", eOther);

            break;

        case 'READ':
            # 2 arguments: tVar and tType
            check_argument(2, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            
            if($token[1][0] == tVar){
                $var = get_var($token[1][1]);
                $argcnt++;
                put_xml("\t\t<arg$argcnt type=\"var\">$var</arg$argcnt>\n");
            } else exit_error("Error: wrong argument at $inst", eOther);

            if($token[2][0] == tType){
                $type = $token[2][1];
                $argcnt++;
                put_xml("\t\t<arg$argcnt type=\"type\">$type</arg$argcnt>\n");
            } else exit_error("Error: wrong argument at $inst", eOther);
            break;
        case 'CONCAT':     
        case 'GETCHAR':
        case 'SETCHAR':    
        case 'STRI2INT':    
        case 'ADD':
        case 'SUB':     
        case 'MUL':
        case 'IDIV':    
        case 'LT':
        case 'GT':
        case 'EQ':
        case 'AND':
        case 'OR':
            # 3 arguments: tVar, symbol and symbol (tVar or tConst)
            check_argument(3, $token);
            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");

            if($token[1][0] == tVar){
                $var = get_var($token[1][1]);
                $argcnt++;
                put_xml("\t\t<arg$argcnt type=\"var\">$var</arg$argcnt>\n");
            } else exit_error("Error: wrong argument at $inst", eOther);

            for ($i=2; $i <= 3; $i++) { 
                if($token[$i][0] == tVar || $token[$i][0] == tConst){
                    $symbol = get_symb($token[$i][1], $token[$i][0]);
                    $argcnt++;
                    if($symbol[1] == tVar){
                        put_xml("\t\t<arg$argcnt type=\"var\">$symbol[0]</arg$argcnt>\n");
                    } else {
                        put_xml("\t\t<arg$argcnt type=\"$symbol[1]\">$symbol[0]</arg$argcnt>\n");
                    }
                } else exit_error("Error: wrong argument at $inst", eOther);
            }
            break;
        case 'JUMPIFEQ':    
        case 'JUMPIFNEQ':
            # 3 arguments: tLabel, symbol and symbol
            check_argument(3, $token);

            $cnt++;
            put_xml("\t<instruction order=\"$cnt\" opcode=\"".$inst."\">\n");
            
            if($token[1][0] == tLabel){
                $label = $token[1][1];
                $argcnt++;
                if(preg_match("~^[a-zA-Z_\-$&%*][a-zA-Z0-9_\-$&%*]*$~", $label)){
                    put_xml("\t\t<arg$argcnt type=\"label\">".$label."</arg$argcnt>\n");
                } else exit_error("Error: wrong label at $inst", eOther);
            } else exit_error("Error: wrong argument at $inst", eOther);

            for ($i=2; $i <= 3; $i++) { 
                if($token[$i][0] == tVar || $token[$i][0] == tConst){
                    $symbol = get_symb($token[$i][1], $token[$i][0]);
                    $argcnt++;
                    if($symbol[1] == tVar){
                        put_xml("\t\t<arg$argcnt type=\"var\">$symbol[0]</arg$argcnt>\n");
                    } else {
                        put_xml("\t\t<arg$argcnt type=\"$symbol[1]\">$symbol[0]</arg$argcnt>\n");
                    }
                } else exit_error("Error: wrong argument at $inst", eOther);
            }
            break;
        default:
            exit_error("Error: wrong instruction at $inst", eOpcode);
            break;
    }

    put_xml("\t</instruction>\n");
}

parse();