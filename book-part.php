<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Booking;
session_start();
require("inc/common.php");

/**
 * Displays a nested view of categories with their items
 * @param Category $cat Starting category
 * @param User $user User whose access rights shall be used
 * @param int $fbStart Timestamp for date from which to start showing freebusy information
 * @return string Header with cat caption, and list of items in category. Steps down into child categories.
 */
function displayCat(Category $cat, $user, $fbStart) {
	if ($cat->showFor($user)) {
		$access = $cat->getAccess($user);
		echo "<div data-role='collapsible' data-inset='false'>";
		echo "<h3><div class='cat-list-img'>" . embedImage($cat->thumb) . "</div>" . htmlspecialchars($cat->caption) . "</h3>";
		echo $cat->prebookMsg ? "<p>" . str_replace("\n", "<br>", htmlspecialchars($cat->prebookMsg)) . "</p>" : "";
		if ($access) {
			echo "<ul data-role='listview' data-split-icon='info' data-split-theme='a'>";
			foreach ($cat->items() as $item) {
				if ($item->active) {
					echo "<li class='book-item' id='book-item-{$item->id}'><a href=\"javascript:toggleItem({$item->id});\">";
					echo embedImage($item->getFeaturedImage()->thumb);
					echo "<h4>" . htmlspecialchars($item->caption) . "</h4>";
					echo "<div class='freebusy-bar'><div id='freebusy-item-{$item->id}'></div>" . Item::freebusyScale() . "</div>";
					echo "</a><a href='javascript:popupItemDetails({$item->id})'></a>";
					echo "</li>";
				}
			}
			echo "<br></ul>";
		}
		foreach ($cat->children() as $child) {
			displayCat($child, $user, $fbStart);
		}
		echo "</div>";
	}
}

/**
 * Get freebusy information for all items in (and below) a category
 * @param string[] $fbList Array of HTML strings representing an item's freebusy information for 1 week. Found busy times will be appended to this array.
 * @param Category $cat Category in which to start searching for items
 * @param User $user User to which the items shall be visible.
 * @param int $start Unix timestamp of start of the week
 */
function getFreebusy(&$fbList, Category $cat, $user, $start) {
    $acc = $cat->getAccess($user);
    foreach ($cat->items() as $item) {
        if ($item->active) {
            if ($acc >= FFBoka::ACCESS_PREBOOK) {
				$fbList["item-".$item->id] = $item->freebusyBar(['start'=>$start]);
            } else {
                $fbList["item-".$item->id] = Item::freebusyUnknown();
            }
		}
	}
	foreach ($cat->children() as $child) {
		getFreebusy($fbList, $child, $user, $start);
	}
}

/**
 * Get a combined freebusy bar for all passed items 
 * @param int[] $ids
 * @param User $user
 * @param int $start Unix timestamp
 * @return string HTML code
 */
function getFreebusyCombined($ids, $user, $start) {
    $freebusyCombined = "";
    foreach ($ids as $id) {
        $item = new Item($id);
        if ($item->category()->getAccess($user) >= FFBoka::ACCESS_PREBOOK) {
            $freebusyCombined .= $item->freebusyBar(['start'=>$start]);
        } else {
            $freebusyCombined .= Item::freebusyUnknown();
        }
    }
    return $freebusyCombined;
}
    

if (isset($_REQUEST['sectionId'])) $_SESSION['sectionId'] = $_REQUEST['sectionId'];
if (!$_SESSION['sectionId']) {
    header("Location: index.php?action=sessionExpired");
    die();
}

$section = new Section($_SESSION['sectionId']);
if ($_SESSION['authenticatedUser']) $currentUser = new User($_SESSION['authenticatedUser']);
else $currentUser = new User(0);


