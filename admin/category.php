<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Section;

session_start();
require(__DIR__ . "/../inc/common.php");
global $cfg;

if (!isset($_SESSION['sectionId']) || !isset($_SESSION['authenticatedUser'])) {
    header("Location: /?action=sessionExpired");
    die();
}

if (isset($_REQUEST['catId'])) $_SESSION['catId'] = $_REQUEST['catId'];
$cat = new Category($_SESSION['catId']);
$currentUser = new User($_SESSION['authenticatedUser']);
$section = new Section($_SESSION['sectionId']);

// Check access permissions.
if (!$cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    header("Location: /?action=accessDenied&to=" . urlencode("administrationssidan för {$cat->caption}"));
    die();
}

/**
 * Echoes a category tree as <select> options
 * @param Category $parent Output the tree from here downwards
 * @param Category $currentCat Do not include this category, but preselect option for this category's parent
 * @param User $user Only categories where this user is at least CATADMIN will be shown.
 * @param number $indent Indentation for visual arrangement.
 */
function showCatTree(Category $parent, Category $currentCat, User $user, $indent=0) {
    if ($parent->getAccess($user) >= FFBoka::ACCESS_CATADMIN || $parent->id == $currentCat->parentId) {
        echo "<option value='{$parent->id}'" . ($parent->id==$currentCat->parentId ? " selected='true'" : "") . ">" . str_repeat("&mdash;", $indent) . " " . htmlspecialchars($parent->caption) . "</option>";
    } else {
        echo "<option disabled>" . str_repeat("&mdash;", $indent) . " " . htmlspecialchars($parent->caption) . "</option>";
    }
    foreach ($parent->children() as $child) {
        if ($child->id != $currentCat->id) showCatTree($child, $currentCat, $user, $indent+1);
    }
}

/**
 * Echo HTML code showing the booking questions for this category
 * @param Category $cat
 * @param Section $section
 */
function showQuestions(Category $cat, Section $section) {
    $catQuestions = $cat->getQuestions();
    $ret = "";
    foreach ($section->questions() as $question) {
        $color=""; $icon=""; $mandatory=FALSE;
        if (isset($catQuestions[$question->id])) {
            $icon = "check";
            if ($catQuestions[$question->id]->inherited) {
                $color = "style='background:var(--FF-lightblue);'";
            } else {
                $color = "style='color:white; background:var(--FF-blue);'";
            }
            if ($catQuestions[$question->id]->required) {
                $mandatory = TRUE;
            }
        } else {
            $icon = "false";
        }
        $ret .= "<li data-icon='$icon'>" .
            "<a href='#' $color onClick='toggleQuestion({$question->id});'>" .
            ($mandatory ? "<span style='font-weight:bold; color:red;'>*</span> " : "") .
            "<span style='white-space:normal;'>" . htmlspecialchars($question->caption) . "</span>" . 
            "<p style='white-space:normal;' >{$question->optionsReadable()}</p>" .
            "</a></li>\n";
    }
    return $ret;
}

/**
 * Returns html code for displaying the category access as a list
 * @param Category $cat
 * @param string[] $accLevels Access levels from $cfg['catAccessLevels']
 * @return string Formatted HTML <li> list
 */
function displayCatAccess($cat, $accLevels) {
	$ret = "";
	if ($cat->accessExternal) $ret .= "<li><a href='#' class='ajax-input'>Icke-medlemmar<p>{$accLevels[$cat->accessExternal]}</p></a><a href='#' onclick=\"unsetAccess('accessExternal');\">Återkalla behörighet</a></li>";
	if ($cat->accessMember) $ret .= "<li><a href='#' class='ajax-input'>Medlem i valfri lokalavdelning<p>{$accLevels[$cat->accessMember]}</p></a><a href='#' onclick=\"unsetAccess('accessMember');\">Återkalla behörighet</a></li>";
	if ($cat->accessLocal) $ret .= "<li><a href='#' class='ajax-input'>Lokal medlem<p>{$accLevels[$cat->accessLocal]}</p></a><a href='#' onclick=\"unsetAccess('accessLocal');\">Återkalla behörighet</a></li>";
	foreach ($cat->admins() as $adm) {
	    $ret .= "<li><a href='#' class='ajax-input'>{$adm['userId']} " . ($adm['name'] ? htmlspecialchars($adm['name']) : "(ingen persondata tillgänglig)") . "<p>{$accLevels[$adm['access']]}</p></a><a href='#' onclick=\"unsetAccess('{$adm['userId']}');\">Återkalla behörighet</a></li>";
	}
	if ($ret) return "<ul data-role='listview' data-inset='true' data-split-icon='delete' data-split-theme='c'>$ret</ul>";
	else return "<p><i>Inga behörigheter har tilldelats än. Använd alternativen nedan för att tilldela behörigheter.</i></p>";
}

