<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\Item;

session_start();
require(__DIR__."/../inc/common.php");
global $FF, $cfg;

if ($_GET['sectionId']) $_SESSION['sectionId'] = $_GET['sectionId'];
// This page may only be accessed by registered users 
if (!$_SESSION['authenticatedUser'] || !$_SESSION['sectionId']) {
    header("Location: {$cfg['url']}index.php?message=" . urlencode("Du måste logga in för att använda bokningsöversikten.") . "&redirect=" . urlencode("{$cfg['url']}admin/bookings-d.php?sectionId={$_REQUEST['sectionId']}"));
    die();
}
// Set current section and user
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);
// User must have some sort of admin function
if (!$section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) {
    header("Location: {$cfg['url']}");
    die();
}

/**
 * Displays the items of a category, including child categories, where user has admin access.
 * The IDs of all items included will be added to $_SESSION['itemIds'] array.
 * @param Category $cat Category to show
 * @param User $user 
 */
function showCat(Category $cat, User $user) {
    if ($cat->showFor($user, FFBoka::ACCESS_CONFIRM)) {
        // User has access to this or some child category
        if ($cat->getAccess($user) >= FFBoka::ACCESS_CONFIRM) {
            // User has sufficient access to this category and its items.
            $items = $cat->items();
            if (count($items)) {
                echo "<h2>";
                for ($elems = $cat->getPath(), $i = 1; $i < count($elems); $i++) {
                    if ($i > 1) echo " &rarr; ";
                    if ($cat->showFor($user, FFBoka::ACCESS_CATADMIN)) echo "<a href='#' onClick=\"openSidePanelOrWindow('category.php?catId={$elems[$i]['id']}');\">{$elems[$i]['caption']}</a>";
                    else echo $elems[$i]['caption'];
                }
                echo "</h2>\n<table>\n";
            }
            foreach ($items as $item) {
                echo "<tr><td class='col-caption" . ($item->active ? "" : " inactive") . "' onClick=\"showItemDetails({$item->id});\"><span title='" . htmlspecialchars($item->caption) . "'>" . htmlspecialchars($item->caption) . "</span></td>\n";
                echo "<td class='col-freebusy'><div class='freebusy-bar' id='freebusy-item-{$item->id}' style='margin-bottom:0px;'></div></td></tr>\n";
                $_SESSION['itemIds'][] = $item->id;
            }
            if (count($items)) echo "</table>\n";
        }
        foreach ($cat->children() as $child) showCat($child, $user);
    }
}

