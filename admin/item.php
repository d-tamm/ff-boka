<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Image;
use FFBoka\Section;
global $cfg, $message;

session_start();
require(__DIR__ . "/../inc/common.php");

if (isset($_REQUEST['catId'])) $_SESSION['catId'] = $_REQUEST['catId'];

if (!isset($_SESSION['sectionId']) || !isset($_SESSION['authenticatedUser']) || !isset($_SESSION['catId'])) {
    header("Location: {$cfg['url']}?redirect=admin");
    die();
}

if (isset($_REQUEST['itemId'])) $_SESSION['itemId'] = $_REQUEST['itemId'];
$item = new Item($_SESSION['itemId']);
$currentUser = new User($_SESSION['authenticatedUser']);
$section = new Section($_SESSION['sectionId']);
$cat = new Category($_SESSION['catId']);

// Check access permissions.
if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för " . htmlspecialchars($item->caption)));
    die();
}


/**
 * Composes an HTML string showing the item's images and captions
 * @param Item $item
 * @return string Returns HTML code for the image block.
 */
function imageHtml(Item $item) {
    $ret = "";
    foreach ($item->images() as $image) {
        $ret .= "<div class='ui-body ui-body-a ui-corner-all'>\n";
        $ret .= "<img class='item-img-preview' src='../image.php?type=itemImage&id={$image->id}'>";
        $ret .= "<textarea class='item-img-caption ajax-input' placeholder='Bildtext' data-id='{$image->id}'>" . htmlspecialchars($image->caption) . "</textarea>";
        $ret .= "<div class='ui-grid-a'>";
        $ret .= "<div class='ui-block-a'><label><input type='radio' name='imageId' onClick=\"setItemProp('imageId', {$image->id});\" value='{$image->id}' " . ($image->id==$item->imageId ? "checked='true'" : "") . ">Huvudbild</label></div>";
        $ret .= "<div class='ui-block-b'><input type='button' data-corners='false' class='ui-btn ui-corner-all' value='Ta bort' onClick='deleteImage({$image->id});'></div>";
        $ret .= "</div></div><br>";
    }
    return $ret;
}


/**
 * Echoes a category tree as "select" options
 * @param Category $startAt Output the tree from here downwards
 * @param Category $selected Preselect option for this category
 * @param User $user Only categories where this user is at least CATADMIN will be shown.
 * @param number $indent Indentation for visual arrangement.
 */
function showCatTree(Category $startAt, Category $selected, User $user, $indent=0) {
    if ($startAt->getAccess($user) >= FFBoka::ACCESS_CATADMIN || $startAt->id == $selected->id) {
        echo "<option value='{$startAt->id}'" . ($startAt->id==$selected->id ? " selected='true'" : "") . ">" . str_repeat("&mdash;", $indent) . " " . htmlspecialchars($startAt->caption) . "</option>";
    } else {
        echo "<option disabled>" . str_repeat("&mdash;", $indent) . " " . htmlspecialchars($startAt->caption) . "</option>";
    }
    foreach ($startAt->children() as $child) {
        showCatTree($child, $selected, $user, $indent+1);
    }
}


