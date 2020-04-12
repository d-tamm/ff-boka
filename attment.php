<?php
session_start();
require(__DIR__."/inc/common.php");
global $db, $cfg;

// TODO: authenticate
$path = __DIR__."/uploads/" . (int)$_GET['fileId'];
if (!is_readable($path)) {
    http_response_code(404);
    die();
}
$stmt = $db->prepare("SELECT * FROM cat_files WHERE fileId=?");
$stmt->execute(array($_GET['fileId']));
if (!($row = $stmt->fetch(PDO::FETCH_OBJ)) || !array_key_exists(pathinfo($row->filename, PATHINFO_EXTENSION), $cfg['allowedAttTypes'])) {
    http_response_code(415); // unsupported media type
    die();
}

$ext = strtolower(pathinfo($row->filename, PATHINFO_EXTENSION));
if (is_array($cfg['allowedAttTypes'][$ext])) header('Content-Type: ' . $cfg['allowedAttTypes'][$ext][0]);
else header('Content-Type: ' . $cfg['allowedAttTypes'][$ext]);

header("Content-Disposition: attachment; filename=" . $row->filename);

readfile($path);
