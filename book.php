<?php
<<<<<<< HEAD
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
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
		echo $cat->bookingMsg ? "<p>{$cat->bookingMsg}</p>" : ""; // TODO: Display the user's access level?
		if ($access) {
			echo "<ul data-role='listview' data-split-icon='info' data-split-theme='a'>";
			foreach ($cat->items() as $item) {
				if ($item->active) {
					echo "<li id='book-item-{$item->id}'><a href=\"javascript:toggleItem({$item->id});\">";
					echo embedImage($item->getFeaturedImage()->thumb);
					echo "<h4>{$item->caption}</h4>";
					if ($access >= FFBoka::ACCESS_PREBOOK) {
						echo "<div id='freebusy-item-{$item->id}' style='overflow: hidden; width:100%; height:20px; position:relative; background-color:#D0BA8A; font-weight:normal; font-size:small;'></div>";
					}
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
 * @param [ string ] $fbList Array of HTML strings representing freebusy information for 1 week. This array will be completed with all items found.
 * @param Category $cat Category in which to start searching for items
 * @param User $user User to which the items must be visible. Non-visible items will not be part of the result.
 * @param int $fbStart Unix timestamp of start of the week
 */
function getFreebusy(&$fbList, Category $cat, $user, $fbStart) {
	if ($cat->getAccess($user) >= FFBoka::ACCESS_PREBOOK) {
		foreach ($cat->items() as $item) {
			if ($item->active) {
				$fbList["item-".$item->id] = $item->freebusyBar($fbStart);
			}
		}
	}
	foreach ($cat->children() as $child) {
		getFreebusy($fbList, $child, $user, $fbStart);
	}
}

$message = "";

if (isset($_REQUEST['sectionId'])) $_SESSION['sectionId'] = $_REQUEST['sectionId'];
$section = new Section($_SESSION['sectionId']);
if ($_SESSION['authenticatedUser']) $currentUser = new User($_SESSION['authenticatedUser']);
else $currentUser = new User(0);

switch ($_REQUEST['action']) {
    case "getItemDetails":
        // Reply to ajax request
        // TODO: add more data (images, ...)
        header("Content-Type: application/json");
        $item = new Item($_REQUEST['id']);
        $ret = "<h3>{$item->caption}</h3>";
        $ret .= $item->description;
        die(json_encode($ret));
        break;

	case "updateFreebusy":
        // Reply to ajax request
        header("Content-Type: application/json");
		$fbList = array();
		foreach ($section->getMainCategories() as $cat) {
			getFreebusy($fbList, $cat, $currentUser, $_REQUEST['fbStart']);
		}
		die(json_encode($fbList));
		break;

	case "getComposedFreebusy":
	    // Reply to ajax request
	    header("Content-Type: application/json");
	    $fb = "";
	    $access = FFBoka::ACCESS_SECTIONADMIN-1;
	    try {
	    foreach (array_keys($_REQUEST['ids']) as $id) {
	        $item = new Item($id);
	        $fb .= $item->freebusyBar($_REQUEST['start'], FALSE);
	        $access = ($access & $item->category()->getAccess($currentUser));
	    }
	    } catch(Exception $e) {
	    	die();
	    }
	    $fb .= Item::freebusyScale();
	    die(json_encode([ "freebusy"=>$fb, "access"=>$access, "accessReadable"=>$cfg['catAccessLevels'][$access]]));
	    break;
=======
session_start();
require(__DIR__."/inc/common.php");
global $db;

if (isset($_REQUEST['sectionID'])) {
	$_SESSION['sectionID'] = $_REQUEST['sectionID'];
} else {
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418
}

?><!DOCTYPE html>
<html>
<head>
<<<<<<< HEAD
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
=======
	<?php htmlHeaders("Friluftsfrämjandets resursbokning") ?>

	<script>
	$( document ).on( "mobileinit", function() {
		<?php if (isset($message)) { ?>
		$( document ).on( "pagecontainershow", function( event, ui ) {
			setTimeout(function() {
				$("#popupMessage").popup('open');
			}, 500); // We need some delay here to make this work on Chrome.
		} );
		<?php } ?>
	});
	</script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418
</head>


<body>
<<<<<<< HEAD
<div data-role="page" id="page-book1">
    <?= head("Boka: Välj resurser", $currentUser) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
        <p><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= $section->name ?>
        <a href="#popup-help-admin-access" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Tipps</a>
    </h4>
    <div data-role="popup" id="popup-help-admin-access" class="ui-content">
        <h4>Hur bokar jag?</h4>
        <p>Här visas alla resurser i lokalavdelningen som du har tillgång till. Klicka på de resurser du vill boka. När du har valt resurserna går du vidare till nästa steg där du väljer start- och sluttid.</p>
		<p>För varje resurs visas tillgängligheten under en vecka i taget. För att se tillgängligheten vid andra tider, använd knapparna längst ned. Administratören kan för vissa poster ha valt att inte visa upptaget-information. I dessa fall blir din bokning bara en förfrågan där du anger önskad start- och sluttid i nästa steg.</p>
		<p>Du kan se mer information om varje resurs genom att klicka på info-knappen till höger.</p>
		<p>Om du vill göra en bokning där olika resurser behövs olika länge delar du upp bokningen. Börja med att boka alla resurser som ska ha samma tid. Sedan får du möjlighet att lägga till fler delbokningar med andra tider och/eller resurser.</p>
    </div>

    <?php
    foreach ($section->getMainCategories() as $cat) {
		displayCat($cat, $currentUser, strtotime("last monday"));
    } ?>
    <button data-transition="slideup" onClick="if (Object.keys(checkedItems).length==0) { alert('Välj först de resurser som du vill boka.'); return false; } else gotoStep2();" id="btn-book-goto2" class="ui-btn">Gå vidare: välj tid</button>
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
	
	<div data-role="popup" id="popup-item-details" class="ui-content">
		Här kommer det visas detaljer kring resursen.
	</div>

    <script>
        var checkedItems = {};
		var fbStart = new Date(<?= strtotime("last monday") ?> * 1000);
		
		scrollDate(0);
		
		function scrollDate(offset) {
            $.mobile.loading("show", {});
			fbStart.setDate(fbStart.getDate() + offset);
			var fbEnd = new Date(fbStart.valueOf());
			fbEnd.setDate(fbEnd.getDate() + 6);
			var readableRange = "må " + fbStart.getDate() + "/" + (fbStart.getMonth()+1);
			if (fbStart.getFullYear() != fbEnd.getFullYear()) readableRange += " '"+fbStart.getFullYear().toString().substr(-2);
			readableRange += " &ndash; sö " + fbEnd.getDate() + "/" + (fbEnd.getMonth()+1) + " '"+fbEnd.getFullYear().toString().substr(-2);
			$("#book-current-range-readable").html( readableRange );
            $.getJSON("book.php", { action: "updateFreebusy", fbStart: fbStart.valueOf()/1000 }, function(data, status) {
				$.each(data, function(key, value) {
					$("#freebusy-"+key).html(value);
				});
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
        }
		
		function popupItemDetails(id) {
            $.getJSON("book.php", {action: "getItemDetails", id: id}, function(data, status) {
                $("#popup-item-details").html(data).popup('open', { transition: "pop", y: 0 });
            });
		}
        
        function gotoStep2() {
            $.mobile.pageContainer.pagecontainer("change", "#page-book2", { transition: "slideup" });
            // Get composed freebusy information
            $.getJSON("book.php", { action: "getComposedFreebusy", ids: checkedItems, start: fbStart.valueOf()/1000 }, function(data, status) {
                console.log(data);
                $("#book-composed-freebusy").html(data['freebusy']);
            });
        }

    </script>

</div><!-- /page -->
=======
<div data-role="page" id="page_book1">
	<?= head("Boka: Välj resurser") ?>
	<div role="main" class="ui-content">

	<div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
		<p><?= isset($message) ? $message : "" ?></p>
		<?= isset($dontShowOK) ? "" : "<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>" ?>
	</div>

	<h4>Lokalavdelning: <?= sectionName($_SESSION['sectionID']) ?></h4>

	<div data-role="collapsible" data-inset="true" data-mini="true" data-collapsed-icon="info">
		<h4>Hur gör jag?</h4>
		<p>Klicka på de resurser du vill boka. För varje resurs visas tillgängligheten under en vecka i taget. För att se tillgängligheten vid andra tider, använd knapparna längst ned. Du kan se mer information om varje post genom att klicka på info-knappen till höger. När du har valt resurserna går du vidare till nästa steg där du väljer tid.<br>
		Om du vill göra en bokning där olika resurser behövs olika länge delar du upp bokningen. Börja med att boka alla resurser som ska ha samma tid. Sedan får du möjlighet att lägga till fler delbokningar med andra tider och andra resurser.</p>
	</div>
	
	<?php
	$stmt = $db->query("SELECT * FROM categories WHERE sectionID={$_SESSION['sectionID']} ORDER BY caption");
	while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (catAccess($cat['catID'])) { ?>
			<div data-role='collapsible' data-inset='false'>
				<h3><div class="cat-list-img"><?= embedImage($cat['thumb']) ?></div><?= $cat['caption'] ?></h3>
				<?= $cat['booking_msg'] ? "<p>{$cat['booking_msg']}</p>" : "" ?>
				<ul data-role='listview' data-split-icon='info' data-split-theme='a'><?php
				$stmt2 = $db->query("SELECT items.*, thumb FROM items LEFT JOIN item_images USING (imageID) WHERE catID={$cat['catID']} AND active ORDER BY caption");
				while ($item = $stmt2->fetch(PDO::FETCH_ASSOC)) {
					echo "<li id='book-item-{$item['itemID']}'><a href=\"javascript:toggleItem({$item['itemID']});\">" .
						embedImage($item['thumb']) .
						"<h4>{$item['caption']}</h4>". bookingBar($item['itemID'], strtotime("this monday")) . "</a>" .
						"<a href=\"javascript:alert('Detaljinformation om posten...');\"></a>" . // TODO: show detail information in popup
						"</li>";
				} ?>
				<br>
				</ul>
			</div><?php
		}
	} ?>
	<a href="#page-book2" data-transition="slideup" onClick="if (Object.keys(checkedItems).length==0) { alert('Välj först de resurser som du vill boka.'); return false; } else return true;" class='ui-btn' id="btn-book-goto2">Gå vidare: välj tider</a>
	</div><!--/main-->
    
	<div data-role="footer" data-position="fixed" data-theme="a">
		<div class="footer-button-left">
			<a href="javascript:alert('Kommer att bläddra flera veckor.');" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-ll ui-nodisc-icon">-10 veckor</a>
			<a href="javascript:alert('Kommer att bläddra en vecka.');" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-l ui-nodisc-icon ui-alt-icon">-1 vecka</a>
		</div>
		<h2>
			11/11 - 17/11 2019
		</h2>
		<div class="footer-button-right">
			<a href="javascript:alert('Kommer att bläddra en vecka.');" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-r ui-nodisc-icon ui-alt-icon">+1 vecka</a>
			<a href="javascript:alert('Kommer att bläddra flera veckor.');" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-carat-rr ui-nodisc-icon ui-alt-icon">+10 veckor</a>
		</div>
	</div><!--/footer-->

	<script>
		var checkedItems = {};
		function toggleItem(itemID){
			if (checkedItems[itemID]) {
				delete checkedItems[itemID];
			} else {
				checkedItems[itemID] = true;
			}
			$("#book-item-"+itemID).toggleClass("item-checked");
			$("#btn-book-goto2").attr("disabled", Object.keys(checkedItems).length==0);
		}
		function gotoStep2() {
			location.href="#page_book2";
		}
	</script>

</div><!--/page-->
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418



<div data-role="page" id="page-book2">
<<<<<<< HEAD
    <?= head("Boka: Välj tid", $currentUser) ?>
    <div role="main" class="ui-content">
        <div id='book-composed-freebusy' style='overflow: hidden; width:100%; height:50px; position:relative; background-color:#D0BA8A; font-weight:normal; font-size:small;'></div>
    </div><!-- /main -->
</div><!-- /page -->
=======
	<?= head("Boka: Välj tid") ?>
	<div role="main" class="ui-content">
		<button onclick="console.log(checkedItems);">test</button>
	</div><!--/main-->
</div><!--/page-->
>>>>>>> b40479cdce884253c62fd0e7ada605ec7e708418

</body>
</html>