switch ($_REQUEST['action']) {
    case "help":
        echo <<<EOF
<h3>Allmänt</h3>
<p>Inställningarna här sparas direkt. Du behöver inte trycka på någon spara-knapp. Längst
upp ser du var i strukturen resursen är placerad, med klickbara överordnade element. Det 
är användbart för att snabbt navigera upp i hirarkin.</p>
<p><b>Rubriken</b> visas i listor och bör hållas kort och tydlig. Har du flera resurser av
samma typ kan det vara bra att lägga till ett löpnummer eller dylikt  som hjälper dig att 
identifiera resurserna.</p>
<p><b>Beskrivningen</b> kan vara en längre text. Här kan du samla all information om 
resursen som kan vara användbar för användaren. Texten visas i resursens detailjvy.</p>
<p>Med <b>Aktiv (kan bokas)</b> bestämmer du om resursen ska visas för bokning. Det kan 
vara användbart under tiden du lägger upp resursen tills all information är på plats, eller 
när en resurs inte är tillgänglig på grund av skada, förlust mm.</p>
<p>Knappen <b>Duplicera resursen</b> skapar en kopia. Om rubriken i din resurs slutar på
<tt>(n)</tt> (där n är ett löpnummer) så får kopian nästa löpnummer. Om du t.ex. kopierar 
<tt>Kanadensare (1)</tt> så heter kopian <tt>Kanadensare (2)</tt>. Annars får kopians 
rubrik tillägget <tt>(kopia)</tt>. <b>OBS</b>, du måste själv aktivera kopian!</p>

<h3>Bilder</h3>
<p>Du kan lägga in ett valfritt antal bilder på din resurs. En av bilderna blir huvudbilden, 
vilket innebär att den visas i listor vid t.ex. bokning. Till varje bild kan du även lägga 
in en bildtext som visas under bilden.</p>
EOF;
        die();
    case "newItem":
        $item = $cat->addItem();
        $_SESSION['itemId'] = $item->id;
        break;
        
    case "copyItem":
        $item = $item->copy();
        $_SESSION['itemId'] = $item->id;
        break;
        
    case "moveItem":
        $item->moveToCat($cat);
        break;
        
    case "setItemProp":
        // Reply to AJAX request
        switch ($_REQUEST['name']) {
            case "caption":
            case "description":
            case "active":
            case "imageId":
                header("Content-Type: application/json");
                if ($_REQUEST['value']=="NULL") $item->{$_REQUEST['name']} = null;
                else $item->{$_REQUEST['name']} = $_REQUEST['value'];
                die(json_encode(["status"=>"OK"]));
        }
        break;
        
    case "ajaxDeleteItem":
        header("Content-Type: application/json");
        if ($item->delete()) die(json_encode(array("status"=>"OK")));
        break;
        
    case "ajaxAddImage":
        header("Content-Type: application/json");
        if (is_uploaded_file($_FILES['image']['tmp_name'])) {
            $image = $item->addImage();
            $res = $image->setImage($_FILES['image'], $cfg['maxImgSize'], 80, $cfg['uploadMaxFileSize']);
            if ($res!==TRUE) {
                die(json_encode($res));
            }
            // Set as featured image if it is the first one for this item
            if (!$item->imageId) $item->imageId = $image->id;
            die(json_encode(array("html"=>imageHtml($item))));
        }
        die(json_encode(array("error"=>"File is not an uploaded file")));
        
    case "ajaxDeleteImage":
        header("Content-Type: application/json");
        $image = new Image($_GET['id']);
        $image->delete();
        die(json_encode(array("html"=>imageHtml($item))));
        
    case "ajaxSaveImgCaption":
        header("Content-Type: application/json");
        $image = new Image($_GET['id']);
        $image->caption = $_GET['caption'];
        die(json_encode(array("html"=>$image->caption)));
        
}


?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Utrustning", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-admin-item">
    <?= head($item->caption ? htmlspecialchars($item->caption) : "Ny utrustning", $cfg['url'], $currentUser, $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">
    
        <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-item" class="ui-content">
            <p id="msg-page-admin-item"><?= $message ?></p>
            <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
        </div>
        
        <div data-role="popup" data-overlay-theme="b" id="popup-move-item" class="ui-content">
            <h3>Flytta resursen</h3>
            <form data-ajax="false">
                <p>Flytta den här resursen till en annan kategori:</p>
                <select name="catId" id="move-item-cat-id"><?php
                foreach ($section->getMainCategories() as $c) {
                    showCatTree($c, $cat, $currentUser);
                }
                ?></select>
                <p style="text-align:center;">
                    <a href="#" data-rel="back" class="ui-btn ui-btn-inline">Avbryt</a>
                    <h href="#" onClick="location.href='?action=moveItem&catId='+$('#move-item-cat-id').val();" class="ui-btn ui-btn-b ui-btn-inline">Spara</a>
                </p>
            </form>
        </div>

        <div class="saved-indicator" id="item-saved-indicator">Sparad</div>

        <p><?php
        foreach ($cat->getPath() as $p) {
            if ($p['id']) echo " &rarr; ";
            echo "<a data-transition='slide' data-direction='reverse' href='" . ($p['id'] ? "category.php?catId={$p['id']}&expand=items" : "index.php") . "'>" . htmlspecialchars($p['caption']) . "</a>";
        }?>&emsp;<a href="#popup-move-item" data-rel="popup" data-position-to="window" data-transition="pop" class="ui-btn ui-btn-inline ui-icon-edit ui-btn-icon-notext">Flytta</a>
        </p>
        
        <div class="ui-field-contain">
            <label for="item-caption">Rubrik:</label>
            <input name="caption" class="ajax-input" id="item-caption" placeholder="Rubrik" value="<?= htmlspecialchars($item->caption) ?>">
        </div>
        
        <div class="ui-field-contain">
            <label for="item-description">Beskrivning:</label>
            <textarea name="description" class="ajax-input" id="item-description" placeholder="Beskrivning"><?= htmlspecialchars($item->description) ?></textarea>
        </div>
        
        <label>        <input type="checkbox" name="active" value="1" id="item-active" <?= $item->active ? "checked='true'" : "" ?>>Aktiv (kan bokas)</label>
        
        <div><input type='button' data-corners="false" id='delete-item' value='Ta bort resursen' data-theme='c'></div>
        <div><input type='button' data-corners="false" value='Duplicera resursen' onClick="location.href='?action=copyItem';"></div>
        
        <hr>
        
        <h3>Bilder</h3>
        <div class="ui-field-contain">
            <label for="file-item-img">Ladda upp ny bild:</label>
            <input type="file" name="image" id="file-item-img">
        </div>
        <div id='item-images'><?= imageHtml($item) ?></div>
        
    </div><!--/main-->

</div><!--/page-->
</body>
</html>
