<?php
use FFBoka\Booking;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\User;
global $cfg, $FF, $db;

session_start();
require __DIR__ . "/inc/common.php";

// This page may only be accessed by registered users
if ( !$_SESSION[ 'authenticatedUser' ] ) {
    header( "Location: /" );
    die();
}

$currentUser = new User( $_SESSION[ 'authenticatedUser' ] );

/**
 * Show a list of all categories and their children where user has admin permissions,
 * with switches to opt out of messages when new bookings arrive 
 * @param User $user
 * @param Category $cat
 */
function showNotificationOptout( User $user, Category $cat ) : void {
    if ( $cat->getAccess( $user, FALSE ) >= FFBoka::ACCESS_CONFIRM ) {
        $notify = $user->getNotifyAdminOnNewBooking( $cat );
        ?>
        <div class='ui-field-contain'>
            <label><?= htmlspecialchars( $cat->caption ) ?></label>
            <fieldset data-role="controlgroup" data-type="horizontal" data-mini="true">
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="0"<?= $notify == "no" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'no');">Av</label>
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="1"<?= $notify == "confirmOnly" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'confirmOnly');">Bekräfta</label>
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="2"<?= $notify == "yes" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'yes');">Alla</label>
            </fieldset>
        </div><?php
    }
    foreach ( $cat->children() as $child ) {
        if ( $child->showFor( $user, FFBoka::ACCESS_CONFIRM ) ) showNotificationOptout( $user, $child );
    }
}

if ( !isset( $_REQUEST[ 'expand' ] ) ) $_REQUEST[ 'expand' ] = "";

