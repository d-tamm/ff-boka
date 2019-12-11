<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Image;
global $cfg;

session_start();
require(__DIR__ . "/../inc/common.php");

if (!isset($_SESSION['sectionId']) || !isset($_SESSION['authenticatedUser']) || !isset($_SESSION['catId'])) {
    header("Location: /?action=sessionExpired");
    die();
}

if (isset($_REQUEST['itemId'])) $_SESSION['itemId'] = $_REQUEST['itemId'];
$item = new Item($_SESSION['itemId']);
$currentUser = new User($_SESSION['authenticatedUser']);
$cat = new Category($_SESSION['catId']);

// Check access permissions.
if (!$cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    header("Location: /?action=accessDenied&to=" . urlencode("administrationssidan för " . htmlspecialchars($item->caption)));
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


switch ($_REQUEST['action']) {
    case "newItem":
        $item = $cat->addItem();
        $_SESSION['itemId'] = $item->id;
        break;
        
    case "copyItem":
        $item = $item->copy();
        $_SESSION['itemId'] = $item->id;
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
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Utrustning") ?>
</head>


<body>
<div data-role="page" id="page-admin-item">
    <?= head($item->caption ? htmlspecialchars($item->caption) : "Ny utrustning", $currentUser) ?>
    <div role="main" class="ui-content">
    
        <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-item" class="ui-content">
            <p id="msg-page-admin-item"><?= $message ?></p>
            <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
        </div>

        <div class="saved-indicator" id="item-saved-indicator">Sparad</div>

        <p><?php
        foreach ($cat->getPath() as $p) {
            if ($p['id']) echo " &rarr; ";
            echo "<a data-transition='slide' data-direction='reverse' href='" . ($p['id'] ? "category.php?catId={$p['id']}&expand=items" : "index.php") . "'>" . htmlspecialchars($p['caption']) . "</a>";
        }?></p>
        
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
