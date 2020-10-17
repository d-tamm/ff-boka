<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Question;
use FFBoka\Category;

/**
 * Get the list of admins for this section
 * @param \FFBoka\Section $section
 * @return string HTML string with <li> elements.
 */
function adminList($section, $currentUserId) {
    if (!$admins = $section->getAdmins()) $ret = "<li>Inga administratörer har lagts upp än.</li>";
    $ret = "";
    foreach ($admins as $admId) {
        $adm = new User($admId);
        $ret .= "<li><a href='#'><h2>" . ($adm->name ? htmlspecialchars($adm->name) : "(ingen persondata tillgänglig)") . "</h2><p>{$adm->id}</p></a><a href=\"javascript:removeAdmin({$adm->id}, $currentUserId, '" . htmlspecialchars($adm->name) . "');\">Ta bort</a></li>";
    }
    return $ret;
}


session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $FF;

// Set current section and user
if (isset($_GET['sectionId'])) $_SESSION['sectionId'] = $_GET['sectionId'];
if (!$_SESSION['sectionId']) {
    header("Location: {$cfg['url']}");
    die();
}
$section = new Section($_SESSION['sectionId']);

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för {$section->name}"));
    die();
}
// Only allow users which have at least some admin role in this section
$currentUser = new User($_SESSION['authenticatedUser']);
if (
    !$section->showFor($currentUser, FFBoka::ACCESS_CATADMIN) && 
    (!isset($_SESSION['assignments'][$section->id]) || !array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins']))
) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för {$section->name}"));
    die();
}
$userAccess = $section->getAccess($currentUser);
if (isset($_SESSION['assignments'][$section->id]) && is_array($_SESSION['assignments'][$section->id])) {
    if (array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins'])) $userAccess = FFBoka::ACCESS_SECTIONADMIN;
}

if (isset($_REQUEST['message'])) $message = $_REQUEST['message'];

/**
 * Find all categories in section which contain items but do not have an admin who at least can confirm bookings.
 * @param Category $cat
 * @param array[id=>caption] $catsWithoutAdmin Found categories are returned in this array
 */
function findCatsWithoutAdmin(Category $cat, &$catsWithoutAdmin) {
    $admins = $cat->admins(FFBoka::ACCESS_CONFIRM);
    if (count($admins)==0) {
        if (count($cat->items())>0) {
            $catsWithoutAdmin[$cat->id] = $cat->caption;
        } else {
            foreach ($cat->children() as $child) {
                findCatsWithoutAdmin($child, $catsWithoutAdmin);
            }
        }
    }
    
}
$catsWithoutAdmin = array();
if ($userAccess >= FFBoka::ACCESS_CATADMIN) {
    foreach ($section->getMainCategories() as $cat) {
        findCatsWithoutAdmin($cat, $catsWithoutAdmin);
    }
}

if (!isset($_REQUEST['expand'])) $_REQUEST['expand']="";

if (isset($_REQUEST['action'])) {
switch ($_REQUEST['action']) {
    case "help":
        $allAss = $cfg['sectionAdmins'];
        $oneAss = array_pop($allAss);
        echo "
<p>Den här sidan är navet för att administrera din lokalavdelnings bokningssystem. Beroende på din behörighetsnivå kommer du att se några eller alla av följande avsnitt.</p>
<p>Om du har behörighet att bekräfta bokningarna i någon kategori så visas knappen \"Öppna bokningsadmin\" längst upp på sidan. Bokningsadmin ger dig en överblick över alla bokningar.</p>
<h3>Kategorier</h3>
<p>Alla resurser är organiserade i kategorier. Du kan skapa så många kategorier som du behöver. De första kategorierna du skapar ligger som huvudkategori och ligger på översta nivå. Beroende på hur komplex verksamhet din lokalavdelning har kan du även skapa underordnade kategorier, men det gör du inte här, utan för det går du till den kategori där du vill skapa underkategorin. Det är upp till dig att bestämma hur många sådana nivåer du vill skapa.</p>
<h3>Bokningsfrågor</h3> 
<p>Ibland vill man hämta in kompletterande information när folk bokar resurser. Om man t.ex. hyr ut kanoter vill man kanske veta var kunden ska hämta dem. Eller så vill man att kunden bekräftar att hen har tagit del av uthyrningsreglerna. Det är här bokningsfrågorna kommer in. De definieras här på lokalavdelningsnivå och kan sedan aktiveras i valfri kategori. Det är också på kategorinivå du ställer in ifall en fråga är frivillig eller måste besvaras.</p>
<p>Det finns 4 olika frågetyper:</p>
<dl>
    <dt>Flera optioner, ett svar</dt><dd>Används för att visa en fråga med flera möjliga svar, där kunden ska välja exakt ett svar. Frågan skriver du in i fältet längst upp i dialogen, och svarsalternativen i rutan längst ner. Börja en ny rad för varje alternativ.</dd>
    <dt>Flera optioner, flera svar</dt><dd>Som ovan, men kunden kan kryssa i flera alternativ.</dd>
    <dt>Fritext</dt><dd>För att ge användaren möjlighet att lämna valfri text. Om du vill begränsa längden på texten skriver du in maxlängden i avsedd ruta.</dd>
    <dt>Numerisk</dt><dd>För att hämta in ett numeriskt svar. Här kan du även begränsa svarsmöjligheterna till ett intervall, t.ex. bara tillåta svar mellan 3 och 5.</dd>
</dl>
<p>Tipps: Om du vill att användaren bekräftar något (t.ex. bokningsreglerna) så kan du använda typen \"Flera optioner, flera svar\" och bara skriva in själva frågan (t.ex. \"Jag godkänner bokningsreglerna\"). Lämna rutan för svarsalternativen tom. Aktivera sedan svaret om obligatoriskt på kategorinivå.</p> 
<p>Glöm inte att spara dina ändringar.</p>
<h3>Administratörer</h3>
<p>Här ställer du in vilka medlemmar som ska ha behörighet att administrera resursbokningen i lokalavdelningen. Du kan alltid lägga till en ny admin genom att ange dess medlemsnummer. Om personen tidigare har loggat in i resursbokningen och lagt in sina kontaktuppgifter kan du även hitta hen genom att leta efter namnet istället.</p>
<p>" .($allAss ? implode(", ", $allAss) . " och " : "") . $oneAss . " har automatiskt administratörsbehörighet.</p>
<p>Om du bara vill tilldela behörigheter för att någon ska hantera enskilda kategorier eller bokningar gör du det under respektive kategori.</p>";
        die();
    case "ajaxFindUser":
        // This is also called from category.php
        header("Content-Type: application/json");
        die(json_encode($FF->findUser($_REQUEST['q'])));
        
    case "ajaxAddSectionAdmin":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
        if (!is_numeric($_REQUEST['id'])) {
            die(json_encode(["error"=>"Ogiltigt medlemsnummer {$_REQUEST['id']}."]));
        } elseif ($section->addAdmin($_REQUEST['id'])) {
            $adm = new User($_REQUEST['id']);
            if ($_REQUEST['id'] != $currentUser->id && $adm->mail) {
                $FF->queueMail(
                    $adm->mail, // to
                    "Du är nu administratör", // subject
                    "notify_new_admin", // template
                    array( // replace
                        "{{name}}"=>$adm->name,
                        "{{role}}"=>"lokalavdelnings-admin",
                        "{{link}}"=>$cfg['url'],
                        "{{sectionName}}"=>$section->name,
                        "{{superadmin-name}}"=>$currentUser->name,
                        "{{superadmin-mail}}"=>$currentUser->mail,
                        "{{superadmin-phone}}"=>$currentUser->phone
                    )
                );
            }
            die(json_encode([ "html"=>adminList($section, $currentUser->id), "currentUserId"=>$currentUser->id ]));
        } else {
            die(json_encode(["error"=>"Kunde inte lägga till administratören. Är den kanske redan med i listan?"]));
        }
        
    case "ajaxRemoveSectionAdmin":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) { header("HTTP/1.0 403 Forbidden"); die("Forbidden"); }
        header("Content-Type: application/json");
        if ($section->removeAdmin($_REQUEST['id'])) die(json_encode(["html"=>adminList($section, $currentUser->id)]));
        else die(json_encode(["error"=>"Kan inte ta bort LA-administratören."]));
        
    case "ajaxSetLocation":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) { header("HTTP/1.0 403 Forbidden"); die("Forbidden"); }
        header("Content-Type: application/json");
        //if (FALSE === ($section->lat = $_GET['lat'])) die(json_encode([ "error"=> "Kan inte spara."] ));
        $section->lat = $_GET['lat'];
        //if (FALSE === ($section->lon = $_GET['lon'])) die(json_encode([ "error"=> "Kan inte spara."] ));
        $section->lon = $_GET['lon'];
        die(json_encode([ "lat"=>$section->lat, "lon"=>$section->lon ]));

    case "ajaxGetQuestion":
        header("Content-Type: text/plain");
        $question = new Question($_REQUEST['id']);
        die(json_encode([
            "id"=>$question->id,
            "caption"=>$question->caption,
            "type"=>$question->type,
            "options"=>$question->options,
        ]));
        
    case "ajaxGetQuestions":
        header("Content-Type: text/plain");
        foreach ($section->questions() as $question) {
            echo "<li><a href='#' onClick='showQuestion({$question->id})'><span style='white-space:normal;'>" . htmlspecialchars($question->caption) . "</span><p style='white-space:normal;'>";
            echo $question->optionsReadable();
            echo "</p></a><a href='#' onClick='deleteQuestion({$question->id});'>Ta bort frågan</a></li>";
        }
        die();
        
    case "ajaxSaveQuestion":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
        if ($_REQUEST['id']==0) {
            $question = $section->addQuestion();
        } else {
            $question = new Question($_REQUEST['id']);
        }
        $question->caption = $_REQUEST['caption'];
        $question->type = $_REQUEST['type'];
        switch ($question->type) {
            case "radio":
            case "checkbox":
                $question->options = json_encode([ "choices" => explode("\n", $_REQUEST['choices']) ]); break;
            case "text":
                $question->options = json_encode([ "length"=>$_REQUEST['length'] ]); break;
            case "number":
                $question->options = json_encode([ "min"=>$_REQUEST['min'], "max"=>$_REQUEST['max'] ]); break;
        }
        die (json_encode("OK"));
        
    case "ajaxDeleteQuestion":
        if ($userAccess < FFBoka::ACCESS_SECTIONADMIN) die("Ajja bajja!");
        header("Content-Type: application/json");
        $question = new Question($_REQUEST['id']);
        die(json_encode($question->delete()));
}
}

