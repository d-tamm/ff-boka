<?php
use FFBoka\Booking;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\User;
global $cfg, $FF;

session_start();
require(__DIR__."/inc/common.php");

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: /");
    die();
}

$currentUser = new User($_SESSION['authenticatedUser']);

/**
 * Show a list of all categories and their children where user has admin permissions,
 * with switches to opt out of messages when new bookings arrive 
 * @param User $user
 * @param Category $cat
 */
function showNotificationOptout(User $user, Category $cat) {
    if ($cat->getAccess($user, FALSE) >= FFBoka::ACCESS_CONFIRM) {
        $notify = $user->getNotifyAdminOnNewBooking($cat);
        ?>
        <div class='ui-field-contain'>
            <label><?= htmlspecialchars($cat->caption) ?></label>
            <fieldset data-role="controlgroup" data-type="horizontal" data-mini="true">
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="0"<?= $notify=="no" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'no');">Av</label>
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="1"<?= $notify=="confirmOnly" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'confirmOnly');">Bekräfta</label>
                <label><input type="radio" name="optout-cat-<?= $cat->id ?>" value="2"<?= $notify=="yes" ? " checked='checked'" : "" ?> onClick="setNotificationOptout(<?= $cat->id ?>, 'yes');">Alla</label>
            </fieldset>
        </div><?php
    }
    foreach ($cat->children() as $child) {
        if ($child->showFor($user, FFBoka::ACCESS_CONFIRM)) showNotificationOptout($user, $child);
    }
}

switch ($_REQUEST['action']) {
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
<p>Om du byter till <b>Av</b> och hittills har varit den enda administratören som fått aviseringar så kommer du få en varning, eftersom nya bokningar som måste bekräftas riskerar att inte bearbetas.</p>

<h3>Inloggningar</h3>
<p>Här ser du alla enheter/webbläsare där du har loggat in med "Kom ihåg mig"-funktionen. Funktionen gör att du inte behöver logga in varje gång du använder resursbokningen. Du kan ta bort enskilda poster genom att klicka på knappen längst till höger. På det viset kan du t.ex. logga ut en enhet som du inte längre har kontroll över.</p>

<h3>Kontaktuppgifter</h3>
<p>Resursbokningen kan inte fungera utan att bokningsansvariga vid behov kan ta kontakt med dig. Därför måste du lägga in några grundläggande uppgifter om dig själv. Även om vi skulle kunna hämta dessa uppgifter från Friluftsfrämjandets centrala register gör vi det inte för att undvika krångel med GDPR. Uppgifterna som du matar in här (namn, epost och telefon) sparas lokalt i databasen och delas inte med något annat system.</p>
<p>När du ändrar epostadressen kommer systemet att skicka en aktiveringskod till den nya adressen som du måste bekräfta. Det gör vi för att säkerställa att du kan nås på adressen och utesluta stavningsfel.</p>
<p>Av säkerhetsskäl behöver du knappa in ditt lösenord för att bekräfta ändringar av dina kontaktuppgifter.</p>
<p><b>OBS:</b> Lösenordet kan du inte ändra här eftersom vi använder samma inloggning som Friluftsfrämjandets hemsida. Om du vill ändra ditt lösenord måste du därför logga in på <a target="_blank" href="https://www.friluftsframjandet.se">Friluftsfrämjandets hemsida</a>.</p>

<h3>Radera kontot</h3>
<p>Om du inte längre vill använda resursbokningen kan du radera alla dina personuppgifter i systemet. Om du gör det loggas du ut, och ditt konto med alla relaterade uppgifter <b>inklusive alla bokningar (både kommande och avslutade)</b> raderas.</p>
<p>Om du åter vill använda tjänsten loggar du in igen med ditt medlemsnummer och måste då ange dina personuppgifter på nytt.</p>
<p>Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
EOF;
        die();
    case "bookingDeleted":
        $message = "Din bokning har nu tagits bort.";
        break;
    case "deleteAccount":
        if ($currentUser->delete()) {
            header("Location: index.php?logout&action=accountDeleted");
            break;
        } else {
            $message = "Något gick fel. Kontakta webmaster tack.";
        }
        break;
        
    case "save user data":
        // User shall supply name, mail and phone
        if ($_POST['name'] && $_POST['mail'] && $_POST['phone']) {
            $result = $FF->authenticateUser($currentUser->id, $_POST['password']);
            if ($result===FALSE) {
                $message = "Kan inte verifiera lösenordet just nu. Vänligen försök igen senare.";
                break;
            }
            if ($result['authenticated']===FALSE) {
                $message = "Fel lösenord. Vänligen försök igen. Lösenordet du ska ange är samma som du använder på Friluftsfrämjandets hemsida.";
                break;
            }
            $currentUser->name = $_POST['name'];
            $currentUser->phone = $_POST['phone'];
            if ($_POST['mail'] !== $currentUser->mail) {
                $token = $currentUser->setUnverifiedMail($_POST['mail']);
                sendmail(
                    $_POST['mail'], // to
                    "Bekräfta din epostadress", // subject
                    "confirm_mail_address", // template name
                    array( // replace.
                        "{{name}}" => $currentUser->name,
                        "{{new_mail}}" => $_POST['mail'],
                        "{{link}}" => "{$cfg['url']}index.php?t=$token",
                    )
                );
                $message = "Dina kontaktuppgifter har sparats. Ett meddelande har skickats till adressen {$_POST['mail']}. Använd länken i mejlet för att aktivera den nya adressen.";
            } else {
                header("Location: index.php?message=" . urlencode("Dina kontaktuppgifter har sparats."));
                die();
            }
        } else {
            $message = "Fyll i namn, epostadress och mobilnummer, tack.";
        }
        break;
        
    case "ajaxSetNotificationOptout":
        header("Content-Type: application/json");
        $ret = $currentUser->setNotifyAdminOnNewBooking($_REQUEST['catId'], $_REQUEST['notify']);
        if ($ret === FALSE ) {
            die(json_encode([ "status"=>"error", "error"=>"Något har gått fel. Kunde inte spara." ]));
        } elseif ($ret === 0) {
            die(json_encode([ "status"=>"warning", "warning"=>"OBS: Nu finns det inte någon bokningsansvarig kvar som får meddelande om nya bokningar som måste bekräftas!" ]));
        } else {
            die(json_encode([ "status"=>"OK" ]));
        }
        
    case "ajaxRemovePersistentLogin":
        header("Content-Type: application/json");
        $currentUser->removePersistentLogin($_REQUEST['userAgent']);
        die(json_encode([ "status"=>"OK" ]));
}
    

