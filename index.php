<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;

session_start();
require(__DIR__."/inc/common.php");
global $db, $cfg, $FF;
$message = "";
if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') || strpos($_SERVER['HTTP_USER_AGENT'], 'Trident/7')) $message = "Du använder Internet Explorer, en föråldrad webbläsare som struntar i webbstandarder. Vi har valt att inte lägga tid på att stödja den, så bokningen kommer inte att fungera. Vänligen använd en annan webbläsare. Vi rekommenderar Firefox eller Chrome.";

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case "help":
			$allAss = $cfg['sectionAdmins'];
			$oneAss = array_pop($allAss);
			echo "
<h3>Inloggning</h3>
<p>Resursbokningen använder samma inloggning som Friluftsfrämjandets aktivitetshanterare. Har du problem med inloggningen, vänd dig i första hand till dem som har hand om inloggningen på friluftsframjandet.se.</p>

<h3>Komma igång med din lokalavdelning</h3>
<p>Uppdragen " . ($allAss ? implode(", ", $allAss) . " och " : "") . $oneAss . " har alltid administratörsbehörighet i varje lokalavdelning. För att komma igång med att använda resursbokningen i din lokalavdelning måste alltså någon med ett sådant uppdrag logga in och göra de första inställningarna, t.ex. lägga till behörighet för andra att ta över administrationen.</p>

<h3>Säkerhet och integritet</h3>
<p>Vi jobbar aktivt med säkerheten och integriteten på sajten:</p>
<ul>
	<li>Vi sparar inte ditt lösenord, varken i klartext eller krypterat.</li>
	<li>När vi visar epostadresser här på hemsidan gör vi det på ett sätt som gör det praktiskt omöjligt för automatiserade system att läsa ut adressen i syfte att missbruka den för att skicka spam.</li>
	<li>Vi delar aldrig dina uppgifter med tredje part. All data ligger på en server som finns i Sverige.</li>
	<li>Det går att ansluta med en krypterad uppkoppling. Skriv \"https://\" i webbläsarens adressfält." . ($_SERVER['HTTPS'] ? " - Grattis! Du är ansluten med en säker, krypterad förbindelse." : "<strong>Du använder just nu en osäker anslutning.</strong> <a href='https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}'>Klicka här</a> för att byta till krypterad uppkoppling.") . "</li>
</ul>

<h3>Personuppgifter och GDPR</h3>
<p>Det ligger i sakens natur att vi måste bearbeta personuppgifter för att kunna bedriva bokningssystemet. De uppgifter som sparas om dig i systemet är:</p>
<ul>
	<li>De userkontaktuppgifter som du själv har angett vid registreringen. De behövs för att plattformen ska kunna fungera. T.ex. används din epost-adress för att kunna skicka bekräftelser och påminnelser om bokningar. Kontaktuppgifterna kan även användas om det uppstår frågor om någon bokning.</li>
	<li>Om du gör en bokning kommer all data som du lämnar med bokningen vara tillgänglig för respektive materialansvarig/administratör. Informationen visas inte för andra användare.</li>
	<li>Om du tilldelas en administratörsroll behöver vi spara information om detta för att kunna ge dig tillgång till de funktioner som du behöver i rollen. Är du materialansvarig/administratör för en kategori kommer dina kontaktuppgifter att visas för användare som vill boka utrustningen.</li>
</ul>
<p>Du kan alltid höra av dig till oss för att ta reda på vad som är sparat om just dig.</p>

<h3>Kontakt</h3>
<p>Om du har frågor kan du skicka ett mejl till " . obfuscatedMaillink($cfg['mailReplyTo'], "Fråga om resursbokningen") . " eller ringa Daniel (076-105 69 75).</p>";
            die();
        case "make me admin":
			if (is_numeric($_REQUEST['sectionId'])) {
				$section = new Section($_REQUEST['sectionId']);
				if ($section->addAdmin($_SESSION['authenticatedUser'])) {
					$message = "Bra jobbat! Du har nu administratörsrollen i {$section->name}. Titta gärna runt och återkoppla till Daniel med dina erfarenheter!";
				} else {
					$message = "Något har gått fel.";
				}
			}
            break;
		case "accountDeleted":
			$message = "Ditt konto har nu raderats. Välkommen åter!";
			break;
		case "bookingNotFound":
		    $message = "Bokningen finns inte i systemet.";
		    break;
		case "sessionExpired":
		    $message = "Du har blivit utloggad på grund av inaktivitet.";
		    // Remove session
		    session_unset();
		    session_destroy();
		    session_write_close();
		    setcookie(session_name(), "", 0, "/");
		    break;
		case "accessDenied":
		    $message = "Du har inte tillgång till {$_REQUEST['to']}.";
		    break;
		case "bookingDeleted":
		    $message = "Din bokning har nu tagits bort.";
		    break;
		case "bookingConfirmed":
		    $message = "Din bokning är nu klar. En bekräftelse har skickats till din epostadress " . htmlspecialchars($_REQUEST['mail']) . ".";
		    break;
    }
}

