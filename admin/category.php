<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Section;

session_start();
require(__DIR__ . "/../inc/common.php");
global $cfg;
$message = "";

if (!isset($_SESSION['authenticatedUser'])) {
    header("Location: {$cfg['url']}index.php?action=sessionExpired&redirect=" . urlencode("admin/category.php"));
    die();
}
if (!isset($_SESSION['sectionId'])) {
    header("Location: {$cfg['url']}");
    die();
} 

if (isset($_REQUEST['catId'])) $_SESSION['catId'] = $_REQUEST['catId'];
$cat = new Category(isset($_SESSION['catId']) ? $_SESSION['catId'] : 0);
$currentUser = new User($_SESSION['authenticatedUser']);
$section = new Section($_SESSION['sectionId']);

// Check access permissions.
if (!$cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för {$cat->caption}"));
    die();
}

/**
 * Echoes a category tree as "select" options
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
 * Returns html code for displaying the category access as a list.
 * 
 * @param Category $cat
 * @param string[] $accLevels Access levels from $cfg['catAccessLevels']
 * @param bool $inherited If set to true, the list entries are marked as inherited privileges and without delete button (i.e. readonly).
 * @return string Formatted HTML <li> list
 */
function displayCatAccess(Category $cat, array $accLevels, bool $inherited=false) : string {
    $ret = "";
    foreach ($cat->getAccessAll() as $key=>$access) {
        if ($key==="accessExternal") {
            if ($access['inherited']) $ret .= "<li class='wrap'>Icke-medlemmar (ärvd behörighet)<p>{$accLevels[$access['level']]}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Icke-medlemmar<p>{$accLevels[$access['level']]}</p></a><a href='#' onclick=\"unsetAccess('accessExternal');\">Återkalla behörighet</a></li>";
        } elseif ($key==="accessMember") {
            if ($access['inherited']) $ret .= "<li class='wrap'>Medlem i valfri lokalavdelning (ärvd behörighet)<p>{$accLevels[$access['level']]}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Medlem i valfri lokalavdelning<p>{$accLevels[$access['level']]}</p></a><a href='#' onclick=\"unsetAccess('accessMember');\">Återkalla behörighet</a></li>";
        } elseif ($key==="accessLocal") {
            if ($access['inherited']) $ret .= "<li class='wrap'>Lokal medlem (ärvd behörighet)<p>{$accLevels[$access['level']]}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Lokal medlem<p>{$accLevels[$access['level']]}</p></a><a href='#' onclick=\"unsetAccess('accessLocal');\">Återkalla behörighet</a></li>";
        } elseif (is_numeric($key)) {
            if ($access['inherited']) $ret .= "<li class='wrap'>$key " . ($access['name'] ? htmlspecialchars($access['name']) : "(ingen persondata tillgänglig)") . " (ärvd behörighet)<p>{$accLevels[$access['level']]}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>$key " . ($access['name'] ? htmlspecialchars($access['name']) : "(ingen persondata tillgänglig)") . "<p>{$accLevels[$access['level']]}</p></a><a href='#' onclick=\"unsetAccess('$key');\">Återkalla behörighet</a></li>";    
        } else {
            if ($access['inherited']) $ret .= "<li class='wrap'>$key (ärvd behörighet)<p>{$accLevels[$access['level']]}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>$key<p>{$accLevels[$access['level']]}</p></a><a href='#' onclick=\"unsetAccess('$key');\">Återkalla behörighet</a></li>";    
        }
    }
    if ($ret) return "<ul data-role='listview' data-inset='true' data-split-icon='delete' data-split-theme='c'>$ret</ul>";
    else return "<p><i>Inga behörigheter har tilldelats än. Använd alternativen ovan för att tilldela behörigheter.</i></p>";
}


/**
 * Creates HTML code which shows all attachments for $cat. Attachments defined at mother categories are shown as non-editable items.
 *
 * @param Category $cat The category for which to show the attachments
 * @param bool $inherited Indicates that the results shall be shown as non-editable, inherited items.
 * @return string HTML code
 */
