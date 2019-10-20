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


switch ($_REQUEST['action']) {
case "delete_cat":
	$stmt = $db->prepare("DELETE FROM categories WHERE catID=?");
	$stmt->execute(array($_GET['catID']));
	header("Location: admin.php?section=cat");
	die();		
	break;
	
case "save category":
	if ($_REQUEST['catID']) {
		$stmt = $db->prepare("UPDATE categories SET name=:name, contact_name=:contact_name, contact_mail=:contact_mail, contact_phone=:contact_phone WHERE catID=:catID");
		$stmt->bindValue(":catID", $_REQUEST['catID']);
	} else {
		$stmt = $db->prepare("INSERT INTO categories SET sectionID=:sectionID, name=:name");
		$stmt->bindValue(":sectionID", $_SESSION['sectionID']);
	}
	$stmt->bindValue(":name", $_REQUEST['name']);
	$stmt->bindValue(":contact_name", $_REQUEST['contact_name']);
	$stmt->bindValue(":contact_mail", $_REQUEST['contact_mail']);
	$stmt->bindValue(":contact_phone", $_REQUEST['contact_phone']);
	$stmt->execute();
	if (!$_REQUEST['catID']) {
		$_REQUEST['catID'] = $db->lastInsertId();
		$stayOnPage = true;
	}
	// Handle uploaded image separately
	if (is_uploaded_file($_FILES['image']['tmp_name'])) {
		if ($image = getUploadedImage($_FILES['image'])) {
			$stmt = $db->prepare("UPDATE categories SET image=:image, thumb=:thumb WHERE catID=:catID");
			$stmt->execute(array(
				":image" => $image['image'],
				":thumb" => $image['thumb'],
				":catID" => $_REQUEST['catID'],
			));
		}
	}
	if (!$stayOnPage) {
		header("Location: admin.php?section=cat");
		die();
	}
	break;
	
case "setCatAccess":
	switch ($_GET['ass']) {
	case "access_external":
	case "access_member":
	case "access_smember":
		$stmt = $db->prepare("UPDATE categories SET {$_GET['ass']}=:prebook WHERE catID=:catID");
		if ($stmt->execute(array(
			":prebook"=>$_GET['prebook'],
			":catID"=>$_SESSION['catID'],
		))) die($_GET['ass']);
		break;
	default:
		$ass = substr($_GET['ass'], strpos($_GET['ass'], "_")+1);
		if ($_GET['prebook']) {
			$stmt = $db->prepare("INSERT INTO cat_access SET catID=:catID, ass_name=:ass, prebook=:prebook ON DUPLICATE KEY UPDATE prebook=VALUES(prebook)");
			if ($stmt->execute(array(
				":catID"=>$_SESSION['catID'],
				":ass"=>$ass,
				":prebook"=>$_GET['prebook'],
			))) die($_GET['ass']);
		} else {
			$stmt = $db->prepare("DELETE FROM cat_access WHERE catID=:catID AND ass_name=:ass");
			if ($stmt->execute(array(
				":catID"=>$_SESSION['catID'],
				":ass"=>$ass,
			))) die($_GET['ass']);
		}
	}
	die();
	break;
}

if ($_REQUEST['catID']) $_SESSION['catID'] = $_REQUEST['catID'];
if ($_SESSION['catID']) {
	// Get category data
	$stmt = $db->prepare("SELECT * FROM categories WHERE catID=?");
	$stmt->execute(array($_SESSION['catID']));
	$cat = $stmt->fetch(PDO::FETCH_ASSOC);
}


