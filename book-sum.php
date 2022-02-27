<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\FFBoka;
use FFBoka\Booking;
use FFBoka\Question;
use FFBoka\Item;
global $cfg, $message;

session_start();
require( __DIR__ . "/inc/common.php" );

$currentUser = new User( $_SESSION[ 'authenticatedUser' ] ?? 0 );

// Need bookingId
if ( isset( $_REQUEST[ 'bookingId' ] ) ) {
    $_SESSION[ 'bookingId' ] = $_REQUEST[ 'bookingId' ];
} elseif ( !isset( $_SESSION[ 'bookingId' ] ) ) {
    header( "Location: index.php" );
    die();
}
// Open existing booking
try {
    $booking = new Booking( $_SESSION[ 'bookingId' ] );
} catch ( Exception $e ) {
    logger( __METHOD__ . " User {$currentUser->id} tried to access invalid booking {$_SESSION[ 'bookingId' ]}", E_WARNING );
    unset( $_SESSION[ 'bookingId' ] );
    header( "Location: index.php?action=bookingNotFound" );
    die();
}

// Check that current booking belongs to current user, or correct token is given
if ( isset( $_REQUEST[ 'token' ] ) ) $_SESSION[ 'token' ] = $_REQUEST[ 'token' ];
if ( !(
    ( isset( $_SESSION[ 'token' ] ) && ( $_SESSION[ 'token' ] == $booking->token ) ) || // correct token
    ( $_SESSION[ 'authenticatedUser' ] && ( $booking->userId == $currentUser->id ) ) // same user
) ) {
    if ( !$_SESSION[ 'authenticatedUser' ] ) {
        header( "Location: index.php?message=" . urlencode( "Du måste logga in för att se bokningen." ) . "&redirect=book-sum.php" );
        die();
    }
    // Last access check: current user must be admin of some category
    if ( !$booking->section()->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) {
        logger( __METHOD__ . " Non-admin user {$currentUser->id} tried to access other user's booking {$_SESSION[ 'bookingId' ]}.", E_WARNING );
        unset( $_SESSION[ 'bookingId' ] );
        header( "Location: index.php?action=accessDenied&to=bokningen" );
        die();
    }
}

$_SESSION[ 'sectionId' ] = $booking->sectionId;
$section = new Section( $_SESSION[ 'sectionId' ] );
$items = $booking->items();

// Get start and end time for first item in booking, as default for other items
if ( count( $items ) ) {
    $startTime = $items[ 0 ]->start;
    $endTime = $items[ 0 ]->end;
}

// Check if booking collides with existing ones or with itself
$unavail = array();
$overlap = $booking->getOverlappingItems();
foreach ( $booking->items() as $item ) {
    if ( $item->status !== FFBoka::STATUS_REJECTED && !$item->isAvailable( $item->start, $item->end ) ) {
        if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_PREBOOK ) {
            // User can see freebusy information. Let them change booking.
            $unavail[] = $item->bookedItemId;
        }
    }
}


if ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == "help" ) {
    echo <<<EOF
    <h2>Bokningssammanfattning</h2>
    <p>Denna sida visar en sammanfattning av bokningen.</p>

    <h3>Resurslista</h3>
    <p>Här ser du alla resurser som ingår i din bokning. Du kan ändra tiden på valda resurser genom att klicka på dem, och ta bort dem genom att klicka på krysset i högerkanten. Du kan även lägga till fler resurser med knappen under resurslistan. Vid varje resurs visas statusen såsom slutgiltigt bokad eller måste bekräftas.</p>
    <p>Om du är bokningsansvarig kan du här även bekräfta eller avvisa förfrågningar på enskilda resurser och sätta pris på dem. När du gjort det är det bra om du skickar en uppdaterad bokningsbekräftelse genom att klickar på knappen <i>Spara ändringar</i> längst ner.</p>

    <h3>Pris</h3>
    <p>Under resurslistan visas en sammanfattning av kostnaderna om bokningsansvarig har satt ett pris på någon av resurserna. Om du är bokningsansvarig kan du mata in beloppet som har betalats.</p>

    <h3>Bokningsfrågor</h3>
    <p>Beroende på vad du håller på att boka kan det finnas ett avsnitt med frågor som ska besvaras. Frågor märkta med en asterisk (<span class="required"></span>) måste du svara på för att kunna boka, övriga frågor är frivilliga.</p>

    <h3>Kontaktuppgifter</h3>
    <p>Om du är inloggad så visas kontaktuppgifterna som tillhör ditt konto. Du kan när som helst ändra dem (även efter att du avslutat bokningen) genom att gå till <a href="userdata.php">Min Sida</a>.</p>
    <p>Om du bokar som gäst ska du här skriva in ditt namn och dina kontaktuppgifter så vi kan nå dig vid frågor. I bokningsbekräftelsen kommer du att få en länk till bokningen så att du kan komma tillbaka och uppdatera den.</p>
    <p>Dina kontaktuppgifter kommer att vara synliga för andra inloggade användare, i syfte att ni ska kunna ta kontakt med varandra för eventuell samordning mellan bokningar, hantering av kvarglömda saker mm. Därför måste du bekräfta att du tillåter att dina kontaktuppgifter visas för andra. Informationen visas inte för gäster.</p>

    <h3>Namn till bokningen</h3>
    <p>Här kan du skriva in en valfri kort beskrivande text, så att du lättare kan se vad bokningen avser. Texten kommer att visas som rubrik till bokningen på Min Sida, samt i epostmeddelanden.</p>

    <h3>Meddelande</h3>
    <p>Längst ner på sidan finns det en kommentarsruta som bokande och bokningsansvarig kan använda för att lämna meddelanden till varandra. När den bokande ändrar i meddelandefältet så notifieras bokningsansvarig om detta. När bokningsansvarig ändrar något i fältet bör hen klicka på Spara ändringar för att skicka ett meddelande till den bokande (se nedan).</p>

    <h3>Knappen Slutföra bokningen / Spara ändringar</h3>
    <p>Knappen sparar den aktuella bokningen och ändrar status på resurserna till <i>väntar på bekräftelse</i> eller <i>bekräftat</i> beroende på din behörighetsnivå. Sedan skickar systemet ut bekräftelsemejl till dig som bokar, samt vid behov till bokningsansvarig.</p>

    <h3>Återkommande bokningar</h3>
    <p>Om du har behörighet att lägga din bokning utan behov av att en administratör godkänner den, så kan du även skapa en bokningsserie. Välj mellan daglig, veckovis eller månadsvis upprepning samt antal tillfällen. När du skapar serien så skapas varje tillfälle som en fristående bokning, där det visas länkar för att hoppa till de andra tillfällena i serien. Om du ändrar en bokning påverkas dock inte de andra tillfällena.</p>
    <p><b>Lyft ut det här tillfället</b> löser länken mellan den här bokningen och övriga serien utan att ta bort själva tillfället.</p>
    <p><b>Lös upp serien</b> löser upp serien men lämnar kvar alla tillfällen som olänkade bokningar.</p>
    <p><b>Radera serien</b> raderar hela serien förutom det första tillfället och alla tillfällen som redan har passerat så att historiken behålls.</p>
    EOF;
    die();
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning - Bekräfta bokningen", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-book-sum">
    <?= head( "Din bokning", $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-book-sum" class="ui-content">
        <p id="msg-page-book-sum"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars( $section->name ) ?></h4>

    <?php
    if ( count( $unavail ) ) echo "<p class='ui-body ui-body-c'>Några av de resurser du har valt är inte längre tillgängliga vid den valda tiden. De är markerade nedan. För att kunna slutföra bokningen behöver du ta bort dessa resurser eller ändra tiden till en ledig tid.</p>";
    if ( count( $overlap ) ) echo "<p class='ui-body ui-body-c'>Du har lagt in " . ( count( $overlap ) == 1 ? "en resurs" : "några resurser" ) . " flera gånger vid samma tid eller så att tiderna överlappar. De berörda raderna är markerade nedan. Du behöver ta bort dubletten eller justera tiden för att kunna slutföra bokningen.</p>";

    // Show teaser for admin if booking is dirty
    if ( $booking->dirty && $_SESSION[ 'authenticatedUser' ] && $booking->user()->id != $_SESSION[ 'authenticatedUser' ] ) echo "<p class='ui-body ui-body-b' id='book-sum-dirty-msg' onClick='removeDirty();' title='Klicka här för att kvittera notifieringen.' style='cursor:pointer;'>Användaren har ändrat meddelandet eller något svar på en bokningsfråga.</p>";
    ?>
    
    <ul data-role='listview' id='book-sum-item-list' data-inset='true' data-divider-theme='a' data-split-icon='delete'>
    </ul>

    <?= count( $items ) == 0 ? "<p>Bokningen innehåller inte några resurser.</p>" : "" ?>

    <button onClick="location.href='book-part.php<?= isset( $startTime ) ? "?start=$startTime&end=$endTime" : "" ?>'" data-transition='slide' data-direction='reverse' class='ui-btn ui-icon-plus ui-btn-icon-right'>Lägg till fler resurser</button>
    
    <button onClick='confirmAllItems();' id='btn-confirm-all-items' class='ui-btn ui-icon-check ui-btn-icon-right' style="display:none;">Bekräfta alla obekräftade</button>
    
    <div id="book-sum-pay-state" class='ui-body ui-body-a' style='margin-top: 20px; display:none;'>
    <table style='width: 100%;'>
        <tr>
            <td>Pris för bokningen<span id="book-sum-price-prel" style="display:none;"> (preliminärt)</span></td>
            <td style='text-align: right;'><span id="book-sum-price">0</span>&nbsp;kr</td>
        </tr>
        <tr>
            <td>Betalt</td>
            <td style='text-align: right; white-space: nowrap;'>
                <?= $section->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ? "<a href='#' onClick='setPaid();' class='ui-btn ui-btn-a ui-btn-inline ui-icon-edit ui-btn-icon-notext'>Ändra</a>" : "" ?>
                <span id="book-sum-paid">0</span>&nbsp;kr
            </td>
        </tr>
        <tr>
            <td>Kvar att betala</td>
            <td style='font-weight:bold; text-align: right; border-top:1px solid var(--FF-blue); border-bottom:double var(--FF-blue);'><span id="book-sum-to-pay">0</span>&nbsp;kr</td>
        </tr>
    </table>
    </div>
    
    <form id='form-booking' name='formBooking' action="ajax.php" style='margin-top: 20px;'>
        <input type="hidden" name="action" value="confirmBooking">
        
        <div id="book-sum-questions" class='ui-body ui-body-a' style="display:none;"></div>
        
        <?php if ( $booking->userId ) { 
            $bookUser = new User( $booking->userId ); ?>
            <p class='ui-body ui-body-a'>
                Bokningen görs för <?= $bookUser->contactData() ?><br>
                Medlemsnummer: <?= $bookUser->id ?><br>
                Lokalavdelning: <?= $bookUser->section->name ?><br>
            </p>
        <?php } else { ?>
            <div class='ui-body ui-body-a'>Ange dina kontaktuppgifter så vi kan nå dig vid frågor:<br>
                <div class="ui-field-contain">
                    <label for="booker-name" class="required">Namn:</label>
                    <input type="text" name="extName" id="booker-name" required placeholder="Namn" value="<?= $booking->extName ?>">
                </div>
                <div class="ui-field-contain">
                    <label for="booker-mail" class="required">Epost:</label>
                    <input type="email" name="extMail" id="booker-mail" required placeholder="Epost" value="<?= $booking->extMail ?>">
                </div>
                <div class="ui-field-contain">
                    <label for="booker-phone" class="required">Telefon:</label>
                    <input type="tel" name="extPhone" id="booker-phone" required placeholder="Mobilnummer" value="<?= $booking->extPhone ?>">
                </div>
            </div>
        <?php } ?>

        <label>
            <input type="checkbox" data-mini="true" required name="okShowContactData" value="1" <?= $booking->okShowContactData==1 ? "checked" : "" ?>><span class="required">Jag medger att mina kontaktuppgifter visas för andra inloggade användare i samband med bokningen</span>
        </label>

        <div class="ui-field-contain">
            <label for="book-sum-ref">Valfritt namn till bokningen:</label>
            <input name="ref" id="book-sum-ref" placeholder="visas som rubrik i din bokningsöversikt" value="<?= $booking->ref ?>">
        </div>
        
        Här kan du lämna valfritt meddelande:
        <textarea name="commentCust" placeholder="Plats för meddelande"><?= $booking->commentCust ?></textarea>
        
        <?php
        if ( $section->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) echo "Intern anteckning:<br><textarea name='commentIntern' placeholder='Intern anteckning'>{$booking->commentIntern}</textarea>";
        ?>
    
        <input type="submit" data-icon="carat-r" data-iconpos="right" data-theme="b" data-corners="false" value="<?= $booking->status()==FFBoka::STATUS_PENDING ? "Slutför bokningen" : "Spara ändringar" ?>" <?= count( $overlap ) ? " disabled='disabled'" : "" ?>>
        <a href="#" onClick="deleteBooking(<?= $currentUser->id ?: 0 ?>);" class='ui-btn ui-btn-c ui-icon-delete ui-btn-icon-right'>Ta bort bokningen</a>
            
        <div id="book-sum-series" data-role="collapsible" data-corners="false" <?= is_null( $booking->repeatId ) ? "" : "data-collapsed='false'" ?>>
            <h4>Återkommande bokningar</h4>
            <div id='series-panel'></div>
        </div>
    </form>
            
    </div><!--/main-->

    <div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b">
        <h2 id='item-caption'></h2>
        <div class='ui-body ui-body-a' id='book-item-booking-details'>
            <p>Bokad från <span id='book-item-booked-start'></span> till <span id='book-item-booked-end'></span>.</p>
            <h3>Ändra bokningen</h3>
            <div id='book-item-select-dates'>
                <div class='freebusy-bar' style='height:50px;'>
                    <div id='book-freebusy-bar-item'></div>
                    <div id='book-chosen-timeframe'></div>
                    <?= Item::freebusyScale( true ) ?>
                </div>
    
                <div>
                    <a href='#' onclick='scrollItemDate(-7);' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-left'>bakåt</a>
                    <span id='book-current-range-readable'>1/1 - 7/1 2020</span>
                    <a href='#' onclick='scrollItemDate(7);' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-right'>framåt</a>
                </div>
    
                <div id='book-warning-conflict'>Den valda tiden krockar med befintliga bokningar.</div>
                <div id='book-date-chooser-next-click'>Klicka på önskat startdatum.</div>
    
                <div class='ui-field-contain'>
                    <label for='book-time-start'>Vald bokningstid från:</label>
                    <div data-role='controlgroup' data-type='horizontal'>
                        <input type='date' id='book-date-start' data-wrapper-class='controlgroup-textinput ui-btn'>
                        <select name='book-time-start' id='book-time-start'><?php
                        for ( $h = 0; $h < 24; $h++ ) echo "\n<option value='$h'>$h:00</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class='ui-field-contain'>
                    <label for='book-time-end'>Till:</label>
                    <div data-role='controlgroup' data-type='horizontal'>
                        <input type='date' id='book-date-end' data-wrapper-class='controlgroup-textinput ui-btn'>
                        <select name='book-time-end' id='book-time-end'><?php
                        for ( $h = 0; $h < 24; $h++ ) echo "\n<option value='$h'>$h:00</option>"; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button id="book-btn-save-part" disabled="disabled" onClick="checkTimes(true);">Spara ändringarna</button>
        </div><!-- /book-item-booking-details -->
        <div id="item-details"></div>
    </div>

</div><!-- /page -->

</body>
</html>
