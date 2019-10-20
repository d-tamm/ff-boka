<?php
session_start();
require("common.php");

// TODO: authenticate
switch ($_GET['type']) {
case "category":
	$stmt = $db->prepare("SELECT image FROM categories WHERE catID=?");
	break;

case "item":
	$stmt = $db->prepare("SELECT image FROM item_images WHERE imageID=?");
	break;
}
$stmt->execute(array($_GET['ID']));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['image'];