function showAttachments(Category $cat, bool $inherited=false) : string {
    $ret = "";
    if ($parent = $cat->parent()) $ret .= showAttachments($parent, true);
    $files = $cat->files();
    if (count($files)) {
        foreach ($files as $file) {
            if ($inherited) $ret .= "<p class='ui-body ui-body-a'><b>" . htmlspecialchars($file->caption) . "</b><br><i>ärvt från kategori <a href='?catId={$cat->id}'>{$cat->caption}</a></i><br>" . ($file->displayLink ? "• Visa länk<br>" : "") . ($file->attachFile ? "• Skicka med filen<br>" : "") . "</p>";
            else $ret .= "<div class='ui-body ui-body-a'>
            <button style='position:absolute; right:0px; top:0px;' title='Radera bilagan' onClick='catFileDelete({$file->fileId})' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-delete' id='cat-file-delete-{$file->fileId}'>Radera bilaga</button>
            <h3><a id='cat-file-header-{$file->fileId}' href='../attment.php?fileId={$file->fileId}' data-ajax='false'>" . htmlspecialchars($file->caption) . "</a></h3>
            <p>Filnamn: " . htmlspecialchars($file->filename) . "</p>
            <div class='ui-field-contain'>
                <label for='cat-file-caption-{$file->fileId}'>Rubrik:</label>
                <input id='cat-file-caption-{$file->fileId}' onInput=\"clearTimeout(toutSetValue); toutSetValue = setTimeout(setCatFileProp, 1000, {$file->fileId}, 'caption', this.value);\" placeholder='Rubrik' value='" . htmlspecialchars($file->caption) . "'>
            </div>
            <fieldset data-role='controlgroup' data-mini='true'>
                <label><input onChange=\"setCatFileProp({$file->fileId}, 'displayLink', this.checked ? 1 : 0);\" type='checkbox'" . ($file->displayLink==1 ? " checked" : "") . "> Visa länk till fil i bokningsflödet</label>
                <label><input onChange=\"setCatFileProp({$file->fileId}, 'attachFile', this.checked ? 1 : 0);\" type='checkbox'" . ($file->attachFile==1 ? " checked" : "") . "> Skicka med som fil i bokningsbekräftelsen</label>
            </fieldset>\n</div>\n";
        }
    }
    if (!$ret && !$inherited) {
        $ret = "<p><i>Här laddar du upp filer som du vill skicka med vid bokning av resurser från den här kategorin eller underordnade kategorier.</i></p>";
    }
    return $ret;
}

if (!isset($_REQUEST['expand'])) $_REQUEST['expand']="";