if (isset($_POST['login'])) {
	// User trying to log in.
	// Reject DoS attacks by throttling
	$stmt = $db->query("SELECT * FROM logins WHERE INET_NTOA(IP)='{$_SERVER['REMOTE_ADDR']}' AND TIMESTAMPDIFF(SECOND, timestamp, NOW()) < {$cfg['DoSDelay']} AND NOT success");
	if ($stmt->rowCount() > $cfg['DoSCount']) {
		// Too many attempts. We do not even bother to log this to login log.
		$message = "För många inloggningsförsök. Försök igen om " . (int)($cfg['DoSDelay']/60) . " minuter.";
	} else {
	    $result = $FF->authenticateUser($_POST['id'], $_POST['password']);
	    if ($result['authenticated']) {
	        $_SESSION['authenticatedUser'] = $_POST['id'];
			$u = new User($_SESSION['authenticatedUser'], $result['section']);
			if (!$u->updateLastLogin()) die("Cannot update user.");
	        $db->exec("INSERT INTO logins (ip, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 1)");
            // If requested, set persistent login cookie
            if (isset($_POST['rememberMe'])) $u->createPersistentLogin($cfg['TtlPersistentLogin']);
            // Redirect if requested by login form
            if ($_POST['redirect']) {
                header("Location: {$_POST['redirect']}");
                die();
            }
        } else {
	        // Password wrong.
	        $message = "Fel medlemsnummer eller lösenord.";
	        $db->exec("INSERT INTO logins (ip, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 0)");
		}
	}
}

if (isset($_REQUEST['t'])) {
    // Token handling.
    // Figure out if the given token is still valid, and what it shall be used for.
    try {
        $token = $FF->getToken($_REQUEST['t']);
        switch ($token->useFor) {
            case "change mail address":
                $user = new User($token->forId);
                $user->mail = $token->data;
                $FF->deleteToken($token->token);
                $message="Din epostadress {$token->data} är nu aktiverad.";
                break;
        }
    } catch (Exception $e) {
        $message = $e;
    }
}


if ($_SESSION['authenticatedUser']) {
    $currentUser = new User($_SESSION['authenticatedUser']);
    if (isset($_REQUEST['logout'])) {
        // Remove persistent login cookie
        $currentUser->removePersistentLogin();
        // Remove session
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), "", 0, "/");
    } elseif (!$currentUser->name || !$currentUser->mail || !$currentUser->phone) {
    	// We are missing contact details for this user. Redirect to page where he/she must supply them.
    	// (We don't allow to use the system without contact data.)
    	header("Location: userdata.php?first_login=1");
    	die();
    }
}

