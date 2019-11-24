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
    header("Location: /?action=accessDenied&to=" . urlencode("administrationssidan för {$item->caption}"));
    die();
}


/**
 * Composes an HTML string showing the item's images
 * @param Item $item
 * @return string Returns HTML code for the image block.
 */
function imageHtml(Item $item) {
    $ret = "";
    foreach ($item->images() as $image) {
        $ret .= "<div class='ui-body ui-body-a ui-corner-all'>\n";
        $ret .= "<img class='item-img-preview' src='../image.php?type=itemImage&id={$image->id}'>";
        $ret .= "<textarea class='item-img-caption ajax-input' placeholder='Bildtext' data-id='{$image->id}'>{$image->caption}</textarea>";
        $ret .= "<div class='ui-grid-a'>";
        $ret .= "<div class='ui-block-a'><label><input type='radio' name='imageId' onClick='setFeaturedImg({$image->id});' value='{$image->id}' " . ($image->id==$item->imageId ? "checked='true'" : "") . ">Huvudbild</label></div>";
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
                else $item->{$_REQUEST['name']} = htmlentities($_REQUEST['value']);
                die(json_encode(["status"=>"OK"]));
        }
        break;
        
    case "deleteItem":
        $item->delete();
        header("Location: category.php?expand=items");
        break;
        
    case "addImage":
        // Reply to AJAX request
        header("Content-Type: application/json");
        if (is_uploaded_file($_FILES['image']['tmp_name'])) {
            $image = $item->addImage();
            if (!$image->setImage($_FILES['image'], $cfg['maxImgSize'], 80, $cfg['uploadMaxFileSize'])) {
                die(json_encode(array("error"=>"Fel filtyp eller för stor fil. Prova med en jpg- eller png-bild som är mindre än {$cfg['uploadMaxFileSize']}.")));
            }
            // Set as featured image if it is the first one for this item
            if (!$item->imageId) $item->imageId = $image->id;
            die(json_encode(array("html"=>imageHtml($item))));
        }
        die(json_encode(array("error"=>"File is not an uploaded file")));
        
    case "deleteImage":
        // Reply to AJAX request
        header("Content-Type: application/json");
        $image = new Image($_GET['id']);
        $image->delete();
        die(json_encode(array("html"=>imageHtml($item))));
        
    case "saveImgCaption":
        // Reply to AJAX request
        header("Content-Type: application/json");
        $image = new Image($_GET['id']);
        $image->caption = htmlentities($_GET['caption']);
        die(json_encode(array("html"=>$image->caption)));
        
}


?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Utrustning") ?>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_item">
	<?= head($item->caption ? $item->caption : "Ny utrustning") ?>
	<div role="main" class="ui-content">
	
	<form action="" method="post" enctype="multipart/form-data" data-ajax="false">
		<input type="hidden" name="action" value="save item">
		<input type="hidden" name="itemID" value="<?= $item->id ?>">

		<p><?php
		foreach ($cat->getPath() as $p) {
		    if ($p['id']) echo " &rarr; ";
		    echo "<a href='" . ($p['id'] ? "category.php?catId={$p['id']}" : "index.php") . "'>{$p['caption']}</a>";
		}?></p>
		
		<div class="ui-field-contain">
			<label for="item-caption">Rubrik:</label>
			<input name="caption" class="ajax-input" id="item-caption" placeholder="Rubrik" value="<?= $item->caption ?>">
		</div>
		
		<div class="ui-field-contain">
			<label for="item-description">Beskrivning:</label>
			<textarea name="description" class="ajax-input" id="item-description" placeholder="Beskrivning"><?= $item->description ?></textarea>
		</div>
		
		<label>		<input type="checkbox" name="active" value="1" id="item-active" <?= $item->active ? "checked='true'" : "" ?>>Aktiv (kan bokas)</label>
		
		<div><input type='button' data-corners="false" id='delete-item' value='Ta bort resursen' data-theme='c'></div>
		<div><input type='button' data-corners="false" value='Duplicera resursen' onClick="location.href='?action=copyItem';"></div>
		
		<hr>
		
		<h3>Bilder</h3>
		<div class="ui-field-contain">
			<label for="file-item-img">Ladda upp ny bild:</label>
			<input type="file" name="image" id="file-item-img">
		</div>
		<div id='item-images'><?= imageHtml($item) ?></div>
		
	</form>

	</div><!--/main-->

	<script>
		var toutSavedIndicator;
		var toutSetValue;

		function setItemProp(name, val) {
			$.getJSON("item.php", {action: "setItemProp", name: name, value: val}, function(data, status) {
				if (data.status=="OK") {
					$("#item-"+name).addClass("change-confirmed");
					setTimeout(function(){ $("#item-"+name).removeClass("change-confirmed"); }, 1500);
				} else {
					alert("Kan inte spara ändringen :(");
				}
			});
		}

		$("#item-caption").on('input', function() {
			clearTimeout(toutSetValue);
			toutSetValue = setTimeout(setItemProp, 1000, "caption", this.value);
		});
		
		$("#item-description").on('input', function() {
			clearTimeout(toutSetValue);
			toutSetValue = setTimeout(setItemProp, 1000, "description", this.value);
		});

		$("#item-active").click(function() {
			setItemProp("active", this.checked ? 1 : 0);
		});
		
		$("#delete-item").click(function() {
			if (confirm("Du håller på att ta bort utrustningen. Fortsätta?")) {
				location.href="?action=deleteItem";
			}
		});

		$("#file-item-img").change(function() {
			// Save image via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
			var fd = new FormData();
			var file = $('#file-item-img')[0].files[0];
			fd.append('image',file);
			fd.append('action', "addImage");
			$.mobile.loading("show", {});
			$.ajax({
				url: 'item.php',
				type: 'post',
				data: fd,
				dataType: 'json',
				contentType: false,
				processData: false,
				success: function(data) {
					$.mobile.loading("hide", {});
					if (data.html) {
						$('#item-images').html(data.html).enhanceWithin();
					} else {
						alert(data.error);
					}
				},
			});
		});
		
		$("#item-images").on("input", ".item-img-caption", function(e, data) {
			// Save image caption to DB via ajax
			var _this = this;
			clearTimeout(toutSavedIndicator);
			toutSavedIndicator = setTimeout(function() {
				$.getJSON(
					"item.php",
					{ action: 'saveImgCaption', id: $(_this).data('id'), caption: _this.value },
					function(data, status) {
						$(_this).addClass("change-confirmed");
						setTimeout(function(){ $(_this).removeClass("change-confirmed"); },1000);
					}
				);
			}, 1000);
		});
		
		function setFeaturedImg(imageId) {
			$.getJSON(
				"item.php",
				{ action: "setItemProp", name: "imageId", value: imageId },
				function(data, status) {
					if (data.error) alert(data.error);
				}
			);
		}
		
		function deleteImage(id) {
			if (confirm("Vill du ta bort denna bild?")) {
				$.getJSON("?action=deleteImage&id="+id, function(data, status) {
					if (data.html) {
						$('#item-images').html(data.html).enhanceWithin();
					} else {
						alert(data.error);
					}
				});
			}
		}
				
	</script>

</div><!--/page-->
</body>
</html>