<?php
session_start();
require("common.php");
global $db;

if (isset($_REQUEST['sectionID'])) {
	$_SESSION['sectionID'] = $_REQUEST['sectionID'];
} else {
}

?><!DOCTYPE html>
<html>
<head>
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
</head>


<body>
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



<div data-role="page" id="page-book2">
	<?= head("Boka: Välj tid") ?>
	<div role="main" class="ui-content">
		<button onclick="console.log(checkedItems);">test</button>
	</div><!--/main-->
</div><!--/page-->

</body>
</html>
