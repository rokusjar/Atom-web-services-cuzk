<?php
/**
 *
 *
 */
include('../functions/httpStatus.php');
// Definice pripojeni do Databaze
include('../passwords.php');
//-----------------------------------------------------------------------------------------------------------------

$originalUrl = $_SERVER['HTTP_X_ORIGINAL_URL'];
$originalUrlPieces = explode('/', $originalUrl);

// Identifikator stahovaci sluzby
$serviceID = $originalUrlPieces[2];
// Hledany text - bude se hledat v nazvech datasetu
$q = $_GET['q'];
// Pole obsahujici objekty tridy Dataset - bude obsahovat datasety jejichz nazev obsahuje text ulozeny v parametru q
$datasets = array();
//-----------------------------------------------------------------------------------------------------------------
/*
 * Trida predstavujici dataset
 *  - title = nazev datasetu
 *  - link = odkaz na dataset
 */
class Dataset {
    private $title;
    private $link;
    
    function __construct($title, $link){
        $this->title = $title;
        $this->link = $link;
    }
    
    public function getTitle(){
        return $this->title;
    }
    
    public function getLink(){
        return $this->link;
    }
}
//-----------------------------------------------------------------------------------------------------------------
/*
 * Vyhledam datasety
 *  - hledam fulltextove v nazvech datasetu
 *  - hledanou hodnotou je parametr q
 *  - naplnim pole $datasets objekty tridy Dataset
 */
try {
    $conn = oci_connect(DBUSER, DBPASSWORD, DBNAME, "AL32UTF8");
    $select = "
        SELECT title, feed_id
        FROM atom_dataset
        WHERE service_id = :id AND title LIKE '%' || :q  || '%'
        ";
    $stm = oci_parse($conn, $select);
    oci_bind_by_name( $stm, ':id', $serviceID );
    oci_bind_by_name( $stm, ':q', $q );
    oci_execute($stm);
    
    while($row = oci_fetch_array($stm, OCI_NUM+OCI_RETURN_NULLS)){
        $title = $row[0];
        $link = $row[1];
        $d = new Dataset($title, $link);
        $datasets[] = $d;
    }
    oci_free_statement($stm);
    oci_close($conn);
        
} catch (Exception $e){
    try { if( !is_null($conn) ) oci_close($conn); } catch (Exception $err) {}
    httpStatus("500", $e->getMessage());
}
/*
 * Sestavim html odpoved
 *
 */
$html = "<!DOCTYPE>";
$html = $html . "<html><head><meta charset=\"UTF-8\"></head><body>";
$html = $html . "<ul class=\"datasetList\">";

foreach($datasets as $dataset){
    $title = $dataset->getTitle();
    $link = $dataset->getLink();
    $html = $html . "<li><a href=\"$link\">$title</a></li>";
}

$html = $html . "</ul>";
$html = $html . "</body></html>";


echo $html;


?>