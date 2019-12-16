<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\FFBoka;
use FFBoka\Booking;
use FFBoka\Question;
use FFBoka\Subbooking;
global $cfg;

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

// Check if booking collides with existing ones
$unavail = array();
$conflicts = array();
foreach ($booking->subbookings() as $subbooking) {
    foreach ($subbooking->items() as $item) {
        if (!$item->isAvailable($subbooking->start, $subbooking->end, $subbooking->id)) {
            if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_PREBOOK) {
                // User can see freebusy information. Let him change booking.
                $unavail[] = $item->bookedItemId;
            } else {
                // User can't see freebusy information. Flag as conflict upon confirmation.
                $conflicts[] = $item->bookedItemId;
            }
        }
        
    }
}

switch ($_REQUEST['action']) {
	case "confirmBooking":
	    if (count($unavail)) break;
	    $mailItems = "";
	    // save answers to questions
	    if (isset($_REQUEST['questionId'])) {
    	    foreach ($_REQUEST["questionId"] as $id) {
    	        $question = new Question($id);
    	        $booking->addAnswer($question->caption, implode(", ", isset($_REQUEST["answer-$id"]) ? $_REQUEST["answer-$id"] : array()));
    	    }
	    }
		// Set booking status of each item, and build confirmation string incl post-booking messages
		$leastStatus = FFBoka::STATUS_CONFIRMED;
		$messages = array();
		foreach ($booking->subbookings() as $subbooking) {
		    $mailItems .= "<p><b>Bokat från " . strftime("%F kl %k", $subbooking->start) . " till " . strftime("%F kl %k", $subbooking->end) . ":</b></p><ul>";
			foreach ($subbooking->items() as $item) {
			    $msgRef = array();
			    foreach ($item->category()->postbookMsgs() as $msg) {
    			    if (in_array($msg, $messages)) $msgRef[] = "(".(array_search($msg, $messages)+1).")";
    			    else { $messages[] = "<p>(" . (count($messages)+1) . ") $msg</p>"; $msgRef[] = "(".count($messages).")"; }
			    }
			    $mailItems .= "<li>{$item->caption} " . implode(" ", $msgRef) . "</li>";
			    if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_BOOK) $status=FFBoka::STATUS_CONFIRMED;
			    else {
			        if (in_array($item->bookedItemId, $conflicts)) $status = FFBoka::STATUS_CONFLICT;
			        else $status = FFBoka::STATUS_PREBOOKED; 
			    }
		        $item->setStatus($status);
		        $leastStatus = $leastStatus & $status;
	        }
	        $mailItems .= "</ul>";
		}
		$booking->commentCust = $_REQUEST['commentCust'];
		$answers = $booking->answers();
		if (count($answers)) {
		    $mailAnswers = "<p>Bokningsfrågor och dina svar:</p>"; 
		    foreach ($answers as $ans) $mailAnswers .= "<p>Fråga: {$ans->question}<br>Ditt svar: {$ans->answer}</p>";
		}
		try {
		    sendmail(
		        $cfg['mailFrom'],
		        $_REQUEST['booker-mail'],
		        "",
		        "Bokningsbekräftelse #{$booking->id}",
		        $cfg['SMTP'], // SMPT options
		        "confirm_booking", // template name
		        array( // replace.
		            "{{name}}"   => $_REQUEST['booker-name'],
		            "{{items}}"  => $mailItems,
		            "{{messages}}" => implode("", $messages),
		            "{{status}}" => $leastStatus==FFBoka::STATUS_CONFIRMED ? "Bokningen är nu bekräftad." : "Bokningen är preliminär och behöver bekräftas av ansvarig handläggare.",
		            "{{answers}}"=> $mailAnswers,
		            "{{bookingLink}}" => $_SESSION['authenticatedUser'] ? "<a href='{$cfg['url']}'>Logga in på resursbokningen</a> för att se och ändra din bokning." : "",
		        )
	        );
	    } catch(Exception $e) {
	        $message = "Kunde inte skicka bekräftelsen:" . $e;
	        break;
	    }
		unset($_SESSION['bookingId']);
		header("Location: index.php?action=bookingConfirmed&mail=" . urlencode($_REQUEST['booker-mail']));
		break;
		
	case "deleteBooking":
	    $booking->delete();
	    unset($_SESSION['bookingId']);
	    header("Location: index.php?action=bookingDeleted");
	    break;
	    
	case "ajaxRemoveItem":
	    $subbooking = new Subbooking($_REQUEST['subId']);
	    if ($subbooking->removeItem($_REQUEST['bookedItemId'])) {
	        if (count($subbooking->items()) == 0) {
	            if ($subbooking->delete()) {
	                if (count($booking->subbookings()) == 0) {
	                    $booking->delete();
	                    unset($_SESSION['bookingId']);
	                    die(json_encode([ "status"=>"booking empty" ]));
	                }
	            }
	            else die(json_encode([ "error"=>"Oväntat fel: Kan inte ta bort delbokningen. Kontakta systemadministratören."]));
	        }
	        die(json_encode([ "status"=>"OK" ]));
	    }
	    die(json_encode([ "error"=>"Oväntat fel: Kan inte ta bort resursen. Kontakta systemadministratören."]));
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Bekräfta bokningen") ?>
</head>