if ($_GET['first_login']) $message = "Välkommen till resursbokningen! Innan du sätter igång med din bokning vill vi att du berättar vem du är, så att andra (t.ex. administratörer) kan komma i kontakt med dig vid frågor. Du kan läsa om hur vi hanterar dina uppgifter i <a href='help.php'>Hjälpen</a>.";

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-userdata">
    <?= head("Min sida", $cfg['url'], $currentUser) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-userdata" class="ui-content">
        <p id="msg-page-userdata"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role='collapsibleset' data-inset='false'>
        
        <div data-role='collapsible' data-collapsed='<?= $_GET['first_login'] ? "true" : "false" ?>'>
            <h3>Mina bokningar</h3>
            <?php
            $bookingIds = $currentUser->bookingIds();
            if (count($bookingIds)) {
                // Sort the bookings in unconfirmed, upcoming and completed
                $unconfirmed = "";
                $upcoming = "";
                $completed = "";
                foreach ($bookingIds as $id) {
                    $b = new Booking($id);
                    $latestEnd = time();
                    $html = "<li><a href='book-sum.php?bookingId={$b->id}'><p>Bokat {$b->timestamp} i LA {$b->section()->name}:</p>";
                    foreach ($b->items() as $item) {
                        $html .= "<p><b>" . htmlspecialchars($item->caption) . "</b> (" . strftime("%F kl %k:00", $item->start) . " &mdash; " . strftime("%F kl %k:00", $item->end) . ($item->status<FFBoka::STATUS_CONFIRMED ? ", <b>obekräftat</b>" : "") . ")</p>";
                        $latestEnd = min($latestEnd, $item->end);
                    }
                    $html .= "</a></li>";
                    if ($b->status() < FFBoka::STATUS_CONFIRMED) $unconfirmed .= $html;
                    elseif ($latestEnd<time()) $completed .= $html;
                    else $upcoming .= $html;
                }
                if ($unconfirmed) echo "<h4>Obekräftade bokningar</h4><ul data-role='listview'>$unconfirmed</ul>";
                if ($upcoming) echo "<h4>Kommande bokningar</h4><ul data-role='listview'>$upcoming</ul>";
                if ($completed) echo "<h4>Avslutade bokningar</h4><ul data-role='listview'>$completed</ul>";
            } else {
                echo "<ul data-role='listview'><li>Du har inga bokningar.</li></ul>";
            } ?>
        </div>
        
        <?php
        $sections = $currentUser->bookingAdminSections();
        if (count($sections)) { ?>
        <div data-role='collapsible'>
            <h3>Avisering vid nya bokningar</h3>
            <?php 
            foreach ($sections as $sec) {
                echo "<p><b>" . htmlspecialchars($sec->name) . "</b></p>";
                foreach ($sec->getMainCategories() as $cat) {
                    if ($cat->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) showNotificationOptout($currentUser, $cat);
                }
            } ?>
        </div><?php
        } ?>
        
        <div data-role='collapsible'>
            <h3>Inloggningar</h3>
            <ul data-role="listview" data-split-icon="delete">
            <?php
            $logins = $currentUser->persistentLogins();
            if ($logins) {
                foreach ($logins as $login) {
                    echo "<li class='wrap'><a href='#' style='white-space:normal; font-weight:normal;'>" . htmlspecialchars($login->userAgent) . ($login->selector == explode(":", $_COOKIE['remember'])[0] ? " <i>(den här inloggningen)</i>" : "") . "<br>Förfaller " . strftime("%F", $login->expires) . "</a><a href='#' onClick=\"removePersistentLogin(this.parentElement, '" . htmlspecialchars($login->userAgent) . "');\" title='ta bort inloggningen'></a></li>";
                }
            } else echo "<li style='white-space:normal'>Här kommer du se dina inloggningar där du har valt alternativet \"Kom ihåg mig\". Just nu har du inte några sådana permanenta inloggningar.</li>";
            ?>
            </ul>
        </div>

        <div data-role='collapsible' data-collapsed='<?= $_GET['first_login'] ? "false" : "true" ?>'>
            <h3>Kontaktuppgifter</h3>
            
            <form action="userdata.php" method="post" data-ajax="false">
                <p>Uppgifter om dig så andra vet vem du är och hur de kan få tag i dig.</p>
                <input type="hidden" name="action" value="save user data">
                <p>Medlemsnummer: <?= $currentUser->id ?></p>
                <p>Lokalavdelning: <?= $currentUser->section->name ?></p>
                <div class="ui-field-contain">
                    <label for="userdata-name" class="required">Namn:</label>
                    <input type="text" name="name" id="userdata-name" required placeholder="Namn" value="<?= htmlspecialchars($_POST['name'] ? $_POST['name'] : $currentUser->name) ?>">
                </div>
                <div class="ui-field-contain">
                    <label for="userdata-mail" class="required">Epost:</label>
                    <input type="email" name="mail" id="userdata-mail" required placeholder="Epost" value="<?= htmlspecialchars($_POST['mail'] ? $_POST['mail'] : $currentUser->mail) ?>">
                </div>
                <div class="ui-field-contain">
                    <label for="userdata-phone" class="required">Telefon:</label>
                    <input type="tel" name="phone" id="userdata-phone" required placeholder="Mobilnummer" value="<?= htmlspecialchars($_POST['phone'] ? $_POST['phone'] : $currentUser->phone) ?>">
                </div>
                Ange ditt aktuella lösenord nedan för att bekräfta att du vill ändra dina kontaktuppgifter.
                <div class="ui-field-contain">
                    <label for="userdata-password" class="required">Lösenord:</label>
                    <input type="password" name="password" id="userdata-password" required placeholder="Ange ditt FF-lösenord" autocomplete="off">
                </div>
                <input type="submit" value="Spara" data-icon="check">
            </form>
        </div>
    
        <div data-role='collapsible'>
            <h3>Radera kontot</h3>
            <p>Om du inte längre vill använda resursbokningen kan du radera alla dina personuppgifter i systemet. Om du gör det loggas du ut, och ditt konto med alla relaterade uppgifter raderas. Om du åter vill använda tjänsten loggar du in igen med ditt medlemsnummer och måste då ange dina personuppgifter på nytt.</p>
            <p>Att radera ditt konto här påverkar inte ditt konto i aktivitetshanteraren.</p>
            <button class="ui-btn ui-btn-c" onClick="deleteAccount();" data-ajax='false'>Radera mina uppgifter</button>
        </div>
    
    
        <div data-role='collapsible'>
            <h3>Debug-info</h3><!-- TODO ta bort efter testfasen -->
            <p>Visas för teständamål. Tas bort i produktion.</p>
            <p>$_SESSION:</p>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>

    </div><!--/collapsibleset-->
    
    </div><!--/main-->

</div><!--/page-->
</body>
</html>
