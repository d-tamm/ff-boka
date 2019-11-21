<?php
use FFBoka\Category;
use FFBoka\item;
<<<<<<< HEAD
use FFBoka\Image;
=======
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418

session_start();
require(__DIR__."/inc/common.php");

// TODO: send appropriate headers
// TODO: authenticate
switch ($_GET['type']) {
case "category":
    $cat = new Category($_GET['id']);
    echo $cat->image;
	break;

<<<<<<< HEAD
case "itemImage":
    $image= new Image($_GET['id']);
    echo $image->image;
=======
case "item":
    $item = new item($_GET['id']);
    echo $item->image;
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418
	break;
}