if ( isset( $_REQUEST[ 'action' ] ) ) {
switch ( $_REQUEST[ 'action' ] ) {
    case "help":
        echo <<<EOF
        <p>På den här sidan kan du se och ändra dina personliga inställningar.</p>
        <h3>Mina bokningar</h3>
        <p>Här ser du alla bokningar du har gjort, uppdelade på kommande och avslutade (upp till 1 år gamla). Du kan klicka på bokningarna för att se detaljerna och ändra/avboka.</p>

        <h3>Aviseringar</h3>
        <p>Avsnittet visas bara om du har en administratörsroll i någon kategori (kategori- eller bokningsansvarig). Här listas alla sådana kategorier, och du kan ställa in om du vill få aviseringar per epost när nya bokningar kommer in.</p>
        <ul>
            <li><b>Av</b> stänger av alla aviseringar.</li>
            <li><b>Bekräfta</b> innebär att du bara får meddelanden för preliminärbokningar som måste bekräftas av någon bokningsansvarig.</li>
            <li><b>Alla</b> innebär att du får en avisering för varje ny bokning, även om den inte behöver bekräftas.</li>
        </ul>
        <p>Om du byter till <b>Av</b> och hittills har varit den enda administratören som fått aviseringar så kommer du få en varning, eftersom nya bokningar som måste bekräftas riskerar att inte hanteras.</p>

        <h3>Inloggningar</h3>
        <p>Här ser du alla enheter/webbläsare där du har loggat in med "Kom ihåg mig"-funktionen. Funktionen gör att du inte behöver logga in varje gång du använder resursbokningen. Du kan ta bort enskilda poster genom att klicka på knappen längst till höger. På det viset kan du t.ex. logga ut en enhet som du inte längre har kontroll över.</p>

        <h3>Kontaktuppgifter</h3>
        <p>Resursbokningen kan inte fungera utan att bokningsansvariga vid behov kan ta kontakt med dig. Det kan även vara nyttigt för vanliga användare att ta kontakt med andra användare som har bokat samma resurs. Därför måste du lägga in några grundläggande uppgifter om dig själv. Även om vi skulle kunna hämta dessa uppgifter från Friluftsfrämjandets centrala register gör vi det inte för att undvika krångel med GDPR. Uppgifterna som du matar in här (namn, epost och telefon) sparas lokalt i databasen och delas inte med något annat system.</p>
        <p>När du ändrar epostadressen kommer systemet att skicka en aktiveringskod till den nya adressen som du måste bekräfta. Det gör vi för att säkerställa att du kan nås på adressen och utesluta stavningsfel.</p>
        <p>Av säkerhetsskäl behöver du knappa in ditt lösenord för att bekräfta ändringar av dina kontaktuppgifter.</p>
        <p><b>OBS:</b> Lösenordet kan du inte ändra här eftersom vi använder samma inloggning som Friluftsfrämjandets hemsida. Om du vill ändra ditt lösenord måste du därför logga in på <a target="_blank" href="https://www.friluftsframjandet.se">Friluftsfrämjandets hemsida</a>.</p>

        <h3>Radera kontot</h3>
        <p>Om du inte längre vill använda resursbokningen kan du radera alla dina personuppgifter i systemet. Om du gör det loggas du ut, och ditt konto med alla relaterade uppgifter <b>inklusive alla bokningar (både kommande och avslutade)</b> raderas.</p>
        <p>Om du åter vill använda tjänsten loggar du in igen med ditt medlemsnummer och måste då ange dina personuppgifter på nytt.</p>
        <p>Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
        EOF;
        die();

    case "ajaxDeleteAccount":
        $result = $FF->authenticateUser( $currentUser->id, $_POST[ 'password' ] );
        if ( $result === FALSE ) {
            http_response_code( 503 ); // Service unavailable
            die( "Kan inte verifiera lösenordet just nu. Vänligen försök igen senare." );
        }
        if ( $result[ 'authenticated' ] !== TRUE ) {
            http_response_code( 401 ); // Unauthorized
            die( "Fel lösenord. Vänligen försök igen. Lösenordet du ska ange är samma som du använder på Friluftsfrämjandets hemsida." );
        }
        if ( $currentUser->delete() ) {
            // We remove the session cookie here. Otherwise, the user would be recreated in index.php
            session_unset();
            session_destroy();
            session_write_close();
            setcookie( session_name(), "", 0, "/" );
            die();
        }
        http_response_code( 500 ); // Internal Server Error
        die( "Något gick fel. Kontakta webmaster tack." );
        
    case "ajaxGetUserdata":
        header( "Content-Type: application/json" );
        die( json_encode( [
            "name" => $currentUser->name,
            "mail" => $currentUser->mail,
            "mailPending" => $currentUser->getUnverifiedMail(),
            "phone" => $currentUser->phone,
        ] ) );

    case "ajaxSaveUserdata":
        $result = $FF->authenticateUser( $currentUser->id, $_POST[ 'password' ] );
        if ( $result === FALSE ) {
            http_response_code( 503 ); // Service unavailable
            die( "Kan inte verifiera lösenordet just nu. Vänligen försök igen senare." );
        }
        if ( $result[ 'authenticated' ] !== TRUE ) {
            http_response_code( 403 ); // Forbidden
            die( "Fel lösenord. Vänligen försök igen. Lösenordet du ska ange är samma som du använder på Friluftsfrämjandets hemsida." );
        }
        if ( $_POST[ 'mail' ] && !filter_var( $_POST[ 'mail' ], FILTER_VALIDATE_EMAIL ) ) {
            http_response_code( 400 );
            die( "Epostadressen är ogiltig." );
        }
        if ( $_POST[ 'name' ] ) $currentUser->name = $_POST[ 'name' ];
        if ( $_POST[ 'phone' ] ) $currentUser->phone = $_POST[ 'phone' ];
        if ( $_POST[ "mail" ] && $_POST[ 'mail' ] !== $currentUser->mail ) {
            // Mail address change. Send a verification token.
            $token = $currentUser->setUnverifiedMail( $_POST[ 'mail' ] );
            $FF->sendMail(
                $_POST[ 'mail' ], // To
                "Bekräfta din epostadress", // subject
                "confirm_mail_address", // template
                [
                    "{{name}}" => $currentUser->name,
                    "{{new_mail}}" => $_POST[ 'mail' ],
                    "{{link}}" => "{$cfg[ 'url' ]}index.php?t=$token",
                    "{{expires}}" => date( "Y-m-d H:i", time() + 86400 )
                ],
                [], // attachments
                $cfg[ 'mail' ],
                false // send immediately
            );
            $msg = "Ett meddelande har skickats till adressen " . htmlspecialchars( $_POST[ 'mail' ] ) . ". Använd länken i mejlet för att aktivera den nya adressen.\n\nHittar du inte mejlet? Kolla i skräpkorgen!";
        }
        die( "Dina kontaktuppgifter har sparats. $msg" );
        
    case "ajaxSetNotificationOptout":
        header( "Content-Type: application/json" );
        $ret = $currentUser->setNotifyAdminOnNewBooking( $_REQUEST[ 'catId' ], $_REQUEST[ 'notify' ] );
        if ( $ret === FALSE ) {
            die( json_encode( [ "status" => "error", "error" => "Något har gått fel. Kunde inte spara." ] ) );
        } elseif ( $ret === 0 ) {
            die( json_encode( [ "status" => "warning", "warning" => "OBS: Nu finns det inte någon bokningsansvarig kvar som får meddelande om nya bokningar som måste bekräftas!" ] ) );
        } else {
            die( json_encode( [ "status" => "OK" ] ) );
        }
        
    case "ajaxRemovePersistentLogin":
        header( "Content-Type: application/json" );
        try {
            if ( $currentUser->removePersistentLogin( $_REQUEST[ 'selector' ] ) === false ) die( json_encode( [ "status" => "error", "error" => "Kunde inte utföra begäran." ] ) );
        } catch ( \Exception $e ) {
            die( json_encode( [ "status" => "error", "error" => "Något har gått fel. Kan inte logga ut denna inloggning." ] ) );
        }
        die( json_encode( [ "status" => "OK" ] ) );
}
}
    

