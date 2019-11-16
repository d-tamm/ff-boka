<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Section;

session_start();
require(__DIR__ . "/../inc/common.php");
global $cfg;

if (!isset($_SESSION['sectionId'])) {
    header("Location: ..");
    die();
}

if (isset($_REQUEST['catId'])) $_SESSION['catId'] = $_REQUEST['catId'];
$cat = new Category($_SESSION['catId']);
$currentUser = new User($_SESSION['authenticatedUser']);
$section = new Section($_SESSION['sectionId']);

// Check access permissions.
if (!$cat->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) {
	header("Location: ..");
	die();
}

/**
 * Echoes a category tree as <select> options
 * @param Category $parent Output the tree from here downwards
 * @param Category $currentCat Do not include this category, but preselect option for this category's parent
 * @param number $indent
 */
function showCatTree(Category $parent, Category $currentCat, $indent=0) {
    echo "<option value='{$parent->id}'" . ($parent->id==$currentCat->parentId ? " selected='true'" : "") . ">" . str_repeat("&mdash;", $indent) . " {$parent->caption}</option>";
    foreach ($parent->children() as $child) {
        if ($child->id != $currentCat->id) showCatTree($child, $currentCat, $indent+1);
    }
}

/**
 * Returns html code for displaying the category access as a list
 * @param Category $cat
 * @param string[] $accLevels Access levels from $cfg['catAccessLevels']
 * @return string Formatted HTML <li> list
 */
function displayCatAccess($cat, $accLevels) {
	$ret = "";
	if ($cat->accessExternal) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('accessExternal');\">Icke-medlemmar<p>{$accLevels[$cat->accessExternal]}</p></a></li>";
	if ($cat->accessMember) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('accessMember');\">Medlem i valfri lokalavdelning<p>{$accLevels[$cat->accessMember]}</p></a></li>";
	if ($cat->accessLocal) $ret .= "<li><a href='#' onclick=\"unsetCatAccess('accessLocalMember');\">Lokal medlem<p>{$accLevels[$cat->accessLocal]}</p></a></li>";
	foreach ($cat->admins() as $adm) {
		$ret .= "<li><a href='#' onclick=\"unsetCatAccess('{$adm['userId']}');\">{$adm['name']}<p>{$accLevels[$adm['access']]}</p></a></li>";
	}
	if ($ret) return "<p>Tilldelade behörigheter:</p><ul data-role='listview' data-inset='true' data-icon='delete'>$ret</ul>";
	return "<p>Inga behörigheter har tilldelats än. Använd knappen nedan för att ställa in behörigheterna.</p>";
}

/**
 * Show contact data for user
 * @param User $u
 * @return string Formatted HTML code
 */
function contactData(User $u) {
    if ($u->id) {
        if ($u->name) {
            $ret = $u->name . "<br>";
            $ret .= "&phone;: " . ($u->phone ? $u->phone : "<b>Inget telefonnummer har angetts.</b>") . "<br>";
            $ret .= "<b>@</b>: " . ($u->mail ? $u->mail : "<b>Ingen epostadress har angetts.</b>");
            return $ret;
        } else {
            return "Medlem med nummer ".$u->id."<br><b>OBS: Kontaktpersonen måste logga in och ange sina kontaktuppgifter!</b>";
        }
    } else {
        return "<i>Ingen kontaktperson har valts.</i>";
    }
}

