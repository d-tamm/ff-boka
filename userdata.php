<?php
use FFBoka\User;
use FFBoka\Section;

session_start();
require(__DIR__."/inc/common.php");

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: /");
    die();
}

$currentUser = new User($_SESSION['authenticatedUser']);

switch ($_REQUEST['action']) {
case "deleteAccount":
    if ($currentUser->delete()) {
    	header("Location: index.php?logout&action=accountDeleted");
        break;
    } else {
        $message = "Något gick fel. Kontakta webmaster tack.";
    }
    break;
	
case "save user data":
	// User shall supply name, mail and phone
	if ($_POST['name'] && $_POST['mail'] && $_POST['phone']) {
	    $currentUser->name = $_POST['name'];
	    $currentUser->mail = $_POST['mail'];
	    $currentUser->phone = $_POST['phone'];
		header("Location: index.php?message=" . urlencode("Dina kontaktuppgifter har sparats."));
	} else {
		$message = "Fyll i namn, epostadress och mobilnummer, tack.";
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
		if (window.confirm("Bekräfta att du vill radera ditt konto i resursbokningen. Alla dina bokningar och persondata tas bort från systemet och kan inte återställas!")) {
			location.href="userdata.php?action=deleteAccount";
		}
	}
	</script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_userdata">
	<?= head("Min sida", $currentUser) ?>
	<div role="main" class="ui-content">
    <div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
    	<p><?= isset($message) ? $message : "" ?></p>
    	<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

	<div data-role='collapsibleset' data-inset='false'>
		
		<div data-role='collapsible' data-collapsed='false'>
			<h3>Kontaktuppgifter</h3>
			
			<form action="" method="post" data-ajax="false">
				<p>Uppgifter om dig så andra vet vem du är och hur de kan får tag i dig.</p>
				<input type="hidden" name="action" value="save user data">
				<p>Medlemsnummer: <?= $currentUser->id ?></p>
				<div class="ui-field-contain">
					<label for="userdata-name" class="required">Namn:</label>
					<input type="text" name="name" id="userdata-name" required placeholder="Namn" value="<?= $_POST['name'] ? $_POST['name'] : $currentUser->name ?>">
				</div>
				<div class="ui-field-contain">
					<label for="userdata-mail" class="required">Epost:</label>
					<input type="email" name="mail" id="userdata-mail" required placeholder="Epost" value="<?= $_POST['mail'] ? $_POST['mail'] : $currentUser->mail ?>">
				</div>
				<div class="ui-field-contain">
					<label for="userdata-phone" class="required">Telefon:</label>
					<input type="tel" name="phone" id="userdata-phone" required placeholder="Mobilnummer" value="<?= $_POST['phone'] ? $_POST['phone'] : $currentUser->phone ?>">
				</div>
				<input type="submit" value="Spara" data-icon="check">
				<p>Ditt lösenord hanteras på <a href="https://www.friluftsframjandet.se" target="_blank">Friluftsfrämjandets hemsida</a>. Du kan inte ändra det här.</p>
			</form>
		</div>
	
		<div data-role='collapsible'>
			<h3>Radera kontot</h3>
			<p>Om du inte längre vill använda resursbokningen kan du radera alla dina personuppgifter i systemet. Om du gör det loggas du ut, och ditt konto med alla relaterade uppgifter raderas. Om du åter vill använda tjänsten loggar du in igen med ditt medlemsnummer och måste då ange dina personuppgifter på nytt.</p>
			<p>Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
			<button class="ui-btn ui-btn-c" onClick="deleteAccount();" data-ajax='false'>Radera mina uppgifter</button>
		</div>
	
	
		<div data-role='collapsible'>
			<h3>Debug-info</h3><!-- TODO ta bort efter testfasen -->
			<p>Visas för teständamål. Tas bort i produktion.</p>
			<p>$_SESSION:</p>
			<pre><?php print_r($_SESSION); ?></pre>

			<?php if ($currentUser->assignments) { ?>
				<p>Uppdrag enligt aktivitetshanteraren:</p>
				<ul><?php
					foreach ($currentUser->assignments as $sectionId=>$assInSec) {
						$section = new Section($sectionId);
						foreach ($assInSec as $ass) {
							echo "<li>$ass ({$section->name})</li>";
						}
					} ?>
				</ul>
			<?php } ?>
		</div>

	</div><!--/collapsibleset-->
	
	</div><!--/main-->

</div><!--/page-->
</body>
</html>
