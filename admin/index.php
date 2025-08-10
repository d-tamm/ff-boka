<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Question;
use FFBoka\Category;
use FFBoka\Item;


/**
 * Find all categories in section which contain items but do not have an admin who at least can confirm bookings.
 * @param Category $cat
 * @param array[id=>caption] $catsWithoutAdmin Found categories are returned in this array
 */
function findCatsWithoutAdmin( Category $cat, &$catsWithoutAdmin ) : void {
    $admins = $cat->admins( FFBoka::ACCESS_CONFIRM );
    if ( count( $admins ) == 0 && $cat->sendAlertTo == "" ) {
        if ( count( $cat->items() ) > 0 ) {
            $catsWithoutAdmin[ $cat->id ] = $cat->caption;
        } else {
            foreach ( $cat->children() as $child ) {
                findCatsWithoutAdmin( $child, $catsWithoutAdmin );
            }
        }
    }
}


session_start();
require __DIR__ . "/../inc/common.php";
global $cfg, $FF;

// Set current section
if ( isset( $_GET[ 'sectionId' ] ) ) $_SESSION[ 'sectionId' ] = $_GET[ 'sectionId' ];
if ( !$_SESSION[ 'sectionId' ] ) {
    header( "Location: {$cfg[ 'url' ]}" );
    die();
}
$section = new Section( $_SESSION[ 'sectionId' ] );

// This page may only be accessed by registered users
if ( !$_SESSION[ 'authenticatedUser' ] ) {
    header( "Location: {$cfg[ 'url' ]}?action=accessDenied&to=" . urlencode( "administrationssidan för {$section->name}" ) );
    die();
}
$currentUser = new User( $_SESSION[ 'authenticatedUser' ] );

// Only allow users which have at least some admin role in this section
if (
    !$section->showFor( $currentUser, FFBoka::ACCESS_CATADMIN ) && 
    ( !isset( $_SESSION[ 'assignments' ][ $section->id ] ) || !array_intersect( $_SESSION[ 'assignments' ][ $section->id ], $cfg[ 'sectionAdmins' ] ) )
) {
    header( "Location: {$cfg[ 'url' ]}?action=accessDenied&to=" . urlencode( "administrationssidan för {$section->name}" ) );
    die();
}

if (isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == "help") {
    $allAss = $cfg[ 'sectionAdmins' ];
    $oneAss = array_pop( $allAss );
    $admins = ( $allAss ? implode( ", ", $allAss ) . " och " : "") . $oneAss;
    $ret = <<<EOF
    <p>Den här sidan är navet för att administrera din lokalavdelnings bokningssystem. Beroende på din behörighetsnivå kommer du att se några eller alla av följande avsnitt.</p>
    <p>Om du har behörighet att bekräfta bokningarna i någon kategori så visas knappen "Öppna bokningsadmin" längst upp på sidan. Bokningsadmin ger dig en överblick över alla bokningar.</p>
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
    <p>Tipps: Om du vill att användaren bekräftar något (t.ex. bokningsreglerna) så kan du använda typen "Flera optioner, flera svar" och bara skriva in själva frågan (t.ex. "Jag godkänner bokningsreglerna"). Lämna rutan för svarsalternativen tom. Aktivera sedan svaret om obligatoriskt på kategorinivå.</p> 
    <p>Glöm inte att spara dina ändringar.</p>
    <h3>Administratörer</h3>
    <p>Här ställer du in vilka medlemmar som ska ha behörighet att administrera resursbokningen i lokalavdelningen. Du kan alltid lägga till en ny admin genom att ange dess medlemsnummer. Om personen tidigare har loggat in i resursbokningen och lagt in sina kontaktuppgifter kan du även hitta hen genom att leta efter namnet istället.</p>
    <p>$admins har automatiskt administratörsbehörighet.</p>
    <p>Om du bara vill tilldela behörigheter för att någon ska hantera enskilda kategorier eller bokningar gör du det under respektive kategori.</p>
    EOF;
    die( $ret );
}

$userAccess = $section->getAccess( $currentUser );
if ( isset( $_SESSION[ 'assignments' ][ $section->id ] ) && is_array( $_SESSION[ 'assignments' ][ $section->id ] ) ) {
    if ( array_intersect( $_SESSION[ 'assignments' ][ $section->id ], $cfg[ 'sectionAdmins' ] ) ) $userAccess = FFBoka::ACCESS_SECTIONADMIN;
}

