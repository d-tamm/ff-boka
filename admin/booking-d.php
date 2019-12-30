<?php
use FFBoka\User;
use FFBoka\Booking;
use FFBoka\Section;
use FFBoka\FFBoka;
global $cfg;

session_start();
require(__DIR__."/../inc/common.php");

// This page may only be accessed by registered users
if (!$_SESSION['sectionId'] || !$_SESSION['authenticatedUser'] || !$_REQUEST['bookingId']) {
    header("Location: /");
    die();
}
// Set current user and booking
$section = new Section($_SESSION['sectionId']);
$currentUser = new User($_SESSION['authenticatedUser']);

$booking = new Booking($_REQUEST['bookingId']);
if (!$section->showFor($currentUser, FFBoka::ACCESS_CONFIRM)) {
    header("Location: /?action=accessDenied&to=" . urlencode("administrationssidan för bokning #{$booking->id}"));
    die();
}

switch ($_REQUEST['action']) {
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning - Bokning " . $booking->id, "desktop") ?>
	<script>
	$( function() {
    });
	</script>
</head>


<body class="desktop">
<div id="head">
    <h1>Bokning #<?= $booking->id ?></h1>
</div>

<fieldset style="display:inline-block; vertical-align:top;">
	<h2>Allmänna uppgifter</h2>
    <p>Bokningsdatum: <?= $booking->timestamp ?></p>
    <p>Kontaktuppgifter:<br><?php
    if ($booking->userId) $u = new User($booking->userId);
    echo "Namn: " . htmlspecialchars($booking->userId ? $u->name : $booking->extName) . "<br>";
    echo "Medlemsnummer: " . htmlspecialchars($booking->userId ? $u->id : "(ej medlem)") . "<br>";
    echo "&phone;: " . htmlspecialchars($booking->userId ? $u->phone : $booking->extPhone) . "<br>";
    echo "<b>@</b>: " . htmlspecialchars($booking->userId ? $u->mail : $booking->extMail);
    ?></p>
    <p>Användarens kommentar:<br>
    	<textarea><?= $booking->commentCust ?></textarea></p>
    <p>Intern notering:<br>
    	<textarea><?= $booking->commentIntern ?></textarea></p>
	<p>Pris: <?= $booking->price ?> SEK<br>
		Bokning betald: <?= $booking->payed ? $booking->payed : "nej" ?></p>
</fieldset>


<fieldset style="display:inline-block; vertical-align:top;">
    <h2>Resurser</h2>
    <a href="/book-sum.php?bookingId=<?= $booking->id ?>">Lägg till eller ta bort resurser</a>
    <?php
    foreach ($booking->subbookings() as $sub) {
        echo "<h3>Bokad från " . strftime("%a, %e %b %Y kl %k:00", $sub->start) . " till " . strftime("%a, %e %b %Y kl %k:00", $sub->end) . ":</h3>";
        echo "<ul>";
        foreach ($sub->items() as $item) {
            echo "<li>" . htmlspecialchars($item->caption) . "</li>";
            // TODO: if user is catadmin, display buttons for delete, confirm
        }
        echo "</ul>";
    }
    ?>
</fieldset>

<?php // ==== Bokningsfrågor ====
$answers = $booking->answers();
if (count($answers)) {
    echo "<fieldset style='display:inline-block; vertical-align:top;'>
        <h2>Bokningsfrågor</h2>";
    foreach ($answers as $answer) {
        echo "<p>Fråga: " . htmlspecialchars($answer->question) . "<br>";
        echo "Svar: " . htmlspecialchars($answer->answer) . "</p>";
    }
    echo "</fieldset>"; 
}
?>

bookedItems:
itemId
status
price
</body>
</html>
