<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Booking;
session_start();
require(__DIR__."/inc/common.php");

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
		echo "<h3><div class='cat-list-img'>" . embedImage($cat->thumb) . "</div>{$cat->caption}</h3>";
		echo $cat->bookingMsg ? "<p>{$cat->bookingMsg}</p>" : "";
		if ($access) {
			echo "<ul data-role='listview' data-split-icon='info' data-split-theme='a'>";
			foreach ($cat->items() as $item) {
				if ($item->active) {
					echo "<li class='book-item' id='book-item-{$item->id}'><a href=\"javascript:toggleItem({$item->id});\">";
					echo embedImage($item->getFeaturedImage()->thumb);
					echo "<h4>{$item->caption}</h4>";
					echo "<div id='freebusy-item-{$item->id}' class='freebusy-bar'></div>";
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
 * @param [ string ] $fbList Array of HTML strings representing an item's freebusy information for 1 week. Found busy times will be appended to this array.
 * @param Category $cat Category in which to start searching for items
 * @param User $user User to which the items shall be visible.
 * @param int $start Unix timestamp of start of the week
 * @param bool $scale Whether to include the weekday scale.
 */
function getFreebusy(&$fbList, Category $cat, $user, $start) {
    $acc = $cat->getAccess($user);
    foreach ($cat->items() as $item) {
        if ($item->active) {
            if ($acc >= FFBoka::ACCESS_PREBOOK) {
				$fbList["item-".$item->id] = $item->freebusyBar($start);
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
            $freebusyCombined .= $item->freebusyBar($start);
        } else {
            $freebusyCombined .= Item::freebusyUnknown();
        }
    }
    return $freebusyCombined;
}
    

$message = "";

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
        // Reply to ajax request
        header("Content-Type: application/json");
        $item = new Item($_REQUEST['id']);
        $ret = "<h3>{$item->caption}</h3>";
        $ret .= $item->description;
        $cat = $item->category();
        foreach ($item->images() as $img) {
            $ret .= "<div class='item-image'><img src='image.php?type=itemImage&id={$img->id}'><label>{$img->caption}</label></div>";
        }
        $ret .= "<a href='#' data-rel='back' class='ui-btn ui-icon-delete ui-btn-icon-left'>Stäng inforutan</a>";
        die(json_encode($ret));

	case "ajaxFreebusy":
        // Reply to ajax request: Get freebusy bars for all items in section
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
	    // Reply to ajax request: Return least common access rights for item selection.
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
	    // Reply to ajax request: Check that chosen start and end time are OK
	    // If everything is OK, create a subbooking.
	    // TODO: add options for shortest/longest booking duration, latest/earliest booking in advance?
	    header("Content-Type: application/json");
	    $unavail = array();
	    $minAccess = FFBoka::ACCESS_CATADMIN;
	    foreach (array_keys($_REQUEST['ids']) as $id) {
	        // For every item with visible freebusy information, check availability
	        $item = new Item($id);
	        $acc = $item->category()->getAccess($currentUser);
	        $minAccess = ($minAccess & $acc);
	        if ($acc >= FFBoka::ACCESS_PREBOOK) {
	            if (!$item->isAvailable($_REQUEST['start'], $_REQUEST['end'])) $unavail[] = $item->caption;
	        }
	    }
	    if (count($unavail)===0) {
	        // Everything is OK. Create (sub)booking
	        if (isset($_SESSION['bookingId'])) {
	            $booking = new Booking($_SESSION['bookingId']);
	        } else {
	            $booking = $currentUser->addBooking();
	            $_SESSION['bookingId'] = $booking->id;
	        }
	        $subbooking = $booking->addSubbooking();
	        $subbooking->start = htmlentities($_REQUEST['start']);
	        $subbooking->end = htmlentities($_REQUEST['end']);
	        foreach (array_keys($_REQUEST['ids']) as $id) {
	            $subbooking->addItem(htmlentities($id));
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

    <script>
    $( document ).on( "mobileinit", function() {
        <?php if ($message) { ?>
        $( document ).on( "pagecontainershow", function( event, ui ) {
            setTimeout(function() {
                $("#popupMessage").popup('open');
            }, 500); // We need some delay here to make this work on Chrome.
        } );
        <?php } ?>
    });
    </script>
    <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page-book1">
    <?= head("Boka resurser", $currentUser) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
        <p><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= $section->name ?>
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

	<?php // TODO: Visa ev. oavslutad bokning ?>

    <h3 class="ui-bar ui-bar-a">Steg 1. Välj resurser</h3>
    <?php
    foreach ($section->getMainCategories() as $cat) {
		displayCat($cat, $currentUser, strtotime("last monday"));
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
            <div id='book-combined-freebusy-bar' class='freebusy-bar' style='height:50px;'></div>
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
	
	<div data-role="popup" id="popup-times-ok" class="ui-content" data-overlay-theme="b">
		<h3>Bra jobbat!</h3>
		<p>Resurserna har nu lagts till din bokning.</p>
		<p>Du kan nu fortsätta med att lägga till fler resurser, t.ex. med andra tider, eller gå vidare och slutföra bokningen.</p>
		<a href="#" class="ui-btn" data-rel="back">Lägg till fler</a>
		<a href="book2.php" class="ui-btn">Slutför bokningen</a>
	</div>

    <script>
        var checkedItems = {};
		var fbStart = new Date(<?= strtotime("last monday") ?> * 1000);
		
		scrollDate(0);
		
		function scrollDate(offset) {
            $.mobile.loading("show", {});
            // Calculate start end end of week
			fbStart.setDate(fbStart.getDate() + offset);
			var fbEnd = new Date(fbStart.valueOf());
			fbEnd.setDate(fbEnd.getDate() + 6);
			var readableRange = "må " + fbStart.getDate() + "/" + (fbStart.getMonth()+1);
			if (fbStart.getFullYear() != fbEnd.getFullYear()) readableRange += " '"+fbStart.getFullYear().toString().substr(-2);
			readableRange += " &ndash; sö " + fbEnd.getDate() + "/" + (fbEnd.getMonth()+1) + " '"+fbEnd.getFullYear().toString().substr(-2);
            // Get freebusy bars
            $.getJSON("book.php", { action: "ajaxFreebusy", start: fbStart.valueOf()/1000, ids: checkedItems }, function(data, status) {
                $("#book-current-range-readable").html( readableRange );
				$.each(data.freebusyBars, function(key, value) {
					$("#freebusy-"+key).html(value).append("<?= Item::freebusyScale() ?>");
				});
                $("#book-combined-freebusy-bar").html(data.freebusyCombined).append("<?= Item::freebusyScale(true) ?>");
                $.mobile.loading("hide", {});
            });
		}

        function toggleItem(itemId){
            if (checkedItems[itemId]) {
                delete checkedItems[itemId];
            } else {
                checkedItems[itemId] = true;
            }
            $("#book-item-"+itemId).toggleClass("item-checked");
            
            if (Object.keys(checkedItems).length>0) {
                // Get access information for all selected items
                $.getJSON("book.php", { action: "ajaxCombinedAccess", start: fbStart.valueOf()/1000, ids: checkedItems }, function(data, status) {
                    if (data.access <= <?= FFBoka::ACCESS_READASK ?>) {
                         $("#book-access-msg").html("<p>Komplett information om tillgänglighet kan inte visas för ditt urval av resurser. Ange önskad start- och sluttid nedan för att skicka en intresseförfrågan.</p><p>Ansvarig kommer att höra av sig till dig med besked om tillgänglighet och eventuell bekräftelse av din förfrågan.</p>");
                    } else {
                        $("#book-access-msg").html("");
                        if (data.access <= <?= FFBoka::ACCESS_PREBOOK ?>) {
                            $("#book-access-msg").append("<p><b>OBS: Bokninen är preliminär.</b> För ditt urval av resurser kommer bokningen behöva bekräftas av materialansvarig.</p>"); 
                        }
                    }
                    $("#book-combined-freebusy-bar").html(data.freebusyCombined).append("<?= Item::freebusyScale(true) ?>");
                    $.mobile.loading("hide", {});
                });
                $("#book-step2").show();
            } else {
                $("#book-step2").hide();
            }
        }
		
		function popupItemDetails(id) {
            $.getJSON("book.php", { action: "ajaxItemDetails", id: id }, function(data, status) {
                $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
            });
		}

		function checkTimes() {
			// User has chosen start and end time. Check that the chosen range  
			// does not collide with existing bookings visible to the user.
			// First, check that user has entered some times:
			if ($("#book-date-start").val()=="" | $("#book-time-start").val()=="" | $("#book-date-end").val()=="" | $("#book-time-end").val()=="") {
				alert("Du måste välja start- och sluttid först.");
				return false;
			}
			// Ensure that end time is later than start time:
			var startDate = new Date($("#book-date-start").val() + " " + $("#book-time-start").val());
			var endDate = new Date($("#book-date-end").val() + " " + $("#book-time-end").val());
			if (startDate.valueOf() >= endDate.valueOf()) {
				alert("Du har valt en sluttid som ligger före starttiden.");
				return false;
			}
			// Send times to server to check availability:
			$.getJSON("book.php", {
				action: "ajaxCheckTimes",
				ids: checkedItems,
				start: startDate.valueOf()/1000,
				end: endDate.valueOf()/1000
			}, function(data, status) {
				if (data.timesOK) {
					// Reset subbooking section to prepare for next subbooking
					checkedItems = {};
					$(".book-item").removeClass("item-checked");
	                $("#book-step2").hide();
					$("#book-date-start").val("");
					$("#book-time-start").val("");
					$("#book-date-end").val("");
					$("#book-time-end").val("");
					// update freebusy
					scrollDate(0);
					// Show OK dialog
					$("#popup-times-ok").popup('open', { transition: "pop" });
				} else {
					$("#ul-items-unavail").html("");
					$.each(data.unavail, function( key, item ) {
						$("#ul-items-unavail").append("<li>"+item+"</li>");
					});
					$("#popup-items-unavail").popup('open', { transition: "pop" });
				}
			});
		}

	</script>

</div><!-- /page -->

</body>
</html>
