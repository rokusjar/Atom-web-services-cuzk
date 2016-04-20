<?php
/**
 * Test:
 * atom.cuzk.cz/opensearch/AD/describe?spatial_dataset_identifier_code=CZ-00025712-CUZK_AD_548111&spatial_dataset_identifier_namespace=CUZK&language=cs
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
// odkaz na nalezeny datasetovy feed
$link;
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
//-----------------------------------------------------------------------------------------------------------------
/*
 * Vyhledani odpovidajiciho datasetoveho feedu
 * Poznamka: feed_id je URL adresa feedu
 */
try {
    $conn = oci_connect(DBUSER, DBPASSWORD, DBNAME, "AL32UTF8");
    $select = "
        SELECT feed_id
        FROM atom_dataset
        WHERE
            service_id = :id AND
            inspire_dls_code = :code AND
            inspire_dls_namespace = :namespace
        ";
    $stm = oci_parse($conn, $select);
    oci_bind_by_name( $stm, ':id', $serviceID );
    oci_bind_by_name( $stm, ':code', $code );
    oci_bind_by_name( $stm, ':namespace', $namespace );
    oci_execute($stm);
    
    while($row = oci_fetch_array($stm, OCI_NUM+OCI_RETURN_NULLS)){
        $link = $row[0];
    }
    oci_free_statement($stm);
    oci_close($conn);
        
} catch (Exception $e){
    try { if( !is_null($conn) ) oci_close($conn); } catch (Exception $err) {}
    httpStatus("500", $e->getMessage());
}

/*
 * Presmerovani na nalezeny feed
 */
header('Location: '.$link);

?>