if (isset($_REQUEST['action'])) {
switch ($_REQUEST['action']) {
    case "help":
        echo "
<p>Alla resurser är organiserade i kategorier. På den här sidan kan du göra inställningarna för den aktuella kategorin. Det beror på din behörighetsnivå vilka avsnitt du kan se på sidan.</p>

<h3>Allmänt</h3>
<p>Här gör du de flesta inställningar som ska gälla för hela kategorin. Inställningarna sparas direkt, du behöver inte trycka på någon Spara-knapp. Inställningar du gör här gäller (med några få undantag) även för underordnade kategorier. Längst upp visas var kategorin sorteras in i strukturen, med klickbara länkar till överordnade element. Det är användbart för att snabbt navigera upp i hirarkin.</p>
<dl>
    <dt>Rubrik</dt><dd>Välj en rubrik som är tydlig och kort, t.ex. <tt>Kanadensare</tt> eller <tt>Kajaker</tt>.</dd>
    <dt>Överordnad kategori</dt><dd>Här kan du flytta hela kategorin (inklusive alla resurser och eventuella underordnade kategorier) till ett annat ställe i hirarkin. Välj <tt>- ingen -</tt> för att ställa in kategorin som huvudkategori.</dd>
    <dt>Bild</dt><dd>I listor kommer kategorin ofta att visas tillsammans med en liten bild. Här kan du ladda upp den.</dd>
    <dt>Text vid bokning</dt><dd>Denna text kommer att visas i samband med kategorin medans man bokar resurser. Det är alltså information som gäller för alla resurser i den här kategorin, samt för underordnade kategorier, och som du vill att den visas i bokningsflödet även om användaren inte öppnar detaljsidan för resurserna. Det kan t.ex. vara särskilda bokningsregler som gäller, eller en påminnelse om att även boka relaterad utrustning som inte ingår per automatik.</dd>
    <dt>Text i bokningsbekräftelse</dt><dd>Som ovan, men visas istället i meddelandet som användaren får som bokningsbekräftelse. Det är ett bra ställe för att nämna var nyckeln ska hämtas, regler kring slutstädning mm.</dd>
    <dt>Bufferttid</dt><dd>Ibland behöver man lite tid mellan bokningarna, t.ex. för städning av stugor eller översyn av utrustning. Här kan du ställa in antalet timmar före/efter befintliga bokningar som är spärrade för nya bokningar. <i>OBS: Denna inställning gäller bara för resurser i den här kategorin och ärvs inte till underordnade kategorier!</i></dd>
    <dt>Bokningsmeddelanden</dt><dd>När nya bokningar görs i kategorin skickas normalt ett meddelande till bokningsansvariga (se nedan), som dock kan välja att stänga av dessa meddelanden i sina personliga inställningar. Om du vill att bokningsmeddelanden dessutom ska skickas till en eller flera funktionella epostadresser kan du lägga in dem här.</dd>  
</dl>

<h3>Kontaktuppgifter</h3>
<p>Kontaktuppgifterna skickas alltid med bokningsbekräftelsen (ifall några anges), så att användarna kan vända sig till någon vid problem. Du kan även välja att visa kontaktuppgifterna redan i bokningsflödet.</p>
<p>Du kan antingen sätta namn, telefon och epost manuellt, eller välja en medlem som kontaktperson. Om du väljer en medlem så används de vid varje tidpunkt gällande kontaktuppgifter till denne, dvs eventuella ändringar som medlemmen gör i sina kontaktuppgifter återspeglas här. Om inget anges här, men kategorin har en överordnad kategori så ärvs den överordnade kategorins kontaktuppgifter.</p> 

<h3>Underkategorier</h3>
<p>Vid komplex verksamhet kan det vara intressant att organisera resurserna på flera nivåer, t.ex. med <tt>Kanoter</tt> som huvudkategori och <tt>Kajaker</tt> och <tt>Kanadensare</tt> som underordnade kategorier. Du kan skapa så många nivåer som du behöver, eller bara använda den översta nivån.</p>

<h3>Resurser</h3>
<p>Här ser du alla resurser som finns i kategorin, med titel och grundläggande information. Klicka på resurserna för att ändra.</p>

<h3>Behörigheter</h3>
<p>Här bestäms vem som får se och boka resurserna i kategorin, samt vem som administrerar. Först väljer du vem som ska få behörighet. Sedan väljer du önskad behörighetsnivå.</p>
<p>Återkalla en behörighet genom att klicka på den röda knappen höger om den.</p>
<p>Behörigheter ärvs, dvs de gäller även för underordnade kategorier.</p>
<p>Du kan tilldela behörigheter för:</p>
<ul>
    <li>Icke-medlemmar (dvs externa användare som bokar som gäst utan inloggning)</li>
    <li>Medlemmar i valfri lokalavdelning</li>
    <li>Lokala medlemmar (dvs medlemmar som tillhör samma lokalavdelning som kategorin)</li>
    <li>Lokala medlemmar med ett visst uppdrag</li>
    <li>Enskilda medlemmar genom att ange medlemsnummer</li>
</ul>
<p>Du kan välja mellan följande behörighetsnivåer:</p>
<ol>
    <li>" . $cfg['catAccessLevels'][FFBoka::ACCESS_READASK] . ": Användbar t.ex. för stugor där du av säkerhetsskäl inte vill visa för allmänheten när de är lediga</li>
    <li>" . $cfg['catAccessLevels'][FFBoka::ACCESS_PREBOOK] . ": Användbar om du har en bokningsansvarig som ska ha sista ordet och ska bekräfta bokningar</li>
    <li>" . $cfg['catAccessLevels'][FFBoka::ACCESS_BOOK] . ": Ingen bekräftelse från bokningsansvarig behövs</li>
    <li>" . $cfg['catAccessLevels'][FFBoka::ACCESS_CONFIRM] . "</li>
    <li>" . $cfg['catAccessLevels'][FFBoka::ACCESS_CATADMIN] . ". Som ovan, men kan även ändra inställningarna för kategorin och lägga till resurser.</li>
</ol>
<p>Nivå 4 eller 5 kan bara väljas för enskilda medlemmar och medlemmar med specifika uppdrag. När du tilldelar behörighet på dessa nivåer till enskilda medlemmar skickas ett meddelande till användaren så att hen är med på banan.</p>

<h3>Bokningsfrågor</h3>
<p>Om du vill hämta in kompletterande information vid bokning av resurser i den här kategorin använder du bokningsfrågor. Frågorna måste först läggas upp i din lokalavdelning, så prata med någon administratör om du saknar någon fråga.</p>
<p>Aktivera/avaktivera och byt mellan valfritt och obligatoriskt genom att klicka på frågorna. Valda frågor visas även vid bokning i underordnade kategorier, men kan där ändras till obligatoriska respektive valfria.</p>

<h3>Bilagor</h3>
<p>Här laddar du upp filer som du vill skicka med bokningsbekräftelser och/eller visa som länk i bokningsflödet. Det kan vara sådant som städrutiner, en särskild blankett du vill att användaren fyller i, körinstruktioner mm. Du kan ladda upp filer av typerna " . implode(", ", array_keys($cfg['allowedAttTypes'])) . ". Filstorleken får maximalt vara " . FFBoka::formatBytes($cfg['uploadMaxFileSize']). ". Rubriken är den text som visas för användaren på skärmen.</p>

<h3>Påminnelser</h3>
<p>Du kan ställa in att användarna får ett meddelande ett visst antal timmar före eller efter bokningsstart. Använd ett positivt antal timmar för påminnelser före bokningsstart, och negativa tal för påminnelser efter bokningsstart.</p>
";
        die();
    case "new":
        if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) {
            $cat = $section->createCategory();
            if ($_SESSION['catId']) $cat->parentId = $_SESSION['catId'];
            $_SESSION['catId'] = $cat->id;
        }
        break;
        
    case "ajaxSetCatProp":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        switch ($_REQUEST['name']) {
            case "sendAlertTo":
            case "contactUserId":
            case "contactName":
            case "contactPhone":
            case "contactMail":
            case "showContactWhenBooking":
            case "caption":
            case "parentId":
            case "prebookMsg":
            case "postbookMsg":
            case "bufferAfterBooking":
                if ($_REQUEST['name']=="contactMail") {
                    // check if this is a valid email address
                    if ($_REQUEST['value']!=="" && !filter_var($_REQUEST['value'], FILTER_VALIDATE_EMAIL)) {
                        header("Content-Type: application/json");
                        die(json_encode([ "status"=>"contactMailInvalid" ]));
                    }
                }
                if ($_REQUEST['value']=="NULL") $cat->{$_REQUEST['name']} = null;
                else $cat->{$_REQUEST['name']} = $_REQUEST['value'];
                // Yes, continue. No break here.
            case "onlyGetContactData":
                header("Content-Type: application/json");
                die(json_encode([
                    "status" => "OK",
                    "catId" => $cat->id,
                    "contactType" => $cat->contactType,
                    "contactData" => $cat->contactData(),
                    "contactName" => $cat->contactName,
                    "contactPhone" => $cat->contactPhone,
                    "contactMail" => $cat->contactMail
                ]));
            default:
                logger("Trying to set unknown category property via ajax.", "ERROR");
                die(json_encode([ "status"=>"error", "error"=>"Unknown field name." ]));
        }

    case "ajaxAddAlert":
        header("Content-Type: application/json");
        // Validate input
        if (filter_var($_GET['sendAlertTo1'], FILTER_VALIDATE_EMAIL) === false) {
            die(json_encode([ "status"=>"error", "error"=>"{$_GET['sendAlertTo1']} är ingen giltig epostadress." ]));
        }
        if ($alerts = $cat->sendAlertTo) $alerts = explode(", ", $alerts);
        else $alerts = [];
        $alerts[] = $_GET['sendAlertTo1'];
        $cat->sendAlertTo = implode(", ", $alerts);
        die(json_encode([ "status" => "OK" ]));

    case "ajaxDeleteAlert":
        header("Content-Type: application/json");
        $alerts = explode(", ", $cat->sendAlertTo);
        if (($key = array_search($_GET['sendAlertTo1'], $alerts)) !== false) {
            unset($alerts[$key]);
            $cat->sendAlertTo = implode(", ", $alerts);
            die(json_encode([ "status"=>"OK" ]));
        }
        logger(__METHOD__." Tried to remove non-existent sendAlertTo-address.");
        die(json_encode([ "status"=>"error" ]));

    case "ajaxSetImage":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        header("Content-Type: application/json");
        $ret = $cat->setImage($_FILES['image']);
        if($ret===TRUE) die(json_encode( ["status"=>"OK", "id"=>$cat->id] ));
        else die(json_encode( ["error"=>$ret] ));
        
    case "ajaxSetAccess":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        switch ($_GET['id']) {
        case "accessExternal":
        case "accessMember":
        case "accessLocal":
            $cat->{$_GET['id']} = $_GET['access']==="NULL" ? NULL : $_GET['access'];
            break;
        default:
            $cat->setAccess($_GET['id'], $_GET['access']==="NULL" ? NULL : $_GET['access']);
            if (isset($_GET['access']) && $_GET['access'] >= FFBoka::ACCESS_CONFIRM && is_numeric($_GET['id'])) {
                // New admin added. Send notification if not same as current user and if not an assignment group
                $adm = new User($_GET['id']);
                if ($_GET['id'] != $currentUser->id && $adm->mail) {
                    $FF->sendMail(
                        $adm->mail, // to
                        "Du är nu bokningsansvarig", // subject
                        "notify_new_admin", // template
                        array( // replace
                            "{{name}}"=>$adm->name,
                            "{{role}}"=>"bokningsansvarig för kategorin {$cat->caption}",
                            "{{link}}"=>$cfg['url'],
                            "{{sectionName}}"=>$section->name,
                            "{{superadmin-name}}"=>$currentUser->name,
                            "{{superadmin-mail}}"=>$currentUser->mail,
                            "{{superadmin-phone}}"=>$currentUser->phone
                        ),
                        [], // attachments
                        $cfg['mail']
                    );
                } elseif ($_GET['id'] != $currentUser->id) $message = "OBS! Vi har inte någon epostadress till denna användare och kan inte meddela hen om den nya rollen. Därför ska du informera hen på annat sätt. Se gärna också till att hen loggar in och lägger upp sin epostadress för att kunna få meddelanden om nya bokningar.";
            }
        }
        die(json_encode([ "html"=>displayCatAccess($cat, $cfg['catAccessLevels']), "message"=>$message ]));
        
    case "ajaxToggleQuestion":
        // empty -> show -> show+required -> empty
        // inherited -> show+required -> inherited
        // inherited+required -> show -> inherited+required
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
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
        
    case "ajaxDeleteCat":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        header("Content-Type: application/json");
        if ($cat->delete()) {
            die(json_encode(['status'=>'OK']));
        } else {
            die(json_encode(['status'=>'error']));
        }
        
    case "ajaxAddFile":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        header("Content-Type: application/json");
        try {
            $cat->addFile($_FILES['file'], $cfg['allowedAttTypes'], $cfg['uploadMaxFileSize']);            
        } catch (Exception $e) {
            die(json_encode([ 'status'=>'error', 'error'=>$e->getMessage() ]));
        }
        die(json_encode([ "status"=>"OK", "html"=>showAttachments($cat) ]));

    case "ajaxSetCatFileProp":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        header("Content-Type: application/json");
        if ($cat->setFileProp($_REQUEST['fileId'], $_REQUEST['name'], $_REQUEST['value']) === FALSE) {
            die(json_encode([ "status"=>"error", "error"=>"Kunde inte spara." ]));
        }
        die(json_encode([ "status"=>"OK" ]));
        
    case "ajaxDeleteFile":
        if ($cat->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN) {
            http_response_code(403);
            die();
        }
        $cat->removeFile($_REQUEST['fileId']);
        header("Content-Type: application/json");
        die(json_encode([ "status"=>"OK", "html"=>showAttachments($cat) ]));

    case "ajaxGetReminders":
        if ( $cat->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
            http_response_code( 403 );
            die();
        }
        $ret = array();
        // Collect reminders of this category
        foreach( $cat->reminders() as $reminder ) {
            $ret[] = "<li data-icon='edit'><a href='#' onclick='editCatReminder({$reminder->id});'>" . $FF::formatReminderOffset( $reminder->offset ) . "<p>\"" . htmlspecialchars( $reminder->message ) . "\"</p></a></li>";
        }
        // Add inherited reminders
        while ( !is_null( $cat = $cat->parent() ) ) {
            foreach ( $cat->reminders() as $reminder ) {
                $ret[] = "<li><strong>" . $FF::formatReminderOffset( $reminder->offset ) . "</strong><p>\"" . htmlspecialchars( $reminder->message ) . "\"<br><i><small>ärvt från kategori <a href='?catId={$cat->id}'>{$cat->caption}</p></a></small></i></li>";
            }
        }
        $ret[] = "<li data-icon='plus'><a href='#' onclick='editCatReminder(0);'>Skapa ny påminnelse</a></li>";
        if ( count($ret)==1 ) $ret[] = "<li style='white-space:normal;'><i><small>Här kan du ställa in påminnelser som ska skickas till användaren ett antal timmar före eller efter början på en bokning. Ett klassiskt användningsfall är att skicka ut passerkod strax innan bokningen.</small></i></li>";
        die( implode( "", $ret ) );

    case "ajaxGetReminder":
        header("Content-Type: application/json");
        if ($_GET['id']==0) die( json_encode( [ "id"=>0, "message"=>"Ny påminnelse", "offset"=>0 ] ));
        die( json_encode( $FF->catReminder( $_GET['id'] ) ) );

    case "ajaxSaveReminder":
        if ( $cat->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
            http_response_code( 403 );
            die();
        }
        if ( $_GET['id'] ) $cat->editReminder( $_GET['id'], $_GET['offset'], $_GET['message'] );
        else $cat->addReminder( $_GET['offset'], $_GET['message'] );
        die("OK");
    }
}

