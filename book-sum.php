<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\FFBoka;
use FFBoka\Booking;
use FFBoka\Question;
use FFBoka\Item;
global $cfg;

session_start();
require(__DIR__."/inc/common.php");

$currentUser = new User($_SESSION['authenticatedUser'] ? $_SESSION['authenticatedUser'] : 0);

if (isset($_REQUEST['bookingId'])) {
    $_SESSION['bookingId'] = $_REQUEST['bookingId'];
}

if ($_SESSION['bookingId']) {
    // Open existing booking
    try {
        $booking = new Booking($_SESSION['bookingId']);
    } catch (Exception $e) {
        unset($_SESSION['bookingId']);
        header("Location: index.php?action=bookingNotFound");
        die();
    }
} else {
    header("Location: index.php");
    die();
}

// Check that current booking belongs to current user, or correct token is given
if (isset($_REQUEST['token'])) $_SESSION['token'] = $_REQUEST['token'];
if (!(
    (isset($_SESSION['token']) && ($_SESSION['token'] == $booking->token)) || // correct token
    ($_SESSION['authenticatedUser'] && ($booking->userId == $currentUser->id)) // same user
)) {
    if (!$_SESSION['authenticatedUser']) {
        header("Location: index.php?message=" . urlencode("Du måste logga in för att se bokningen.") . "&redirect=" . urlencode("book-sum.php?bookingId={$_REQUEST['bookingId']}"));
        die();
    }
    // Last access check: current user must be admin of some used category
    $isAdmin = FALSE;
    foreach ($booking->items() as $item) {
        if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_CONFIRM) {
            $isAdmin = TRUE;
            break;
        }
    }
    if (!$isAdmin) {
        unset($_SESSION['bookingId']);
        header("Location: index.php?action=accessDenied&to=" . urlencode("bokningen.") . "&redirect=" . urlencode("book-sum.php?bookingId={$_REQUEST['bookingId']}"));
        die();
    }
}

$_SESSION['sectionId'] = $booking->sectionId;
$section = new Section($_SESSION['sectionId']);

// Check if booking collides with existing ones
$unavail = array();
$conflicts = array();
foreach ($booking->items() as $item) {
    if (!$item->isAvailable($item->start, $item->end)) {
        if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_PREBOOK) {
            // User can see freebusy information. Let him change booking.
            $unavail[] = $item->bookedItemId;
        } else {
            // User can't see freebusy information. Flag as conflict upon confirmation.
            $conflicts[] = $item->bookedItemId;
        }
    }
}