<body>
<div data-role="page" id="page-book-sum">
    <?= head("Din bokning", $currentUser) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-book-sum" class="ui-content">
        <p id="msg-page-book-sum"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars($section->name) ?></h4>

	<?php
	if (count($unavail)) echo "<p class='ui-body ui-body-c'>Några av de resurser du har valt är inte längre tillgängliga vid den valda tiden. De är markerade nedan. För att kunna slutföra bokningen behöver du ta bort dessa resurser eller ändra tiden genom att ta bort dem och sedan lägga till dem igen med en annan, ledig tid.</p>";
	?>

	<ul data-role='listview' data-inset='true' data-divider-theme='a' data-split-icon='delete'>
	<?php
	$questions = array();
	foreach ($booking->subbookings() as $sub) {
		echo "<li data-role='list-divider'>" . strftime("%F kl %H", $sub->start) . " &mdash; " . strftime("%F kl %H", $sub->end) . "</li>";
		foreach ($sub->items() as $item) {
		    foreach ($item->category()->getQuestions() as $id=>$q) {
		        if (isset($questions[$id])) $questions[$id]=($questions[$id] || $q->required);
		        else $questions[$id]=$q->required;
		    }
		    
			echo "<li" . (in_array($item->bookedItemId, $unavail) ? " data-theme='c'" : "") . "><a href='javascript:popupItemDetails({$item->id})'>" .
			embedImage($item->getFeaturedImage()->thumb) .
			"<h3 style='white-space:normal;'>" . htmlspecialchars($item->caption) . "</h3><p>";
			if (in_array($item->bookedItemId, $unavail)) echo "Inte tillgänglig";
			else {
			    switch ($item->getStatus()) {
			    case FFBoka::STATUS_PENDING: echo "Bokning ej slutförd än"; break;
			    case FFBoka::STATUS_CONFLICT:
			    case FFBoka::STATUS_PREBOOKED: echo "Väntar på bekräftelse"; break;
			    case FFBoka::STATUS_CONFIRMED: echo "Bekräftat"; break;
			    }
		    };
		    echo "</p></a><a href='#' onClick='removeItem({$sub->id},{$item->bookedItemId});'>Ta bort</a></li>\n";
		}
	}
	?>
	</ul>
	<button onClick="location.href='book-part.php'" data-transition='slide' data-direction='reverse' class='ui-btn ui-icon-plus ui-btn-icon-right'>Lägg till fler resurser</button>
	

	<form id='form-booking' action="book-sum.php" method='post'>
		<input type="hidden" name="action" value="confirmBooking">
		
		<?php
		$requiredCheckboxradios = array();
		if (count($questions)) echo "<div class='ui-body ui-body-a'>";
		foreach ($questions as $id=>$required) {
		    $question = new Question($id);
		    echo "<input type='hidden' name='questionId[]' value='$id'>\n";
		    echo "<fieldset data-role='controlgroup' data-mini='true'>\n";
		    // For checkbox questions with only one empty choice, move the caption to the checkbox instead
		    if ( !( ($question->type=="checkbox" || $question->type=="radio") && $question->options->choices[0]=="") ) echo "\t<legend" . ($required ? " class='required'" : "") . ">" . htmlspecialchars($question->caption) . "</legend>\n";
		    switch ($question->type) {
		        case "radio":
		        case "checkbox":
		            if ($required) $requiredCheckboxradios[$id] = $question->caption;
		            foreach ($question->options->choices as $choice) {
		                echo "\t<label><input type='{$question->type}' name='answer-{$id}[]' value='" . htmlspecialchars($choice ? $choice : "Ja") . "'> " . htmlspecialchars($choice ? $choice : $question->caption) . ($choice ? "" : " <span class='required'></span>") . "</label>\n";
		            }
		            break;
		        case "text":
		            echo "<input" . ($question->options->length ? " maxlength='{$question->options->length}'" : "") . " name='answer-{$id}[]'" . ($required ? " required='true'" : "") . ">\n";
		            break;
		        case "number":
		            echo "<input type='number'" . (strlen($question->options->min) ? " min='{$question->options->min}'" : "") . ($question->options->max ? " max='{$question->options->max}'" : "") . " name='answer-{$id}[]'" . ($required ? " required='true'" : "") . ">\n";
		            break;
		    }
		    echo "</fieldset>";
		}
		// We need to inject verification code for checking required questions here - can't be done in central js file. 
		echo "<script>reqCheckRadios = " . json_encode($requiredCheckboxradios) . ";</script>";

		if (count($questions)) echo "</div>\n\n";
		?>
		
		
		<?php if ($currentUser->id) { ?>
			<p class='ui-body ui-body-a'>
				Du är inloggad som <?= htmlspecialchars($currentUser->name) ?>, med följande kontaktuppgifter:<br>
				&#9742;: <?= htmlspecialchars($currentUser->phone) ?><br>
				<b>@</b>: <?= htmlspecialchars($currentUser->mail) ?>
			</p>
			<input type="hidden" name="booker-mail" value="<?= htmlspecialchars($currentUser->mail) ?>">
			<input type="hidden" name="booker-name" value="<?= htmlspecialchars($currentUser->name) ?>">
		<?php } else { ?>
    	    <div class='ui-body ui-body-a'>Ange dina kontaktuppgifter så vi kan nå dig vid frågor:<br>
    			<div class="ui-field-contain">
    				<label for="booker-name" class="required">Namn:</label>
    				<input type="text" name="booker-name" id="booker-name" required placeholder="Namn">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-mail" class="required">Epost:</label>
    				<input type="email" name="booker-mail" id="booker-mail" required placeholder="Epost">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-phone" class="required">Telefon:</label>
    				<input type="tel" name="booker-phone" id="booker-phone" required placeholder="Mobilnummer">
    			</div>
    		</div>
		<?php } ?>
		
		<textarea name="commentCust" placeholder="Här kan du lämna valfritt meddelande."><?= $booking->commentCust ?></textarea>

    	<input type="submit" data-icon="carat-r" data-iconpos="right" data-theme="b" data-corners="false" value="Bekräfta bokningen">
    	<a href="#" onClick="deleteBooking();" class='ui-btn ui-btn-c ui-icon-delete ui-btn-icon-right'>Ta bort bokningen</a>
    </form>
    
    </div><!--/main-->

	<div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b"></div>

</div><!-- /page -->

</body>
</html>
