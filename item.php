<?php
session_start();
require("common.php");

// Check permissions
if (!isset($_SESSION['sectionID'])) {
	header("Location: index.php");
	die();
}
$where = array();
foreach ($_SESSION['user']['assignments'] as $ass) {
	if ($ass['typeID']==478880001 && in_array($ass['name'], $cfg['sectionAdmins']) && $ass['party']==$_SESSION['sectionName']) {
		// User is section admin by cfg setting
		$adminOK = true;
		break;
	}
	if ($ass['typeID']==478880001) { $where[] = "(name='{$ass['party']}' AND ass_name='{$ass['name']}')"; }
}
if (!$adminOK) {
	$stmt = $db->query("SELECT sectionID, name FROM section_admins INNER JOIN sections USING (sectionID) WHERE " . implode(" OR ", $where));
	if (!$stmt->rowCount()) {
		header("Location: index.php");
		die();
	}
}


function imageHtml($itemID) {
	// Returns HTML code for the image block.
	global $db;
	if (!$itemID) return "";
	$stmt = $db->query("SELECT item_images.imageID AS imageID, thumb, items.imageID AS featured_image FROM item_images INNER JOIN items USING (itemID) WHERE itemID=$itemID");
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ret .= "<img class='item-img-preview' src='image.php?type=item&ID={$row['imageID']}'>";
		$ret .= "<label><input type='radio' name='main_image' onClick='setFeaturedImg({$row['imageID']});' value='{$row['imageID']}' " . ($row['imageID']==$row['featured_image'] ? "checked='true'" : "") . ">Huvudbild</label>";
		$ret .= "<input type='button' class='ui-btn ui-corner-all' value='Ta bort' onClick='delItemImg({$row['imageID']});'>";
		$ret .= "<div style='clear:both;'></div>\n";
	}
	return $ret;
}


switch ($_REQUEST['action']) {
case "save item":
	if ($_REQUEST['itemID']) {
		$stmt = $db->prepare("UPDATE items SET caption=:caption, description=:description, active=:active WHERE itemID=:itemID");
		$stmt->bindValue(":itemID", $_REQUEST['itemID']);
	} else {
		$stmt = $db->prepare("INSERT INTO items SET catID=:catID, caption=:caption, description=:description, active=:active");
		$stmt->bindValue(":catID", $_SESSION['catID']);
	}
	$stmt->bindValue(":caption", $_REQUEST['caption']);
	$stmt->bindValue(":description", $_REQUEST['description']);
	$stmt->bindValue(":active", $_REQUEST['active']+0);
	$stmt->execute();
	if (!$_REQUEST['itemID']) {
		$_REQUEST['itemID'] = $db->lastInsertId();
		$stayOnPage = true;
	}
	if (!$stayOnPage) {
		header("Location: category.php?section=items");
		die();
	}
	break;
	
case "delete_item":
	$stmt = $db->prepare("DELETE FROM items WHERE itemID=?");
	$stmt->execute(array($_GET['itemID']));
	header("Location: category.php?section=items");
	break;
	
case "copy_item":
	// copy the item itself
	$stmt = $db->prepare("INSERT INTO items (catID, caption, description) SELECT catID, caption, description FROM items WHERE itemID=?");
	$stmt->execute(array($_GET['itemID']));
	$newItemID = $db->lastInsertId();
	// copy the associated item images
	$stmt = $db->prepare("INSERT INTO item_images (itemID, image, thumb) SELECT $newItemID, image, thumb FROM item_images WHERE itemID=?");
	$stmt->execute(array($_GET['itemID']));
	$_REQUEST['itemID'] = $newItemID;
	break;
	
case "add image":
	if (is_uploaded_file($_FILES['image']['tmp_name'])) {
		if ($image = getUploadedImage($_FILES['image'])) {
			$stmt = $db->prepare("INSERT INTO item_images SET itemID=:itemID, image=:image, thumb=:thumb");
			$stmt->execute(array(
				":itemID" => $_REQUEST['itemID'],
				":image" => $image['image'],
				":thumb" => $image['thumb'],
			));
			// Set as featured image if it is the first one for this item
			$stmt = $db->prepare("UPDATE items SET imageID=:imageID WHERE itemID=:itemID AND imageID IS NULL");
			$stmt->execute(array(
				":imageID"=>$db->lastInsertId(),
				":itemID"=>$_REQUEST['itemID'],
			));
			die(json_encode(array("html"=>imageHtml($_REQUEST['itemID']))));
		}
		die(json_encode(array("error"=>"Wrong file type")));
	}
	die(json_encode(array("error"=>"File is not an uploaded file")));
	break;
	
case "delete_image":
	$stmt = $db->prepare("DELETE FROM item_images WHERE imageID=?");
	$stmt->execute(array($_GET['imageID']));
	die(imageHtml($_REQUEST['itemID']));
	break;
	
case "set_featured_img":
	$stmt = $db->prepare("UPDATE items SET imageID=:imageID WHERE itemID=:itemID");
	$stmt->execute(array(
		":imageID"=>$_GET['imageID'],
		":itemID"=>$_GET['itemID'],
	));
	die($_GET['imageID']);
	break;
}

