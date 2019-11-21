<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
$message = "";

/**
 * Get the list of admins for this section
 * @param \FFBoka\Section $section
 * @return string HTML string with <li> elements.
 */
function adminList($section) {
    if (!$admins = $section->getAdmins()) $ret = "<li>Inga administratörer har lagts upp än.</li>";
    foreach ($admins as $admId) {
        $adm = new User($admId);
        $ret .= "<li><a href='#'><h2>" . ($adm->name ? $adm->name : "(ingen persondata tillgänglig)") . "</h2><p>{$adm->id}</p></a><a href=\"javascript:removeAdmin({$adm->id}, '{$adm->name}');\">Ta bort</a></li>";
    }
    return $ret;
}


session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $FF;

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: /");
    die();
}

// Set current section and user
if ($_GET['sectionId']) $_SESSION['sectionId'] = $_GET['sectionId'];
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);

// Only allow users which have at least some admin role in this section
if (!$section->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
	header("Location: ..");
	die();
}
$userAccess = $section->getAccess($currentUser);

switch ($_REQUEST['action']) {
    case "findUser":
        // Reply to AJAX request. This is also called from category.php
        header("Content-Type: application/json");
        die(json_encode($FF->findUser($_REQUEST['q'])));
		
    case "addSectionAdmin":
        // Reply to AJAX request.
		if ($userAccess == FFBoka::ACCESS_SECTIONADMIN) {
		    header("Content-Type: application/json");
		    if (!is_numeric($_REQUEST['id'])) {
		        die(json_encode(["error"=>"Ogiltigt medlemsnummer {$_REQUEST['id']}."]));
		    } elseif ($section->addAdmin(htmlentities($_REQUEST['id']))) {
		        die(json_encode(["html"=>adminList($section)]));
			} else {
			    die(json_encode(["error"=>"Kunde inte lägga till administratören. Är den kanske redan med i listan?"]));
			}
		}
		die("Du får inte göra så!");
		
    case "removeSectionAdmin":
        // Reply to AJAX request.
        if ($userAccess == FFBoka::ACCESS_SECTIONADMIN) {
		    header("Content-Type: application/json");
		    if ($section->removeAdmin(htmlentities($_REQUEST['id']))) die(json_encode(["html"=>adminList($section)]));
		    else die(json_encode(["error"=>"Kan inte ta bort LA-administratören."]));
		}
		die("Du får inte göra så!");
}

unset($_SESSION['catId']);

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Admin ".$section->name) ?>
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
<div data-role="page" id="page-section-admin">
	<?= head("LA ".$section->name, $currentUser) ?>
	<div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
    	<p><?= $message ?></p>
    	<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

	<div data-role="collapsibleset" data-inset="false">

		<div data-role="collapsible" data-collapsed="<?= isset($_REQUEST['expand']) ? "true" : "false" ?>">
			<h2>Kategorier</h2>
			<ul data-role="listview">
			<?php
			foreach ($section->getMainCategories() as $cat) {
			    if ($cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    				echo "<li><a href='category.php?catId={$cat->id}' data-ajax='false'>
    					{$cat->caption}
    					" . embedImage($cat->thumb);
    				$children = array();
    				foreach ($cat->children(TRUE) as $child) $children[] = $child->caption;
    				if ($children) echo "<p>" . implode(", ", $children) . "</p>";
    				echo "<span class='ui-li-count'>{$cat->itemCount}</span></a></li>";
			    }
			}
			if ($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) echo "<li><a href='category.php?action=new' data-ajax='false'>Skapa ny kategori</a></li>"; ?>
			</ul>
			<br>
		</div>

		<?php if ($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) { ?>
		
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']=="admins" ? "false" : "true" ?>">
			<h2>Administratörer</h2>

			<p>Lägg till ny administratör på LA-nivå: (skriv medlemsnummer eller namn)
				<a href="#popup-help-admin-access" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Tipps</a>
			</p>
			<div data-role="popup" id="popup-help-admin-access" class="ui-content">
				<p>Här ställer du in vilka medlemmar som ska ha behörighet att administrera resursbokningen i lokalavdelningen <?= $section->name ?>. Du kan alltid lägga till en ny admin genom att ange dess medlemsnummer. Om personen tidigare har loggat in i resursbokningen och lagt in sina kontaktuppgifter kan du även hitta hen genom att leta efter namnet istället.</p>
				<p><?php
                $allAss = $cfg['sectionAdmins'];
                $oneAss = array_pop($allAss);
                echo implode(", ", $allAss) . " och " . $oneAss; ?>
            	har automatiskt administratörsbehörighet.</p>
            	<p>Om du bara vill tilldela behörigheter för att någon ska hantera enskilda kategorier gör du det under respektive kategori.</p>
			</div>

			<form class="ui-filterable">
				<input id="sec-adm-autocomplete-input" data-type="search" placeholder="Lägg till admin...">
			</form>
			<ul id="sec-adm-autocomplete" data-role="listview" data-filter="true" data-input="#sec-adm-autocomplete-input" data-inset="true"></ul>
				
			<p>Användare med admin-behörighet:</p>
			<ul id="ul-sec-admins" data-role="listview" data-split-icon="delete" data-split-theme="c" data-inset="true">
				<?= adminList($section) ?>
			</ul>
		<?php } ?>

	</div><!--/collapsibleset-->
	</div><!--/main-->

	<script>
    	$( document ).on( "pagecreate", "#page-section-admin", function() {
    	    $( "#sec-adm-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
    	        var $ul = $( this ),
    	            $input = $( data.input ),
    	            value = $input.val(),
    	            html = "";
    	        $ul.html( "" );
    	        if ( value && value.length > 2 ) {
    	            $ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
    	            $ul.listview( "refresh" );
    				$.getJSON("index.php", {action: "findUser", q: value}, function(data, status) {
    	                $.each( data, function ( i, val ) {
        	                html += "<li style='cursor:pointer;' title='Lägg till " + val['name'] + " som LA-admin' onClick='addAdmin(" + val['userId'] + ");'>" + val['userId'] + " " + (val['name'] ? val['name'] : "(ingen persondata tillgänglig)") + "</li>";
    	                });
						if (data.length==0) {
							if (Number(value)) html += "<li style='cursor:pointer;' title='Lägg till medlem med medlemsnummer " + Number(value) + " som LA-admin' onClick='addAdmin(" + Number(value) + ");'>" + Number(value) + " (ny användare)</li>";
							else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
						}
    	                $ul.html( html );
    	                $ul.listview( "refresh" );
    	                $ul.trigger( "updatelayout");
    	            });
    	        }
    	    });
    	});

    	function addAdmin(id) {
        	$.getJSON("index.php", {action: "addSectionAdmin", id: id}, function(data, status) {
    			if (data['html']) {
					$("#ul-sec-admins").html(data['html']).listview("refresh");
		        	$("#sec-adm-autocomplete-input").val("");
		        	$("#sec-adm-autocomplete").html("");
    			} else {
        			alert(data['error']);
    			}
        	});
    	}

		function removeAdmin(id, name) {
			if (confirm('Du håller på att återkalla admin-behörighet för ' + (name ? name : "(okänd)") + '. Vill du fortsätta?')) {
    			//location.href = "?action=removeSectionAdmin&id=" + id;
    			$.getJSON("index.php", {action: "removeSectionAdmin", id: id}, function(data, status) {
        			if (data['html']) {
						$("#ul-sec-admins").html(data['html']).listview("refresh");
        			} else {
            			alert(data['error']);
        			}
    			});
			}
		}
	</script>

</div><!--/page-->
</body>
</html>