// First admin login from a section? Give some hints on how to get started.
if (
    isset($_SESSION['assignments'][$section->id]) && 
    count(array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins']))>0 && 
    count($section->getAdmins())==0
) {
    $message = "Hej!<br><br>Vill du komma igång med din lokalavdelning? Första steget är att lägga till LA-administratörer.<br><br>Tipps: Använd medlemsnumret i sökrutan!";
}

unset($_SESSION['catId']);

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Admin ".$section->name, $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-admin-section">
    <?= head("LA " . htmlspecialchars($section->name), $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-section" class="ui-content">
        <p id="msg-page-admin-section"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <?php
    if ($section->getAccess($currentUser) < FFBoka::ACCESS_CATADMIN && array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins'])) {
        echo "<p class='ui-body ui-body-c'>Du har just nu tillgång till den här administrationssidan genom din roll som " . htmlspecialchars(array_shift(array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins']))) . ". Detta ger dig möjlighet att tilldela dig själv och andra administratörsrollen för lokalavdelningen. Det är bara administratörer som kan lägga upp t.ex. kategorier, så om du själv vill lägga upp saker så måste du först tilldela dig själv administratörsrollen i avsnittet <b>Administratörer</b> nedan.</p>";
    }
        
    if ($userAccess >= FFBoka::ACCESS_CATADMIN && count($catsWithoutAdmin)>0) {
        echo "<div class='ui-body ui-body-a'><p>Följande kategorier innehåller resurser men saknar administratör. Lägg till minst en administratör med behörighet att bekräfta bokningar.</p><ul data-role='listview' data-inset='true'>";
        foreach ($catsWithoutAdmin as $id=>$caption) {
            echo "<li><a href='category.php?catId=$id'>" . htmlspecialchars($caption) . "</a></li>";
        }
        echo "</ul></div>";
    }
    ?>

    <a class='ui-btn ui-btn-b ui-icon-calendar ui-btn-icon-left' href='#' onClick="openBookingAdmin('<?= $cfg['url'] ?>', <?= $section->id ?>);" data-ajax='false'>Öppna bokningsadmin</a>

    <div data-role="collapsibleset" data-inset="false">

        <div data-role="collapsible" data-collapsed="<?= isset($_REQUEST['expand']) ? "true" : "false" ?>">
            <h2>Kategorier</h2>
            <ul data-role="listview">
            <?php
            foreach ($section->getMainCategories() as $cat) {
                if ($cat->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
                    echo "<li><a data-transition='slide' href='category.php?catId={$cat->id}'>" .
                        htmlspecialchars($cat->caption) .
                        embedImage($cat->thumb);
                    $children = array();
                    foreach ($cat->children(TRUE) as $child) $children[] = htmlspecialchars($child->caption);
                    if ($children) echo "<p>" . implode(", ", $children) . "</p>";
                    echo "<span class='ui-li-count'>{$cat->itemCount}</span></a></li>";
                }
            }
            if ($section->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN) echo "<li><a href='category.php?action=new'>Skapa ny kategori</a></li>"; ?>
            </ul>
            <br>
        </div>

        <?php if ($userAccess & FFBoka::ACCESS_SECTIONADMIN) { ?>
        
        <div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']=="admins" ? "false" : "true" ?>">
            <h2>Bokningsfrågor</h2>
            <p>Här kan du skapa frågor som sedan kan visas när folk bokar resurser.</p>
            
            <a href="#" onClick="showQuestion(0);" class="ui-btn">Lägg till bokningsfråga</a>
            
            <div data-role="popup" id="popup-section-question" data-overlay-theme="b" class="ui-content">
                <div class="ui-field-contain">
                    <label for="sec-question-caption">Fråga att visa:</label>
                    <input id="sec-question-caption">
                </div>
                <div class="ui-field-contain">
                    <label for="sec-question-types">Typ av fråga:</label>
                    <div data-role="controlgroup" data-mini="true" id="sec-question-types">
                        <label><input type="radio" id="sec-question-type-radio" name="sec-question-type" value="radio">Flera optioner, ett svar</label>
                        <label><input type="radio" id="sec-question-type-checkbox" name="sec-question-type" value="checkbox">Flera optioner, flera svar</label>
                        <label><input type="radio" id="sec-question-type-text" name="sec-question-type" value="text">Fritext</label>
                        <label><input type="radio" id="sec-question-type-number" name="sec-question-type" value="number">Numerisk</label>
                    </div>
                </div>
                
                <div class="ui-content" id="sec-question-opts-checkboxradio">
                    Svarsalternativ (1 svar per rad):
                    <textarea id="sec-question-choices"></textarea>
                </div>
                <div class="ui-content" id="sec-question-opts-text">
                    <div class="ui-field-contain">
                        <label for="sec-question-opts-length">Max längd:</label>
                        <input type="number" id="sec-question-length" placeholder="Längd">
                    </div>
                </div>
                <div class="ui-content" id="sec-question-opts-number">
                    <div class="ui-grid-a">
                        <div class="ui-block-a">Min:</div>
                        <div class="ui-block-b">Max:</div>
                        <div class="ui-block-a"><input type="number" id="sec-question-min" placeholder="min"></div>
                        <div class="ui-block-b"><input type="number" id="sec-question-max" placeholder="max"></div>
                    </div>
                </div>
                <input type="button" onClick="saveQuestion()" data-theme="b" data-corners="false" value="Spara">
            </div>
            
            <ul data-role="listview" id="sec-questions" data-inset="true" data-split-icon="delete"></ul>

        </div>
        
        <div data-role="collapsible" data-collapsed="<?= $_REQUEST['expand']=="admins" ? "false" : "true" ?>">
            <h2>Administratörer</h2>

            <p>Lägg till ny administratör på LA-nivå: (skriv medlemsnummer eller namn)</p>

            <form class="ui-filterable">
                <input id="sec-adm-autocomplete-input" data-type="search" placeholder="Lägg till admin...">
            </form>
            <ul id="sec-adm-autocomplete" data-role="listview" data-filter="true" data-input="#sec-adm-autocomplete-input" data-inset="true"></ul>
                
            <p>Användare med admin-behörighet:</p>
            <ul id="ul-sec-admins" data-role="listview" data-split-icon="delete" data-split-theme="c" data-inset="true">
                <?= adminList($section, $currentUser->id) ?>
            </ul>
        </div>

        <div data-role="collapsible">
            <h2 onclick="$('#sec-map').attr('src', 'map.php?sectionId=<?= $section->id ?>');">Övrigt</h2>
            <h3>Direktlänk</h3>
            <p>Om du vill länka direkt till lokalavdelningens bokningssida kan du använda följande länk:</p>
            <p><a href="<?= $cfg['url'] . "boka-" . urlencode($section->name) ?>"><?= $cfg['url'] . "boka-" . $section->name ?></a></p>

            <h3>Geografiskt läge</h3>
            <p>Kartan nedan visar lokalavdelningens geografiska läge. Det används t.ex. vid sökning från startsidan för att sortera resultatet efter geografiskt avstånd. Du kan justera positionen genom att klicka på kartan.</p>

            <iframe id="sec-map" width="100%" height="450" frameborder="0" marginheight="0" marginwidth="0" src="" style="border: 1px solid grey"></iframe>
        </div>
        <?php } ?>

    </div><!--/collapsibleset-->

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
