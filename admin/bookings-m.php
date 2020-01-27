<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\Item;

session_start();
require(__DIR__."/../inc/common.php");
global $cfg;

if ($_GET['sectionId']) $_SESSION['sectionId'] = $_GET['sectionId'];
// This page may only be accessed by registered users 
if (!$_SESSION['authenticatedUser'] || !$_SESSION['sectionId']) {
    header("Location: {$cfg['url']}index.php?message=" . urlencode("Du måste logga in för att använda bokningsöversikten.") . "&redirect=" . urlencode("{$cfg['url']}admin/bookings-d.php?sectionId={$_REQUEST['sectionId']}"));
    die();
}
// Set current section and user
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);
// User must have some sort of admin function
if (!$section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) {
    header("Location: {$cfg['url']}");
    die();
}

/**
 * Displays the items of a category, including child categories, where user has admin access.
 * The IDs of all items included will be added to $_SESSION['itemIds'] array.
 * @param Category $cat Category to show
 * @param User $user
 */
function showCat(Category $cat, User $user) {
    if ($cat->showFor($user, FFBoka::ACCESS_CONFIRM)) {
        // User has access to this or some child category
        echo "<div data-role='collapsible' data-inset='false'>";
        echo "<h3><div class='cat-list-img'>" . embedImage($cat->thumb) . "</div>" . htmlspecialchars($cat->caption) . "</h3>";
        if ($cat->getAccess($user) >= FFBoka::ACCESS_CONFIRM) {
            // User has sufficient access to this category and its items.
            $items = $cat->items();
            if (count($items)) {
                echo "<ul data-role='listview' data-split-icon='info' data-split-theme='a'>";
            }
            foreach ($items as $item) {
                echo "<li class='book-item' id='book-item-{$item->id}'><a href=\"#\">";
                //echo embedImage($item->getFeaturedImage()->thumb);
                echo "<h4" . ($item->active ? "" : " inactive") . "'>" . htmlspecialchars($item->caption) . "</h4>";
                echo "<div class='freebusy-bar' id='freebusy-item-{$item->id}'></div>";
                echo "</a><a href='javascript:showItemDetails({$item->id})'></a>";
                echo "</li>";
                $_SESSION['itemIds'][] = $item->id;
            }
            if (count($items)) echo "<br></ul>\n";
        }
        foreach ($cat->children() as $child) showCat($child, $user);
        echo "</div>";
    }
}

switch ($_REQUEST['action']) {
    case "help":
        echo <<<END
Det finns inte någon hjälptext för den här sidan.
END;
        die();
    case "ajaxGetFreebusy":
        header("Content-Type: application/json");
        // Get Freebusy bars and compile list with unconfirmed items
        $fbList = array();
        foreach ($_SESSION['itemIds'] as $id) {
            $item = new Item($id);
            $fbList["item-$id"] = $item->freebusyBar([
                'start'=>$_REQUEST['start'],
                'scale'=>TRUE,
                'minStatus'=>FFBoka::STATUS_CONFLICT,
            ]);
        }
        die(json_encode([ "freebusy"=>$fbList ]));
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-bookings">
    <?= head("Bokningar " . htmlspecialchars($section->name), $cfg['url'], $currentUser) ?>
    <div role="main" class="ui-content">

    <?php 
    foreach ($section->getMainCategories() as $cat) {
        showCat($cat, $currentUser);
    }
    ?>
    </div><!-- /main -->

    <div data-role="footer" data-position="fixed" data-theme="a">
        <div class="footer-button-left">
            <a href="javascript:scrollDateBookings(-28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-leftleft ui-nodisc-icon">-4 veckor</a>
            <a href="javascript:scrollDateBookings(-7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-left ui-nodisc-icon ui-alt-icon">-1 vecka</a>
        </div>
        <h2 id="bookings-current-range-readable"></h2>
        <div class="footer-button-right">
            <a href="javascript:scrollDateBookings(7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-right ui-nodisc-icon ui-alt-icon">+1 vecka</a>
            <a href="javascript:scrollDateBookings(28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-rightright ui-nodisc-icon ui-alt-icon">+10 veckor</a>
        </div>
    </div><!--/footer-->
</div>