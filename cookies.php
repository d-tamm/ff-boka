<?php
use FFBoka\User;

session_start();
require( "inc/common.php" );
global $cfg;

if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
    $currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
}

if ( isset( $_REQUEST[ 'action' ] ) ) {
switch ( $_REQUEST[ 'action' ] ) {
    case "help":
        die( "Mer hjälp än texten på sidan finns inte här." );
} }

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning - Kakor", $cfg[ 'url' ] ) ?>
</head>

<body>
<div data-role="page" id="page-cookies">
    <?= head( "Om kakor", $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">
    
    <h3>Vad är kakor?</h3>
    <p>Kakor är som bekant bakelser som gör livet en smula trevligare. :-) Även kakorna i datorn eller din telefon gör det: Det handlar om information som sparas i din webbläsare när du besöker webbplatsen och som hjälper webbplatsen att komma ihåg saker såsom dina inställningar (t.ex. användarnamn, språk, textstorlek och andra förhandsval) under en viss tid. Tanken är att du inte ska behöva göra om inställningarna varje gång du går in på webbplatsen eller bläddrar mellan olika sidor.</p>
    <p>Det finns förstapartskakor som sparas av själva sajten du besöker, och tredjepartskakor som sparas av tredje part, oftast sociala media och reklamföretag. tredjepartskakor har tyvärr blivit en plåga på internet då de i stor skala används för att spåra folks aktivitet och skicka information om dig till sådana som Google och Facebook.</p>
    
    <h3>Hur använder vi kakorna?</h3>
    <p>I resursbokningen använder vi inga tredjepartskakor för att spåra och analysera dig. Den enda kakan som vi normalt använder är en sessionskaka. Den identifierar dig mot andra användare och gör att webbplatsen kommer ihåg var du är i arbetsflödet. Det gäller framförallt att komma ihåg vem du är när du har loggat in, så att vi kan visa dig t.ex. din lokalavdelnings utrustning och dina bokningar. Utan den informationen går det inte att ha ett system med inloggning. Sessionskakan raderas automatiskt så snart du stänger din webbläsare eller loggar ut.</p>
    <p>Och så finns det en speciell kaka som sparas när du väljer ”Kom ihåg mig” vid inloggningen. Denna kaka gör att webbplatsen känner igen dig även när du kommer tillbaka efter en längre tid och sessionskakan har hunnit tas bort. Den fungerar liksom som en engångsnyckel, och du får en ny vid varje besök. Kakan sparas i maximalt ett år, och tas bort när du klickar på ”Logga ut”.</p>
    <p>Kakorna vi använder innehåller inga personuppgifter om dig som skulle kunna användas av tredje part. I och med att sessionskakan är nödvändig för funktionen och kom-ihåg-mig-kakan är frivillig så har vi ingen kak-dialog där du måste göra dina val.</p>
    
    <h3>Hur du kan kontrollera kakorna</h3>
    <p>Du kan kontrollera och radera kakor precis som du vill genom inställningarna som du kan göra i din webbläsare. Läs mer på <a href="http://aboutcookies.org" target="_blank">aboutcookies.org</a>. Du kan ta bort alla kakor som finns på din dator och du kan ställa in webbläsaren så att den inte tar emot några kakor. I så fall måste du eventuellt göra om vissa inställningar varje gång du går in på en webbplats och vissa tjänster och funktioner såsom inloggningen kommer inte att fungera.</p>
    
    </div><!--/main-->

</div><!--/page-->

</body>
</html>
