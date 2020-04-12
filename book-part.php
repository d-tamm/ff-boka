<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Booking;
global $cfg, $message, $FF;
session_start();
require("inc/common.php");

/**
 * Displays a nested view of categories with their items. Steps down into child categories.
 * @param Category $cat Starting category
 * @param User $user User whose access rights shall be used
 * @param int $fbStart Timestamp for date from which to start showing freebusy information
 * @return int Number of items displayed, including those of child categories. 
 */
function displayCat(Category $cat, $user, $fbStart) {
    $numItems = 0;
    if ($cat->showFor($user)) {
        $access = $cat->getAccess($user);
        echo "<div data-role='collapsible' data-inset='false'>";
        echo "<h3><div class='cat-list-img'>" . embedImage($cat->thumb) . "</div>" . htmlspecialchars($cat->caption) . "</h3>";
        echo $cat->prebookMsg ? "<p>" . str_replace("\n", "<br>", htmlspecialchars($cat->prebookMsg)) . "</p>" : "";
        foreach ($cat->files() as $file) {
            if ($file->displayLink) echo "<p><span style='font-size:200%; vertical-align:middle;'>🗎 </span> <a href='attment.php?fileId={$file->fileId}' data-ajax='false' title='Ladda ner " . htmlspecialchars($file->filename) . "'>" . htmlspecialchars($file->caption) . "</a></p>";
        }
        if ($access) {
            echo "<ul data-role='listview' data-split-icon='info' data-split-theme='a'>";
            foreach ($cat->items() as $item) {
                if ($item->active) {
                    $numItems++;
                    echo "<li class='book-item' id='book-item-{$item->id}'><a href=\"javascript:toggleItem({$item->id});\">";
                    echo embedImage($item->getFeaturedImage()->thumb);
                    echo "<h4>" . htmlspecialchars($item->caption) . "</h4>";
                    if ($cat->getAccess($user)>=FFBoka::ACCESS_PREBOOK) echo "<div class='freebusy-bar'><div id='freebusy-item-{$item->id}'></div>" . Item::freebusyScale() . "</div>";
                    echo "</a><a href='javascript:popupItemDetails({$item->id})'></a>";
                    echo "</li>";
                }
            }
            echo "<br></ul>";
        }
        foreach ($cat->children() as $child) {
            $numItems += displayCat($child, $user, $fbStart);
        }
        echo "</div>";
    }
    return $numItems;
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

if (isset($_GET['sectionName'])) {
    // Page was called via direct link with clear text section name and a rewrite rule.
    // Find the corresponding section
    $_REQUEST['sectionId'] = $FF->getSectionIdByName($_GET['sectionName']);
    if ($_REQUEST['sectionId'] === FALSE) {
        header("Location: {$cfg['url']}index.php?message=" . urlencode("Adressen du änvände är ogiltig."));
        die();
    }
}

if (isset($_REQUEST['sectionId'])) {
    $_SESSION['sectionId'] = $_REQUEST['sectionId'];
    unset($_SESSION['bookingId']);
}
if (!$_SESSION['sectionId']) {
    header("Location: index.php?action=sessionExpired");
    die();
}

$section = new Section($_SESSION['sectionId']);
if ($_SESSION['authenticatedUser']) $currentUser = new User($_SESSION['authenticatedUser']);
else $currentUser = new User(0);

switch ($_REQUEST['action']) {
    case "help":
        echo <<<END
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
END;
        die();
        
    case "ajaxItemDetails":
        header("Content-Type: application/json");
        $item = new Item($_REQUEST['id'], $_REQUEST['bookingStep']==2);
        if ($_REQUEST['bookingStep']==2) {
            // Remember for ajax requests for changing booking properties from popup
            $_SESSION['bookedItemId'] = $_REQUEST['id']; 
        }
        $html = "";
        if ($_REQUEST['bookingStep']==2 && $item->category()->getAccess($currentUser) >= FFBoka::ACCESS_CONFIRM && $item->status > FFBoka::STATUS_PENDING) {
            $start = $item->start;
            $end = $item->end;
            $price = $item->price;
        }
        $cat = $item->category();
        $html .= str_replace("\n", "<br>", htmlspecialchars($item->description));
        foreach ($item->images() as $img) {
            $html .= "<div class='item-image'><img src='image.php?type=itemImage&id={$img->id}'><label>" . htmlspecialchars($img->caption) . "</label></div>";
        }
        if ($cat->getAccess($currentUser)>=FFBoka::ACCESS_PREBOOK) { // show coming bookings
            $bookings = $item->upcomingBookings();
            if (count($bookings)) $html .= "<div class='ui-body ui-body-a'><h3>Kommande bokningar</h3>\n<ul>\n";
            foreach ($bookings as $b) {
                $html .= "<li>" . strftime("%a %e/%-m %R", $b->start) . " till " . strftime("%a %e/%-m %R", $b->end) . "</li>\n";
            }
            if (count($bookings)) $html .= "</ul></div>\n";
        }
        $html .= "<a href='#' data-rel='back' class='ui-btn ui-icon-delete ui-btn-icon-left'>Stäng inforutan</a>";
        die(json_encode([ "caption"=>htmlspecialchars($item->caption), "html"=>$html, "start"=>$start, "end"=>$end, "price"=>$price ]));

    case "ajaxFreebusy":
        // Get freebusy bars for all items in section
        // Also include combined freebusy bar for current selection.
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
    case "ajaxSave":
        // Check that chosen start and end time are OK
        // If everything is OK and "save", create a booking if necessary and save item.
        header("Content-Type: application/json");
        $unavail = array();
        $minAccess = FFBoka::ACCESS_CATADMIN;
        if ($_REQUEST['ids']) {
            foreach (array_keys($_REQUEST['ids']) as $id) {
                // For every item with visible freebusy information, check availability
                $item = new Item($id, $_REQUEST['bookingStep']==2);
                $acc = $item->category()->getAccess($currentUser);
                $minAccess = ($minAccess & $acc);
                if ($acc >= FFBoka::ACCESS_PREBOOK) {
                    if (!$item->isAvailable($_REQUEST['start'], $_REQUEST['end'])) $unavail[] = htmlspecialchars($item->caption);
                }
            }
        }
        if (count($unavail)===0 && $_REQUEST['action']==="ajaxSave") {
            // Times are OK. Create or change booking
            if ($_REQUEST['bookingStep']==2) {
                // In step 2, only single items are modified
                $item = new Item(array_keys($_REQUEST['ids'])[0], TRUE);
                $item->start = $_REQUEST['start'];
                $item->end = $_REQUEST['end'];
                if ($acc < FFBoka::ACCESS_CONFIRM) $item->status = FFBoka::STATUS_PENDING;
                else $item->status = FFBoka::STATUS_CONFIRMED;
            } else {
                // Step 1: Several items to save
                if (isset($_SESSION['bookingId'])) {
                    $booking = new Booking($_SESSION['bookingId']);
                } else {
                    $booking = $currentUser->addBooking($section->id);
                    $_SESSION['bookingId'] = $booking->id;
                    $_SESSION['token'] = $booking->token;
                }
                // Add items to booking
                foreach (array_keys($_REQUEST['ids']) as $id) {
                    $item = $booking->addItem($id);
                    $item->start = $_REQUEST['start'];
                    $item->end = $_REQUEST['end'];
                }
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
    <?php htmlHeaders("Friluftsfrämjandets resursbokning", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-book-part">
    <?= head("Lägg till resurser", $cfg['url'], $currentUser, $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-book-part" class="ui-content">
        <p id="msg-page-book-part"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars($section->name) ?></h4>
    <p><i>Välj de resurser du vill boka genom att klicka på dem. Längst ner på skärmen finns det kontroller där du kan bläddra bak och fram i tiden för att se tillgängligheten. Start- och sluttid på din bokning väljer du i steg 2.</i></p>

    <?php
    if (!$_SESSION['authenticatedUser']) echo "<p class='ui-body ui-body-a'>Du bokar som gäst. Om du är medlem i Friluftsfrämjandet och vill boka som medlem så behöver du <a href='index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']) . "'>logga in först</a>.</p>";
    
    if (isset($_SESSION['bookingId'])) echo "<p class='ui-body ui-body-a'>Du har en påbörjad bokning. Resurserna du väljer nedan kommer att läggas till bokningen.<a data-transition='slide' class='ui-btn' href='book-sum.php'>Visa bokningen</a></p>";
    ?>

    <h3 class="ui-bar ui-bar-a">Steg 1. Välj resurser</h3>
    <?php
    $numItems = 0;
    foreach ($section->getMainCategories() as $cat) {
        $numItems += displayCat($cat, $currentUser, strtotime("last sunday +1 day"));
    }
    if ($numItems==0) {
        echo "<p><i>I den här lokalavdelningen finns det inte några resurser som du kan boka. Det kan bero på att lokalavdelningen inte (ännu) använder systemet, eller att du inte har behörighet att boka de resurser som har lagts upp.</i></p>";
    }
    ?>


    <div id="book-step2" style="display:none">
        <h3 class="ui-bar ui-bar-a">Steg 2. Välj tid</h3>
        <p>Nedan visas en sammanfattning av tiderna då dina valda poster är tillgängliga / bokade.</p>
        <div id='book-access-msg'></div>

        <div class='freebusy-bar' style='height:50px;'>
            <div id='book-combined-freebusy-bar'></div>
            <div id='book-chosen-timeframe'></div>
            <?= Item::freebusyScale(true) ?>
        </div>
        <div id='book-warning-conflict'>Den valda tiden krockar med befintliga bokningar.</div>
        <div id='book-date-chooser-next-click'>Klicka på önskat startdatum.</div>

        <div class='ui-field-contain'>
            <label for='book-time-start'>Vald bokningstid från:</label>
            <div data-role='controlgroup' data-type='horizontal'>
                <input type='date' id='book-date-start' data-wrapper-class='controlgroup-textinput ui-btn'>
                <select name='book-time-start' id='book-time-start'><?php for ($h=0;$h<24;$h++) echo "<option value='$h'>$h:00</option>"; ?></select>
            </div>
        </div>
        <div class='ui-field-contain'>
            <label for='book-time-end'>Till:</label>
            <div data-role='controlgroup' data-type='horizontal'>
                <input type='date' id='book-date-end' data-wrapper-class='controlgroup-textinput ui-btn'>
                <select name='book-time-end' id='book-time-end'><?php for ($h=0;$h<24;$h++) echo "<option value='$h'>$h:00</option>"; ?></select>
            </div>
        </div>
        
        <button id="book-btn-save-part" disabled="disabled" onClick="checkTimes(true);">Gå vidare</button>
    </div><!-- /#book-step2 -->
    
    </div><!--/main-->
    

    <div data-role="footer" data-position="fixed" data-theme="a">
        <div class="footer-button-left">
            <a href="javascript:scrollDate(-28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-leftleft ui-nodisc-icon">-4 veckor</a>
            <a href="javascript:scrollDate(-7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-left ui-nodisc-icon ui-alt-icon">-1 vecka</a>
        </div>
        <h2 id="book-current-range-readable"></h2>
        <div class="footer-button-right">
            <a href="javascript:scrollDate(7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-right ui-nodisc-icon ui-alt-icon">+1 vecka</a>
            <a href="javascript:scrollDate(28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-rightright ui-nodisc-icon ui-alt-icon">+10 veckor</a>
        </div>
    </div><!--/footer-->
    
    <div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b">
        <h3 id='item-caption'></h3>
        <div id='item-details'></div>
    </div>
    
    <div data-role="popup" id="popup-items-unavail" class="ui-content" data-overlay-theme="b">
        <h3>Kan inte boka</h3>
        <p>Tiden du har valt är inte tillgänglig. Följande resurser har redan bokningar som krockar med dina valda tider:</p>
        <ul id='ul-items-unavail'></ul>
        <a href="#" data-rel="back" class="ui-btn">OK</a>
    </div>

</div><!-- /page -->

</body>
</html>
