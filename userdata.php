<?php
use FFBoka\User;
use FFBoka\Section;
use FFBoka\Booking;
use FFBoka\Category;
use FFBoka\FFBoka;

session_start();
require(__DIR__."/inc/common.php");

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: /");
    die();
}

$currentUser = new User($_SESSION['authenticatedUser']);

/**
 * Show a list of all categories and their children where user has admin permissions,
 * with switches to opt out of messages when new bookings arrive 
 * @param User $user
 * @param Category $cat
 */
function showNotificationOptout(User $user, Category $cat) {
    if ($cat->getAccess($user, FALSE) >= FFBoka::ACCESS_CONFIRM) {
        $notify = $user->getNotifyAdminOnNewBooking($cat);
        ?>
        <div class='ui-field-contain'>
        	<label><?= htmlspecialchars($cat->caption) ?></label>
        	<fieldset data-role="controlgroup" data-type="horizontal" data-mini="true">
            	<label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="0"<?= $notify=="no" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'no');">Av</label>
            	<label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="1"<?= $notify=="confirmOnly" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'confirmOnly');">Bekräfta</label>
            	<label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="2"<?= $notify=="yes" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'yes');">Alla</label>
        	</fieldset>
        </div><?php
    }
    foreach ($cat->children() as $child) {
        if ($child->showFor($user, FFBoka::ACCESS_CONFIRM)) showNotificationOptout($user, $child);
    }
}

switch ($_REQUEST['action']) {
    case "bookingDeleted":
        $message = "Din bokning har nu tagits bort.";
        break;
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
    	
    case "ajaxSetNotificationOptout":
        header("Content-Type: application/json");
        $ret = $currentUser->setNotifyAdminOnNewBooking($_REQUEST['catId'], $_REQUEST['notify']);
        if ($ret === FALSE ) {
            die(json_encode([ "status"=>"error", "error"=>"Något har gått fel. Kunde inte spara." ]));
        } elseif ($ret === 0) {
            die(json_encode([ "status"=>"warning", "warning"=>"OBS: Nu finns det inte någon bokningsansvarig kvar som får meddelande om nya bokningar som måste bekräftas!" ]));
        } else {
            die(json_encode([ "status"=>"OK" ]));
        }
}
	

if ($_GET['first_login']) $message = "Välkommen till resursbokningen! Innan du sätter igång med din bokning vill vi att du berättar vem du är, så att andra (t.ex. administratörer) kan komma i kontakt med dig vid frågor. Du kan läsa om hur vi hanterar dina uppgifter i <a href='help.php'>Hjälpen</a>.";

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning") ?>
</head>


<body>
<div data-role="page" id="page-userdata">
	<?= head("Min sida", $currentUser) ?>
	<div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-userdata" class="ui-content">
        <p id="msg-page-userdata"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

	<div data-role='collapsibleset' data-inset='false'>
		
		<div data-role='collapsible' data-collapsed='false'>
			<h3>Mina bokningar</h3>
			<ul data-role="listview">
			<?php
			$bookingIds = $currentUser->bookingIds();
			if (count($bookingIds)) {
    			foreach ($bookingIds as $id) {
    			    $b = new Booking($id);
    			    echo "<li><a href='book-sum.php?bookingId={$b->id}'><p>Bokat {$b->timestamp} i LA {$b->section()->name}:</p>";
    			    foreach ($b->items() as $item) {
    			        echo "<p><b>" . htmlspecialchars($item->caption) . "</b> (" . strftime("%F kl %k:00", $item->start) . " &mdash; " . strftime("%F kl %k:00", $item->end) . ")</p>";
    			    }
    			    echo "</a></li>";
    			}
			} else {
			    echo "<li>Du har inga bokningar.</li>";
			} ?>
            </ul>
        </div>
		
		<?php
		$sections = $currentUser->bookingAdminSections();
		if (count($sections)) { ?>
		<div data-role='collapsible'>
			<h3>Notifieringar</h3>
			<h4>Meddelanden vid nya bokningar</h4>
			<p><small>I följande kategorier har du administratörsbehörighet. Här kan du ställa in om du vill få meddelanden när nya bokningar görs. "Bekräfta" innebär att du bara får meddelanden för preliminärbokningar som måste bekräftas av någon bokningsansvarig.</small></p>
			<?php 
    		foreach ($sections as $sec) {
    		    echo "<p><b>" . htmlspecialchars($sec->name) . "</b></p>";
    		    foreach ($sec->getMainCategories() as $cat) {
    		        if ($cat->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) showNotificationOptout($currentUser, $cat);
    		    }
    		} ?>
        </div><?php
		} ?>
		
		<div data-role='collapsible'>
			<h3>Kontaktuppgifter</h3>
			
			<form action="" method="post" data-ajax="false">
				<p>Uppgifter om dig så andra vet vem du är och hur de kan får tag i dig.</p>
				<input type="hidden" name="action" value="save user data">
				<p>Medlemsnummer: <?= $currentUser->id ?></p>
				<p>Lokalavdelning: <?= $currentUser->section->name ?></p>
				<div class="ui-field-contain">
					<label for="userdata-name" class="required">Namn:</label>
					<input type="text" name="name" id="userdata-name" required placeholder="Namn" value="<?= htmlspecialchars($_POST['name'] ? $_POST['name'] : $currentUser->name) ?>">
				</div>
				<div class="ui-field-contain">
					<label for="userdata-mail" class="required">Epost:</label>
					<input type="email" name="mail" id="userdata-mail" required placeholder="Epost" value="<?= htmlspecialchars($_POST['mail'] ? $_POST['mail'] : $currentUser->mail) ?>">
				</div>
				<div class="ui-field-contain">
					<label for="userdata-phone" class="required">Telefon:</label>
					<input type="tel" name="phone" id="userdata-phone" required placeholder="Mobilnummer" value="<?= htmlspecialchars($_POST['phone'] ? $_POST['phone'] : $currentUser->phone) ?>">
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
