<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Booking;
session_start();
require(__DIR__."/inc/common.php");

$currentUser = new User($_SESSION['authenticatedUser'] ? $_SESSION['authenticatedUser'] : 0);

if (isset($_REQUEST['bookingId'])) {
    $_SESSION['bookingId'] = $_REQUEST['bookingId'];
}

if ($_SESSION['bookingId']) {
    // Open existing booking
    $booking = new Booking($_SESSION['bookingId']);
} else {
    header("Location: index.php");
    die();
}

// Check that current booking belongs to current user
if (($_SESSION['authenticatedUser'] && $booking->userId != $currentUser->id) || (!$_SESSION['authenticatedUser'] && !is_null($booking->userId))) {
    unset($_SESSION['bookingId']);
    header("Location: index.php?action=sessionExpired");
    die();
}

$_SESSION['sectionId'] = $booking->sectionId;
$section = new Section($_SESSION['sectionId']);


switch ($_REQUEST['action']) {
	case "confirmBooking":
	    // TODO: Check again if booking collides with existing ones
		// Set booking status of each item
		$leastStatus = FFBoka::STATUS_CONFIRMED;
		foreach ($booking->subbookings() as $subbooking) {
			foreach ($subbooking->items() as $item) {
			    if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_BOOK) $status=FFBoka::STATUS_CONFIRMED;
			    else $status = FFBoka::STATUS_PREBOOKED; 
		        $item->setStatus($status);
		        $leastStatus = $leastStatus & $status;
	        }
		}
		// TODO: send mail to admin and user
		unset($_SESSION['bookingId']);
		header("Location: index.php?action=bookingConfirmed&mail=" . urlencode($_REQUEST['mail']));
		break;
		
	case "deleteBooking":
	    $booking->delete();
	    unset($_SESSION['bookingId']);
	    header("Location: index.php?action=bookingDeleted");
	    break;
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Bekräfta bokningen") ?>
    <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page-booking">
    <?= head("Din bokning", $currentUser) ?>
    <div role="main" class="ui-content">

    <h4>Lokalavdelning: <?= htmlspecialchars($section->name) ?></h4>

	<ul data-role='listview' data-inset='true' data-divider-theme='a' data-split-icon='delete'>
	<?php
	foreach ($booking->subbookings() as $sub) {
		echo "<li data-role='list-divider'>" . strftime("%F kl %H", $sub->start) . " &mdash; " . strftime("%F kl %H", $sub->end) . "</li>";
		foreach ($sub->items() as $item) {
			echo "<li><a href='javascript:popup({$item->id})'>" .
			embedImage($item->getFeaturedImage()->thumb) .
			"<h3>" . htmlspecialchars($item->caption) . "</h3>" .
			"<p>" . htmlspecialchars($item->description) . "</p>" .
			"</a><a href='javascript:deleteItemFromBooking({$item->id})'>Ta bort</a></li>\n";
		}
	}
	?>
	</ul>
	<button onClick="location.href='subbooking.php'" data-ajax="false" class='ui-btn ui-icon-plus ui-btn-icon-right'>Lägg till fler resurser</button>
	

	<form action="" data-ajax="false">
		<input type="hidden" name="action" value="confirmBooking">
		<p class='ui-body ui-body-a'><i>[Visa bokningsfrågor här]</i></p>
		
		<?php if ($currentUser->id) { ?>
			<p class='ui-body ui-body-a'>
				Du är inloggad som <?= htmlspecialchars($currentUser->name) ?>, med följande kontaktuppgifter:<br>
				&phone;: <?= htmlspecialchars($currentUser->phone) ?><br>
				<b>@</b>: <?= htmlspecialchars($currentUser->mail) ?>
			</p>
			<input type="hidden" name="mail" value="<?= htmlspecialchars($currentUser->mail) ?>">
		<?php } else { ?>
    	    <div class='ui-body ui-body-a'>Ange dina kontaktuppgifter så vi kan nå dig vid frågor:<br>
    			<div class="ui-field-contain">
    				<label for="booker-name" class="required">Namn:</label>
    				<input type="text" name="name" id="booker-name" required placeholder="Namn">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-mail" class="required">Epost:</label>
    				<input type="email" name="mail" id="booker-mail" required placeholder="Epost">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-phone" class="required">Telefon:</label>
    				<input type="tel" name="phone" id="booker-phone" required placeholder="Mobilnummer">
    			</div>
    		</div>
		<?php } ?>
    	<input type="submit" data-icon="carat-r" data-iconpos="right" data-theme="b" data-corners="false" value="Bekräfta bokningen">
    	<a href="#" onClick="deleteBooking();" class='ui-btn ui-btn-c ui-icon-delete ui-btn-icon-right'>Ta bort bokningen</a>
    </form>
    
    </div><!--/main-->


    <script>
	function deleteBooking() {
		if (confirm("Är du säker på att du vill ta bort din bokning?")) {
			location.href="?action=deleteBooking";
		}
		return false;
	}
	</script>

</div><!-- /page -->

</body>
</html>
