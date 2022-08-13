<?php
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Image;
use FFBoka\Section;
global $cfg, $message;

session_start();
require( __DIR__ . "/../inc/common.php" );

if ( isset( $_REQUEST[ 'catId' ] ) ) $_SESSION[ 'catId' ] = $_REQUEST[ 'catId' ];

if ( !isset( $_SESSION[ 'sectionId' ] ) || !isset( $_SESSION[ 'authenticatedUser' ] ) || !isset( $_SESSION[ 'catId' ] ) ) {
    header( "Location: {$cfg[ 'url' ]}index.php?action=sessionExpired" );
    die();
}

if ( isset( $_REQUEST[ 'itemId' ] ) ) $_SESSION[ 'itemId' ] = $_REQUEST[ 'itemId' ];
$item = new Item( $_SESSION[ 'itemId' ] );
$currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
$section = new Section( $_SESSION[ 'sectionId' ] );
$cat = new Category( $_SESSION[ 'catId' ] );

// Check access permissions.
if ( $cat->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
    header( "Location: {$cfg[ 'url' ]}?action=accessDenied&to=" . urlencode( "administrationssidan för " . htmlspecialchars( $item->caption ) ) );
    die();
}


/**
 * Echoes a category tree as "select" options
 * @param Category $startAt Output the tree from here downwards
 * @param Category $selected Preselect option for this category
 * @param User $user Only categories where this user is at least CATADMIN will be shown.
 * @param number $indent Indentation for visual arrangement.
 */
function showCatTree( Category $startAt, Category $selected, User $user, $indent = 0 ) {
    if ( $startAt->getAccess( $user ) >= FFBoka::ACCESS_CATADMIN || $startAt->id == $selected->id ) {
        echo "<option value='{$startAt->id}'" . ( $startAt->id == $selected->id ? " selected='true'" : "" ) . ">" . str_repeat( "&mdash;", $indent ) . " " . htmlspecialchars( $startAt->caption ) . "</option>";
    } else {
        echo "<option disabled>" . str_repeat( "&mdash;", $indent ) . " " . htmlspecialchars( $startAt->caption ) . "</option>";
    }
    foreach ( $startAt->children() as $child ) {
        showCatTree( $child, $selected, $user, $indent + 1 );
    }
}


