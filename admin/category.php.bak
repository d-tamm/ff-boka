<?php
session_start();
require("common.php");
global $db, $cfg;

// Check permissions
if (!isset($_SESSION['sectionID'])) {
	header("Location: index.php");
	die();
}
$where = array();
foreach ($_SESSION['user']['assignments'] as $ass) {
	if ($ass['typeID']==478880001 && in_array($ass['name'], $cfg['sectionAdmins']) && $ass['sectionID']==$_SESSION['sectionID']) {
		// User is section admin by cfg setting
		$adminOK = true;
		break;
	}
	if ($ass['typeID']==478880001) { $where[] = "(sectionID='{$ass['sectionID']}' AND ass_name='{$ass['name']}')"; }
}
if (!$adminOK) {
	$stmt = $db->query("SELECT sectionID, name FROM section_admins INNER JOIN sections USING (sectionID) WHERE " . implode(" OR ", $where));
	if (!$stmt->rowCount()) {
		header("Location: index.php");
		die();
	}
}


function displayCatAccess() {
	// Returns html code for displaying the category access as a list
	global $db, $cfg;
	$stmt = $db->query("SELECT * FROM categories WHERE catID={$_SESSION['catID']}");
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$ret = "";
	if ($row['access_external']) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('access_external');\">Icke-medlemmar<p>{$cfg['catAccessLevels'][$row['access_external']]}</p></a></li>";
	if ($row['access_member']) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('access_member');\">Medlem i valfri lokalavdelning<p>{$cfg['catAccessLevels'][$row['access_member']]}</p></a></li>";
	if ($row['access_local_member']) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('access_local_member');\">Lokal medlem<p>{$cfg['catAccessLevels'][$row['access_local_member']]}</p></a></li>";
	$stmt = $db->query("SELECT * FROM cat_access WHERE catID={$_SESSION['catID']}");
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ret .= "<li><a href='#' onclick=\"unsetCatAccess('{$row['ass_name']}');\">{$row['ass_name']}<p>{$cfg['catAccessLevels'][$row['cat_access']]}</p></a></li>";
	}
	if ($ret) return "<p>Tilldelade behörigheter:</p><ul data-role='listview' data-inset='true' data-icon='delete'>$ret</ul>";
	return "<p>Inga behörigheter har tilldelats än. Använd knappen nedan för att ställa in behörigheterna.</p>";
}


switch ($_REQUEST['action']) {
case "delete_cat":
	$stmt = $db->prepare("DELETE FROM categories WHERE catID=?");
	$stmt->execute(array($_GET['catID']));
	header("Location: admin.php");
	die();		
	break;
	
case "save category":
	if ($_REQUEST['catID']) {
		$stmt = $db->prepare("UPDATE categories SET caption=:caption, booking_msg=:booking_msg, contact_name=:contact_name, contact_mail=:contact_mail, contact_phone=:contact_phone WHERE catID=:catID");
		$stmt->bindValue(":catID", $_REQUEST['catID']);
	} else {
		$stmt = $db->prepare("INSERT INTO categories SET sectionID=:sectionID, caption=:caption, booking_msg=:booking_msg, contact_name=:contact_name, contact_mail=:contact_mail, contact_phone=:contact_phone");
		$stmt->bindValue(":sectionID", $_SESSION['sectionID']);
	}
	$stmt->bindValue(":caption", $_REQUEST['caption']);
	$stmt->bindValue(":booking_msg", $_REQUEST['booking_msg']);
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
		header("Location: admin.php");
		die();
	}
	break;
	
case "setCatAccess":
	switch ($_GET['ass']) {
	case "access_external":
	case "access_member":
	case "access_local_member":
		$stmt = $db->prepare("UPDATE categories SET {$_GET['ass']}=:cat_access WHERE catID=:catID");
		if (!$stmt->execute(array(
			":cat_access"=>$_GET['cat_access'],
			":catID"=>$_SESSION['catID'],
		))) die(0);
		break;
	default:
		$stmt = $db->prepare("INSERT INTO cat_access SET catID=:catID, ass_name=:ass, cat_access=:cat_access ON DUPLICATE KEY UPDATE cat_access=VALUES(cat_access)");
		if (!$stmt->execute(array(
			":catID"=>$_SESSION['catID'],
			":ass"=>$_GET['ass'],
			":cat_access"=>$_GET['cat_access'],
		))) die(0);
	}
	die(displayCatAccess());
	break;
	
