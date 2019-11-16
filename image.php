<?php
use FFBoka\Category;
use FFBoka\item;

session_start();
require(__DIR__."/inc/common.php");

// TODO: send appropriate headers
// TODO: authenticate
switch ($_GET['type']) {
case "category":
    $cat = new Category($_GET['id']);
    echo $cat->image;
	break;

case "item":
    $item = new item($_GET['id']);
    echo $item->image;
	break;
}