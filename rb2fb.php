#!/usr/bin/php
<?php
/*

Tool for converting Raiffeisenbank eKonto payments to FlexiBee XML
Author: Maxim Krušina, maxim@mfcc.cz, Massimo Filippi, s.r.o.
Homepage: https://github.com/massimo-filippi/flexibee-tools

Notes:
Source encoding: Windows 1250
Destinantion encoding: UTF-8

Other resources:
http://www.abclinuxu.cz/blog/doli/2012/4/konverze-bankovnich-vypisu-rb-do-formatu-abo

ToDo:
- Add support for CLI parameters
- Add output file option
- Add UI - ie https://www.sveinbjorn.org/platypus


Usage:
======
- Set all parameters in section bellow (User specific settings)
- Export file from RB / eKonto via: Historie účtu / Pohyby na účtu / Formát CSV (button under the listing)
- Rename CSV file to import.csv and put it into same solder as this script
- Run: ./rb2fb.php > output.txt

*/


// User specific settings

$BankCode               = "5500";
$BankCountryID          = "CZ";
$BankName               = "Raiffeisenbank a. s.";
$AccNoID                = "1234567890";                 // Number of your bank account
$AccNoCC                = "";
$AccName                = "Name of account";            // Name of the account
$AccCcy                 = "CZK";                        // Currency code (ie CZK)
$AccCcyText             = "Koruna česká";               // Name of currency (ie Koruna česká)
$ChargesCcy             = "CZK";                        // DOnt remebmer :) (ie CZK)


// Include values from config file, if exists

if(file_exists('config.php'))
    include 'config.php';


// Deine constants (CSV offsets)

const DATUM             = 0;
const CAS               = 1;
const POZNAMKA          = 2;
const NAZEV_UCTU        = 3;
const CISLO_UCTU        = 4;
const DATUM_ODEPSANI    = 5;
const VALUTA            = 6;
const TYP               = 7;
const KOD_TRANSAKCE     = 8;
const VARIABILNI_SYMBOL = 9;
const KONSTANTNI_SYMBOL = 10;
const SPECIFICKY_SYMBOL = 11;
const CASTKA            = 12;
const POPLATEK          = 13;
const SMENA             = 14;
const ZPRAVA            = 15;



// Convert Win 1250 > UTF-8

function w1250_to_utf8($text) {
    // map based on:
    // http://konfiguracja.c0.pl/iso02vscp1250en.html
    // http://konfiguracja.c0.pl/webpl/index_en.html#examp
    // http://www.htmlentities.com/html/entities/
    $map = array(
        chr(0x8A) => chr(0xA9),
        chr(0x8C) => chr(0xA6),
        chr(0x8D) => chr(0xAB),
        chr(0x8E) => chr(0xAE),
        chr(0x8F) => chr(0xAC),
        chr(0x9C) => chr(0xB6),
        chr(0x9D) => chr(0xBB),
        chr(0xA1) => chr(0xB7),
        chr(0xA5) => chr(0xA1),
        chr(0xBC) => chr(0xA5),
        chr(0x9F) => chr(0xBC),
        chr(0xB9) => chr(0xB1),
        chr(0x9A) => chr(0xB9),
        chr(0xBE) => chr(0xB5),
        chr(0x9E) => chr(0xBE),
        chr(0x80) => '&euro;',
        chr(0x82) => '&sbquo;',
        chr(0x84) => '&bdquo;',
        chr(0x85) => '&hellip;',
        chr(0x86) => '&dagger;',
        chr(0x87) => '&Dagger;',
        chr(0x89) => '&permil;',
        chr(0x8B) => '&lsaquo;',
        chr(0x91) => '&lsquo;',
        chr(0x92) => '&rsquo;',
        chr(0x93) => '&ldquo;',
        chr(0x94) => '&rdquo;',
        chr(0x95) => '&bull;',
        chr(0x96) => '&ndash;',
        chr(0x97) => '&mdash;',
        chr(0x99) => '&trade;',
        chr(0x9B) => '&rsquo;',
        chr(0xA6) => '&brvbar;',
        chr(0xA9) => '&copy;',
        chr(0xAB) => '&laquo;',
        chr(0xAE) => '&reg;',
        chr(0xB1) => '&plusmn;',
        chr(0xB5) => '&micro;',
        chr(0xB6) => '&para;',
        chr(0xB7) => '&middot;',
        chr(0xBB) => '&raquo;',
    );
    return html_entity_decode(mb_convert_encoding(strtr($text, $map), 'UTF-8', 'ISO-8859-2'), ENT_QUOTES, 'UTF-8');
}



// Main code