if (isset($_REQUEST['message'])) $message = ($message ? "$message<br>" : "") . $_REQUEST['message'];
elseif ($_SESSION['welcomeMessageShown']!==TRUE) {
	$message = "<b>Hej och välkommen till resursbokningen!</b><br>Ett litet tipps till dig som snabbt vill få en överblick: Kolla på Mölndals lokalavdelning! Där finns det en del resurser upplagda som du kan testa att boka. När du har loggat in kan du även göra dig till admin i någon lokalavdelning - testa även här med Mölndal om du inte vill börja från scratch. Övriga lokalavdelningar är sannolikt tomma än så länge, men du är välkommen att ändra på det!";
	$_SESSION['welcomeMessageShown'] = TRUE;
}

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-start">
	<?= head("Resursbokning", $cfg['url'], $currentUser) ?>
	<div role="main" class="ui-content">

	<div data-role="popup" data-overlay-theme="b" id="popup-msg-page-start" class="ui-content">
		<p id="msg-page-start"><?= $message ?></p>
		<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
	</div>

	<img src="resources/liggande-bla.png" style="width:100%; max-width:600px; display:block; margin-left:auto; margin-right:auto;">

	<p class="ui-body ui-body-b">Välkommen till testplattformen för FFs framtida resursbokningssystem! Här kan du följa utvecklingen av projektet och testa. Var inte rädd för att förstöra något, utan försök gärna att utmana funktionerna och hitta svaga punkter!<br>Kom ihåg att detta är bara testplattformen. Allt som läggs upp kommer att försvinna vid övergången till produktionsplattformen.<br>Mer information hittar du på <a style="color:white;" target="_blank" href="https://github.com/d-tamm/ff-boka">GitHub</a></p>

	<?php
	if ($_SESSION['authenticatedUser']) {
    	if ($ub = $currentUser->unfinishedBookings()) {
    	    echo "<p class='ui-body ui-body-c'>Du har minst en påbörjad bokning som du bör avsluta eller ta bort.";
    	    echo "<a href='book-sum.php?bookingId={$ub[0]}' class='ui-btn ui-btn-a'>Gå till bokningen</a></p>";
    	}
	}
	?>

	<div data-role='collapsibleset' data-inset='false'>
		<?php if ($_SESSION['authenticatedUser']) { ?>
		<div data-role='collapsible' data-collapsed='false'>
			<h3>Boka som medlem</h3>
			<?php
			// Show a list of all sections with categories where user may book resources
			$sectionList = "";
			foreach ($FF->getAllSections($currentUser->sectionId) as $section) {
			    if ($section->showFor($currentUser) && count($section->getMainCategories())) {
					$sectionList .= "<a href='book-part.php?sectionId={$section->id}' class='ui-btn'>" . htmlspecialchars($section->name) . "</a>";
				}
			}
			if ($sectionList) echo $sectionList;
			else echo "<p>Det finns inga resurser du kan boka som medlem i din lokalavdelning.</p>"; ?>
		</div>

		<?php
		// Show a list of all sections where user has admin role
		$sectionList = "";
		foreach ($FF->getAllSections() as $section) {
			if ($section->showFor($currentUser, FFBoka::ACCESS_CATADMIN)) {
				$sectionList .= "<a href='admin/?sectionId={$section->id}' class='ui-btn' data-transition='slideup'>" . htmlspecialchars($section->name) . "</a>";
			}
		}
		if ($sectionList) echo "<div data-role='collapsible' data-collapsed='true'><h3>Administrera</h3>$sectionList";

		// TODO: This is for testing only. Remove before switching to production! ?><br>
		<form class="ui-body ui-body-a">
			<p>Under testfasen kan du ge dig själv administratörs-behörighet i valfri lokalavdelning för att testa alla funktioner.</p>
			<input type="hidden" name="action" value="make me admin">
			<select name="sectionId">
				<option>Välj lokalavdelning</option><?php
				$stmt = $db->query("SELECT * FROM sections ORDER BY name");
				while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
					echo "<option value='{$row->sectionId}'>{$row->name}</option>";
				} ?>
			</select>
			<input data-theme="b" type="submit" data-corners="false" value="Gör mig till admin">
		</form><?php

		if ($sectionList) echo "</div>";
        } ?>
		
	</div><!-- /collapsibleset -->

	<?php if (!($_SESSION['authenticatedUser'])) { ?>
		<div data-role='collapsible' data-collapsed='true'>
			<h3>Boka som gäst</h3>
			<?php // List of sections with categories open for guests
			foreach ($FF->getAllSections() as $section) {
				if ($section->showFor(new User(0)) && count($section->getMainCategories())) {
					echo "<a href='book-part.php?sectionId={$section->id}&guest' class='ui-btn'>" . htmlspecialchars($section->name) . "</a>";
				}
			} ?>
		</div>

		<form id="formLogin" style="padding:10px 20px;" action="index.php" method="post" data-ajax="false">
			<h3>Inloggning</h3>
			<input type="hidden" name="redirect" id="loginRedirect" value="<?= $_REQUEST['redirect'] ?>">
			<input name="id" value="" placeholder="Medlemsnummer" required>
			<input name="password" value="" placeholder="Lösenord" type="password">
			<div id="div-remember-me" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>"><label><input data-mini='true' name='rememberMe' value='1' type='checkbox'> Kom ihåg mig</label></div>
			<button name="login" value="login" class="ui-btn ui-shadow ui-btn-b ui-btn-icon-right ui-icon-user">Logga in</button>
		</form>
	<?php } ?>
	
	</div><!--/main-->

</div><!--/page-->
</body>
</html>
