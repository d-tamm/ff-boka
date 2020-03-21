<?php 
/**
 * PHP code related to DB upgrade to version 5
 * Copy all full size category and item images from database to file system 
 */

// Only execute if called from DB upgrade system
if ($_SESSION['dbUpgradeToVer'] != basename(__FILE__, ".php")) {
    header('HTTP/1.0 401 Unauthorized');
    die();
}

echo(str_pad("<p>Moving images from database to file system...", 4096)); flush();

$imgDir = __DIR__ . "/../../img";
if (!is_dir($imgDir)) {
    if (!mkdir($imgDir)) die("<br><b>Problem: Cannot create image directory $imgDir. Aborting.</b></p>");
}

echo(str_pad("<br>* Created image directory.", 4096)); flush();

// Create .htacces file in new img directory to deny direct access
file_put_contents($imgDir . "/.htaccess", "Order Allow, Deny" . PHP_EOL . "Deny from all" );

echo(str_pad("<br>* Created .htaccess file.", 4096)); flush();

// Copy category images to file system
if (!is_dir($imgDir . "/cat")) {
    if (!mkdir($imgDir . "/cat")) die("<br><b>Problem: Cannot create directory $imgDir/cat. Aborting.</b></p>");
}
$stmt = $db->query("SELECT catId, image FROM categories");
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
    if (!is_null($row->image)) file_put_contents("$imgDir/cat/{$row->catId}", $row->image);
}
echo(str_pad("<br>* Moved category images from database to file system.", 4096)); flush();

// Copy item images to file system
if (!is_dir($imgDir . "/item")) {
    if (!mkdir($imgDir . "/item")) die("<br><b>Problem: Cannot create directory $imgDir/item. Aborting.</b></p>");
}
$stmt = $db->query("SELECT imageId, image FROM item_images");
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
    if (!is_null($row->image)) file_put_contents("$imgDir/item/{$row->imageId}", $row->image);
}
echo(str_pad("<br>* Moved item images from database to file system.</p>", 4096)); flush();
?>