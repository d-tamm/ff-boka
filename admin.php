<?php
session_start();
require("common.php");

if ($_SESSION['user']['role'] !== "admin") {
	header("Location: index.php");
	die();
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHead("Friluftsfrämjandets resursbokning - Administration") ?>
	<script>
	</script>
</head>


<body>
<div data-role="page" id="start">
	<?= head("Admin") ?>

	<div data-role="collapsibleset" data-inset="false">
		<div data-role="collapsible" data-collapsed="false">
			<h3>Tilldela admin-roll</h3>
			<form id="formAddLaAdmin" method="POST" action="admin.php" data-ajax="false">
				<input type="hidden" name="action" value="add LA admin">
				<input name="ID" id="ID" data-clear-btn="true" placeholder="medlemsnummer" type="number" maxlength="7">
				<button type="submit" style="transition:background 2s;" id="btnAddLaSend" class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-plus ui-corner-all ui-shadow">Gör till LA-admin</button>
				<button type="submit" style="transition:background 2s;" id="btnAddAdminSend" class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-plus ui-corner-all ui-shadow">Gör till Admin</button>
			</form>
		</div>

		<div data-role="collapsible">
			<h3>LA-admins</h3>

			<ul data-role="listview" data-filter="true" data-filter-placeholder="Filtrera" data-inset="true" data-split-icon="delete">
			<?php
			$stmt = $db->query("SELECT * FROM admins WHERE la!=''");
			if ($stmt->rowCount()) {
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>		
					<li>
						<a href="" data-transition='slide'>
							<h2><?= $row['name'] ?></h2>
							<p><?= $row['la'] ?></p>
						</a>
						<a href="itemedit.php?ID=<?= $row['ID'] ?>"></a>
					</li><?php
				}
			} else {
				echo "<p>Det finns inga LA-administratörer än.</p>";
			} ?>
			</ul>

		</div>

		<div data-role="collapsible">
			<h3>Admins</h3>
		</div>

	</div><!-- /collapsibleset -->

</div>
</body>
</html>