switch ($_REQUEST['action']) {
    case "newCat":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $cat = $section->createCategory();
            if ($_SESSION['catId']) $cat->parentId = $_SESSION['catId'];
            $cat->caption = htmlentities($_REQUEST['caption']);
            $_SESSION['catId'] = $cat->id;
        }
        break;
        
    case "setCatProp":
        // Reply to AJAX request
        switch ($_REQUEST['name']) {
            case "caption":
            case "parentId":
            case "bookingMsg":
                if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
                    header("Content-Type: application/json");
                    if ($_REQUEST['value']=="NULL") $cat->{$_REQUEST['name']} = null;
                    else $cat->{$_REQUEST['name']} = htmlentities($_REQUEST['value']);
                    die(json_encode(["OK"]));
                }
                break;
        }
        break;
        
    case "setImage":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            header("Content-Type: application/json");
            if(json_encode( $cat->setImage($_FILES['image'] ) )) die(json_encode(["status"=>"OK"]));
            else die(json_encode(["error"=>"Kan inte spara bilden. Bara jpg- och png-filer accepteras."]));
        }
        
    case "setContactUser":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            if ($cat->contactUserId = $_REQUEST['id']) {
                $cuser = $cat->contactUser();
                die(json_encode([ "status"=>"OK", "html"=>contactData($cuser) ]));
            }
            else die(json_encode(["error"=>"Kan inte sätta kontaktperson."]));
        }
        
    case "deleteCat":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $cat->delete();
            header("Location: index.php");
        }
        die();
        
    case "setCatAccess":
    	switch ($_GET['ass']) {
    	case "access_external":
    	case "access_member":
    	case "access_local_member":
    		$stmt = $db->prepare("UPDATE categories SET {$_GET['ass']}=:cat_access WHERE catID=:catID");
    		if (!$stmt->execute(array(
    			":cat_access"=>$_GET['cat_access'],
    			":catID"=>$_SESSION['catID'],
    		))) die(0);
    		break;
    	default:
    		$stmt = $db->prepare("INSERT INTO cat_access SET catID=:catID, ass_name=:ass, cat_access=:cat_access ON DUPLICATE KEY UPDATE cat_access=VALUES(cat_access)");
    		if (!$stmt->execute(array(
    			":catID"=>$_SESSION['catID'],
    			":ass"=>$_GET['ass'],
    			":cat_access"=>$_GET['cat_access'],
    		))) die(0);
    	}
    	die(displayCatAccess());
    	break;
    	
    case "unsetCatAccess":
    	switch ($_GET['ass']) {
    	case "access_external":
    	case "access_member":
    	case "access_local_member":
    		$db->exec("UPDATE categories SET {$_GET['ass']}=0 WHERE catID={$_SESSION['catID']}");
    		break;
    	default:
    		$stmt = $db->prepare("DELETE FROM cat_access WHERE catID={$_SESSION['catID']} AND ass_name=?");
    		if (!$stmt->execute(array($_GET['ass']))) die(0);
    	}
    	die(displayCatAccess());
    	break;
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Kategori " . $cat->caption) ?>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page-category">
	<?= head($cat->caption, $currentUser) ?>
	<div role="main" class="ui-content">

	<div data-role="collapsibleset" data-inset="false">
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand'] ? "true" : "false" ?>">
			<h2>Allmänt</h2>
			<input type="hidden" name="action" value="save category">
			<input type="hidden" name="catID" value="<?= $cat->id ?>">

			<p>Tillhör <a href="index.php">LA <?= $section->name ?></a></p>

			<div class="ui-field-contain">
				<label for="cat-caption" class="required">Rubrik:</label>
				<input name="caption" class="ajax-input" id="cat-caption" placeholder="Namn till kategorin" value="<?= $cat->caption ?>">
			</div>

			<div class="ui-field-contain">
				<label for="cat-parentID">Överordnad kategori:</label>
				<select id="cat-parentId" name="parentID">
					<option value="NULL">- ingen -</option>
					<?php
					foreach ($section->getMainCategories() as $child) {
					    if ($child->id != $cat->id) showCatTree($child, $cat);
					}
					?>
				</select>
			</div>

			<p>Bild att visa med kategorin:</p>
			<img src="../image.php?type=category&id=<?= $cat->id ?>" id="cat-img-preview"<?= $cat->thumb ? "" : " style='display:none;'" ?>>
			<div class="ui-field-contain">
				<label for="file-cat-img">Ladda upp ny bild:</label>
				<input type="file" name="image" id="file-cat-img">
			</div>
			
			<label for="cat-bookingMsg">Text som ska visas när användare vill boka resurser från denna kategori:</label>
			<textarea name="bookingMsg" class="ajax-input" id="cat-bookingMsg" placeholder="Exempel: Kom ihåg att ta höjd för torkningstiden efter användningen!"><?= $cat->bookingMsg ?></textarea>
			
			<h3>Kontaktperson</h3>
			<div id="cat-contact-data"><?= contactData($cat->contactUser()) ?></div>
			<form class="ui-filterable">
				<input id="cat-contact-autocomplete-input" data-type="search" placeholder="Välj kontaktperson...">
			</form>
			<ul id="cat-contact-autocomplete" data-role="listview" data-filter="true" data-input="#cat-contact-autocomplete-input" data-inset="true"></ul>

			<button class="ui-btn ui-btn-c" id="delete-cat">Radera kategorin</button>
			<br>
		</div>

		<?php if ($cat->id) { ?>
		<form data-role="collapsible" class="ui-filterable" data-collapsed="<?= $stayOnPage ? "false" : "true" ?>">
			<h2>Behörigheter</h2>
			<div data-role="collapsible" data-inset="true" data-mini="true" data-collapsed-icon="info">
				<h4>Hur gör jag?</h4>
				<p>Här bestäms vem som får se och boka resurserna i kategorin <?= $cat->caption ?>. Först väljer du gruppen som ska få behörighet. Sedan väljer du vilken behörighetsnivå gruppen ska få.</p>
				<p>Återkalla behörigheter genom att klicka på dem.</p>
				<p>Om en användare tillhör flera grupper gäller den högsta tilldelade behörigheten.</p>
			</div>
			
			<div id="assigned-cat-access"><?= displayCatAccess($cat, $cfg['catAccessLevels']) ?></div>

			<p>Tilldela ny behörighet:</p>
			<fieldset data-role="controlgroup" data-mini="true">
				<label><input type="radio" name="group" value="accessExternal">Icke-medlemmar </label>
				<label><input type="radio" name="group" value="accessMember">Medlem i valfri lokalavdelning</label>
				<label><input type="radio" name="group" value="accessLocal">Lokal medlem</label>
				<label><input type="radio" name="group" value="user">Specifik medlem enligt nedan</label>
				<input id="cat-adm-name-autocomplete-input" data-type="search" placeholder="Medlem">
			</fieldset>
			<ul id="cat-adm-name-autocomplete" data-role="listview" data-inset="true" data-filter="true" data-input="#cat-adm-name-autocomplete-input"></ul>
			
			<fieldset data-role="controlgroup" data-mini="true">
				<p>2. Välj behörighetsnivå här:</p>
				<input type="radio" class="cat-access-choice" name="cat-access" id="cat-access-1" value="1">
				<label for="cat-access-1"> <?= $cfg['catAccessLevels'][1] ?></label>
				<input type="radio" class="cat-access-choice" name="cat-access" id="cat-access-2" value="2">
				<label for="cat-access-2"> <?= $cfg['catAccessLevels'][2] ?></label>
			</fieldset>
			<br>
		</form>
		

		<?php
		$children = $cat->children(); ?>
		<div data-role="collapsible" data-collapsed="true">
			<h2>Underkategorier (<?= count($children) ?>)</h2>
			<ul data-role="listview"><?php
				foreach ($children as $child) {
					echo "<li><a href='category.php?catId={$child->id}' data-ajax='false'>" .
						embedImage($child->thumb) .
						"<h3>{$child->caption}</h3>";
					$subcats = array();
					foreach ($child->children() as $grandchild) $subcats[] = $grandchild->caption;
					if ($subcats) echo "<p>" . implode(", ", $subcats) . "</p>";
					echo "<span class='ui-li-count'>{$child->itemCount}</span></a></li>\n";
				}
				?>
				<li><a href="category.php?action=new&parentID=<?= $cat->id ?>" data-ajax="false">Lägg till underkategori</a></li>
			</ul>
			<br>
		</div>

		<?php
		$items = $cat->items(); ?>
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']!="items" ? "true" : "false" ?>">
			<h2>Resurser (<?= count($items) ?>)</h2>
			<ul data-role="listview">
				<?php
				foreach ($items as $item) {
					echo "<li" . ($item->active ? "" : " class='inactive'") . "><a href='item.php?itemID={$item->id}'>" .
						embedImage($item->getFeaturedImage()->thumb) .
						"<h3>{$item->caption}</h3>" .
						"<p>" . ($item->active ? $item->description : "(inaktiv)") . "</p>" .
						"</a></li>\n";
				} ?>
				<li><a href="item.php">Lägg till resurs</a></li>
			</ul>
			<br>
		</div>
		
		<?php } ?>
	</div><!--/collapsibleset-->
	</div><!--/main-->

	<script>
		var chosenGrp=0;

		$("#cat-access-grp").change(function() {
			$(".cat-access-choice").attr("checked", false).checkboxradio("refresh");
			chosenGrp = this.value;
			$("#cat-access-details").show();
		});
		
		$("#cat-access-cancel").click(function() {
			$("#cat-access-details").hide();
			$("#cat-access-grp").val("").selectmenu("refresh");
			return false;
		});

		$(".cat-access-choice").click(function() {
			$.mobile.loading("show", {});
			$("#cat-access-details").hide();
			$("#cat-access-grp").val("").selectmenu("refresh");
			$.get("?action=setCatAccess&ass="+encodeURIComponent(chosenGrp)+"&cat_access="+this.value, function(data, status) {
				if (data!=0) {
					$("#assigned-cat-access").html(data).enhanceWithin();
				} else {
					alert("Kunde inte spara behörigheten.");
				}
				$.mobile.loading("hide", {});
			});
		});
		
		function unsetCatAccess(ass) {
			$.mobile.loading("show", {});
			$.get("?action=unsetCatAccess&ass="+encodeURIComponent(ass), function(data, status) {
				if (data!=0) {
					$("#assigned-cat-access").html(data).enhanceWithin();
				} else {
					alert("Kunde inte återkalla behörigheten.");
				}
				$.mobile.loading("hide", {});
			});
		}

		var tmrSetValue;
		
		function setCatProp(name, val) {
			console.log("setCatProp " + name + "=" + val);
			$.getJSON("category.php", {action: "setCatProp", name: name, value: val}, function(data, status) {
				console.log(data);
				$("#cat-"+name).addClass("change-confirmed");
				setTimeout(function(){ $("#cat-"+name).removeClass("change-confirmed"); }, 1500);
			});
		}

		function setContactUser(id) {
			$.getJSON("category.php", { action: "setContactUser", id: id }, function(data, status) {
				$("#cat-contact-data").html(data.html);
	        	$("#cat-contact-autocomplete-input").val("");
	        	$("#cat-contact-autocomplete").html("");
			});
		}
		
		$(document).on( "pagecreate", "#page-category", function() {

			$("#cat-caption").on('input', function() {
				clearTimeout(tmrSetValue);
				tmrSetValue = setTimeout(setCatProp, 1000, "caption", this.value);
			});

			$("#cat-parentId").on('change', function() {
				setCatProp("parentId", this.value);
			});

			$("#cat-bookingMsg").on('input', function() {
				clearTimeout(tmrSetValue);
				tmrSetValue = setTimeout(setCatProp, 1000, "bookingMsg", this.value);
			});
			
			$("#file-cat-img").change(function() {
				// Save image via ajax: https://makitweb.com/how-to-upload-image-file-using-ajax-and-jquery/
				var fd = new FormData();
				var file = $('#file-cat-img')[0].files[0];
				fd.append('image', file);
				fd.append('action', "setImage");
				$.mobile.loading("show", {});

				$.ajax({
					url: 'category.php',
					type: 'post',
					data: fd,
					dataType: 'json',
					contentType: false,
					processData: false,
					success: function(data) {
						if (data.status=="OK") {
							var d = new Date();
							$('#cat-img-preview').attr("src", "../image.php?type=category&id=<?= $cat->id ?>&" + d.getTime()).show().trigger( "updatelayout" );
						} else {
							alert(data.error);
						}
						$.mobile.loading("hide", {});
					},
				});
			});
			
    	    $( "#cat-contact-autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
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
    	                    html += "<li style='cursor:pointer;' title='Sätt " + val['name'] + " som kontaktperson' onClick='setContactUser(" + val['userId'] + ");'>" + val['userId'] + " " + (val['name'] ? val['name'] : "(ingen persondata tillgänglig)") + "</li>";
    	                });
						if (data.length==0) {
							if (Number(value)) html += "<li style='cursor:pointer;' title='Lägg till medlem med medlemsnummer " + Number(value) + " som kontaktperson' onClick='setContactUser(" + Number(value) + ");'>" + Number(value) + " (ny användare)</li>";
							else html += "<li>Sökningen på <i>" + value + "</i> gav ingen träff</li>";
						}
    	                $ul.html( html );
    	                $ul.listview( "refresh" );
    	                $ul.trigger( "updatelayout");
    	            });
    	        }
    	    });
    	    
    		$("#delete-cat").click(function() {
    			if (confirm("Du håller på att ta bort kategorin och alla poster i den. Fortsätta?")) {
    				location.href="?action=deleteCat";
    			}
    		});
		});
	</script>

</div><!--/page-->
</body>
</html>