if ( isset( $_REQUEST['message'] ) ) $message = $_REQUEST['message'];

$catsWithoutAdmin = [];
if ( $userAccess >= FFBoka::ACCESS_CATADMIN ) {
    foreach( $section->getMainCategories() as $cat ) {
        findCatsWithoutAdmin( $cat, $catsWithoutAdmin );
    }
}

if ( !isset( $_REQUEST[ 'expand' ] ) ) $_REQUEST[ 'expand' ] = "";

// First admin login from a section? Give some hints on how to get started.
$accessByAssignment = (
    isset( $_SESSION[ 'assignments' ][ $section->id ] ) && 
    $section->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN &&
    count( array_intersect( $_SESSION[ 'assignments' ][ $section->id ], $cfg[ 'sectionAdmins' ] ) ) > 0
);
if ( $accessByAssignment && count( $section->getAdmins() ) == 0 ) {
    $message = "Hej!<br><br>Vill du komma igång med din lokalavdelning? Första steget är att lägga till LA-administratörer.<br><br>Tipps: Använd medlemsnumret i sökrutan!";
}

unset( $_SESSION[ 'catId' ] );

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning - Admin " . $section->name, $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-admin-section">
    <?= head( "LA " . htmlspecialchars( $section->name ), $cfg[ 'url' ], $cfg[ 'sysAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-admin-section" class="ui-content">
        <p id="msg-page-admin-section"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <?php
    if ( $accessByAssignment ) {
        echo "<p class='ui-body ui-body-c'>Du har just nu tillgång till den här administrationssidan genom din roll som " . htmlspecialchars( array_shift( array_intersect( $_SESSION[ 'assignments' ][ $section->id ], $cfg[ 'sectionAdmins' ] ) ) ) . ". Detta ger dig möjlighet att tilldela dig själv och andra administratörsrollen för lokalavdelningen. Det är bara administratörer som kan lägga upp t.ex. kategorier, så om du själv vill lägga upp saker så måste du först tilldela dig själv administratörsrollen i avsnittet <b>Administratörer</b> nedan.</p>";
    }
        
    if ( $userAccess >= FFBoka::ACCESS_CATADMIN && count( $catsWithoutAdmin ) > 0 ) {
        echo "<div class='ui-body ui-body-a'><p>Följande kategorier innehåller resurser men saknar administratör. Lägg till minst en administratör med behörighet att bekräfta bokningar.</p><ul data-role='listview' data-inset='true'>";
        foreach ( $catsWithoutAdmin as $id => $caption ) {
            echo "<li><a href='category.php?catId=$id'>" . htmlspecialchars( $caption ) . "</a></li>";
        }
        echo "</ul></div>";
    }
    ?>

    <?php
    $unconfirmed = [];
    foreach ( $section->getUnconfirmedBookings( $currentUser ) as $bookingId ) {
        if ( !isset( $unconfirmed[ $bookingId ] ) ) $unconfirmed[ $bookingId ] = 0;
        $unconfirmed[ $bookingId ]++;
    } ?>
    <a class='ui-btn <?= $unconfirmed ? "ui-btn-c" : "ui-btn-b" ?> ui-icon-calendar ui-btn-icon-left' title='Öppna bokningsadministratören' href='#' onClick="openBookingAdmin( '<?= $cfg[ 'url' ] ?>', <?= $section->id ?> );" data-ajax='false'><?= $unconfirmed ? ( count( $unconfirmed ) == 1 ? "1 obekräftad bokning" : count( $unconfirmed ) . " obekräftade bokningar" ) : "Öppna bokningsadmin" ?></a>

    <div data-role="collapsibleset" data-inset="false">

        <div data-role="collapsible" data-collapsed="<?= isset( $_REQUEST[ 'expand' ] ) ? "true" : "false" ?>" style="<?= $accessByAssignment ? "display:none;" : "" ?>">
            <h2>Kategorier</h2>
            <ul data-role="listview">
            <?php
            foreach ( $section->getMainCategories() as $cat ) {
                if ( $cat->showFor( $currentUser, FFBoka::ACCESS_CATADMIN ) ) {
                    echo "<li class=" . ( $cat->active ? "active" : "inactive" ) . "><a data-transition='slide' href='category.php?catId={$cat->id}'>" .
                        htmlspecialchars( $cat->caption ) .
                        embedImage( $cat->thumb );
                    $children = [];
                    foreach ( $cat->children() as $child ) $children[] = htmlspecialchars( $child->caption );
                    echo "<p>" . ( $cat->active ? "" : "(inaktiv) " ) . ( $children ? implode( ", ", $children ) : "" ) . "</p>";
                    echo "<span class='ui-li-count'>{$cat->itemCount}</span></a></li>";
                }
            }
            if ( $section->getAccess( $currentUser ) & FFBoka::ACCESS_SECTIONADMIN ) echo "<li><a href='category.php?action=new'>Skapa ny kategori</a></li>"; ?>
            </ul>
            <br>
        </div>

        <?php if ( $userAccess & FFBoka::ACCESS_SECTIONADMIN ) { ?>
        
        <div data-role="collapsible" data-collapsed="<?= $_REQUEST[ 'expand' ] == "admins" ? "false" : "true" ?>" style="<?= $accessByAssignment ? "display:none;" : "" ?>">
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
        
        <div data-role="collapsible" data-collapsed="<?= $_REQUEST[ 'expand' ] == "admins" ? "false" : "true" ?>">
            <h2>Administratörer</h2>

            <p>Lägg till ny administratör på LA-nivå: (skriv medlemsnummer eller namn)</p>

            <form class="ui-filterable">
                <input id="sec-adm-autocomplete-input" data-type="search" placeholder="Lägg till admin...">
            </form>
            <ul id="sec-adm-autocomplete" data-role="listview" data-filter="true" data-input="#sec-adm-autocomplete-input" data-inset="true"></ul>
                
            <p>Användare med admin-behörighet:</p>
            <ul id="ul-sec-admins" data-role="listview" data-split-icon="delete" data-split-theme="c" data-inset="true">
            </ul>
        </div>

        <div data-role="collapsible" style="<?= $accessByAssignment ? "display:none;" : "" ?>">
            <h2 onclick="$('#sec-map').attr('src', 'map.php?sectionId=<?= $section->id ?>');">Övrigt</h2>

            <h3>Användning</h3>
            <ul>
                <li><strong><?= $section->registeredUsers ?></strong> registrerade användare</li>
                <li><strong><?= $section->activeUsers ?></strong> aktiva användare senaste 12 månader</li>
                <li><strong><?= $section->activeItems ?></strong> aktiva resurser i <strong><?= $section->numberOfCategories ?></strong> kategorier</li>
                <li><strong><?= $section->inactiveItems ?></strong> icke-aktiva resurser</li>
            </ul>
            <table style="width:100%; max-width:30em;">
                <tr>
                    <th align='left'>Bokningar</th><?php
                    $data = [];
                    for ( $i = 0; $i <= 3; $i++ ) {
                        $data[] = $section->usageOverview( date( "Y" ) - $i );
                        echo "<th align='right'>". ( date( "Y" ) - $i ) ."</th>";
                    } ?>
                </tr>
                <tr>
                    <td>Antal bokningar</td><?php
                    for ( $i = 0; $i <= 3; $i++ ) echo "<td align='right'>". $data[ $i ]->bookings ."</td>"; ?>
                </tr>
                <tr>
                    <td>Bokade resurser</td><?php 
                    for ( $i = 0; $i <= 3; $i++ ) echo "<td align='right'>". $data[ $i ]->bookedItems ."</td>"; ?>
                </tr>
                <tr>
                    <td>Total bokad tid (h)</td><?php 
                    for ( $i = 0; $i <= 3; $i++ ) echo "<td align='right'>". $data[ $i ]->duration ."</td>"; ?>
                </tr>
            </table>
            <a href='usage.php' data-ajax='false' class="ui-btn">Mer användningsstatistik</a>

            <h3>Direktlänk</h3>
            <p>Om du vill länka direkt till lokalavdelningens bokningssida kan du använda följande länk:</p>
            <p><a href="<?= $cfg[ 'url' ] . "boka-" . urlencode( $section->name ) ?>"><?= $cfg[ 'url' ] . "boka-" . $section->name ?></a></p>

            <h3>Geografiskt läge</h3>
            <p>Kartan nedan visar lokalavdelningens geografiska läge. Det används t.ex. vid sökning från startsidan för att sortera resultatet efter geografiskt avstånd.</p>

            <iframe id="sec-map" width="100%" height="450" frameborder="0" marginheight="0" marginwidth="0" src="" style="border: 1px solid grey"></iframe>
        </div>
        <?php } ?>

    </div><!--/collapsibleset-->

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