/**
 * Show contact data for user
 * @param User $u
 * @return string Formatted HTML code
 */
function contactData(User $u) {
    if ($u->id) {
        if ($u->name) {
            $ret = htmlspecialchars($u->name) . "<br>";
            $ret .= "&phone;: " . ($u->phone ? htmlspecialchars($u->phone) : "<b>Inget telefonnummer har angetts.</b>") . "<br>";
            $ret .= "<b>@</b>: " . ($u->mail ? htmlspecialchars($u->mail) : "<b>Ingen epostadress har angetts.</b>");
            return $ret;
        } else {
            return "Medlem med nummer ".$u->id."<br><b>OBS: Kontaktpersonen måste logga in och ange sina kontaktuppgifter!</b>";
        }
    } else {
        return "<i>Ingen kontaktperson har valts.</i>";
    }
}

switch ($_REQUEST['action']) {
    case "new":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $cat = $section->createCategory();
            if ($_SESSION['catId']) $cat->parentId = $_SESSION['catId'];
            $_SESSION['catId'] = $cat->id;
        }
        break;
        
    case "ajaxSetCatProp":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            switch ($_REQUEST['name']) {
                case "caption":
                case "parentId":
                case "prebookMsg":
                case "postbookMsg":
                case "bufferAfterBooking":
                    header("Content-Type: application/json");
                    if ($_REQUEST['value']=="NULL") $cat->{$_REQUEST['name']} = null;
                    else $cat->{$_REQUEST['name']} = $_REQUEST['value'];
                    die(json_encode(["status"=>"OK"]));
                    break;
            }
        }
        break;
        
    case "ajaxSetImage":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            header("Content-Type: application/json");
            if(json_encode( $cat->setImage($_FILES['image'] ) )) die(json_encode(["status"=>"OK"]));
            else die(json_encode(["error"=>"Kan inte spara bilden. Bara jpg- och png-filer accepteras."]));
        }
        
    case "ajaxSetContactUser":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            header("Content-Type: application/json");
            $cuser = new User($_REQUEST['id']);
            $cat->contactUserId = $cuser->id;
            die(json_encode([ "status"=>"OK", "html"=>contactData($cuser) ]));
        }
        
    case "ajaxSetAccess":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
        	switch ($_GET['id']) {
            	case "accessExternal":
            	case "accessMember":
            	case "accessLocal":
            	    $cat->{$_GET['id']} = $_GET['access'];
            	    break;
            	default:
            	    $cat->setAccess($_GET['id'], $_GET['access']);
        	}
        	die(displayCatAccess($cat, $cfg['catAccessLevels']));
        }
    	break;
    	
    case "ajaxToggleQuestion":
        // empty -> show -> show+required -> empty
        // inherited -> show+required -> inherited
        // inherited+required -> show -> inherited+required
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $questions = $cat->getQuestions();
            if (isset($questions[$_REQUEST['id']])) {
                if ($questions[$_REQUEST['id']]->inherited) {
                    $cat->addQuestion($_REQUEST['id']);
                } elseif ($questions[$_REQUEST['id']]->required) {
                    $cat->removeQuestion($_REQUEST['id']);
                } else {
                    $cat->addQuestion($_REQUEST['id'], TRUE);
                }
            } else {
                $cat->addQuestion($_REQUEST['id']);
            }
            header("Content-Type: application/json");
            die(json_encode(['html'=>showQuestions($cat, $section)]));
        }
        
    case "deleteCat":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $cat->delete();
            header("Location: index.php");
        }
        die();
}

unset ($_SESSION['itemId']);

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Kategori " . htmlspecialchars($cat->caption)) ?>
</head>