case "unsetCatAccess":
	switch ($_GET['ass']) {
	case "access_external":
	case "access_member":
	case "access_local_member":
		$db->exec("UPDATE categories SET {$_GET['ass']}=0 WHERE catID={$_SESSION['catID']}");
		break;
	default:
		$stmt = $db->prepare("DELETE FROM cat_access WHERE catID={$_SESSION['catID']} AND ass_name=?");
		if (!$stmt->execute(array($_GET['ass']))) die(0);
	}
	die(displayCatAccess());
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
	<?= head($cat['caption'] ? $cat['caption'] : "Ny kategori") ?>
	<div role="main" class="ui-content">
		
	<div data-role="collapsibleset" data-inset="false">
		<form action="" method="post" enctype="multipart/form-data" data-ajax="false" data-role="collapsible" data-collapsed="<?= $cat['catID'] ? "true" : "false" ?>">
			<h2>Allmänt</h2>
			<input type="hidden" name="action" value="save category">
			<input type="hidden" name="catID" value="<?= $cat['catID'] ?>">
			<p>Tillhör <a href="admin.php">LA <?= $_SESSION['sectionName'] ?></a></p>
			<div class="ui-field-contain">
				<label for="cat-caption">Rubrik:</label>
				<input name="caption" id="cat-caption" placeholder="Namn till kategorin" value="<?= $cat['caption'] ?>">
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
			<label for="cat-booking-msg">Text som ska visas när användare vill boka resurser från denna kategori:</label>
			<textarea name="booking_msg" id="cat-booking-msg" placeholder="Exempel: Kom ihåg att ta höjd för torkningstiden efter användningen!"><?= $cat['booking_msg'] ?></textarea>
			<h3>Kontaktuppgifter</h3>
			<label for="cat-contact-name" class="ui-hidden-accessible">Namn:</label>
			<input name="contact_name" id="cat-contact-name" placeholder="Kontaktpersonens namn" value="<?= $cat['contact_name'] ?>">
			<label for="cat-contact-mail" class="ui-hidden-accessible">Epost:</label>
			<input type="email" name="contact_mail" id="cat-contact-mail" placeholder="Epost-adress" value="<?= $cat['contact_mail'] ?>">
			<label for="cat-contact-phone" class="ui-hidden-accessible">Telefon:</label>
			<input type="tel" name="contact_phone" id="cat-contact-phone" placeholder="Telefon" value="<?= $cat['contact_phone'] ?>">
			<?php if ($cat['catID']) { ?>
			<div class="ui-grid-a">
				<div class="ui-block-a"><input type="submit" data-corners="false" value="Spara" data-theme="b"></div>
				<div class='ui-block-b'><input type='button' data-corners="false" id='delete_cat' value='Ta bort' data-theme='c'></div>
			</div>
			<?php } else { ?>
			<input type="submit" value="Fortsätt">
			<?php } // TODO: add settings for limiting booking time span (Github issue #6) ?>
		</form>

		<?php if ($cat['catID']) { ?>
		<form data-role="collapsible" data-collapsed="<?= $stayOnPage ? "false" : "true" ?>">
			<h2>Behörigheter</h2>
			<div data-role="collapsible" data-inset="true" data-mini="true" data-collapsed-icon="info">
				<h4>Hur gör jag?</h4>
				<p>Här bestäms vem som får se och boka resurserna i kategorin <?= $cat['caption'] ?>. Först väljer du gruppen som ska få behörighet. Sedan väljer du vilken behörighetsnivå gruppen ska få.</p>
				<p>Återkalla behörigheter genom att klicka på dem.</p>
				<p>Om en användare tillhör flera grupper gäller den högsta tilldelade behörigheten.</p>
			</div>
			
			<div id="assigned-cat-access"><?= displayCatAccess() ?></div>

			<p>Tilldela ny behörighet:</p>
			<fieldset data-role="controlgroup">
			<select id="cat-access-grp">
				<option value="">1. Välj användargrupp här</option>
				<optgroup label="Allmänna grupper:">
					<option value="access_external">Icke-medlemmar</option>
					<option value="access_member">Medlem i valfri lokalavdelning</option>
					<option value="access_local_member">Lokal medlem</option>
				</optgroup>
				<optgroup label="Enligt tilldelat uppdrag:">
					<?php
					$stmt = $db->query("SELECT ass_name FROM assignments WHERE typeID=".TYPE_SECTION);
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						echo "<option>{$row['ass_name']}</option>";
					} ?>
				</optgroup>
			</select>
			
			<div id="cat-access-details">
				<p>2. Välj behörighetsnivå här:</p>
				<input type="radio" class="cat-access-choice" name="cat-access" id="cat-access-1" value="1">
				<label for="cat-access-1"> <?= $cfg['catAccessLevels'][1] ?></label>
				<input type="radio" class="cat-access-choice" name="cat-access" id="cat-access-2" value="2">
				<label for="cat-access-2"> <?= $cfg['catAccessLevels'][2] ?></label>
				<input type="radio" class="cat-access-choice" name="cat-access" id="cat-access-3" value="3">
				<label for="cat-access-3"> <?= $cfg['catAccessLevels'][3] ?></label>
				<button id="cat-access-cancel" class="ui-btn">Avbryt</button>
			</div>
			</fieldset>
		</form>
		
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']!="items" ? "true" : "false" ?>">
			<h2>Resurser</h2>
			<ul data-role="listview" data-filter="true">
				<?php
				$stmt = $db->prepare("SELECT items.*, item_images.thumb FROM items LEFT JOIN item_images USING (imageID) WHERE catID=? ORDER BY caption");
				$stmt->execute(array($cat['catID']));
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					echo "<li" . ($row['active'] ? "" : " class='inactive'") . "><a href='item.php?itemID={$row['itemID']}'>" .
						embedImage($row['thumb']) .
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
		var chosenGrp=0;

		$("#delete_cat").click(function() {
			if (confirm("Du håller på att ta bort kategorin och alla poster i den. Fortsätta?")) {
				location.href="?action=delete_cat&catID=<?= $cat['catID'] ?>";
			}
		});

		$("#cat-access-grp").change(function() {
			$(".cat-access-choice").attr("checked", false).checkboxradio("refresh");
			chosenGrp = this.value;
			$("#cat-access-details").show();
		});
		$("#cat-access-cancel").click(function() {
			$("#cat-access-details").hide();
			$("#cat-access-grp").val("").selectmenu("refresh");
			return false;
		});

		$(".cat-access-choice").click(function() {
			$.mobile.loading("show", {});
			$("#cat-access-details").hide();
			$("#cat-access-grp").val("").selectmenu("refresh");
			$.get("?action=setCatAccess&ass="+encodeURIComponent(chosenGrp)+"&cat_access="+this.value, function(data, status) {
				if (data!=0) {
					$("#assigned-cat-access").html(data).enhanceWithin();
				} else {
					alert("Kunde inte spara behörigheten.");
				}
				$.mobile.loading("hide", {});
			});
		});
		
		function unsetCatAccess(ass) {
			$.mobile.loading("show", {});
			$.get("?action=unsetCatAccess&ass="+encodeURIComponent(ass), function(data, status) {
				if (data!=0) {
					$("#assigned-cat-access").html(data).enhanceWithin();
				} else {
					alert("Kunde inte återkalla behörigheten.");
				}
				$.mobile.loading("hide", {});
			});
		}
	</script>

</div><!--/page-->
</body>
</html>
