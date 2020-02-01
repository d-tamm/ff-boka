<?php
use FFBoka\Category;
use FFBoka\Image;

session_start();
require(__DIR__."/inc/common.php");

// TODO: send appropriate headers
// TODO: authenticate
switch ($_GET['type']) {
case "category":
    $cat = new Category($_GET['id']);
    echo $cat->image;
    break;

case "itemImage":
    $image= new Image($_GET['id']);
    echo $image->image;
    break;
}