if ( isset( $_REQUEST[ 'action' ] ) ) {
switch ( $_REQUEST[ 'action' ] ) {
    case "help":
        echo <<<EOF
        <h3>Allmänt</h3>
        <p>Inställningarna här sparas direkt. Du behöver inte trycka på någon spara-knapp. Längst upp ser du var i strukturen resursen är placerad, med klickbara överordnade element. Det är användbart för att snabbt navigera upp i hirarkin. Klicka på knappen med pennsymbol till höger om sökvägen för att flytta resursen till en annan kategori.</p>
        <p><b>Rubriken</b> visas i listor och bör hållas kort och tydlig. Har du flera resurser av samma typ kan det vara bra att lägga till ett löpnummer eller dylikt  som hjälper dig att identifiera resurserna.</p>
        <p><b>Beskrivningen</b> kan vara en längre text. Här kan du samla all information om resursen som kan vara användbar för användaren. Texten visas bara i resursens detailjvy, inte i listor.</p>
        <p><b>Text i bokningsbekräftelse:</b> Du kan lägga in en text som skickas med i den bekräftelse som användaren får när hen har lagt en bokning. Fungerar på samma sätt som motsvarande fält på kategorinivå.</p>
        <p>Med <b>Aktiv (kan bokas)</b> bestämmer du om resursen ska visas för bokning. Det kan vara användbart under tiden du lägger upp resursen tills all information är på plats, eller när en resurs inte är tillgänglig på grund av skada, förlust mm.</p>
        <p>Du kan även lägga in <b>interna anteckningar</b>. De visas bara för administratörer.</p>
        <p><b>Direktlänken</b> kan användas för att dirigera en användare direkt till denna resurs. Länken öppnar bokningsflödet så att den här resursen redan är förvald.</p>
        <p>Knappen <b>Duplicera resursen</b> skapar en kopia. Om rubriken i din resurs slutar på en siffra eller en siffra i parenteser så får kopian nästa löpnummer. Om du t.ex. kopierar <tt>Kanadensare (1)</tt> så heter kopian <tt>Kanadensare (2)</tt>. Annars får kopians rubrik tillägget <tt>(kopia)</tt>. <b>OBS</b>, kopian är avaktiverad från början! Du måste själv aktivera den.</p>

        <h3>Påminnelser</h3>
        <p>Du kan ställa in att användarna får ett meddelande ett visst antal timmar före eller efter bokningens start eller slut. Funktionen är t.ex. användbar för att skicka ut aktuell kod till kodlås, påminna om slutstädning mm. Meddelandet som skickas till användaren är det som är aktuellt vid utskickstidpunkten, inte vid bokningstidpunkten. Om du t.ex. vill skicka ut en kod som ändras varje vecka så innehåller meddelandet den kod som vid tiden för utskicket gäller, oavsett när bokningen har lagts.</p>
        <p>Påminnelser kan även ställas in på kategorinivå. För att ändra dessa, gå till respektive kategori.</p>

        <h3>Bilder</h3>
        <p>Du kan lägga in ett valfritt antal bilder till din resurs. En av bilderna blir huvudbilden, vilket innebär att den visas i listor vid t.ex. bokning. Övriga bilder visas bara på detaljsidor. Till varje bild kan du även lägga in en bildtext som visas under bilden.</p>
        EOF;
        die();

    case "newItem":
        $item = $cat->addItem();
        $_SESSION[ 'itemId' ] = $item->id;
        break;
        
    case "copyItem":
        $item = $item->copy();
        $_SESSION[ 'itemId' ] = $item->id;
        break;
        
    case "moveItem":
        $item->moveToCat( $cat );
        break;            
}
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning - Utrustning", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-admin-item">
    <?= head( $item->caption ? htmlspecialchars( $item->caption ) : "Ny utrustning", $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">
    
        <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-admin-item" class="ui-content">
            <p id="msg-page-admin-item"><?= $message ?></p>
            <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
        </div>

        <div data-role="popup" data-overlay-theme="b" id="popup-reminder" class="ui-content">
            <h3>Påminnelse</h3>
            <input type="hidden" id="reminder-id">
            <div class="ui-field-contain">
                <label for="reminder-message">Meddelande</label>
                <textarea id="reminder-message" placeholder="T.ex. koden till hänglåset är 12345."></textarea>
            </div>
            <div class="ui-field-contain">
                <label for="reminder-offset">Tidpunkt för att skicka</label>
                <fieldset data-role="controlgroup" data-type="horizontal">
                    <select id="reminder-offset" style="min-width:25em;"><?php
                        foreach ( [ -15552000, -7776000, -5184000, -2592000, -1209600, -604800, -345600, -172800, -86400, -43200, -21600, -10800, -3600, -1800, -300, 0, 300, 1800, 3600, 10800, 21600, 43200, 86400, 172800, 345600, 604800, 1209600, 2592000, 5184000, 7776000, 15552000 ] as $offset ) echo "<option value='$offset'>" . $FF::formatReminderOffset( $offset ) . "</option>\n"; ?>
                    </select>
                    <select id="reminder-anchor">
                        <option value="start">start</option>
                        <option value="end">slut</option>
                    </select>
                </fieldset>
            </div>
            <a href='#' data-rel="back" class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-back">Avbryt</a>
            <button class="ui-btn ui-btn-inline ui-btn-icon-left ui-icon-check" onclick="saveReminder( 'item' );">Spara</button>
        </div>

        <div data-role="popup" data-overlay-theme="b" id="popup-move-item" class="ui-content">
            <h3>Flytta resursen</h3>
            <form data-ajax="false">
                <p>Flytta den här resursen till en annan kategori:</p>
                <select name="catId" id="move-item-cat-id"><?php
                foreach ( $section->getMainCategories() as $c ) {
                    showCatTree( $c, $cat, $currentUser );
                }
                ?></select>
                <p style="text-align:center;">
                    <a href="#" data-rel="back" class="ui-btn ui-btn-inline">Avbryt</a>
                    <a href="#" onClick="location.href='?action=moveItem&catId='+$('#move-item-cat-id').val();" class="ui-btn ui-btn-b ui-btn-inline">Spara</a>
                </p>
            </form>
        </div>

        <div class="saved-indicator" id="item-saved-indicator">Sparad</div>

        <p><?php
        foreach ( $cat->getPath() as $p ) {
            if ( $p[ 'id' ] ) echo " &rarr; ";
            echo "<a data-transition='slide' data-direction='reverse' href='" . ( $p[ 'id' ] ? "category.php?catId={$p[ 'id' ]}&expand=items" : "index.php" ) . "'>" . htmlspecialchars( $p[ 'caption' ] ) . "</a>";
        }
        ?>&emsp;<a href="#popup-move-item" data-rel="popup" data-position-to="window" data-transition="pop" class="ui-btn ui-btn-inline ui-icon-edit ui-btn-icon-notext">Flytta</a>
        </p>
        
        <div class="ui-field-contain">
            <label for="item-caption">Rubrik:</label>
            <input name="caption" class="ajax-input" id="item-caption" placeholder="Rubrik" value="<?= htmlspecialchars( $item->caption ) ?>">
        </div>
        
        <div class="ui-field-contain">
            <label for="item-description">Beskrivning:</label>
            <textarea name="description" class="ajax-input" id="item-description" placeholder="Beskrivning"><?= htmlspecialchars( $item->description ) ?></textarea>
        </div>

        <div class="ui-field-contain">
            <label for="item-postbookMsg">Text som ska skickas med bokningsbekräftelsen:</label>
            <textarea name="postbookMsg" class="ajax-input" id="item-postbookMsg" placeholder="T.ex. kod till hänglås"><?= htmlspecialchars( $item->postbookMsg ) ?></textarea>
        </div>
        
        <label><input type="checkbox" name="active" value="1" id="item-active" <?= $item->active ? "checked='true'" : "" ?>>Aktiv (kan bokas)</label>
        
        <div class="ui-field-contain">
            <label for="item-note">Intern anteckning:</label>
            <textarea name="note" class="ajax-input" id="item-note" placeholder="Intern anteckning"><?= htmlspecialchars( $item->note ) ?></textarea>
        </div>

        <p>Direktlänk: <?php
        $directLink = "{$cfg['url']}book-part.php?sectionId={$section->id}&selectItemId={$item->id}"; ?>
        <a target="_blank" href="<?= $directLink ?>"><?= $directLink ?></a> <span style="cursor:pointer;" onclick="navigator.clipboard.writeText( '<?= $directLink ?>' ); alert( 'Länken har kopierats till urklipp' );" title="Kopiera länk">&#x1f4cb;</span></p>

        <div><input type='button' data-corners="false" id='delete-item' value='Ta bort resursen' data-theme='c'></div>
        <div><input type='button' data-corners="false" value='Duplicera resursen' onClick="location.href='?action=copyItem';"></div>
        
        <hr>
        
        <h3>Påminnelser</h3>
        <ul data-role="listview" id="reminders" data-split-icon="delete" data-split-theme="c">
        </ul>

        <h3>Bilder</h3>
        <div class="ui-field-contain">
            <label for="file-item-img">Ladda upp ny bild:</label>
            <input type="file" name="image" id="file-item-img">
        </div>
        <div id='item-images'></div>
        
    </div><!--/main-->

</div><!--/page-->
</body>
</html>
