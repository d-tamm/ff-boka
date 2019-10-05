<?php
session_start();
require("common.php");


?><!DOCTYPE html>
<html>
<head>
	<?php htmlHead("Friluftsfrämjandets resursbokning - Hjälp") ?>
</head>
<body>
<div data-role="page">
	<?= head("Hjälp till resursbokning") ?>

	<h3>Inloggning</h3>
	<p>Resursbokningen använder samma inloggning som Friluftsfrämjandets aktivitetshanterare. Har du problem med inloggningen, vänd dig i första hand till dem som har hand om inloggningen på friluftsframjandet.se.</p>

	<h3>Säkerhet och integritet</h3>
	<p>Vi jobbar aktivt med säkerheten och integriteten på sajten:</p>
	<ul>
		<li>Vi sparar inte ditt lösenord, varken i klartext eller krypterat.</li>
		<li>Vi delar aldrig dina uppgifter med tredje part. All data ligger på en server som finns i Sverige.</li>
		<li>Det går att ansluta med en krypterad uppkoppling. Skriv "http<b>s</b>://" i webbläsarens adressfält.</li>
	</ul>

	<h3>Personuppgifter och GDPR</h3>
	<p>Det ligger i sakens natur att vi måste bearbeta personuppgifter för att kunna bedriva verktygspoolen. De uppgifter som sparas om dig i systemet är:</p>
	<ul>
		<li>Om du gör en bokning: All data som du lämnar med bokningen. Andra inloggade användare kan se dina kontaktuppgifter för att kunna ta kontakt vid behov.</li>
		<li>Om du tilldelas en administratörsroll behöver vi spara information om detta för att kunna ge dig tillgång till de funktioner som du behöver i rollen.</li>
		<li>Om någon administratör ger dig tillgång till utrustning hos andra lokalavdelningar måste detta också sparas i systemet.</li>
	</ul>
	<p>Du kan alltid höra av dig till oss för att ta reda på vad som är sparat om just dig.</p>

	<h3>Kontakt</h3>
	<p>Om du har frågor kan du skicka ett mejl till <?= obfuscated_maillink($cfg['mailReplyTo'], $subject="Fråga om resursbokningen") ?> eller ringa Daniel (076-105 69 75).</p>

</div>

</body>
</html>
