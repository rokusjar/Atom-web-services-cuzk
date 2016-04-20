<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Test:
 * atom.cuzk.cz/opensearch/AD/get?spatial_dataset_identifier_code=CZ-00025712-CUZK_AD_548111&spatial_dataset_identifier_namespace=CUZK&language=cs&crs=5514
 */
include('../functions/httpStatus.php');
// Definice pripojeni do Databaze
include('../passwords.php');
//-----------------------------------------------------------------------------------------------------------------
$originalUrl = $_SERVER['HTTP_X_ORIGINAL_URL'];
$originalUrlPieces = explode('/', $originalUrl);

// Identifikator stahovaci sluzby
$serviceID = $originalUrlPieces[2];
// jedinecny kod datasetu
$code;
// namespace
$namespace;
// pozadovany jazyk vraceneho Atom kanalu. Zatim podporujeme pouze cestinu.
$language;
// souradny system
$crs;
// sestaveny feed - pokud zadani vyhovuje vice souboru
$atom = "";
// pole objektu tridy Resource
$resources = array();
//-----------------------------------------------------------------------------------------------------------------
class Resource{
    
    private $link;
    private $format;
    private $title;
    private $updated;
    
    function __construct($title, $link, $format, $updated){
        $this->link = $link;
        $this->title = $title;
        $this->format = $format;
        $this->updated = $updated;
    }
    
    public function getTitle(){
        return $this->title;
    }
    
    public function getLink(){
        return $this->link;
    }
    
    public function getFormat(){
        return $this->format;
    }
    
    public function getUpdated(){
        return $this->updated;
    }
}
//-----------------------------------------------------------------------------------------------------------------
if(!array_key_exists('spatial_dataset_identifier_code', $_GET)){
    httpStatus("400", "Nebyl zadán parametr: spatial_dataset_identifier_code");
}else{
    $code = $_GET['spatial_dataset_identifier_code'];
}

if(!array_key_exists('spatial_dataset_identifier_namespace', $_GET)){
    httpStatus("400", "Nebyl zadán parametr: spatial_dataset_identifier_namespace");
}else{
    //$namespace = $_GET['spatial_dataset_identifier_namespace'];
    $namespace = "ČÚZK";
}

if(!array_key_exists('language', $_GET)){
    $language = 'cs';
}else{
    $language = $_GET['language'];
}
if(!array_key_exists('crs', $_GET)){
    //TODO poresit
    $crs = '5514';
}else{
    $crs = $_GET['crs'];
}
//-----------------------------------------------------------------------------------------------------------------
/*
 * Vyhledani odpovidajiciho datasetoveho feedu
 * Poznamka: feed_id je URL adresa feedu
 */

try {
    $conn = oci_connect(DBUSER, DBPASSWORD, DBNAME, "AL32UTF8");
    $select = "
        SELECT web_path, format, last_update
        FROM atom_files
        WHERE
            service_id = :id AND
            inspire_dls_code = :code AND
            crs_epsg = :crs
        ";
    
    $stm = oci_parse($conn, $select);
    oci_bind_by_name( $stm, ':id', $serviceID );
    oci_bind_by_name( $stm, ':code', $code );
    oci_bind_by_name( $stm, ':crs', $crs );
    oci_execute($stm);
    
    $select2 = "
        SELECT title
        FROM atom_dataset
        WHERE service_id = :id AND inspire_dls_code = :code
    ";    
    
    $stm2 = oci_parse($conn, $select2);
    oci_bind_by_name( $stm2, ':id', $serviceID );
    oci_bind_by_name( $stm2, ':code', $code );
    oci_execute($stm2);
    
    $titleRow = oci_fetch($stm2);
    $resTitle = oci_result($stm2, 'TITLE');
    
    
    while($row = oci_fetch_array($stm, OCI_NUM+OCI_RETURN_NULLS)){
        $reslink = $row[0];
        $resFormat = $row[1];
        $resUpdated = $row[2];
        $r = new Resource($resTitle, $reslink, $resFormat, $resUpdated);
        $resources[] = $r;
    }
    oci_free_statement($stm);
    oci_free_statement($stm2);
    oci_close($conn);
        
} catch (Exception $e){
    try { if( !is_null($conn) ) oci_close($conn); } catch (Exception $err) {}
    httpStatus("500", $e->getMessage());
}
//-----------------------------------------------------------------------------------------------------------------
if(count($resources) > 0){
    /*
     * sestaveni Atom feedu
     */
    date_default_timezone_set('Europe/Berlin');
    $date = date('Y-m-d\TH:i:s+01:00', time());
    
    $atom = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
    <feed xmlns="http://www.w3.org/2005/Atom" xml:lang="cs">
        <id>1215</id>
        <title>Výsledek operace Get Spatial Dataset</title>
        <subtitle>' . "
            Parametry[
                spatial_dataset_identifier_namespace: $namespace, 
                spatial_dataset_identifier_code: $code, 
                crs: $crs, 
                language: $language
            ] " . '
        </subtitle>
        <updated>$date</updated>
    ';
    
    foreach($resources as $res){
        
        $resLink = $res->getLink();
        $resTitle = $res->getTitle();
        $resFormat = $res->getFormat();
        $resUpdated = $res->getUpdated();
        
        $atom = $atom . "<entry>";
        $atom = $atom . "<id>$resLink</id>";
        $atom = $atom . "<title>" . $resTitle . " - formát " . $resFormat . "</title>";
        $atom = $atom . "<updated>$resUpdated</updated>";
        $atom = $atom . "<link href=\"$resLink\" />";
        $atom = $atom . "</entry>";
    }
    $atom = $atom . '</feed>';
    $xml = new SimpleXMLElement($atom);
    Header('Content-type: text/xml');
    print($xml->asXML());
}
//elseif (count($resources) > 0){
//    header('Location: '.$resources[0]->getLink());
//}
else{
    httpStatus(404, "Dataset nebyl nalezen");
}

?>