<?php
use FFBoka\User;

session_start();
require("inc/common.php");
global $cfg;

if ($_SESSION['authenticatedUser']) {
    $currentUser = new User($_SESSION['authenticatedUser']);
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Hjälp", $cfg['url']) ?>
</head>

<body>
<div data-role="page" id="page-help">
	<?= head("Hjälp till resursbokning", $cfg['url'], $currentUser) ?>
	<div role="main" class="ui-content">

	<h3>Inloggning</h3>
	<p>Resursbokningen använder samma inloggning som Friluftsfrämjandets aktivitetshanterare. Har du problem med inloggningen, vänd dig i första hand till dem som har hand om inloggningen på friluftsframjandet.se.</p>

	<h3>Komma igång med din lokalavdelning</h3>
	<p>Uppdragen <?php
	$allAss = $cfg['sectionAdmins'];
	$oneAss = array_pop($allAss);
	echo implode(", ", $allAss) . " och " . $oneAss;
	?> har alltid administratörsbehörighet i varje lokalavdelning. För att komma igång med att använda resursbokningen i din lokalavdelning måste alltså någon med ett sådant uppdrag logga in och göra de första inställningarna, t.ex. lägga till behörighet för andra att ta över administrationen.</p>
	
	<h3>Säkerhet och integritet</h3>
	<p>Vi jobbar aktivt med säkerheten och integriteten på sajten:</p>
	<ul>
		<li>Vi sparar inte ditt lösenord, varken i klartext eller krypterat.</li>
		<li>När vi visar epostadresser här på hemsidan gör vi det på ett sätt som gör det praktiskt omöjligt för automatiserade system att läsa ut adressen i syfte att missbruka den för att skicka spam.</li>
		<li>Vi delar aldrig dina uppgifter med tredje part. All data ligger på en server som finns i Sverige.</li>
		<li>Det går att ansluta med en krypterad uppkoppling. Skriv "http<b>s</b>://" i webbläsarens adressfält.</li>
	</ul>
	<p><?= $_SERVER['HTTPS'] ? "Grattis! Du är ansluten med en säker, krypterad förbindelse." : "<strong>Du använder just nu en osäker anslutning.</strong> <a href='https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}'>Klicka här</a> för att byta till krypterad uppkoppling." ?></p>

	<h3>Personuppgifter och GDPR</h3>
	<p>Det ligger i sakens natur att vi måste bearbeta personuppgifter för att kunna bedriva verktygspoolen. De uppgifter som sparas om dig i systemet är:</p>
	<ul>
		<li>De <a href='userdata.php'>kontaktuppgifter</a> som du själv har angett vid registreringen. De behövs för att plattformen ska kunna fungera. T.ex. används din epost-adress för att kunna skicka bekräftelser och påminnelser om bokningar. Kontaktuppgifterna kan även användas om det uppstår frågor om någon bokning.</li>
		<li>Om du gör en bokning kommer all data som du lämnar med bokningen vara tillgänglig för respektive materialansvarig/administratör. Andra <u>inloggade</u> användare kan se dina kontaktuppgifter för att kunna ta kontakt, t.ex. för samordning vid överlämnande av material.</li>
		<li>Om du tilldelas en administratörsroll behöver vi spara information om detta för att kunna ge dig tillgång till de funktioner som du behöver i rollen. Är du materialansvarig/administratör för en kategori kommer dina kontaktuppgifter att visas för alla användare.</li>
		<li>Om någon administratör ger dig tillgång till utrustning hos andra lokalavdelningar måste detta också sparas i systemet.</li>
	</ul>
	<p>Du kan alltid höra av dig till oss för att ta reda på vad som är sparat om just dig.</p>

	<h3>Kontakt</h3>
	<p>Om du har frågor kan du skicka ett mejl till <?= obfuscatedMaillink($cfg['mailReplyTo'], "Fråga om resursbokningen") ?> eller ringa Daniel (076-105 69 75).</p>

	</div><!--/main-->

</div><!--/page-->

</body>
</html>