switch ($_REQUEST['action']) {
    case "ajaxFindUser":
        header("Content-Type: application/json");
        die(json_encode($FF->findUser($_REQUEST['term'])));

    case "ajaxGetFreebusy":
        header("Content-Type: application/json");
        $start = new DateTime("{$_GET['year']}-{$_GET['month']}-01");
        $end = clone $start;
        $end->add(new DateInterval("P1M"));
        // compose scale with day numbers
        $daysInMonth = $end->diff($start)->days;
        $day = clone $start;
        $scale = "";
        $style = "border-left:none;";
        while ($day->format('n') == $start->format('n')) {
            $class = ($day->format('N') > 5) ? "freebusy-weekend" : "";
            $scale .= "<div class='freebusy-tic $class' style='$style width:" . (100/$daysInMonth) . "%; left:" . (100/$daysInMonth*($day->format('j')-1)) . "%;'>{$day->format('j')}</div>";
            $style = "";
            $day->add(new DateInterval('P1D'));
        };
        // Freebusy bars
        $fbList = array();
        foreach ($_SESSION['itemIds'] as $id) {
            $item = new Item($id);
            $fbList["item-$id"] = $item->freebusyBar([
                'start'=>$start->getTimestamp(),
                'scale'=>TRUE,
                'days'=>$daysInMonth,
                'minStatus'=>FFBoka::STATUS_CONFLICT,
                'showPrice'=>TRUE
            ]);
        }
        die(json_encode(["scale"=>$scale, "freebusy"=>$fbList]));
        
    case "ajaxAddBookingOnBehalf":
        header("Content-Type: application/json");
        $user = new User($_REQUEST['userId']);
        $booking = $user->addBooking($section->id);
        $booking->commentIntern = "Bokning inlagd av " . $currentUser->name;
        $_SESSION['bookingId'] = $booking->id;
        $_SESSION['token'] = $booking->token;
        die(json_encode([ "status"=>"OK" ]));
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Bokningsadmin ".$section->name, $cfg['url'], "desktop") ?>
	<script>
	var startDate,
		lastSeenBookingId=0;
	const dateOptions = { year: 'numeric', month: 'long' };
	
    startDate = new Date(new Date().setHours(0,0,0,0)); // Midnight

    setInterval(function() { // update view every 2 minutes
        scrollDate(0);
    }, 120000);
    
	$( function() {
        // Initialise date chooser
        scrollDate(0);

        $("#popup-add-booking").dialog({
			autoOpen: false
        });

        $("#search-member").autocomplete({
            source: "<?= basename(__FILE__) ?>?action=ajaxFindUser",
            minLength: 2,
            response: function( event, ui ) {
                for (var i=0; i<ui.content.length; i++) {
                    ui.content[i].label = "Boka som "+ui.content[i].name;
                };
                if (ui.content.length == 0) ui.content.push({ label:"Ingen träff. Boka som gäst?", value:0 });
            },
            select: function( event, ui ) {
            	$("#search-member").val("");
                addBooking(ui.item.userId);
            }
        });
        
        $(document).on('keydown', function(ev) {
            switch(ev.which) {
            case 37: // left
                scrollDate(-1);
                break;
            case 39: // right
                scrollDate(1);
                break;
            }
        });
        
        $(document).on('click', ".freebusy-busy", function() {
        	openSidePanelOrWindow("../book-sum.php?bookingId=" + this.dataset.bookingId, "booking"+this.dataset.bookingId);
        });
    });

	// Show details for an item in side panel
	function showItemDetails(itemId) {
    	openSidePanelOrWindow("../item-details.php?itemId=" + itemId, "itemDetails" + itemId);
	}
	
	// Scroll by x months
    function scrollDate(offset) {
        startDate.setMonth(startDate.getMonth() + offset, 1); // 1st of month
        var endDate = new Date(startDate.valueOf());
        endDate.setMonth(endDate.getMonth() + 1);
        // Get freebusy bars
        $.getJSON("bookings-d.php", {
            action: "ajaxGetFreebusy",
            year: startDate.getFullYear(),
            month: startDate.getMonth()+1
        }, function(data, status) {
            $("#booking-adm-date").html( startDate.toLocaleDateString("sv-SE", dateOptions) );
            $("#booking-adm-scale").html( data.scale );
            $.each(data.freebusy, function(key, value) { // key will be "item-nn"
                $("#freebusy-"+key).html(value);
            });
            lastSeenBookingId = data.lastSeenBookingId;
        });
    }

	// Add a new booking on behalf of another user	
    function addBooking(userId) {
        $.getJSON("<?= basename(__FILE__) ?>", { action: "ajaxAddBookingOnBehalf", userId: userId }, function(data) {
            if (data.status=="OK") {
                openSidePanelOrWindow("../book-part.php");
            }
            else alert("Något har gått fel. Kontakta systemadmin.");
            $('#popup-add-booking').dialog('close');
        });
    }

	function openSidePanelOrWindow(url, windowId="_blank") {
		if (screen.width < 800) {
			var newtab = window.open(url, windowId);
			newtab.focus();
		} else {
			$("#iframe-booking").attr("src", url).css('width','33%');
			$("#booking-admin").css("padding-right", "35%");
			$("#close-iframe-booking").show();
		}
	}
	
	// Close the side panel
	function closeSidePanel() {
		$("#close-iframe-booking").hide();
		$('#booking-admin').css('padding-right', '0');
		$('#iframe-booking').css('width','0').attr('src','');
	}
	</script>
</head>


<body class='desktop'>

<iframe id="iframe-booking"></iframe>
<div id="close-iframe-booking"><a href="#" title="Stäng sidpanel" onClick="closeSidePanel();"><i class="fas fa-times"></i></a></div>

<div id='popup-add-booking' title="Lägg till bokning">
	<div class="ui-widget">
		<label for="search-member">Leta efter medlem:</label>
		<input id="search-member">
	</div>
	<div>
		eller:
		<button onClick="addBooking(0);">Boka som gäst</button>
	</div>
</div>

<div id='booking-admin'>
	<div id="head">
		<div id="indicator-new-bookings">10 (2 &#9889;)
			<div>Nya bokningar:<ul><li>En bokning</li><li>Annan bokning</li><li>Tredje bokningen</li><li>Tredje bokningen</li></ul></div>
		</div>
        <h1><a href="<?= $cfg['url'] ?>" title="Till startsidan"><i class='fas fa-home' style='color:white; margin-right:20px;'></i></a> Bokningar i <?= $section->name ?>, <span id='booking-adm-date'></span></h1>
        <table>
        	<tr><td class='col-caption navbuttons'>
        		<a title="1 månad bakåt (vänsterpil)" href="#" onClick="scrollDate(-1);"><i class='fas fa-chevron-left'></i></a>
        		<a title="Gå till idag" href="#" onClick="startDate = new Date(new Date().setHours(0,0,0,0));scrollDate(0);"><i class='fas fa-calendar-day'></i></a>
        		<a title="1 månad framåt (högerpil)" href="#" onClick="scrollDate(1);"><i class='fas fa-chevron-right'></i></a>
        		<a title="Uppdatera" href="#" onClick="scrollDate(0);"><i class='fas fa-sync'></i></a>
        		<a title="Lägg in ny bokning" href="#" onClick="$('#popup-add-booking').dialog('open');"><i class='fas fa-plus'></i></a>
        	</td>
        	<td><div class='freebusy-bar' id='booking-adm-scale'></div></td></tr>
        </table>
	</div>
	
    <?php 
    $_SESSION['itemIds'] = array();
    foreach ($section->getMainCategories() as $cat) {
        showCat($cat, $currentUser);
    }
    ?>
    
    <div id='legend'>
    	<h3>Teckenförklaring</h3>
		<p>
			<span class='freebusy-free' style='display:inline-block; width:2em;'>&nbsp;</span> tillgänglig tid<br>
            <span class='freebusy-busy' style='display:inline-block; width:2em;'>&nbsp;</span> bokad tid<br>
            <span class='freebusy-busy unconfirmed' style='display:inline-block; width:2em;'>&nbsp;</span> obekräftad bokning<br>
            <span class='freebusy-busy conflict' style='display:inline-block; width:2em;'>&nbsp;</span> obekräftad bokning som krockar med annan befintlig bokning<br>
            <span class='freebusy-blocked' style='display:inline-block; width:2em;'>&nbsp;</span> ej bokbar tid<br>
        </p>
    </div>
</div>
</body>
</html>