if ( isset( $_GET[ 'first_login' ] ) ) $message = "Välkommen till resursbokningen! Innan du sätter igång med din bokning vill vi att du berättar vem du är, så att andra (t.ex. administratörer) kan komma i kontakt med dig vid frågor. Du kan läsa om hur vi hanterar dina uppgifter genom att klicka på <b>?</b> uppe till höger på sidan.";

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-userdata">
    <?= head( "Min sida", $cfg[ 'url' ], $cfg[ 'sysAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-userdata" class="ui-content">
        <p id="msg-page-userdata"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role='collapsibleset' data-inset='false'>
        
        <div data-role='collapsible' data-collapsed='<?= isset( $_GET[ 'first_login' ] ) || $_REQUEST[ 'expand' ]!="bookings" ? "true" : "false" ?>'>
            <h3>Mina bokningar</h3>
            <ul data-role='listview'>
            <li data-icon='plus'><a href="book-part.php?sectionId=<?= $currentUser->sectionId ?>">Lägg en ny bokning</a></li>
            <?php
            $bookingIds = $currentUser->bookingIds();
            // Sort by start date of first item respectively
            usort( $bookingIds, function( $b1, $b2 ) {
                $booking1 = new Booking( $b1 );
                $booking2 = new Booking( $b2 );
                return $booking1->start() - $booking2->start();
            } );
            if ( count( $bookingIds ) ) {
                // Sort the bookings in unconfirmed, upcoming and completed
                $pending = "";
                $unconfirmed = "";
                $upcoming = "";
                $completed = "";
                foreach ( $bookingIds as $id ) {
                    $b = new Booking( $id );
                    $start = NULL;
                    $end = NULL;
                    $html = "";
                    $items = $b->items();
                    foreach ( $b->items() as $item ) {
                        $start = is_null( $start ) ? $item->start : min( $start, $item->start );
                        $end = is_null( $end ) ? $item->end : min( $end, $item->end );
                        $html .= "&bull; " . htmlspecialchars( $item->caption ) . ( $item->status == FFBoka::STATUS_PREBOOKED ? " (<b>obekräftat</b>)" : "" ) . "<br>";
                    }
                    $html = "<li><a href='book-sum.php?bookingId={$b->id}'>\n{$b->id}" .
                        ( $b->ref ? " &ndash; " . htmlspecialchars( $b->ref ) : "" ) .
                        "<p>" . ( is_null( $b->repeatId ) ? "" : "🔄 " ) . "<b>" . ( is_null( $start ) ? "Bokningen är tom" : date( "Y-m-d \k\l H:00", $start ) . " &mdash; " . date( "Y-m-d \k\l H:00", $end ) ) . "</b></p>\n" .
                        "<p>$html</p>" .
                        "<p>Bokat {$b->timestamp} i LA {$b->section()->name}</p>\n" .
                        "</a></li>";
                    if ( $b->status() == FFBoka::STATUS_PENDING ) $pending .= $html;
                    elseif ( $b->status() < FFBoka::STATUS_CONFIRMED ) $unconfirmed .= $html;
                    elseif ( $end < time() ) $completed .= $html;
                    else $upcoming .= $html;
                }
                if ( $pending ) echo "<li data-role='list-divider'>Ej slutförda bokningar</li>$pending";
                if ( $unconfirmed ) echo "<li data-role='list-divider'>Obekräftade bokningar</li>$unconfirmed";
                if ( $upcoming ) echo "<li data-role='list-divider'>Aktuella bokningar</li>$upcoming";
                if ( $completed ) echo "<li data-role='list-divider'>Avslutade bokningar</li>$completed";
            } else {
                echo "<li>Du har inga bokningar.</li>";
            } ?>
            </ul>
        </div>
        
        <?php
        $sections = $currentUser->bookingAdminSections();
        if ( count( $sections ) ) { ?>
        <div data-role='collapsible'>
            <h3>Avisering vid nya bokningar</h3>
            <p>Skicka avisering vid nya bokningar:</p>
            <?php 
            foreach ( $sections as $sec ) {
                echo "<p><b>" . htmlspecialchars( $sec->name ) . "</b></p>";
                foreach ( $sec->getMainCategories() as $cat ) {
                    if ( $cat->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) showNotificationOptout( $currentUser, $cat );
                }
            } ?>
        </div><?php
        } ?>
        
        <div data-role='collapsible'>
            <h3>Inloggningar</h3>
            <p>Här visas dina inloggningar där du har valt alternativet "Kom ihåg mig". </p>
            <ul data-role="listview" data-split-icon="delete">
            <?php
            $logins = $currentUser->persistentLogins();
            if ( $logins ) {
                foreach ( $logins as $login ) {
                    echo "<li class='wrap'><a href='#' style='white-space:normal; font-weight:normal;'>" . htmlspecialchars( resolveUserAgent( $login->userAgent, $db ) ) . ( $login->selector == explode( ":", $_COOKIE[ 'remember' ] )[ 0 ] ? " <i>(den här inloggningen)</i>" : "" ) . "<br>Förfaller " . date( "Y-m-d", $login->expires ) . "</a><a href='#' onClick=\"removePersistentLogin(this.parentElement, '" . htmlspecialchars( $login->selector ) . "');\" title='Logga ut'></a></li>";
                }
            } else echo "<li style='white-space:normal'>Just nu har du inte några sådana permanenta inloggningar.</li>";
            ?>
            </ul>
        </div>

        <div data-role='collapsible' data-collapsed='<?= isset( $_GET[ 'first_login' ] ) || $_REQUEST[ 'expand' ] == "contact" ? "false" : "true" ?>'>
            <h3>Kontaktuppgifter</h3>
            
            <p>Uppgifter om dig så andra vet vem du är och hur de kan få tag i dig. För att kunna använda systemet behöver du lägga in både namn, epost och telefonnummer. Uppgifterna används enbart för kommunikationen inom bokningssystemet.</p>
            <p>Medlemsnummer: <?= $currentUser->id ?></p>
            <p>Lokalavdelning: <?= $currentUser->section->name ?></p>
            <div class="ui-field-contain">
                <label for="userdata-name" class="required">Namn:</label>
                <input type="text" id="userdata-name" placeholder="Namn">
            </div>
            <div id="userdata-div-mail" class="ui-field-contain">
                <label class="required">Epost:</label>
                <span id="userdata-mail"></span>
            </div>
            <div class="ui-field-contain">
                <label id="userdata-lbl-new-mail" for="userdata-mail">Epost:</label>
                <input type="email" id="userdata-new-mail" autocomplete="off" placeholder="Knappa in din (nya) epostadress här" value=""><!-- This field will be set to writable by Javascript, which prevents autofilling -->
            </div>
            <p id="userdata-msg-mail-pending" class='ui-body ui-body-a'><small>Du har ett pågående ärende att använda epostadressen <b id="userdata-mail-pending">...</b>. Ett meddelande med aktiveringslänk har skickats till den nya adressen. Klicka på länken i mejlet för att aktivera adressen. Kolla i din skräppostmapp om du inte hittar mejlet. Behöver du en ny kod? Skriv in adressen igen i fältet ovan!</small></p>
            <div class="ui-field-contain">
                <label for="userdata-phone" class="required">Telefon:</label>
                <input type="tel" id="userdata-phone" placeholder="Mobilnummer">
            </div>
            Ange ditt aktuella lösenord nedan för att bekräfta att du vill ändra dina kontaktuppgifter.
            <div class="ui-field-contain">
                <label for="userdata-password" class="required">Lösenord:</label>
                <input type="password" id="userdata-password" placeholder="Ange ditt FF-lösenord" autocomplete="off">
            </div>
            <button onClick="saveUserdata();" class="ui-btn ui-btn-icon-right ui-icon-check">Spara</button>
        </div>
    
        <div data-role='collapsible'>
            <h3>Radera kontot</h3>
            <p>Om du inte längre vill använda resursbokningen kan du radera alla dina personuppgifter i systemet. Om du gör det loggas du ut, och ditt konto med alla relaterade uppgifter raderas. Om du åter vill använda tjänsten loggar du in igen med ditt medlemsnummer och måste då ange dina personuppgifter på nytt.</p>
            <p>Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
            <button class="ui-btn" onclick="$('#div-delete-account').show();">Radera mitt konto</button>
            <div class="ui-body ui-body-a" id="div-delete-account" style="display:none;">
                Ange ditt lösenord för att bekräfta att du vill radera ditt konto.<br><b>OBS, allt data tillhörande ditt konto (t.ex. dina bokningar) raderas och kan inte återställas!</b>
                <input type="password" id="delete-account-password">
                <button class="ui-btn ui-btn-c" onClick="deleteAccount();" data-ajax='false'>Radera mina uppgifter</button>
            </div>
        </div>

    </div><!--/collapsibleset-->
    
    </div><!--/main-->

</div><!--/page-->
</body>
</html>
