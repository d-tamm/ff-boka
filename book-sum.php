<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\FFBoka;
use FFBoka\Booking;
use FFBoka\Question;
use FFBoka\Item;
global $cfg, $message;

session_start();
require(__DIR__."/inc/common.php");

/**
 * Show a list of bookings belonging to this series. If this is not (yet) a series, show form elements to create a series
 * @param \FFBoka\Booking $booking
 * @return HTML formatted list
 */
function showBookingSeries(\FFBoka\Booking $booking) {
    if(is_null($booking->repeatId)) {
        $ret = "<p style='margin:0;'>Skapa serie med <span style='display:inline-block; width:4em;'><input type='number' name='repeat-count' id='repeat-count' value='2' min='2' max='40'></span> tillfällen</p>
    	    <fieldset data-role='controlgroup' data-type='horizontal' data-mini='true'>
    	    	<legend>Upprepning varje</legend>
    	    	<label><input type='radio' name='repeat-type' value='day' onClick=\"repeatType=this.value;
repeatPreview($('#repeat-count').val(), repeatType);\">dag</label>
    	    	<label><input type='radio' name='repeat-type' value='week' onClick=\"repeatType=this.value;
repeatPreview($('#repeat-count').val(), repeatType);\">vecka</label>
    	    	<label><input type='radio' name='repeat-type' onClick=\"repeatType=this.value;
repeatPreview($('#repeat-count').val(), repeatType);\" value='month'>månad</label>
    	    </fieldset>
            <div id='repeat-preview'><i>När du har valt antal och typ så kommer du här se ifall resurserna är tillgängliga vid respektive tillfälle.</i></div>
            <p><i>Icke-tillgängliga resurser kommer inte att bokas.</i></p>
            <a href='#' disabled='disabled' class='ui-btn' id='repeat-create' onClick='repeatCreate()'>Skapa serien</a>";
	} else {
	    $ret = "<p>Den här bokningen är del av en bokningsserie.</p>";
        $series = $booking->getBookingSeries();
        if (count($series)==0) {
            $ret .= "<p>Det finns inga fler tillfällen än det här.</p>";
        } else {
            $ret .= "<ul data-role='listview' data-inset='true'>";
            foreach ($series as $b) {
                $ret .= "<li><a href='book-sum.php?bookingId={$b->id}'>" . (is_null($start = $b->start()) ? "(bokning utan resurser)" : strftime("%F", $start)) . "</a></li>";
            }
            $ret .= "</ul>";
        }
        $ret .= "<div data-role='controlgroup'>
            <a href='#' onClick='unlinkBooking()' class='ui-btn ui-mini ui-icon-action ui-btn-icon-left'>Lyft ut det här tillfället</a>
            <a href='#' onClick='unlinkSeries()' class='ui-btn ui-mini ui-icon-bars ui-btn-icon-left'>Lös upp serien</a>
            <a href='#' onClick='deleteSeries()' class='ui-btn ui-mini ui-btn-c ui-icon-delete ui-btn-icon-left'>Radera serien</a>
            </div>";
	}
    return $ret;
}

/**
 * Save information related to the whole booking (user contact information, reference etc) from $_REQUEST array.
 * Also, set the status of pending items.
 * @param Booking $booking The booking where to save the information
 * @param int[] $conflicts Array with conflicting item ids (unavailable, but hidden to user)
 * @param int[] $unavail Array with unavailable item ids (unavailable, seen by user)
 * @param User $user The user whose permissions shall be used for setting the status
 */