if (($handle = fopen("import.csv", "r")) !== FALSE) {

    $out  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $out .= "<!-- This file stores exported account movements from Gemini/CS 5 application. -->\n";
    $out .= "<AccountMovements version='1.0'\n";
    $out .= "   xmlns='urn:schemas-bscpraha-cz:gemini5:export:movements'\n";
    $out .= "   Official='N'\n";
    // $out .= " StatemDebitTotal='36 104,15'\n";
    // $out .= " StatemCreditTotal='38 345,00'\n";
    // $out .= " StatemTransactionCount='13'\n";
    // $out .= " StatemDebitCount='10'\n";
    // $out .= " StatemCreditCount='3'\n";
    $out .= ">";

    // skip the first line of csv (header row)
    $data = fgetcsv($handle, 1000, ";");

    for ($row = 1; ($data = fgetcsv($handle, 1000, ";")) !== FALSE; $row++) {

        // Convert Win1250 to UTF 8
        foreach ($data as &$text) {
          $text = w1250_to_utf8($text);
        }

        // Date string refarmating
        $date = date_parse_from_format("j.n.Y",$data[DATUM]);
        $date_formated = $date[year].sprintf("%02d", $date[month]).sprintf("%02d", $date[day]);

        $date_valute = date_parse_from_format("j.n.Y",$data[VALUTA]);
        $date_valute_formated = $date_valute[year].sprintf("%02d", $date_valute[month]).sprintf("%02d", $date_valute[day]);


        // XML construction loop
        $out .= "   <Movement ItemNo='".$data[KOD_TRANSAKCE]."'\n";
        $out .= "          Amount='".ltrim($data[CASTKA],"-")."'\n";                                                           // Strip minus sign
        $out .= "          Direction='" . (substr($data[CASTKA],0,1) == "-" ? "D" : "C") . "'\n";                              // D = Debet C = Credit
        $out .= "          PostingDate='".$date_formated."'>\n";                                                               // Date formated as: 20170217
        $out .= "       <PartnerAccNo>".strstr($data[CISLO_UCTU],"/",true)."</PartnerAccNo>\n";                                // Parse part before slash: 190842040287
        $out .= "       <PartnerAccBank>".substr($data[CISLO_UCTU],strpos($data[CISLO_UCTU], "/")+1)."</PartnerAccBank>\n";    // Parse part after slash: 0100
        $out .= "       <PartnerAccName>".$data[NAZEV_UCTU]."</PartnerAccName>\n";
        $out .= "       <ValueDate>".$date_valute_formated."</ValueDate>\n";
        // $out .= "       <PartnerValueDate></PartnerValueDate>\n";
        // $out .= "       <PayAmount>9,99</PayAmount>\n";
        // $out .= "       <ExcRate></ExcRate>\n";
        $out .= "       <ChargesAmount>".ltrim($data[POPLATEK],"-")."</ChargesAmount>\n";
        $out .= "       <ChargesCcy>".$ChargesCcy."</ChargesCcy>\n";
        // $out .= "       <CancelIndicator>0</CancelIndicator>\n";
        // $out .= "       <GeminiRef></GeminiRef>\n";
        $out .= "       <BankRef>".$data[KOD_TRANSAKCE]."</BankRef>\n";
        // $out .= "       <ClientRef></ClientRef>\n";
        $out .= "       <MovementTypeText>".$data[TYP]."</MovementTypeText>\n";
        $out .= "       <BankCode>".$BankCode."</BankCode>\n";
        $out .= "       <BankCountryID>".$BankCountryID."</BankCountryID>\n";
        $out .= "       <BankName>".$BankName."</BankName>\n";
        $out .= "       <AccNoID>".$AccNoID."</AccNoID>\n";
        $out .= "       <AccNoCC>".$AccNoCC."</AccNoCC>\n";
        $out .= "       <AccName>".$AccName."</AccName>\n";
        $out .= "       <AccCcy>".$AccCcy."</AccCcy>\n";
        $out .= "       <AccCcyText>".$AccCcyText."</AccCcyText>\n";
        // $out .= "       <AccTypeID>1</AccTypeID>\n";
        // $out .= "       <AccTypeCode>001</AccTypeCode>\n";
        // $out .= "       <AccTypeText>Běžný účet</AccTypeText>\n";
        $out .= "       <Statistics1>".sprintf("%010d", $data[KONSTANTNI_SYMBOL])."</Statistics1>\n";           // konstatní symbol, 10 znaků
        $out .= "       <Statistics2>".sprintf("%010d", $data[VARIABILNI_SYMBOL])."</Statistics2>\n";           // variabilní symbol, 10 znaků
        $out .= "       <Statistics3>".sprintf("%010d", $data[SPECIFICKY_SYMBOL])."</Statistics3>\n";           // specifický symbol, 10 znaků
        $out .= "       <Statistics4></Statistics4>\n";           // platební titul, 10 znaků
        $out .= "       <Description1>".$data[POZNAMKA]."</Description1>\n";
        // $out .= "       <Description2></Description2>\n";
        // $out .= "       <Description3></Description3>\n";
        // $out .= "       <Description4></Description4>\n";
        // $out .= "       <Description5></Description5>\n";
        // $out .= "       <Description6></Description6>\n";
        // $out .= "       <Description7></Description7>\n";
        // $out .= "       <Description8></Description8>\n";
        $out .= "   </Movement>\n";

    }

    fclose($handle);

    $out  .= "</AccountMovements>\n";
    echo $out;

}

?>
