<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\Item;

session_start();
require(__DIR__."/../inc/common.php");

if ($_GET['sectionId']) $_SESSION['sectionId'] = $_GET['sectionId'];
// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser'] || !$_SESSION['sectionId']) {
    header("Location: /");
    die();
}
// Set current section and user
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);

/**
 * Displays the items of a category, including child categories, where user has admin access.
 * The IDs of all items included will be added to $_SESSION['itemIds'] array.
 * @param Category $cat Category to show
 * @param User $user 
 */
function showCat(Category $cat, User $user) {
    if ($cat->showFor($user, FFBoka::ACCESS_CONFIRM)) {
        $items = $cat->items();
        if (count($items)) {
            echo "<h2>";
            $elems = array_column($cat->getPath(), "caption");
            array_shift($elems);
            echo implode(" &rarr; ", $elems); 
            echo "</h2>\n<table>\n";
        }
        foreach ($items as $item) {
            echo "<tr><td class='col-caption'>{$item->caption}</td>\n";
            echo "<td class='col-freebusy'><div class='freebusy-bar' id='freebusy-item-{$item->id}' style='margin-bottom:0px;'></div></td></tr>\n";
            $_SESSION['itemIds'][] = $item->id;
        }
        if (count($items)) echo "</table>\n";
        foreach ($cat->children() as $child) showCat($child, $user);
    }
}

switch ($_REQUEST['action']) {
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
            $fbList["item-$id"] = $item->freebusyBar(['start'=>$start->getTimestamp(), 'scale'=>TRUE, 'days'=>$daysInMonth, 'minStatus'=>FFBoka::STATUS_CONFLICT, 'includeTokens'=>TRUE]);
        }
        die(json_encode(["scale"=>$scale, "freebusy"=>$fbList]));
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Bokningsadmin ".$section->name, "desktop") ?>
	<script>
	var startDate;
	const dateOptions = { year: 'numeric', month: 'long' }
    startDate = new Date(new Date().setHours(0,0,0,0)); // Midnight

    setInterval(function() { // update view every 2 minutes
        scrollDate(0);
    }, 120000);
    
	$( function() {
        // Initialise date chooser
        scrollDate(0);

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
            var bookingView = window.open("/book-sum.php?bookingId=" + this.dataset.bookingId + "&token=" + this.dataset.token, "booking" + this.dataset.bookingId);
            bookingView.focus();
        });
    });

	// Scroll by x months
    function scrollDate(offset) {
        startDate.setMonth(startDate.getMonth() + offset, 1); // 1st of month
        console.log("scrollDate", startDate);
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
        });
    }
	</script>
</head>


<body class='desktop'>
<div id='booking-admin'>
	<div id="head">
        <h1>Bokningar i <?= $section->name ?>, <span id='booking-adm-date'></span></h1>
        <table>
        	<tr><td class='col-caption'>
        		<button title="1 månad bakåt (&larr;)" onClick="scrollDate(-1);"><span class='ui-icon ui-icon-caret-1-w'></span></button>
        		<button title="Uppdatera" onClick="scrollDate(0);"><span class='ui-icon ui-icon-refresh'></span></button>
        		<button title="1 månad framåt (&rarr;)" onClick="scrollDate(1);"><span class='ui-icon ui-icon-caret-1-e'></span></button>
        	</td><td><div class='freebusy-bar' id='booking-adm-scale'></div></td></tr>
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
            <span class='freebusy-busy' style='display:inline-block; width:2em;'>&nbsp;</span> bokning<br>
            <span class='freebusy-busy unconfirmed' style='display:inline-block; width:2em;'>&nbsp;</span> obekräftad bokning<br>
            <span class='freebusy-busy conflict' style='display:inline-block; width:2em;'>&nbsp;</span> obekräftad bokning som krockar med annan befintlig bokning<br>
            <span class='freebusy-blocked' style='display:inline-block; width:2em;'>&nbsp;</span> ej bokbar tid<br>
        </p>
    </div>
</div>
</body>
</html>
