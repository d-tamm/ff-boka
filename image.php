<?php
use FFBoka\Category;
use FFBoka\Image;

session_start();
require(__DIR__."/inc/common.php");

// TODO: authenticate

switch ($_GET['type']) {
case "category":
    if (is_readable("img/cat/" . (int)$_GET['id'])) {
        header('Content-Type: image/jpeg');
        readfile("img/cat/" . (int)$_GET['id']);
    }
    else {
        header('Content-Type: image/png');
        readfile("resources/noimage.png");
    }
    break;

case "itemImage":
    if (is_readable("img/item/" . (int)$_GET['id'])) {
        header('Content-Type: image/jpeg');
        readfile("img/item/" . (int)$_GET['id']);
    }
    else {
        header('Content-Type: image/png');
        readfile("resources/noimage.png");
    }
    break;
}