function saveBookingFields(Booking $booking, $conflicts, $unavail, User $user) {
    $booking->ref = $_REQUEST['ref'];
    $booking->commentCust = $_REQUEST['commentCust'];
    if (isset($_REQUEST['commentIntern'])) $booking->commentIntern = $_REQUEST['commentIntern'];
    $booking->extName = $_REQUEST['extName'];
    $booking->extPhone = $_REQUEST['extPhone'];
    $booking->extMail = $_REQUEST['extMail'];
    $booking->okShowContactData = $_REQUEST['okShowContactData'];
    // remove old answers previously saved with booking and save new answers to questions
    $booking->clearAnswers();
    if (isset($_REQUEST['questionId'])) {
        foreach ($_REQUEST["questionId"] as $id) {
            $question = new Question($id);
            $booking->addAnswer($question->caption, implode(", ", isset($_REQUEST["answer-$id"]) ? $_REQUEST["answer-$id"] : array()));
        }
    }
    // Set status of pending items
    foreach ($booking->items() as $item) {
        if ($item->status == FFBoka::STATUS_PENDING && !in_array($item->bookedItemId, $unavail)) {
            if ($item->category()->getAccess($user) >= FFBoka::ACCESS_BOOK) {
                $item->status = FFBoka::STATUS_CONFIRMED;
            } else {
                if (in_array($item->bookedItemId, $conflicts)) $item->status = FFBoka::STATUS_CONFLICT;
                else $item->status = FFBoka::STATUS_PREBOOKED;
            }
        }
    }
}

$currentUser = new User(isset($_SESSION['authenticatedUser']) ? $_SESSION['authenticatedUser'] : 0);

if (isset($_REQUEST['bookingId'])) {
    $_SESSION['bookingId'] = $_REQUEST['bookingId'];
}

if (isset($_SESSION['bookingId'])) {
    // Open existing booking
    try {
        $booking = new Booking($_SESSION['bookingId']);
    } catch (Exception $e) {
        logger(__METHOD__." User {$currentUser->id} tried to access invalid booking {$_SESSION['bookingId']}", E_WARNING);
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
        logger(__METHOD__." Non-admin user {$currentUser->id} tried to access other user's booking {$_SESSION['bookingId']}.", E_WARNING);
        unset($_SESSION['bookingId']);
        header("Location: index.php?action=accessDenied&to=" . urlencode("bokningen.") . "&redirect=" . urlencode("book-sum.php?bookingId={$_REQUEST['bookingId']}"));
        die();
    }
}

$_SESSION['sectionId'] = $booking->sectionId;
$section = new Section($_SESSION['sectionId']);

// Check if booking collides with existing ones ...
$unavail = array();
// ... or with itself
$overlap = $booking->getOverlappingItems();
$conflicts = array();
$items = $booking->items();
foreach ($items as $item) {
    if ($item->status != FFBoka::STATUS_REJECTED && !$item->isAvailable($item->start, $item->end)) {
        if ($item->category()->getAccess($currentUser) >= FFBoka::ACCESS_PREBOOK) {
            // User can see freebusy information. Let him change booking.
            $unavail[] = $item->bookedItemId;
        } else {
            // User can't see freebusy information. Flag as conflict upon confirmation.
            $conflicts[] = $item->bookedItemId;
        }
    }
}

// Get start and end time for first item in booking, as default for other items
if (count($items)) {
    $startTime = $items[0]->start;
    $endTime = $items[0]->end;
}