// Get item data
if ($_REQUEST['itemID']) {
	$stmt = $db->prepare("SELECT items.*, categories.name AS catName FROM items INNER JOIN categories ON (items.catID=categories.catID) WHERE itemID=?");
	$stmt->execute(array($_REQUEST['itemID']));
} else {
	$stmt = $db->query("SELECT categories.name AS catName FROM categories WHERE catID={$_SESSION['catID']}");
}
$item = $stmt->fetch(PDO::FETCH_ASSOC);


?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Utrustning") ?>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_item">
	<?= head($item['caption'] ? $item['caption'] : "Ny utrustning") ?>
	<div role="main" class="ui-content">
	
	<?= $newItemID ? "<div class='ui-body ui-body-b ui-corner-all'><p>Här visas den nya kopian av din post.</p><p><b>OBS:</b> Posten är inte ännu aktiverad. Du måste också välja huvudbild igen.</p><p>Du kanske vill justera rubriken?</p></div>" : "" ?>
	
	<form action="" method="post" enctype="multipart/form-data" data-ajax="false">
		<input type="hidden" name="action" value="save item">
		<input type="hidden" name="itemID" value="<?= $item['itemID'] ?>">
		<p>Kategori: <?= $item['catName'] ?></p>
		<div class="ui-field-contain">
			<label for="item-caption">Rubrik:</label>
			<input name="caption" id="item-caption" placeholder="Rubrik" value="<?= $item['caption'] ?>">
		</div>
		<div class="ui-field-contain">
			<label for="item-desc">Beskrivning:</label>
			<textarea name="description" id="item-desc" placeholder="Beskrivning"><?= $item['description'] ?></textarea>
		</div>
		<div class="ui-field-contain">
			<label for="item-active">Aktiv (kan bokas)</label>
			<input type="checkbox" name="active" value="1" id="item-active" <?= $item['active'] ? "checked='true'" : "" ?>>
		</div>
		<?php if ($item['itemID']) { ?>
		<div class="ui-grid-a">
			<div class="ui-block-a"><input type="submit" value="Spara" data-theme="b"></div>
			<div class='ui-block-b'><input type='button' id='delete_item' value='Ta bort' data-theme='c'></div>
		</div>
		<div class='ui-grid-solo'>
			<div class='ui-block-a'><input type='button' value='Duplicera posten' onClick="location.href='?action=copy_item&itemID=<?= $item['itemID'] ?>';"></div>
		</div>
		<?php } else { ?>
		<input type="submit" value="Fortsätt">
		<?php }
		
		if ($item['itemID']) { ?>
		<h3>Bilder</h3>
		<div class="ui-field-contain">
			<label for="file-item-img">Ladda upp ny bild:</label>
			<input type="file" name="image" id="file-item-img">
		</div>
		<div id='item-images'><?= imageHtml($item['itemID']) ?></div>
		<?php } ?>
		
	</form>

	</div><!--/main-->

	<script>
		$("#delete_item").click(function() {
			if (confirm("Du håller på att ta bort utrustningen. Fortsätta?")) {
				location.href="?action=delete_item&itemID=<?= $item['itemID'] ?>";
			}
		});

		$("#file-item-img").change(function() {
			// Save image via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
			var fd = new FormData();
			var file = $('#file-item-img')[0].files[0];
			fd.append('image',file);
			fd.append('action', "add image");
			fd.append('itemID', <?= $item['itemID'] ?>);
			
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
						alert('Filen har inte laddats upp. Välj en jpg- eller png-fil.');
					}
				},
			});
		});
		
		function setFeaturedImg(imageID) {
			$.get("?action=set_featured_img&itemID=<?= $item['itemID'] ?>&imageID="+imageID, function(data, status) {
				console.log("Set featured image: "+data);
			});
		}
		
		function delItemImg(imageID) {
			if (confirm("Vill du ta bort denna bild?")) {
				$.get("?action=delete_image&itemID=<?= $item['itemID'] ?>&imageID="+imageID, function(data, status) {
					$('#item-images').html(data).enhanceWithin();
				});
			}
		}
				
	</script>

</div><!--/page>
</body>
</html>
