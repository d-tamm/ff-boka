<?php
session_start();
require("common.php");
global $db, $cfg;

// Remember section name and ID
if ($_GET['sectionID']) {
	$stmt = $db->prepare("SELECT sectionID, name FROM sections WHERE sectionID=?");
	$stmt->execute(array($_GET['sectionID']));
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$_SESSION['sectionID'] = $row['sectionID'];
	$_SESSION['sectionName'] = $row['name'];
}
unset($_SESSION['catID']);

if (!isSectionAdmin($_SESSION['sectionID'])) {
	header("Location: index.php");
	die();
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
		<div data-role="collapsible" data-collapsed="<?= isset($_REQUEST['expand']) ? "true" : "false" ?>">
			<h2>Kategorier</h2>
			<ul data-role="listview">
			<?php
			$stmt = $db->prepare("SELECT * FROM categories WHERE sectionID=? ORDER BY caption");
			$stmt->execute(array($_SESSION['sectionID']));
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				echo "<li><a href='category.php?catID={$row['catID']}' data-ajax='false'>
					{$row['caption']}
					" . embedImage($row['thumb']) . "
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
					// First list all assignments which are set in config or have been set earlier
					foreach ($cfg['sectionAdmins'] as $ass) {
						echo "<label class='check-ass'><input class='section-admin' type='checkbox' value='$ass' data-mini='true' checked='true' disabled='true'> $ass</label>";
					}
					$stmt = $db->query("SELECT * FROM section_admins WHERE sectionID={$_SESSION['sectionID']}");
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						echo "<label class='check-ass'><input class='section-admin' type='checkbox' value='{$row['ass_name']}' data-mini='true' checked='true'> {$row['ass_name']}</label>";
					}
					// Then all others
					$stmt1 = $db->query("SELECT ass_name FROM assignments WHERE typeID=".TYPE_SECTION);
					while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
						$stmt2 = $db->query("SELECT ID FROM section_admins WHERE sectionID={$_SESSION['sectionID']} AND ass_name='{$row['ass_name']}'");
						if (!$stmt2->rowCount() && !in_array($row['ass_name'], $cfg['sectionAdmins'])) echo "<label class='check-ass'><input class='section-admin' type='checkbox' value='{$row['ass_name']}' data-mini='true'> {$row['ass_name']}</label>";
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
				if (data=="OK") {
					$(_this).parent().addClass("confirm-change");
					setTimeout(function(){
						$(_this).parent().removeClass("confirm-change");
					},1000);
				} else {
					alert("Kunde inte spara ditt val.");
				}
			});
		});
	</script>

</div><!--/page-->
</body>
</html>
