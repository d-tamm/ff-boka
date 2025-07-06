<?php
session_start();
require __DIR__ . "/inc/common.php";
global $db;

// TODO: authenticate
$path = __DIR__ . "/uploads/" . (int)$_GET[ 'fileId' ];
if ( !is_readable( $path ) ) {
    http_response_code( 404 );
    die();
}
$stmt = $db->prepare( "SELECT * FROM cat_files WHERE fileId=?" );
$stmt->execute( [ $_GET[ 'fileId' ] ] );
if ( !( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) ) {
    http_response_code( 404 );
    die();
}

header( 'Content-Type: ' . mime_content_type( $path ) );

header( "Content-Disposition: attachment; filename=" . $row->filename );

readfile( $path );
