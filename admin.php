<?php
session_start();
require("common.php");

// Remember section name and ID
if ($_GET['sectionID']) {
	$_SESSION['sectionID'] = $_GET['sectionID'];
	$_SESSION['sectionName'] = $_GET['sectionName'];
}

// Check permissions
if (!isset($_SESSION['sectionID']) || !$_SESSION['user']['assignments']) {
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
case "setSectionAdmin":
	if ($_GET['value']=="true") {
		$stmt = $db->prepare("INSERT INTO section_admins SET sectionID=:sectionID, ass_name=:ass");
	} else {
		$stmt = $db->prepare("DELETE FROM section_admins WHERE sectionID=:sectionID AND ass_name=:ass");
	}
	$stmt->execute(array(
		":sectionID"=>$_SESSION['sectionID'],
		":ass"=>$_GET['ass'],
	));
	die("OK");
	break;
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - LA admin ".$_SESSION['sectionName']) ?>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_section_admin">
	<?= head("LA ".$_SESSION['sectionName']) ?>
	<div role="main" class="ui-content">

	<div data-role="collapsibleset" data-inset="false">
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['section']=="cat" ? "false" : "true" ?>">
			<h2>Kategorier</h2>
			<ul data-role="listview">
			<?php
			$stmt = $db->prepare("SELECT * FROM categories WHERE sectionID=? ORDER BY name");
			$stmt->execute(array($_SESSION['sectionID']));
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				echo "<li><a href='category.php?catID={$row['catID']}' data-ajax='false'>
					{$row['name']}
					" . embed_image($row['thumb']) . "
					</a></li>";
			} ?>
			<li><a href="category.php">Skapa ny kategori</a></li>
			</ul>
		</div>

		<div data-role="collapsible">
			<h2>Administratörer</h2>
			<p>Här bestäms vilka uppdrag som ska ge behörighet till att administrera resursbokningen i din lokalavdelning. Tilldelning av uppdragen görs i aktivitetshanteraren.</p>
			<form>
				<fieldset data-role='controlgroup'>
					<?php
					$stmt=$db->prepare("SELECT ass_name, sectionID FROM assignments LEFT JOIN section_admins USING (ass_name) WHERE typeID=478880001 AND (sectionID=? OR sectionID IS NULL)");
					$stmt->execute(array($_SESSION['sectionID']));
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						echo "<label class='check-ass'><input class='section-admin' type='checkbox' value='{$row['ass_name']}' data-mini='true'" . ($row['sectionID'] ? " checked='true'" : "") . (in_array($row['ass_name'], $cfg['sectionAdmins']) ? " checked='true' disabled='true'" : "") . "> {$row['ass_name']}</label>";
					} ?>
				</fieldset>
			</form>
		</div>

	</div><!--/collapsibleset-->
	</div><!--/main-->

	<script>
		$(".section-admin").change(function(){
			var _this = this;
			$.get("?action=setSectionAdmin&ass="+this.value+"&value="+this.checked, function(data, status) {
				$(_this).parent().addClass("confirm-change");
				setTimeout(function(){
					$(_this).parent().removeClass("confirm-change");
				},1000);
			});
		});
	</script>

</div><!--/page>
</body>
</html>
