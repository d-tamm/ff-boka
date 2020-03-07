<?php
use FFBoka\User;

session_start();
require("inc/common.php");
global $cfg;

if ($_SESSION['authenticatedUser']) {
    $currentUser = new User($_SESSION['authenticatedUser']);
}

switch ($_REQUEST['action']) {
    case "help":
        die("Mer hjälp än texten på sidan finns inte här.");
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Kakor", $cfg['url']) ?>
</head>

<body>
<div data-role="page" id="page-cookies">
    <?= head("Om kakor", $cfg['url'], $currentUser, $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <p>För att få den här webbplatsen att fungera ordentligt skickar vi ibland små filer till din dator. Dessa filer kallas kakor eller ”cookies”. De flesta större webbplatser gör på samma sätt.</p>
    
    <h3>Vad är kakor?</h3>
    <p>Kakorna är små textfiler som sparas på din dator, telefon eller surfplatta när du besöker webbplatsen. Kakorna hjälper webbplatsen att komma ihåg dina inställningar (t.ex. användarnamn, språk, textstorlek och andra förhandsval) under en viss tid. Tanken är att du inte ska behöva göra om inställningarna varje gång du går in på webbplatsen eller bläddrar mellan olika sidor.</p>
    
    <h3>Hur använder vi kakorna?</h3>
    <p>I resursbokningen använder vi följande kakor:</p>
    <ul>
        <li>Sessionskakan gör att webbplatsen kommer ihåg var du är i arbetsflödet. Det gäller framförallt att komma ihåg vem du är när du har loggat in, så att vi kan visa dig t.ex. din lokalavdelnings utrustning. Sessionskakan raderas automatiskt så snart du stänger din webbläsare.</li>
        <li>En speciell kaka sparas när du väljer ”Kom ihåg mig” vid inloggningen. Denna kaka gör att webbplatsen känner igen dig även när du kommer tillbaka efter en längre tid och sessionskakan har hunnit tas bort. Kakan sparas i maximalt ett år, och tas bort när du klickar på ”Logga ut”. För att fåt tillgång till kom-ihåg-funktionen måste du samtycka till att denna kaka sparas (se längst ned).</li>
    </ul>
    <p>Kakorna vi använder innehåller inga uppgifter om dig som skulle kunna användas av tredje part.</p>
    
    <h3>Hur du kan kontrollera kakorna</h3>
    <p>Du kan kontrollera och radera kakor precis som du vill. Läs mer på <a href="http://aboutcookies.org" target="_blank">aboutcookies.org</a>.  Du kan ta bort alla kakor som finns på din dator och du kan ställa in webbläsaren så att den inte tar emot några kakor. I så fall måste du eventuellt göra om vissa inställningar varje gång du går in på en webbplats och vissa tjänster och funktioner kanske inte fungerar.</p>
    <p>Du kan enkelt välja om du vill acceptera kakor på den här webbplatsen genom att klicka nedan.</p>

    <button id="acceptCookies" style="<?= empty($_COOKIE['cookiesOK']) ? "" : "display:none;" ?>" onClick="var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='cookiesOK=1; expires='+d.toUTCString()+'; Path=/'; $('#acceptCookies').hide(); $('#rejectCookies').show(); $('#divCookieConsent').hide(); $('#divRememberme').show();">Tillåt permanenta kakor</button>
    <button id="rejectCookies" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>" onClick="document.cookie='cookiesOK=0; path=/'; $('#acceptCookies').show(); $('#rejectCookies').hide(); $('#divCookieConsent').hide(); $('#divRememberme').hide();">Tillåt inte permanenta kakor</button>
    
    </div><!--/main-->

</div><!--/page-->

</body>
</html>