switch ($_REQUEST['action']) {
    case "ajaxItemDetails":
        header("Content-Type: application/json");
        $item = new Item($_REQUEST['id']);
        $ret = "<h3>" . htmlspecialchars($item->caption) . "</h3>";
        $ret .= str_replace("\n", "<br>", htmlspecialchars($item->description));
        $cat = $item->category();
        foreach ($item->images() as $img) {
            $ret .= "<div class='item-image'><img src='image.php?type=itemImage&id={$img->id}'><label>" . htmlspecialchars($img->caption) . "</label></div>";
        }
        $ret .= "<a href='#' data-rel='back' class='ui-btn ui-icon-delete ui-btn-icon-left'>Stäng inforutan</a>";
        die(json_encode($ret));

	case "ajaxFreebusy":
        // Get freebusy bars for all items in section
	    // Also include freebusy bar for current selection.
	    $freebusyBars = array();
		foreach ($section->getMainCategories() as $cat) {
			getFreebusy($freebusyBars, $cat, $currentUser, $_REQUEST['start']);
		}
		$ids = isset($_REQUEST['ids']) ? array_keys($_REQUEST['ids']) : array();
		header("Content-Type: application/json");
		die(json_encode([
		    "freebusyBars"=>$freebusyBars,
		    "freebusyCombined"=>getFreebusyCombined($ids, $currentUser, $_REQUEST['start']),
		]));

	case "ajaxCombinedAccess":
	    // Return least common access rights for item selection.
	    // Also include freebusy bar for same selection.
	    $access = FFBoka::ACCESS_SECTIONADMIN-1; // bit field of ones
	    foreach (array_keys($_REQUEST['ids']) as $id) {
	        $item = new Item($id);
			$access = ($access & $item->category()->getAccess($currentUser));
	    }
	    header("Content-Type: application/json");
	    die(json_encode([
	        "access"=>$access,
	        "freebusyCombined"=>getFreebusyCombined(array_keys($_REQUEST['ids']), $currentUser, $_REQUEST['start']),
	    ]));

	case "ajaxCheckTimes":
	    // Check that chosen start and end time are OK
	    // If everything is OK, create a subbooking.
	    header("Content-Type: application/json");
	    $unavail = array();
	    $minAccess = FFBoka::ACCESS_CATADMIN;
	    foreach (array_keys($_REQUEST['ids']) as $id) {
	        // For every item with visible freebusy information, check availability
	        $item = new Item($id);
	        $acc = $item->category()->getAccess($currentUser);
	        $minAccess = ($minAccess & $acc);
	        if ($acc >= FFBoka::ACCESS_PREBOOK) {
	            if (!$item->isAvailable($_REQUEST['start'], $_REQUEST['end'])) $unavail[] = htmlspecialchars($item->caption);
	        }
	    }
	    if (count($unavail)===0) {
	        // Times are OK. Create (sub)booking
	        if (isset($_SESSION['bookingId'])) {
	            $booking = new Booking($_SESSION['bookingId']);
	        } else {
	            $booking = $currentUser->addBooking();
	            $_SESSION['bookingId'] = $booking->id;
	        }
	        $subbooking = $booking->addSubbooking();
	        $subbooking->start = $_REQUEST['start'];
	        $subbooking->end = $_REQUEST['end'];
			// Add items to subbooking
	        foreach (array_keys($_REQUEST['ids']) as $id) {
	            $subbooking->addItem($id);
	        }
	    }
	    die(json_encode([
	        "timesOK" => (count($unavail) === 0),
            "unavail" => $unavail,
	    ]));
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning") ?>
</head>


<body>
<div data-role="page" id="page-book-part">
    <?= head("Lägg till resurser", $currentUser) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-book-part" class="ui-content">
        <p id="msg-page-book-part"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars($section->name) ?>
        <a href="#popup-help-book1" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Tipps</a>
    </h4>
    <div data-role="popup" id="popup-help-book1" class="ui-content" data-overlay-theme="b">
        <a href="#" data-rel="back" class="ui-btn ui-corner-all ui-btn-a ui-icon-delete ui-btn-icon-notext ui-btn-right">Close</a>
        <h4>Hur bokar jag?</h4>
        <p>Här visas alla resurser i lokalavdelningen som du har tillgång till. Klicka på de resurser du vill boka. När du har valt resurserna går du vidare till nästa steg där du väljer start- och sluttid.</p>
		<p>För varje resurs visas tillgängligheten under en vecka i taget.<br>
            <span class='freebusy-free' style='display:inline-block; width:2em;'>&nbsp;</span> tillgänglig tid<br>
            <span class='freebusy-busy' style='display:inline-block; width:2em;'>&nbsp;</span> upptagen tid<br>
            <span class='freebusy-blocked' style='display:inline-block; width:2em;'>&nbsp;</span> ej bokbar tid<br>
            <span class='freebusy-unknown' style='display:inline-block; width:2em;'>&nbsp;</span> ingen information tillgänglig<br>
            Med knapparna längst ned kan du bläddra bak och fram i tiden.
            Administratören kan för vissa poster ha valt att inte visa upptaget-information.
            I dessa fall blir din bokning bara en förfrågan där du anger önskad start- och sluttid i nästa steg.</p>
		<p>Du kan se mer information om varje resurs genom att klicka på info-knappen till höger.</p>
		<p>Om du vill göra en bokning där olika resurser behövs olika länge delar du upp bokningen. Börja med att boka alla resurser som ska ha samma tid. Sedan får du möjlighet att lägga till fler delbokningar med andra tider och/eller resurser.</p>
    </div>

	<?php
	if (isset($_SESSION['bookingId'])) echo "<p class='ui-body ui-body-a'>Du har en påbörjad bokning. Resurserna du väljer nedan kommer att läggas till bokningen.<a data-transition='slide' class='ui-btn' href='book-sum.php'>Visa bokningen</a></p>";
	?>

    <h3 class="ui-bar ui-bar-a">Steg 1. Välj resurser</h3>
    <?php
    foreach ($section->getMainCategories() as $cat) {
		displayCat($cat, $currentUser, strtotime("last sunday +1 day"));
    } ?>


    <div id="book-step2" style="display:none">
        <h3 class="ui-bar ui-bar-a">Steg 2. Välj tid</h3>
        <div id="book-combined-freebusy-container" class="ui-body ui-body-a" style="margin:1em 0;">
            <h3>Tillgängliga tider</h3>
            <p>Nedan visas en sammanfattning av tiderna då dina valda poster är tillgängliga / bokade.
		        <a href="#popup-help-book2" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Tipps</a>
            </p>
		    <div data-role="popup" id="popup-help-book2" class="ui-content" data-overlay-theme="b">
                <span class='freebusy-free' style='display:inline-block; width:2em;'>&nbsp;</span> tillgänglig tid<br>
                <span class='freebusy-busy' style='display:inline-block; width:2em;'>&nbsp;</span> upptagen tid<br>
                <span class='freebusy-blocked' style='display:inline-block; width:2em;'>&nbsp;</span> ej bokbar tid<br>
                <span class='freebusy-unknown' style='display:inline-block; width:2em;'>&nbsp;</span> ingen information tillgänglig<br>
            </div>
            <div class='freebusy-bar' style='height:50px;'><div id='book-combined-freebusy-bar'></div><?= Item::freebusyScale(true) ?></div>
	        <div id='book-access-msg'></div>
        </div>
        
        <div class="ui-body ui-body-a">
            <h3>Önskad tid</h3>
            <div class="ui-field-contain">
                <label class="required">Från:</label>
                <fieldset data-role="controlgroup" data-type="horizontal">
                    <input type="date" id="book-date-start" data-wrapper-class="ui-btn controlgroup-textinput">
                    <input type="time" id="book-time-start" data-wrapper-class="ui-btn controlgroup-textinput">
                </fieldset>
            </div>
            <div class="ui-field-contain">
                <label class="required">Till:</label>
                <fieldset data-role="controlgroup" data-type="horizontal">
                    <input type="date" id="book-date-end" data-wrapper-class="ui-btn controlgroup-textinput">
                    <input type="time" id="book-time-end" data-wrapper-class="ui-btn controlgroup-textinput">
                </fieldset>
            </div>
	        <button onClick="checkTimes()">Gå vidare</button>
        </div>
    </div><!-- /step 2 -->
    
    </div><!--/main-->
    

    <div data-role="footer" data-position="fixed" data-theme="a">
        <div class="footer-button-left">
            <a href="javascript:scrollDate(-28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-ll ui-nodisc-icon">-4 veckor</a>
            <a href="javascript:scrollDate(-7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-l ui-nodisc-icon ui-alt-icon">-1 vecka</a>
        </div>
        <h2 id="book-current-range-readable"></h2>
        <div class="footer-button-right">
            <a href="javascript:scrollDate(7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-r ui-nodisc-icon ui-alt-icon">+1 vecka</a>
            <a href="javascript:scrollDate(28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-rr ui-nodisc-icon ui-alt-icon">+10 veckor</a>
        </div>
    </div><!--/footer-->
	
	<div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b"></div>
	
	<div data-role="popup" id="popup-items-unavail" class="ui-content" data-overlay-theme="b">
		<h3>Kan inte boka</h3>
		<p>Tiden du har valt är inte tillgänglig. Följande resurser har redan bokningar som krockar med dina valda tider:</p>
		<ul id='ul-items-unavail'></ul>
		<a href="#" data-rel="back" class="ui-btn">OK</a>
	</div>

</div><!-- /page -->

</body>
</html>
