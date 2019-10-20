<?php
session_start();
require("common.php");

// Show only for logged in users
if (!$_SESSION['user']['ID']) {
	header("Location: index.php");
	die();
}

switch ($_REQUEST['action']) {
case "deleteAccount":
	$db->exec("DELETE FROM users WHERE ID='{$_SESSION['user']['ID']}'");
	header("Location: index.php?logout");	
	break;
	
case "save user data":
	// User shall supply at least one of mail and phone
	if ($_POST['name'] && ($_POST['mail'] || $_POST['phone'])) {
		$stmt = $db->prepare("REPLACE INTO users SET ID='{$_SESSION['user']['ID']}', name=:name, mail=:mail, phone=:phone");
		$stmt->execute(array(
			":name"=>$_POST['name'],
			":mail"=>$_POST['mail'],
			":phone"=>$_POST['phone'],
		));
		$_SESSION['user']['name'] = $_POST['name'];
		$_SESSION['user']['mail'] = $_POST['mail'];
		$_SESSION['user']['phone'] = $_POST['phone'];
		header("Location: index.php");
	} else {
		$message = "Ange minst ditt namn samt antingen epostadress eller mobilnummer, tack.";
	}
	break;
}
	

if ($_GET['first_login']) $message = "Välkommen till resursbokningen! Innan du sätter igång med din bokning vill vi att du berättar vem du är, så att andra (t.ex. administratörer) kan komma i kontakt med dig vid frågor. Du kan läsa om hur vi hanterar dina uppgifter i <a href='help.php'>Hjälpen</a>.";

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning") ?>

	<script>
	$( document ).on( "mobileinit", function() {
		<?php if (isset($message)) { ?>
		$( document ).on( "pagecontainershow", function( event, ui ) {
			setTimeout(function() {
				$("#popupMessage").popup('open');
			}, 500); // We need some delay here to make this work on Chrome.
		} );
		<?php } ?>
	});
	
	function deleteAccount() {
		if (window.confirm("Bekräfta att du vill radera ditt konto i resursbokningen.")) {
			location.href="userdata.php?action=deleteAccount";
		}
	}
	</script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_userdata">
	<?= head("Min sida") ?>
	<div role="main" class="ui-content">

	<h3>Kontaktuppgifter</h3>
	<div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
		<p><?= isset($message) ? $message : "" ?></p>
		<?= isset($dontShowOK) ? "" : "<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>" ?>
	</div>
	
	<form action="userdata.php" method="post" data-ajax="false" onSubmit="saveUserData">
		<p>Uppgifter om dig så andra vet vem du är och hur de får tag i dig. Ange minst ditt namn samt antingen epostadress eller mobilnummer, tack.</p>
		<input type="hidden" name="action" value="save user data">
		<div class="ui-field-contain">
			<label for="userdata_name">Namn:</label>
			<input type="text" name="name" id="userdata_name" placeholder="Namn" value="<?= $_POST['name'] ? $_POST['name'] : $_SESSION['user']['name'] ?>">
		</div>
		<div class="ui-field-contain">
			<label for="userdata_mail">Epost:</label>
			<input type="email" name="mail" id="userdata_mail" placeholder="Epost" value="<?= $_POST['mail'] ? $_POST['mail'] : $_SESSION['user']['mail'] ?>">
		</div>
		<div class="ui-field-contain">
			<label for="userdata_phone">Mobil:</label>
			<input type="tel" name="phone" id="userdata_phone" placeholder="Mobilnummer" value="<?= $_POST['phone'] ? $_POST['phone'] : $_SESSION['user']['phone'] ?>">
		</div>
		<input type="submit" value="Spara" data-icon="check">
	</form>

	<hr>

	<?php if ($_SESSION['user']['assignments']) { ?>
	<h3>Uppdrag</h3>
	<p>Här listas de uppdrag som du har enligt aktivitetshanteraren. De används i resurshanteringen för att tilldela behörigheter.</p>
	<ul><?php
		foreach ($_SESSION['user']['assignments'] as $ass) {
			echo "<li>{$ass['name']} ({$ass['party']})</li>";
		} ?>
	</ul>
	<hr>
	<?php } ?>
	
	<h3>Radera kontot</h3>
	<p>Om du inte längre vill använda resursbokningen kan du radera personuppgifterna ovan. Om du gör det loggas du ut, och ditt konto raderas. Nästa gång du loggar in igen måste du ange nya personuppgifter innan du kan använda tjänsten igen.<br>
	Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
	<button class="ui-btn ui-btn-b" onClick="deleteAccount();">Radera mina uppgifter</button>
	</div><!--/main-->

</div><!--/page>
</body>
</html>