unset ($_SESSION['itemId']);

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Kategori " . htmlspecialchars($cat->caption), $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-admin-category">
    <?= head(htmlspecialchars($cat->caption), $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">
    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-category" class="ui-content">
        <p id="msg-page-admin-category"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>
    
    <div data-role="popup" data-overlay-theme="b" id="popup-cat-reminders" class="ui-content">
        <h3>Påminnelse</h3>
        <input type="hidden" id="cat-reminders-id">
        <div class="ui-field-contain">
            <label for="cat-reminders-message">Meddelande</label>
            <textarea id="cat-reminders-message" placeholder="T.ex. koden till hänglåset är 12345."></textarea>
        </div>
        <div class="ui-field-contain">
            <label for="cat-reminders-offset">Timmar före (+) / efter (-) bokningsstart</label>
            <input type="number" min="-8760" max="8760" id="cat-reminders-offset" placeholder="antal timmar">
        </div>
        <button class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-delete" onclick="deleteCatReminder();">Radera</button>
        <button class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-check" onclick="saveCatReminder();">Spara</button>
    </div>

    <div class="saved-indicator" id="cat-saved-indicator">Sparad</div>

    <div data-role="collapsibleset" data-inset="false">
        <?php if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) { ?>
        <div data-role="collapsible" <?= $_GET['action']==="new" ? "data-collapsed='false'" : "" ?>>
            <h2>Allmänt</h2>

            <p id="cat-breadcrumb"><?php
            foreach ($cat->getPath() as $p) {
                if ($p['id']) echo " &rarr; ";
                echo "<a data-transition='slide' data-direction='reverse' href='" . ($p['id'] ? "category.php?catId={$p['id']}" : "index.php") . "'";
                if ($p['id']==$cat->id) echo " id='cat-breadcrumb-last'";
                echo ">" . htmlspecialchars($p['caption']) . "</a>";
            } ?>
            </p>

            <div class="ui-field-contain">
                <label for="cat-caption" class="required">Rubrik:</label>
                <input name="caption" id="cat-caption" placeholder="Namn till kategorin" value="<?= htmlspecialchars($cat->caption) ?>">
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
            <img src="../image.php?type=category&id=<?= $cat->id ?>&<?= time() ?>" id="cat-img-preview"<?= $cat->thumb ? "" : " style='display:none;'" ?>>
            <div class="ui-field-contain">
                <label for="file-cat-img">Ladda upp ny bild:</label>
                <input type="file" name="image" id="file-cat-img">
            </div>
            <hr>
            
            <label for="cat-prebookMsg">Text som ska visas när användare vill boka resurser från denna kategori:</label>
                <textarea name="prebookMsg" id="cat-prebookMsg" placeholder="Exempel: Kom ihåg att ta höjd för torkningstiden efter användningen!"><?= htmlspecialchars($cat->prebookMsg) ?></textarea>
            <hr>

            <label for="cat-postbookMsg">Text som ska skickas med bokningsbekräftelsen:</label>
                <textarea name="postbookMsg" id="cat-postbookMsg" placeholder="Exempel: Uthämtning lör-sön mellan 11 och 16."><?= htmlspecialchars($cat->postbookMsg) ?></textarea>
            <hr>
            
            <div class="ui-field-contain">
                <label for="cat-bufferAfterBooking">Buffertid i timmar mellan bokningar (ärvs inte av överordnad kategori):</label>
                <input name="bufferAfterBooking" type="number" min="0" id="cat-bufferAfterBooking" placeholder="Buffertid mellan bokingnar" value="<?= $cat->bufferAfterBooking ?>">
            </div>
            
            <label for="cat-sendAlertTo">Förutom till bokningsansvariga (se nedan under Behörigheter), skicka aviseringar om nya bokningar även till följande adresser:</label>
            <div data-tags-input-name="sendAlertTo" id="cat-sendAlertTo"><?= htmlspecialchars($cat->sendAlertTo) ?></div>

            <button class="ui-btn ui-btn-c" id="delete-cat">Radera kategorin</button>
        </div>

        <div data-role="collapsible" style="position:relative;">
            <h2>Kontaktuppgifter</h2>

            <div class="ui-field-contain">
                <label for="cat-contactName">Namn:</label>
                <input name="contactName" id="cat-contactName" placeholder="Namn" value="<?= htmlspecialchars($cat->contactName) ?>">
            </div>
            <div class="ui-field-contain">
                <label for="cat-contactPhone">Telefon:</label>
                <input name="contactPhone" id="cat-contactPhone" placeholder="Telefon" value="<?= htmlspecialchars($cat->contactPhone) ?>">
            </div>
            <div class="ui-field-contain">
                <label for="cat-contactMail">Epost:</label>
                <input name="contactMail" id="cat-contactMail" placeholder="Epost" value="<?= htmlspecialchars($cat->contactMail) ?>">
                <span style="font-size:small; color:red; <?= ($cat->contactMail=="" || filter_var($cat->contactMail, FILTER_VALIDATE_EMAIL)) ? "display:none;" : "" ?>" id="cat-contactMailInvalid">Epostadressen är felaktig och sparades inte.</span>
            </div>

            <input id="cat-contact-autocomplete-input" data-type="search" placeholder="Eller välj medlem som kontaktperson...">
            <ul id="cat-contact-autocomplete" data-role="listview" data-filter="true" data-input="#cat-contact-autocomplete-input" data-inset="true"></ul>
            
            <fieldset data-role="controlgroup" data-mini="true">
                <label><input type="checkbox" name="showContactWhenBooking" value="1" onClick="setCatProp('showContactWhenBooking', this.checked ? 1 : 0);" id="cat-showContactWhenBooking" <?= $cat->showContactWhenBooking ? "checked='true'" : "" ?>>Visa i bokningsflödet</label>
                <label><input type="checkbox" checked="true" disabled="true">Skicka med i bokningsbekräftelser</label>
            </fieldset>

            <div class="ui-body ui-body-a">
                <button id='btn-unset-contact-user' title='Ta bort kontaktperson' onClick="setCatProp('contactUserId', 'NULL');" style='position:absolute; right:1em; display:<?= is_null($cat->contactUserId) ? "none" : "block" ?>;' class='ui-btn ui-icon-delete ui-btn-icon-notext'>Ta bort</button>
                <p><i><span id="cat-contact-data-caption"></span></i></p>
                <p id="cat-contact-data"></p>
            </div>
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
                if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) echo "<li><a data-transition='slide' href='category.php?action=new&" . time() . "'>Lägg till underkategori</a></li>";
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


        <?php if ($cat->getAccess($currentUser) >= FFBoka::ACCESS_CATADMIN) { ?>
        <div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="access" ? "false" : "true" ?>">
            <h2>Behörigheter</h2>
            <p><i>Här styr du vem som kan boka, och vem som kan administrera kategorin.</i></p>
            <div id="cat-access-ids">
                <p>1. Välj grupp eller enskild medlem:</p>
                <fieldset data-role="controlgroup" data-mini="true">
                    <select class='cat-access-id' name='id'>
                        <option value=''>Välj grupp...</option>
                        <option value='accessExternal'>Icke-medlemmar</option>
                        <option value='accessMember'>Medlem i valfri LA</option>
                        <option value='accessLocal'>Lokal medlem</option>
                        <optgroup label="Lokal medlem med uppdrag:"></optgroup>
                        <?php
                        foreach ($FF->getAllAssignments() as $ass) {
                            echo "<option value='$ass'>" . htmlspecialchars($ass) . "</option>";
                        }
                        ?>
                    </select>
                    <input id="cat-adm-autocomplete-input" data-type="search" placeholder="... eller enskild medlem...">
                    <ul id="cat-adm-autocomplete" data-role="listview" data-filter="true" data-input="#cat-adm-autocomplete-input" data-inset="true"></ul>
                </fieldset>
            </div>
            
            <div id="cat-access-levels" style="display:none;">
                <p>2. Välj behörighetsnivå här:</p>
                <fieldset data-role="controlgroup" data-mini="true">
                    <label>
                        <input type="radio" class="cat-access-level" name="cat-access" value="<?= FFBoka::ACCESS_NONE ?>">
                        <?= $cfg['catAccessLevels'][FFBoka::ACCESS_NONE] ?>
                    </label>
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
                        <input type="radio" class="cat-access-level cat-access-level-adm" name="cat-access" value="<?= FFBoka::ACCESS_CONFIRM ?>">
                        <?= $cfg['catAccessLevels'][FFBoka::ACCESS_CONFIRM] ?>
                    </label>
                    <label>
                        <input type="radio" class="cat-access-level cat-access-level-adm" name="cat-access" value="<?= FFBoka::ACCESS_CATADMIN ?>">
                        <?= $cfg['catAccessLevels'][FFBoka::ACCESS_CATADMIN] ?>
                    </label>
                </fieldset>
            </div>

            <p>Tilldelade behörigheter:</p>
            <div id="assigned-cat-access"><?= displayCatAccess($cat, $cfg['catAccessLevels']) ?></div>

            <br>
        </div>
        

        <div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="questions" ? "false" : "true" ?>">
            <h2>Bokningsfrågor</h2>
            <?php
            if ($questions = showQuestions($cat, $section)) {
                echo "<p><i><small>Klicka på frågorna som ska visas i bokningsflödet. Frågor med blå bakgrund är aktiverade. Klicka en gång till för att göra frågan obligatorisk (<span class='required'></span>). Klicka en tredje gång för att avaktivera frågan.</small></i></p>";
                echo "<ul data-role='listview' data-inset='true' id='cat-questions'>$questions</ul>";
            } else {
                echo "<p><i>Inga frågor har lagts upp i din lokalavdelning än. Om du vill att någon fråga ska visas vid bokning i denna kategori, be LA-administratören att lägga upp frågan. Detta ska göras på LA-nivå. När detta har gjorts kommer upplagda frågor att dyka upp här så du kan välja ut de frågor som ska visas med din kategori.</i></p>";
            }
            ?>
        </div>
        

        <div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="files" ? "false" : "true" ?>">
            <h2>Bilagor</h2>
            <div id="cat-attachments"><?= showAttachments($cat) ?></div>
            <p class="ui-body ui-body-a">
                Ladda upp en ny bilaga:<br>
                <input type='file' id='cat-file-file'>
            </p>
        </div>


        <div data-role="collapsible" class="ui-filterable" data-collapsed="<?= $_REQUEST['expand']=="reminders" ? "false" : "true" ?>">
            <h2>Påminnelser</h2>
            <ul data-role="listview" id="cat-reminders">
            </ul>
        </div>
        <?php } ?>
        
    </div><!--/collapsibleset-->
    </div><!--/main-->

    <script src="../inc/tagging.js"></script>
</div><!--/page-->
</body>
</html>