<body>
<div data-role="page" id="page-admin-category">
	<?= head(htmlspecialchars($cat->caption), $currentUser) ?>
	<div role="main" class="ui-content">

	<div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-category" class="ui-content">
		<p id="msg-page-admin-category"><?= $message ?></p>
		<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
	</div>
	
	<div class="saved-indicator" id="cat-saved-indicator">Sparad</div>

	<div data-role="collapsibleset" data-inset="false">
		<?php if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) { ?>
		<div data-role="collapsible">
			<h2>Allmänt</h2>

    		<p><?php
    		foreach ($cat->getPath() as $p) {
    		    if ($p['id']) echo " &rarr; ";
    		    echo "<a data-transition='slide' data-direction='reverse' href='" . ($p['id'] ? "category.php?catId={$p['id']}" : "index.php") . "'>" . htmlspecialchars($p['caption']) . "</a>";
    		}?></p>

			<div class="ui-field-contain">
				<label for="cat-caption" class="required">Rubrik:</label>
				<input name="caption" class="ajax-input" id="cat-caption" placeholder="Namn till kategorin" value="<?= htmlspecialchars($cat->caption) ?>">
			</div>

			<div class="ui-field-contain">
				<label for="cat-parentID">Överordnad kategori:</label>
				<select id="cat-parentId" name="parentID">
					<?php
					echo "<option value='NULL'" . ((($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) || is_null($cat->parentId)) ? : " disabled='true'") . ">- ingen -</option>";
					foreach ($section->getMainCategories() as $child) {
					    if ($child->id != $cat->id) showCatTree($child, $cat, $currentUser);
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
			<hr>
			
			<label for="cat-prebookMsg">Text som ska visas när användare vill boka resurser från denna kategori:</label>
				<textarea name="prebookMsg" class="ajax-input" id="cat-prebookMsg" placeholder="Exempel: Kom ihåg att ta höjd för torkningstiden efter användningen!"><?= htmlspecialchars($cat->prebookMsg) ?></textarea>
			<hr>

			<label for="cat-postbookMsg">Text som ska skickas med bokningsbekräftelsen:</label>
				<textarea name="postbookMsg" class="ajax-input" id="cat-postbookMsg" placeholder="Exempel: Uthämtning lör-sön mellan 11 och 16."><?= htmlspecialchars($cat->postbookMsg) ?></textarea>
			<hr>
            
            <div class="ui-field-contain">
                <label for="cat-bufferAfterBooking">Buffertid i timmar mellan bokningar (ärvs inte av överordnad kategori!):</label>
                <input name="bufferAfterBooking" type="number" min="0" class="ajax-input" id="cat-bufferAfterBooking" placeholder="Buffertid mellan bokingnar" value="<?= $cat->bufferAfterBooking ?>">
            </div>
			
			<h3>Kontaktperson</h3>
			<div id="cat-contact-data"><?= contactData($cat->contactUser()) ?></div>
				<input id="cat-contact-autocomplete-input" data-type="search" placeholder="Välj kontaktperson...">
				<ul id="cat-contact-autocomplete" data-role="listview" data-filter="true" data-input="#cat-contact-autocomplete-input" data-inset="true"></ul>

			<button class="ui-btn ui-btn-c" id="delete-cat">Radera kategorin</button>

			<br>
		</div>
		<?php } ?>

		<?php if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) { ?>
		<div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="access" ? "false" : "true" ?>">
			<h2>Behörigheter</h2>
			<div data-role="collapsible" data-inset="true" data-mini="true" data-collapsed-icon="info">
				<h4>Hur gör jag?</h4>
				<p>Här bestäms vem som får se och boka resurserna i kategorin <i><?= htmlspecialchars($cat->caption) ?></i>. Först väljer du vem som ska få behörighet. Sedan väljer du önskad behörighetsnivå.</p>
				<p>Återkalla behörigheter genom att klicka på den röda knappen höger om den.</p>
			</div>
			
			<fieldset data-role="controlgroup" id="cat-access-ids" data-mini="true">
				<p>1. Välj grupp eller enskild medlem:</p>
				<label><input type="radio" class="cat-access-id" name="id" value="accessExternal">Icke-medlemmar </label>
				<label><input type="radio" class="cat-access-id" name="id" value="accessMember">Medlem i valfri lokalavdelning</label>
				<label><input type="radio" class="cat-access-id" name="id" value="accessLocal">Lokal medlem</label>
				<input id="cat-adm-autocomplete-input" data-type="search" placeholder="Välj enskild medlem...">
				<ul id="cat-adm-autocomplete" data-role="listview" data-filter="true" data-input="#cat-adm-autocomplete-input" data-inset="true"></ul>
			</fieldset>
			
			<fieldset data-role="controlgroup" data-mini="true" id="cat-access-levels" style="display:none;">
				<p>2. Välj behörighetsnivå här:</p>
				<label>
					<input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_READASK ?>">
					<?= $cfg['catAccessLevels'][FFBoka::ACCESS_READASK] ?>
				</label>
				<label>
					<input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_PREBOOK ?>">
					<?= $cfg['catAccessLevels'][FFBoka::ACCESS_PREBOOK] ?>
				</label>
				<label>
					<input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_BOOK ?>">
					<?= $cfg['catAccessLevels'][FFBoka::ACCESS_BOOK] ?>
				</label>
				<label>
					<input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_CONFIRM ?>">
					<?= $cfg['catAccessLevels'][FFBoka::ACCESS_CONFIRM] ?>
				</label>
				<label>
					<input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_CATADMIN ?>">
					<?= $cfg['catAccessLevels'][FFBoka::ACCESS_CATADMIN] ?>
				</label>
			</fieldset>

			<p>Tilldelade behörigheter:</p>
			<div id="assigned-cat-access"><?= displayCatAccess($cat, $cfg['catAccessLevels']) ?></div>

			<br>
		</div>
		

		<div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="access" ? "false" : "true" ?>">
			<h2>Bokningsfrågor</h2>
			<p><small>Här ställer du in särskilda frågor som ska visas vid bokning av resurser i denna kategorin. Aktivera/avaktivera och byt mellan valfritt och obligatoriskt genom att klicka på frågorna. Valda frågor visas även vid bokning i underordnade kategorier.</small></p>
			<ul data-role="listview" data-inset="true" id="cat-questions"><?php echo showQuestions($cat, $section); ?></ul>
		</div>
		<?php } ?>
		

		<?php
		$children = $cat->children(); ?>
		<div data-role="collapsible" data-collapsed="<?= $cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN ? "true" : "false" ?>">
			<h2>Underkategorier</h2>
			<ul data-role="listview"><?php
				foreach ($children as $child) {
				    if ($child->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    					echo "<li><a data-transition='slide' href='category.php?catId={$child->id}'>" .
    						embedImage($child->thumb) .
    						"<h3>" . htmlspecialchars($child->caption) . "</h3>";
    					$subcats = array();
    					foreach ($child->children() as $grandchild) $subcats[] = htmlspecialchars($grandchild->caption);
    					if ($subcats) echo "<p>" . implode(", ", $subcats) . "</p>";
    					echo "<span class='ui-li-count'>{$child->itemCount}</span></a></li>\n";
				    }
				}
				if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) echo "<li><a data-transition='slide' href='category.php?action=new'>Lägg till underkategori</a></li>";
				?>
			</ul>
			<br>
		</div>

		<?php
		if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
		$items = $cat->items(); ?>
		<div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']!="items" ? "true" : "false" ?>">
			<h2>Resurser (<?= count($items) ?>)</h2>
			<ul data-role="listview">
				<?php
				foreach ($items as $item) {
					echo "<li" . ($item->active ? "" : " class='inactive'") . "><a data-transition='slide' href='item.php?itemId={$item->id}'>" .
						embedImage($item->getFeaturedImage()->thumb) .
						"<h3>" . htmlspecialchars($item->caption) . "</h3>" .
						"<p>" . ($item->active ? htmlspecialchars($item->description) : "(inaktiv)") . "</p>" .
						"</a></li>\n";
				}
				if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) echo "<li><a data-transition='slide' href='item.php?action=newItem'>Lägg till resurs</a></li>"; ?>
			</ul>
			<br>
		</div>
		<?php } ?>
		
	</div><!--/collapsibleset-->
	</div><!--/main-->

</div><!--/page-->
</body>
</html>