if (isset($_REQUEST['action'])) {
switch ($_REQUEST['action']) {
    case "help":
        echo <<<EOF
<h3>Bokningssammanfattning</h3>
<p>Här ser du alla resurser som ingår i din bokning. Du kan ändra tiden på valda resurser
genom att klicka på dem, och ta bort dem genom att klicka på krysset i högerkanten.
Du kan även lägga till fler resurser med knappen under resurslistan. Vid varje resurs visas
statusen såsom slutgiltigt bokad eller måste bekräftas.</p>
<p>Om du är bokningsansvarig kan du här även bekräfta eller avvisa förfrågningar på enskilda
resurser och sätta pris på dem. När du gjort det är det bra om du skickar en uppdaterad
bokningsbekräftelse genom att klickar på knappen <i>Spara ändringar</i> längst ner.</p>

<h3>Pris</h3>
<p>Under resurslistan visas en sammanfattning av kostnaderna om bokningsansvarig har satt
ett pris på någon av resurserna. Om du är bokningsansvarig kan du mata in beloppet som har
betalats.</p>

<h3>Bokningsfrågor</h3>
<p>Beroende på vad du håller på att boka kan det finnas ett avsnitt med frågor som ska
besvaras. Frågor märkta med en asterisk (<span class="required"></span>) måste du svara på
för att kunna boka, övriga frågor är frivilliga.</p>

<h3>Kontaktuppgifter</h3>
<p>Om du är inloggad så visas kontaktuppgifterna som tillhör ditt konto. Du kan när som
helst ändra dem (även efter att du avslutat bokningen) genom att gå till <a href="userdata.php">Min Sida</a>.</p>
<p>Om du bokar som gäst ska du här skriva in ditt namn och dina kontaktuppgifter så vi kan
nå dig vid frågor. I bokningsbekräftelsen kommer du att få en länk till bokningen så att du
kan komma tillbaka och uppdatera den.</p>
<p>Dina kontaktuppgifter kommer att vara synliga för andra inloggade användare, i syfte att
ni ska kunna ta kontakt med varandra för eventuell samordning mellan bokningar, hantering av
kvarglömda saker mm. Därför måste du bekräfta att du tillåter att dina kontaktuppgifter visas
för andra. Informationen visas inte för gäster.</p>

<h3>Referens</h3>
<p>Här kan du skriva in en valfri kort beskrivande text, så att du lättare kan se vad bokningen
avser. Texten kommer att visas som rubrik till bokningen på Min Sida.</p>

<h3>Meddelande</h3>
<p>Längst ner på sidan finns det en kommentarsruta som bokande och bokningsansvarig kan använda
för att lämna meddelanden till varandra.</p>

<h3>Knappen Slutföra bokningen / Spara ändringar</h3>
<p>Knappen sparar den aktuella bokningen och ändrar status på resurserna till <i>väntar på bekräftelse</i>
eller <i>bekräftat</i> beroende på din behörighetsnivå. Sedan skickar systemet ut bekräftelsemejl
till dig som bokar, samt vid behov till bokningsansvarig.</p>

<h3>Återkommande bokningar</h3>
<p>Om du har behörighet att lägga din bokning utan behov av att en administratör godkänner den, så kan
du även skapa en bokningsserie. Välj mellan daglig, veckovis eller månadsvis upprepning samt antal
tillfällen. När du skapar serien så skapas varje tillfälle som en fristående bokning, där det visas
länkar för att hoppa till de andra tillfällena i serien. Om du ändrar en bokning påverkas dock inte
de andra tillfällena.</p>
<p><b>Lyft ut det här tillfället</b> löser länken mellan den här bokningen och övriga
serien utan att ta bort själva tillfället.</p>
<p><b>Lös upp serien</b> löser upp serien men lämnar kvar alla tillfällen som olänkade bokningar.</p>
<p><b>Radera serien</b> raderar hela serien förutom det första tillfället och alla
tillfällen som redan har passerat så att historiken behålls.</p>
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
    case "repeatCreate":
        saveBookingFields($booking, $conflicts, $unavail, $currentUser);
        if (count($unavail)>0 || count($overlap)>0) {
            if ($_REQUEST['action'] == "repeatCreate") $message .= "Serien kan inte skapas eftersom inte alla resurser i originalet är tillgängliga.";
            break;
        }
        if ($booking->extMail && !filter_var($booking->extMail, FILTER_VALIDATE_EMAIL)) {
            $message = "Epostadressen har ett ogiltigt format.";
            break;
        };
        $booking->sendNotifications($cfg['url']);
        $result = $booking->sendConfirmation($cfg['url']);
        if ($result !== TRUE) {
            $message .= $result;
        } elseif ($_REQUEST['action'] == "confirmBooking") {
            unset($_SESSION['bookingId']);
            header("Location: index.php?action=bookingConfirmed&mail=" . urlencode(is_null($booking->userId) ? $_REQUEST['extMail'] : $booking->user()->mail));
            die();
        } else {
            // Create a booking series
            // Get a new series ID and mark the current booking as part of this series
            $booking->repeatId = $repeatId = $FF->getNextRepeatId();
            // Create the copies
            for ($i=1; $i<$_REQUEST['repeat-count']; $i++) {
                switch ($_REQUEST['repeat-type']) {
                    case "day": $interval = new DateInterval("P{$i}D"); break;
                    case "week": $interval = new DateInterval("P{$i}W"); break;
                    case "month": $interval = new DateInterval("P{$i}M"); break;
                }
                $booking->copy($interval);
            }
            // Send alerts
            $adminsToNotify = array();
            foreach ($booking->items() as $item) {
                $cat = $item->category();
                // Collect functional email addresses to notify:  array[userId][itemId1, itemId2...]
                $alerts = $cat->sendAlertTo;
                if ($alerts) {
                    foreach (explode(",", $alerts) as $alert) $adminsToNotify[trim($alert)][] = $item->bookedItemId;
                }
                // collect admins to notify
                foreach ($cat->admins(FFBoka::ACCESS_CONFIRM, TRUE) as $adm) {
                    $admin = new User($adm['userId']);
                    if ($admin->getNotifyAdminOnNewBooking($cat) == "yes") $adminsToNotify[$adm['userId']][] = $item->bookedItemId;
                }
            }
            foreach ($adminsToNotify as $id=>$itemIds) {
                if (is_numeric($id)) {
                    if (isset($_SESSION['authenticatedUser']) && $id == $_SESSION['authenticatedUser']) continue; // Don't send notification to current user
                    $adm = new User($id);
                    $mail = $adm->mail;
                    $name = $adm->name;
                } else {
                    $mail = $id;
                    $name = "";
                }
                if ($mail) { // can only send if admin has email address
                    $FF->queueMail(
                        $mail, // to
                        "Återkommande bokning har skapats", // subject
                        "alert_series_created", // template
                        array(
                            "{{name}}" => $name,
                            "{{count}}" => $_REQUEST['repeat-count'],
                            "{{user}}" => $booking->user()->name,
                            "{{bookingLink}}" => "{$cfg['url']}book-sum.php?bookingId={$booking->id}",
                        )
                    );
                }
            }
            $message .= "Din bokning har nu sparats och bokningsserien har skapats. En bekräftelse har skickats till epostadressen {$booking->user()->mail}.";
        }
        break;
            
    case "ajaxDeleteBooking":
        header("Content-Type: application/json");
        // Check permissions: Only the original user and section admin can delete whole bookings
        if ($section->getAccess($currentUser) < FFBoka::ACCESS_SECTIONADMIN && (!isset($_SESSION['authenticatedUser']) || $booking->userId !== $_SESSION['authenticatedUser']) && $_SESSION['token'] != $booking->token) {
            logger(__METHOD__." User {$currentUser->id} tried to delete booking {$booking->id} without appropriate permissions.", E_WARNING);
            die(json_encode([ "error"=>"Du har inte behörighet att ta bort bokningen. :-P" ]));
        }
        // Send confirmation to user
        $mailItems = "<tr><th>Resurs</th><th>Datum</th></tr>";
        $adminsToNotify = array();
        $maxStatus = FFBoka::STATUS_PENDING;
        foreach ($booking->items() as $item) {
            $maxStatus = max($maxStatus, $item->status);
            $cat = $item->category();
            // Collect functional email addresses to notify
            $alerts = $cat->sendAlertTo;
            if ($alerts) {
                foreach (explode(",", $alerts) as $alert) {
                    $alert = trim($alert);
                    $adminsToNotify[$alert][] = $item->bookedItemId;
                }
            }
            // collect admins to notify about booking: array[userId][itemId1, itemId2...]
            foreach ($cat->admins(FFBoka::ACCESS_CONFIRM, TRUE) as $adm) {
                $admin = new User($adm['userId']);
                if ($admin->getNotifyAdminOnNewBooking($cat) == "yes") $adminsToNotify[$adm['userId']][] = $item->bookedItemId;
            }
            // Table with booked items
            $mailItems .= "<tr>
                <td>" . htmlspecialchars($item->caption) . "</td>
                <td>" . strftime("%a %F kl %k:00", $item->start) . " till " . strftime("%a %F kl %k:00", $item->end) . "</td>
                </tr>";
        }
        if ($booking->userId == $currentUser->id) $statusText = "Din bokning <i>" . htmlspecialchars($booking->ref) . "</i> har nu raderats.";
        else $statusText = "Din bokning <i>" . htmlspecialchars($booking->ref) . "</i> har raderats av bokningsansvarig (" . htmlspecialchars($currentUser->name . ", " . $currentUser->mail) . "). Om detta är felaktigt, vänligen ta kontakt med " . htmlspecialchars($currentUser->name) . " omgående för att reda ut vad som har hänt.";
        if ($maxStatus > FFBoka::STATUS_PENDING) {
            try {
                $FF->queueMail(
                    is_null($booking->userId) ? $booking->extMail : $booking->user()->mail, // to
                    "Bokning #{$booking->id} har raderats", // subject
                    "booking_deleted", // template name
                    array( // replace.
                        "{{name}}"    => htmlspecialchars(is_null($booking->userId) ? $booking->extName : $booking->user()->name),
                        "{{items}}"   => $mailItems,
                        "{{status}}"  => $statusText,
                        "{{commentCust}}" => $booking->commentCust ? str_replace("\n", "<br>", htmlspecialchars($booking->commentCust)) : "(ingen kommentar har lämnats)",
                    )
                );
            } catch(Exception $e) {
                $message = "Kunde inte skicka bekräftelsen till dig:" . $e;
            }
            // Send notifications to admins
            if (is_null($booking->userId)) {
                $contactData = "Detta är en gästbokning.<br>Namn: " . htmlspecialchars($booking->extName) . "<br>Telefon: " . htmlspecialchars($booking->extPhone) . "<br>Mejl: " . htmlspecialchars($booking->extMail);
            } else {
                $contactData = "Namn: " . htmlspecialchars($booking->user()->name) . "<br>Telefon: " . htmlspecialchars($booking->user()->phone) . "<br>Mejl: " . htmlspecialchars($booking->user()->mail);
            }
            foreach ($adminsToNotify as $id=>$itemIds) {
                if (is_numeric($id)) {
                    if (isset($_SESSION['authenticatedUser']) && $id == $_SESSION['authenticatedUser']) continue; // Don't send notification to current user
                    $adm = new User($id);
                    $mail = $adm->mail;
                    $name = $adm->name;
                } else {
                    $mail = $id;
                    $name = "";
                }
                if ($mail) { // can only send if admin has email address
                    $FF->queueMail(
                        $mail, // to
                        "FF Bokning #{$booking->id} raderad", // subject
                        "booking_deleted", // template
                        array( // replace
                            "{{name}}"    => $name,
                            "{{items}}"   => $mailItems,
                            "{{status}}"  => "Bokningen nedan har raderats.",
                            "{{commentCust}}" => str_replace("\n", "<br>", htmlspecialchars($booking->commentCust)) . "<br><br>$contactData",
                        )
                    );
                }
            }
        }
        // If the booking belongs to a series and only one occurrence would be left, remove the series.
        if (!is_null($booking->repeatId)) {
            $series = $booking->getBookingSeries();
            if (count($series) == 1) $series[0]->repeatId = NULL;
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
            die(json_encode([ "error" => "Du har inte behörighet att ta bort resursen." ]));
        }
        if ($item->removeFromBooking()) die(json_encode([ "status" => "OK" ]));
        else die(json_encode([ "error" => "Oväntat fel: Kan inte ta bort resursen. Kontakta systemadministratören."]));
        
    case "ajaxRepeatPreview":
        header("Content-Type: application/json");
        // check availability for every item and each date
        $unavail = array_fill(0, $_REQUEST['count'], array());
        switch ($_REQUEST['type']) {
            case "day": $interval = new DateInterval("P1D"); break;
            case "week": $interval = new DateInterval("P1W"); break;
            case "month": $interval = new DateInterval("P1M"); break;
        }
        foreach ($booking->items() as $item) {
            // Get start and end time for the original booked item
            $start = new DateTime("@{$item->start}");
            $end = new DateTime("@{$item->end}");
            for ($i=0; $i<$_REQUEST['count']; $i++) {
                if (!$item->isAvailable($start->getTimestamp(), $end->getTimestamp())) $unavail[$i][] = htmlspecialchars($item->caption);
                // From the 1st copy an onwards, use a generic item in order to also get collisions with the original one.
                $item = new Item($item->id);
                $start->add($interval);
                $end->add($interval);
            }
        }
        $start = new DateTime("@".$booking->start());
        $html = [];
        for ($i=0; $i<$_REQUEST['count']; $i++) {
            if (count($unavail[$i])) $html[] = "<li styles='color:var(--FF-orange);' class='repeat-unavail'>" . $start->format("Y-m-d") . ": " . implode(", ", $unavail[$i]) . " ej tillgänglig</li>";
            else $html[] = "<li>" . $start->format("Y-m-d") . ": alla resurser tillgängliga</li>";
            $start->add($interval);
        }
        die(json_encode([ "html" => "<ul>" . implode("", $html) . "</ul>" ]));
        
    case "ajaxUnlinkBooking":
        header("Content-Type: application/json");
        // If there will only be one occurrence left in the series, remove even that one.
        $series = $booking->getBookingSeries();
        if (count($series)==1) $series[0]->repeatId = NULL;
        // Remove this occurrence from the series
        $booking->repeatId = NULL;
        die(json_encode([ "html" => showBookingSeries($booking) ]));
        
    case "ajaxUnlinkSeries":
        header("Content-Type: application/json");
        foreach ($booking->getBookingSeries(TRUE) as $b) $b->repeatId = NULL;
        die(json_encode([ "html" => showBookingSeries($booking) ]));
        
    case "ajaxDeleteSeries":
        header("Content-Type: application/json");
        $first = NULL;
        $gotoFirst = false;
        foreach ($booking->getBookingSeries(TRUE) as $b) {
            if (is_null($first)) { // keep the first instance and switch to it
                $first = $b;
                continue;
            }
            if ($b->start() > time()) { // Booking in the future, delete it
                if ($b->id == $booking->id) $gotoFirst = true;
                $b->delete();
            }
        }
        // If only one occurence is left, remove the series ID
        if (count($first->getBookingSeries(TRUE))==1) $first->repeatId=NULL;
        if ($gotoFirst) die(json_encode([ "gotoBookingId"=>$first->id ]));
        else die(json_encode([ "html" => showBookingSeries($booking) ]));
        
    case "ajaxConfirmBookedItem":
    case "ajaxRejectBookedItem":
        header("Content-Type: application/json");
        $item = new Item($_REQUEST['bookedItemId'], TRUE);
        if ($item->category()->getAccess($currentUser) < FFBoka::ACCESS_CONFIRM && $booking->userId !== $_SESSION['authenticatedUser'] && $booking->token != $_SESSION['token']) {
            die(json_encode([ "error" => "Du har inte behörighet att bekräfta resursen." ]));
        }
        if ($_REQUEST['action']=="ajaxConfirmBookedItem") $item->status = FFBoka::STATUS_CONFIRMED;
        else $item->status = FFBoka::STATUS_REJECTED;
        // Check if this was the last item in the booking to be managed (confirmed/rejected)
        $allManaged = TRUE;
        foreach ($item->booking()->items() as $it) {
            if ($it->status != FFBoka::STATUS_CONFIRMED && $it->status != FFBoka::STATUS_REJECTED) {
                $allManaged = FALSE;
                break;
            }
        }
        die(json_encode([
            'status'=>'OK',
            'allManaged'=>$allManaged
        ]));
}
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Bekräfta bokningen", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-book-sum">
    <?= head("Din bokning", $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-book-sum" class="ui-content">
        <p id="msg-page-book-sum"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars($section->name) ?></h4>

    <?php
    if (count($unavail)) echo "<p class='ui-body ui-body-c'>Några av de resurser du har valt är inte längre tillgängliga vid den valda tiden. De är markerade nedan. För att kunna slutföra bokningen behöver du ta bort dessa resurser eller ändra tiden till en ledig tid.</p>";
    if (count($overlap)) echo "<p class='ui-body ui-body-c'>Du har lagt in " . (count($overlap)==1 ? "en resurs" : "några resurser") . " flera gånger vid samma tid eller så att tiderna överlappar. De berörda raderna är markerade nedan. Du behöver ta bort dubletten eller justera tiden för att kunna slutföra bokningen.</p>";
    ?>
    
    <ul data-role='listview' data-inset='true' data-divider-theme='a' data-split-icon='delete'>
    <?php
    $questions = array();
    $leastStatus = FFBoka::STATUS_CONFIRMED;
    $showRepeating = isset($_SESSION['authenticatedUser']) ? true : false;
    $itemsToConfirm = array();
    $items = $booking->items();
    foreach ($items as $item) {
        $leastStatus = min($leastStatus, $item->status);
        $access = $item->category()->getAccess($currentUser);
        $showRepeating = $showRepeating && $access >= FFBoka::ACCESS_BOOK;
        $showEditButtons = ($access >= FFBoka::ACCESS_CONFIRM && $item->status != FFBoka::STATUS_REJECTED);
        foreach ($item->category()->getQuestions() as $id=>$q) {
            if (isset($questions[$id])) $questions[$id]=($questions[$id] || $q->required);
            else $questions[$id]=$q->required;
        }
        echo "<li id='item-{$item->bookedItemId}'" . ((in_array($item->bookedItemId, $unavail) || array_key_exists($item->id, $overlap)) ? " data-theme='c'" : "") . ($item->status==FFBoka::STATUS_REJECTED ? " class='rejected'" : "") . ">";
        if ($showEditButtons) {
            echo "<div class='item-edit-buttons'>";
            if ($item->status == FFBoka::STATUS_CONFLICT || $item->status == FFBoka::STATUS_PREBOOKED) {
                echo "<button id='book-item-btn-confirm-{$item->bookedItemId}' class='ui-btn ui-btn-inline ui-btn-a' onclick=\"confirmBookedItem({$item->bookedItemId});\">Bekräfta</button>";
                $itemsToConfirm[] = $item->bookedItemId;
                echo "<button id='book-item-btn-reject-{$item->bookedItemId}' class='ui-btn ui-btn-inline ui-btn-a' onclick=\"rejectBookedItem({$item->bookedItemId});\">Avböj</button>";
            }
            echo "<button class='ui-btn ui-btn-inline ui-btn-a' onclick=\"setItemPrice({$item->bookedItemId}, {$item->price});\">Sätt pris</button>";
            echo "</div>";
        }
        echo "<a href='#' onClick='popupItemDetails({$item->bookedItemId});'" . ($showEditButtons ? " class='has-edit-buttons'" : "") . ">" . embedImage($item->getFeaturedImage()->thumb) .
        "<h3 style='white-space:normal;'>" . htmlspecialchars($item->caption) . "</h3><p style='overflow:auto; white-space:normal; margin-bottom:0px;'>";
        echo strftime("%F kl %H", $item->start) . " &mdash; " . strftime("%F kl %H", $item->end) . "<br>\n";
        if (in_array($item->bookedItemId, $unavail)) echo "<span id='book-item-status-{$item->bookedItemId}'>Inte tillgänglig</span>";
        elseif (array_key_exists($item->id, $overlap)) echo "Överlappar";
        else {
            switch ($item->status) {
            case FFBoka::STATUS_PENDING: echo "Bokning ej slutförd än"; break;
            case FFBoka::STATUS_REJECTED: echo "Avböjt"; break;
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

    <?= count($items) == 0 ? "<p>Bokningen innehåller inte några resurser.</p>" : "" ?>

    <button onClick="location.href='book-part.php<?= isset($startTime) ? "?start=$startTime&end=$endTime" : "" ?>'" data-transition='slide' data-direction='reverse' class='ui-btn ui-icon-plus ui-btn-icon-right'>Lägg till fler resurser</button>
    
    <script>itemsToConfirm = <?= json_encode($itemsToConfirm) ?>;</script>
    <?php
    if (count($itemsToConfirm)) {
        echo "<button onClick='confirmAllItems();' id='btn-confirm-all-items' class='ui-btn ui-icon-check ui-btn-icon-right'>Bekräfta alla</button>";
    }
    
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
    
    <form id='form-booking' name='formBooking' action="book-sum.php" method='post' style='margin-top: 20px;'>
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
                Bokningen görs för <?= $bookUser->contactData() ?><br>
                Medlemsnummer: <?= $bookUser->id ?><br>
                Lokalavdelning: <?= $bookUser->section->name ?><br>
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

        <label>
            <input type="checkbox" data-mini="true" required name="okShowContactData" value="1" <?= $booking->okShowContactData==1 ? "checked" : "" ?>><span class="required">Jag medger att mina kontaktuppgifter visas för andra inloggade användare i samband med bokningen</span>
        </label>

        <div class="ui-field-contain">
            <label for="book-sum-ref">Referens:</label>
            <input name="ref" id="book-sum-ref" placeholder="visas i din bokningsöversikt" value="<?= $booking->ref ?>">
        </div>
        
        Här kan du lämna valfritt meddelande:
        <textarea name="commentCust" placeholder="Plats för meddelande"><?= $booking->commentCust ?></textarea>
        
        <?php
        if ($section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) echo "Intern anteckning:<br><textarea name='commentIntern' placeholder='Intern anteckning'>{$booking->commentIntern}</textarea>";
        ?>
    
        <input type="submit" data-icon="carat-r" data-iconpos="right" data-theme="b" data-corners="false" value="<?= $booking->status()==FFBoka::STATUS_PENDING ? "Slutför bokningen" : "Spara ändringar" ?>" <?= count($overlap) ? " disabled='disabled'" : "" ?>>
        <a href="#" onClick="deleteBooking(<?= $currentUser->id ? $currentUser->id : 0 ?>, '<?= $cfg['url'] ?>');" class='ui-btn ui-btn-c ui-icon-delete ui-btn-icon-right'>Ta bort bokningen</a>
            
        <?php if ($showRepeating) { ?>
            <div data-role="collapsible" data-corners="false" <?= is_null($booking->repeatId) ? "" : "data-collapsed='false'" ?>>
                <h4>Återkommande bokningar</h4>
                <div id='series-panel'><?= showBookingSeries($booking) ?></div>
            </div>
        <?php } ?>
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
        </div><!-- /book-item-booking-details -->
        <div id="item-details"></div>
    </div>

</div><!-- /page -->

</body>
</html>