?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Kategori") ?>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_category">
	<?= head($cat['name'] ? $cat['name'] : "Ny kategori") ?>
	<div role="main" class="ui-content">
		
	<div data-role="collapsibleset" data-inset="false">
		<form action="" method="post" enctype="multipart/form-data" data-ajax="false" data-role="collapsible" data-collapsed="<?= $cat['catID'] ? "true" : "false" ?>">
			<h2>Allmänt</h2>
			<input type="hidden" name="action" value="save category">
			<input type="hidden" name="catID" value="<?= $cat['catID'] ?>">
			<div class="ui-field-contain">
				<label for="cat-name">Kategorinamn:</label>
				<input name="name" id="cat-name" placeholder="Kategorinamn" value="<?= $cat['name'] ?>">
			</div>
			<?php if ($cat['image']) { ?>
			<div class='img-preview'>
				<img src="image.php?type=category&ID=<?= $cat['catID'] ?>" id="cat-img-preview" width="100%">
			</div>
			<?php } ?>
			<div class="ui-field-contain">
				<label for="file-cat-img">Ladda upp ny bild:</label>
				<input type="file" name="image" id="file-cat-img">
			</div>
			<h3>Kontaktuppgifter</h3>
			<label for="cat-name" class="ui-hidden-accessible">Namn:</label>
			<input name="contact_name" id="cat-contact-name" placeholder="Kontaktpersonens namn" value="<?= $cat['contact_name'] ?>">
			<label for="cat-name" class="ui-hidden-accessible">Epost:</label>
			<input type="email" name="contact_mail" id="cat-contact-mail" placeholder="Epost-adress" value="<?= $cat['contact_mail'] ?>">
			<label for="cat-name" class="ui-hidden-accessible">Telefon:</label>
			<input type="tel" name="contact_phone" id="cat-contact-phone" placeholder="Telefon" value="<?= $cat['contact_phone'] ?>">
			<?php if ($cat['catID']) { ?>
			<div class="ui-grid-a">
				<div class="ui-block-a"><input type="submit" value="Spara" data-theme="b"></div>
				<div class='ui-block-b'><input type='button' id='delete_cat' value='Ta bort' data-theme='c'></div>
			</div>
			<?php } else { ?>
			<input type="submit" value="Fortsätt">
			<?php } ?>
		</form>

		<?php if ($cat['catID']) { ?>
		<form data-role="collapsible" data-collapsed="<?= $stayOnPage ? "false" : "true" ?>">
			<h2>Behörighet</h2>
			<p>Här bestäms vem som får boka utrustningen i denna kategori, samt hur många dagar i förväg.</p>
			<h4>Generella grupper:</h4>
			<div class="ui-field-contain">
				<label for='cat_access_external'>Icke-medlemmar: <span class='prebook-text'><?= $cat['access_external'] ? $cfg['prebookDays'][$cat['access_external']]." dagar" : "Ingen behörighet" ?></span></label>
				<input class='cat-access' type='range' data-highlight='true' name='access_external' id='cat_access_external' min='0' max='<?= count($cfg['prebookDays'])-1 ?>' value='<?= $cat['access_external'] ?>'>
			</div>
			<div class="ui-field-contain">
				<label for='cat_access_member'>Medlemmar i valfri LA: <span class='prebook-text'><?= $cat['access_member'] ? $cfg['prebookDays'][$cat['access_member']]." dagar" : "Ingen behörighet" ?></span></label>
				<input class='cat-access' type='range' data-highlight='true' name='access_member' id='cat_access_member' min='0' max='<?= count($cfg['prebookDays'])-1 ?>' value='<?= $cat['access_member'] ?>'>
			</div>
			<div class="ui-field-contain">
				<label for='cat_access_smember'>Medlemmar i FF <?= $_SESSION['sectionName']?>: <span class='prebook-text'><?= $cat['access_smember'] ? $cfg['prebookDays'][$cat['access_smember']]." dagar" : "Ingen behörighet" ?></span></label>
				<input class='cat-access' type='range' data-highlight='true' name='access_smember' id='cat_access_smember' min='0' max='<?= count($cfg['prebookDays'])-1 ?>' value='<?= $cat['access_smember'] ?>'>
			</div>
			<h4>Uppdrag i FF <?= $_SESSION['sectionName'] ?>:</h4>
			<?php // show a list of all assignments at section level
			$stmt=$db->prepare("SELECT ass_name, catID, prebook FROM assignments LEFT JOIN cat_access USING (ass_name) WHERE typeID=478880001 AND (catID=? OR catID IS NULL)");
			$stmt->execute(array($cat['catID']));
			$assID=0;
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				echo "<div class='ui-field-contain'>\n";
				echo "\t<label for='cat_access_$assID'>{$row['ass_name']}: <span class='prebook-text'>" . ($row['prebook'] ? $cfg['prebookDays'][$row['prebook']]." dagar" : "Ingen behörighet") . "</span></label>\n";
				echo "\t<input class='cat-access' type='range' data-highlight='true' name='access_{$row['ass_name']}' id='cat_access_$assID' min='0' max='" . (count($cfg['prebookDays'])-1) . "' value='" . ($row['prebook']+0) . "'>\n";
				echo "</div>\n";
				$assID++;
			} ?>
		</form>
		
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['section']=="items" ? "false" : "true" ?>">
			<h2>Utrustning</h2>
			<ul data-role="listview" data-filter="true">
				<?php
				$stmt = $db->prepare("SELECT items.*, item_images.thumb FROM items LEFT JOIN item_images USING (imageID) WHERE catID=? ORDER BY caption");
				$stmt->execute(array($cat['catID']));
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					echo "<li" . ($row['active'] ? "" : " class='inactive'") . "><a href='item.php?itemID={$row['itemID']}'>" .
						embed_image($row['thumb']) .
						"<h3>{$row['caption']}</h3>" .
						"<p>" . ($row['active'] ? $row['description'] : "(inaktiv)") . "</p>" .
						"</a></li>";
				} ?>
				<li><a href="item.php">Lägg till utrustning</a></li>
			</ul>
		</div>
		
		<?php } ?>
	</div><!--/collapsibleset-->
	</div><!--/main-->

	<script>
		$("#delete_cat").click(function() {
			if (confirm("Du håller på att ta bort kategorin och alla poster i den. Fortsätta?")) {
				location.href="?action=delete_cat&catID=<?= $cat['catID'] ?>";
			}
		});

		$(document).ready( function(){
			// Ajax request for saving access to category
			var prebookDays=<?= json_encode($cfg['prebookDays']) ?>;
			$( ".cat-access" ).on( "change", function( event, ui ) {
				switch (prebookDays[this.value]) {
					case 0: var text = "Ingen behörighet"; break;
					case 1: text = "1 dag"; break;
					default: text = prebookDays[this.value]+" dagar";
				}
				$(this).parent().siblings('label').children('.prebook-text').html(text);
				
				var _this = this;
				$.get("?action=setCatAccess&ass="+encodeURIComponent(this.name)+"&prebook="+this.value, function(data, status) {
					if (data!=0) {
						$(_this).parent().siblings('label').addClass("confirm-change");
						setTimeout(function(){
							$(_this).parent().siblings('label').removeClass("confirm-change");
						},1000);
					}
				});
			});
		});
	</script>

</div><!--/page>
</body>
</html>