switch ($_REQUEST['action']) {
    case "help":
        // TODO: write help text for booking summary page
        echo <<<EOF
Det finnt inte ännu någon hjälp till denna sida.
EOF;
        die();
    case "ajaxFreebusyItem":
        // Get freebusy bars for current booked item
        $item = new Item($_SESSION['bookedItemId'], TRUE);
        header("Content-Type: application/json");
        if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_PREBOOK) {
            $freebusyBar = $item->freebusyBar([ 'start'=>$_REQUEST['start'] ]);
        }
        die(json_encode([
            "freebusyBar"=>$freebusyBar,
        ]));

    case "confirmBooking":
	    if (count($unavail)) break;
	    $mailItems = "";
	    // remove old answers previously saved with booking
	    $booking->clearAnswers();
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
		$rawContData = array();
		$adminsToNotify = array();
		foreach ($booking->items() as $item) {
		    $cat = $item->category();
		    // remember contact data for item
		    $itemContact = $cat->contactData();
		    if ($itemContact) $rawContData[$itemContact][] = htmlspecialchars($cat->caption);
		    // Remember postbook messages and build string of reference numbers (e.g. (1), (2))
		    $msgRef = "";
		    foreach ($cat->postbookMsgs() as $msg) {
			    if (in_array($msg, $messages)) $msgRef .= " (".(array_search($msg, $messages)+1).")";
			    else { $messages[] = $msg; $msgRef .= "(".count($messages).")"; }
		    }
		    // Bullet list with booked items
		    $mailItems .= "<li><b>" . htmlspecialchars($item->caption) . "</b> " . strftime("%a %F kl %k:00", $item->start) . " till " . strftime("%a %F kl %k:00", $item->end) . " $msgRef</li>";
		    // Decide which status the booked item shall get
		    if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_BOOK) {
		        $item->status = FFBoka::STATUS_CONFIRMED;
		    }
		    else {
		        if (in_array($item->bookedItemId, $conflicts)) $item->status = FFBoka::STATUS_CONFLICT;
		        else $item->status = FFBoka::STATUS_PREBOOKED; 
		    }
	        $leastStatus = $leastStatus & $item->status;
	        // collect admins to notify about booking: array[userId][itemId1, itemId2...]
	        foreach ($cat->admins(FFBoka::ACCESS_CONFIRM, TRUE) as $adm) {
	            $admin = new User($adm['userId']);
	            switch ($admin->getNotifyAdminOnNewBooking($cat)) {
	                case "confirmOnly":
	                    if ($item->status > FFBoka::STATUS_PREBOOKED) break; 
	                case "yes":
	                    $adminsToNotify[$adm['userId']][] = $item->bookedItemId;
	            }
	        }
		}
		$contactData = "";
		foreach ($rawContData as $cd=>$captions) {
		    $contactData .= "<p>Kontakt vid frågor angående " . implode(" och ", array_unique($captions)) . ":<br>$cd</p>";
		}
		$booking->commentCust = $_REQUEST['commentCust'];
		if ($_REQUEST['commentIntern']) $booking->commentIntern = $_REQUEST['commentIntern'];
		$booking->extName = $_REQUEST['extName'];
		$booking->extPhone = $_REQUEST['extPhone'];
		$booking->extMail = $_REQUEST['extMail'];
		$answers = $booking->answers();
		if (count($answers)) {
		    $mailAnswers = "<p>Bokningsfrågor och dina svar:</p>"; 
		    foreach ($answers as $ans) $mailAnswers .= "<p>Fråga: {$ans->question}<br>Ditt svar: {$ans->answer}</p>";
		}
		try {
		    sendmail(
		        $cfg['mailFrom'], // from address
		        $cfg['mailFromName'], // from name
		        is_null($booking->userId) ? $_REQUEST['extMail'] : $booking->user()->mail, // to
		        "", // replyTo (use From address)
		        "Bokningsbekräftelse #{$booking->id}", // subject
		        $cfg['SMTP'], // SMPT options
		        "confirm_booking", // template name
		        array( // replace.
		            "{{name}}"    => is_null($booking->userId) ? $_REQUEST['extName'] : $booking->user()->name,
		            "{{items}}"   => $mailItems,
		            "{{messages}}"=> $messages ? "<li>".implode("</li><li>", $messages)."</li>" : "",
		            "{{status}}"  => $leastStatus==FFBoka::STATUS_CONFIRMED ? "Bokningen är nu bekräftad." : "Bokningen är preliminär och behöver bekräftas av ansvarig handläggare.",
		            "{{contactData}}" => $contactData,
		            "{{answers}}" => $mailAnswers,
		            "{{commentCust}}" => $booking->commentCust ? "Meddelande du har lämnat:<br>{$booking->commentCust}" : "",
		            "{{bookingLink}}" => "{$cfg['url']}book-sum.php?bookingId={$booking->id}&token={$booking->token}",
		        )
	        );
	    } catch(Exception $e) {
	        $message = "Kunde inte skicka bekräftelsen:" . $e;
	        break;
	    }
	    // Send notifications to admins
	    foreach ($adminsToNotify as $id=>$itemIds) {
	        $adm = new User($id);
	        if ($adm->mail) { // can only send if admin has email address
    	        $mailItems = "";
    	        foreach ($itemIds as $itemId) {
    	            $item = new Item($itemId, TRUE);
    	            $mailItems .= "<li><b>" . htmlspecialchars($item->caption) . "</b> " . strftime("%a %F kl %k:00", $item->start) . " till " . strftime("%a %F kl %k:00", $item->end) . "</li>";
    	        }
        	    sendmail(
        	        $cfg['mailFrom'], // from address
        	        $cfg['mailFromName'], // from Name
        	        $adm->mail, // to
        	        "", // replyTo (use From address)
        	        "Ny bokning att bekräfta", // subject
        	        $cfg['SMTP'], // SMTP options
        	        "booking_alert",
        	        array(
                        "{{name}}"=>htmlspecialchars($adm->name),
        	            "{{items}}"=>$mailItems,
        	            "{{bookingLink}}" => "{$cfg['url']}book-sum.php?bookingId={$booking->id}",
        	        )
    	        );
	        }
	    }
		unset($_SESSION['bookingId']);
		header("Location: index.php?action=bookingConfirmed&mail=" . urlencode(is_null($booking->userId) ? $_REQUEST['extMail'] : $booking->user()->mail));
		break;
		
	case "ajaxDeleteBooking":
	    header("Content-Type: application/json");
	    // Check permissions: Only the original user and section admin can delete whole bookings
	    if ($section->getAccess($currentUser) < FFBoka::ACCESS_SECTIONADMIN && $booking->userId !== $_SESSION['authenticatedUser'] && $_SESSION['token'] != $booking->token) {
	        die(json_encode([ "error"=>"Du har inte behörighet att ta bort bokningen. :-P" ]));
	    }
	    $booking->delete();
        unset($_SESSION['bookingId']);
	    die(json_encode([ "status"=>"OK" ]));
	    
	case "ajaxSetItemPrice":
	    header("Content-Type: application/json");
	    // Check permissions: Only admins for this item may set price
	    $item = new Item($_REQUEST['bookedItemId'], TRUE);
	    if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_CONFIRM) {
	        if (is_numeric($_REQUEST['price'])) $item->price = $_REQUEST['price'];
	        elseif ($_REQUEST['price']==="") $item->price = NULL;
	        else die(json_encode([ "status"=>"error", "error"=>"Ogiltig inmatning." ]));
	        die(json_encode([ "status"=>"OK" ]));
	    } else {
	        die(json_encode([ "status"=>"error", "error"=>"Du har inte behörighet att sätta pris. :-P"]));
	    }
	    
	case "ajaxSetPaid":
	    header("Content-Type: application/json");
	    // Check permissions: User needs to have some admin assignment
	    if ($section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) {
	        if (is_numeric($_REQUEST['paid'])) $booking->paid = $_REQUEST['paid'];
	        elseif ($_REQUEST['paid']==="") $booking->paid = NULL;
	        else die(json_encode([ "status"=>"error", "error"=>"Ogiltig inmatning." ]));
	        die(json_encode([ "status"=>"OK" ]));
	    } else {
	        die(json_encode([ "status"=>"error", "error"=>"Du har inte behörighet att sätta värdet. :-P"]));
	    }
	    
	case "ajaxRemoveItem":
	    header("Content-Type: application/json");
	    // Check permissions
	    $item = new Item($_REQUEST['bookedItemId'], TRUE);
	    if ($item->category()->getAccess($currentUser) < FFBoka::ACCESS_CONFIRM && ($booking->userId && $booking->userId!==$_SESSION['authenticatedUser']) && $_SESSION['token'] != $booking->token) {
	        die(json_encode([ "error"=>"Du har inte behörighet att ta bort resursen." ]));
	    }
	    if ($item->removeFromBooking()) die(json_encode([ "status"=>"OK" ]));
	    else die(json_encode([ "error"=>"Oväntat fel: Kan inte ta bort resursen. Kontakta systemadministratören."]));
	    
	case "ajaxConfirmBookedItem":
	    header("Content-Type: application/json");
        $item = new Item($_REQUEST['bookedItemId'], TRUE);
        if ($item->category()->getAccess($currentUser) < FFBoka::ACCESS_CONFIRM && $booking->userId !== $_SESSION['authenticatedUser'] && $booking->token != $_SESSION['token']) {
            die(json_encode([ "error" => "Du har inte behörighet att bekräfta resursen." ]));
        }
        $item->status = FFBoka::STATUS_CONFIRMED;
        // Check if this was the last item to be confirmed in booking
        $allConfirmed = TRUE;
        foreach ($item->booking()->items() as $it) {
            if ($it->status < FFBoka::STATUS_CONFIRMED) { $allConfirmed = FALSE; break; }
        }
        if ($allConfirmed) {
            try {
                sendmail(
                    $cfg['mailFrom'], // from address
                    $cfg['mailFromName'], // from Name
                    is_null($booking->userId) ? $booking->extMail : $booking->user()->mail, // to
                    "", // replyTo
                    "Bokning #{$booking->id} är nu bekräftad", // subject
                    $cfg['SMTP'], // SMPT options
                    "all_items_confirmed", // template name
                    array( // replace
                        "{{name}}"    => is_null($booking->userId) ? $booking->extName : $booking->user()->name,
                        "{{bookingLink}}" => "{$cfg['url']}book-sum.php?bookingId={$booking->id}&token={$booking->token}",
                    )
                );
            } catch(Exception $e) {
                die(json_encode([ "error" => "Kunde inte skicka meddelande till användaren:" . $e ]));
            }
        }
        die(json_encode(['status'=>'OK']));
        
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Bekräfta bokningen", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-book-sum">
    <?= head("Din bokning", $cfg['url'], $currentUser) ?>
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
	$leastStatus = FFBoka::STATUS_CONFIRMED;
	foreach ($booking->items() as $item) {
	    $leastStatus = min($leastStatus, $item->status);
	    $showEditButtons = ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_CONFIRM);
	    foreach ($item->category()->getQuestions() as $id=>$q) {
	        if (isset($questions[$id])) $questions[$id]=($questions[$id] || $q->required);
	        else $questions[$id]=$q->required;
	    }
		echo "<li" . (in_array($item->bookedItemId, $unavail) ? " data-theme='c'" : "") . ">";
		if ($showEditButtons) {
		    echo "<div class='item-edit-buttons'>";
		    if ($item->status == FFBoka::STATUS_CONFLICT || $item->status == FFBoka::STATUS_PREBOOKED) {
		        echo "<button id='book-item-btn-confirm-{$item->bookedItemId}' class='ui-btn ui-btn-inline ui-btn-b' onclick=\"confirmBookedItem({$item->bookedItemId});\">Bekräfta</button>";
		    }
		    echo "<button class='ui-btn ui-btn-inline ui-btn-b' onclick=\"setItemPrice({$item->bookedItemId}, {$item->price});\">Sätt pris</button></div>";
		}
		echo "<a href='#' onClick='popupItemDetails({$item->bookedItemId});'" . ($showEditButtons ? " class='has-edit-buttons'" : "") . ">" . embedImage($item->getFeaturedImage()->thumb) .
		"<h3 style='white-space:normal;'>" . htmlspecialchars($item->caption) . "</h3><p style='overflow:auto; white-space:normal; margin-bottom:0px;'>";
		echo strftime("%F kl %H", $item->start) . " &mdash; " . strftime("%F kl %H", $item->end) . "<br>\n";
		if (in_array($item->bookedItemId, $unavail)) echo "Inte tillgänglig";
		else {
		    switch ($item->status) {
		    case FFBoka::STATUS_PENDING: echo "Bokning ej slutförd än"; break;
		    case FFBoka::STATUS_CONFLICT:
		    case FFBoka::STATUS_PREBOOKED:
		        echo "<span id='book-item-status-{$item->bookedItemId}'>Väntar på bekräftelse</span>"; break;
		    case FFBoka::STATUS_CONFIRMED: echo "Bekräftat"; break;
		    }
		}
		if (!is_null($item->price)) echo "<span id='book-item-price-{$item->bookedItemId}' class='ui-li-count'>{$item->price} kr</span>";
		echo "</p></a>\n";
	    echo "<a href='#' onClick='removeItem({$item->bookedItemId});'>Ta bort</a></li>\n";
	}
	?>
	</ul>

	<button onClick="location.href='book-part.php'" data-transition='slide' data-direction='reverse' class='ui-btn ui-icon-plus ui-btn-icon-right'>Lägg till fler resurser</button>
	
	<?php
	$price = $booking->price;
	$paid = $booking->paid;
	if (!is_null($price)) { ?>
    	<div class='ui-body ui-body-a' style='margin-top: 20px;'>
    	<table style='width: 100%;'>
    		<tr><td>Pris för bokningen <?= $leastStatus < FFBoka::STATUS_CONFIRMED ? "(preliminärt)" : "" ?></td><td style='text-align: right;'><?= $price ?>&nbsp;kr</td></tr>
    		<tr><td>Betalt</td><td style='text-align: right; white-space: nowrap;'>
    		<?= $section->showFor($currentUser, FFBoka::ACCESS_CONFIRM) ? "<a href='#' onClick='setPaid($paid);' class='ui-btn ui-btn-a ui-btn-inline ui-icon-edit ui-btn-icon-notext'>Ändra</a>" : "" ?>
    		<?= $paid ?>&nbsp;kr</td></tr>
    		<tr><td>Kvar att betala</td><td style='font-weight:bold; text-align: right; border-top:1px solid var(--FF-blue); border-bottom:double var(--FF-blue);'><?= $price - $paid ?>&nbsp;kr</td></tr>
    	</table>
    	</div><?php
	}
	?>
	
	<form id='form-booking' action="book-sum.php" method='post' style='margin-top: 20px;'>
		<input type="hidden" name="action" value="confirmBooking">
		
		<?php
		$prevAnswers = $booking->answers();
		$requiredCheckboxradios = array();
		if (count($questions)) echo "<div class='ui-body ui-body-a'>";
		foreach ($questions as $id=>$required) {
		    $question = new Question($id);
		    $prevAns = "";
		    foreach ($prevAnswers as $prev) {
		        if ($prev->question == $question->caption) {
		            $prevAns = $prev->answer;
		            break;
		        }
		    }
		    echo "<input type='hidden' name='questionId[]' value='$id'>\n";
		    echo "<fieldset data-role='controlgroup' data-mini='true'>\n";
		    // For checkbox questions with only one empty choice, move the caption to the checkbox instead
		    if ( !( ($question->type=="checkbox" || $question->type=="radio") && $question->options->choices[0]=="") ) echo "\t<legend" . ($required ? " class='required'" : "") . ">" . htmlspecialchars($question->caption) . "</legend>\n";
		    switch ($question->type) {
		        case "radio":
		        case "checkbox":
		            if ($required) $requiredCheckboxradios[$id] = $question->caption;
		            foreach ($question->options->choices as $choice) {
		                echo "\t<label><input type='{$question->type}' name='answer-{$id}[]' value='" . htmlspecialchars($choice ? $choice : "Ja") . "'" . ($prevAns==($choice ? $choice : "Ja") ? " checked" : "") . "> " . htmlspecialchars($choice ? $choice : $question->caption) . ($choice ? "" : " <span class='required'></span>") . "</label>\n";
		            }
		            break;
		        case "text":
		            echo "<input" . ($question->options->length ? " maxlength='{$question->options->length}'" : "") . " name='answer-{$id}[]' value='$prevAns'" . ($required ? " required='true'" : "") . ">\n";
		            break;
		        case "number":
		            echo "<input type='number'" . (strlen($question->options->min) ? " min='{$question->options->min}'" : "") . ($question->options->max ? " max='{$question->options->max}'" : "") . " name='answer-{$id}[]' value='$prevAns'" . ($required ? " required='true'" : "") . ">\n";
		            break;
		    }
		    echo "</fieldset>";
		}
		// We need to inject verification JS code for checking required questions here - can't be done in central js file. 
		echo "<script>reqCheckRadios = " . json_encode($requiredCheckboxradios) . ";</script>";

		if (count($questions)) echo "</div>\n\n";
		?>
		
		
		<?php if ($booking->userId) { 
            $bookUser = new User($booking->userId); ?>
			<p class='ui-body ui-body-a'>
				Bokningen görs för <?= $bookUser->contactData() ?> 
				Medlemsnummer: <?= $bookUser->id ?><br>
			</p>
		<?php } else { ?>
    	    <div class='ui-body ui-body-a'>Ange dina kontaktuppgifter så vi kan nå dig vid frågor:<br>
    			<div class="ui-field-contain">
    				<label for="booker-name" class="required">Namn:</label>
    				<input type="text" name="extName" id="booker-name" required placeholder="Namn" value="<?= $booking->extName ?>">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-mail" class="required">Epost:</label>
    				<input type="email" name="extMail" id="booker-mail" required placeholder="Epost" value="<?= $booking->extMail ?>">
    			</div>
    			<div class="ui-field-contain">
    				<label for="booker-phone" class="required">Telefon:</label>
    				<input type="tel" name="extPhone" id="booker-phone" required placeholder="Mobilnummer" value="<?= $booking->extPhone ?>">
    			</div>
    		</div>
		<?php } ?>
		
		Här kan du lämna valfritt meddelande:<textarea name="commentCust" placeholder="Plats för meddelande"><?= $booking->commentCust ?></textarea>
		
		<?php
		if ($section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) echo "Intern anteckning:<br><textarea name='commentIntern' placeholder='Intern anteckning'>{$booking->commentIntern}</textarea>";
		?>

    	<input type="submit" data-icon="carat-r" data-iconpos="right" data-theme="b" data-corners="false" value="Slutför bokningen">
    	<a href="#" onClick="deleteBooking(<?= $_SESSION['authenticatedUser'] ?>, '<?= $cfg['url'] ?>');" class='ui-btn ui-btn-c ui-icon-delete ui-btn-icon-right'>Ta bort bokningen</a>
    </form>
    
    </div><!--/main-->

	<div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b">
		<h2 id='item-caption'></h2>
		<div class='ui-body ui-body-a' id='book-item-booking-details'>
			<p>Bokad från <span id='book-item-booked-start'></span> till <span id='book-item-booked-end'></span>.</p>
			<h3>Ändra bokningen</h3>
    		<div id='book-item-select-dates'>
                <div class='freebusy-bar' style='height:50px;'>
    	            <div id='book-freebusy-bar-item'></div>
    	            <div id='book-chosen-timeframe'></div>
    	            <?= Item::freebusyScale(true) ?>
    	        </div>
    
                <div>
                    <a href='#' onclick='scrollItemDate(-7);' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-left'>bakåt</a>
                    <span id='book-current-range-readable'>1/1 - 7/1 2020</span>
                    <a href='#' onclick='scrollItemDate(7);' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-right'>framåt</a>
                </div>
    
        		<div id='book-warning-conflict'>Den valda tiden krockar med befintliga bokningar.</div>
            	<div id='book-date-chooser-next-click'>Klicka på önskat startdatum.</div>
    
                <div class='ui-field-contain'>
                    <label for='book-time-start'>Vald bokningstid från:</label>
                    <div data-role='controlgroup' data-type='horizontal'>
                    	<input type='date' id='book-date-start' data-wrapper-class='controlgroup-textinput ui-btn'>
                    	<select name='book-time-start' id='book-time-start'><?php
                        for ($h=0;$h<24;$h++) echo "\n<option value='$h'>$h:00</option>"; ?>
                    	</select>
                	</div>
            	</div>
                <div class='ui-field-contain'>
                    <label for='book-time-end'>Till:</label>
                    <div data-role='controlgroup' data-type='horizontal'>
        				<input type='date' id='book-date-end' data-wrapper-class='controlgroup-textinput ui-btn'>
        	        	<select name='book-time-end' id='book-time-end'><?php
                        for ($h=0;$h<24;$h++) echo "\n<option value='$h'>$h:00</option>"; ?>
                    	</select>
                	</div>
            	</div>
        	</div>
	        <button id="book-btn-save-part" disabled="disabled" onClick="checkTimes(true);">Spara ändringarna</button>
		</div><!-- /ui-body change booking -->
    	<div id="item-details"></div>
	</div>

</div><!-- /page -->

</body>
</html>
