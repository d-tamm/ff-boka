<?php
use FFBoka\FFBoka;
use FFBoka\User;
use FFBoka\Section;

session_start();
require(__DIR__."/inc/common.php");
global $db, $cfg, $FF;

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case "make me admin":
            $section = new Section(52);
            if ($section->addAdmin($_SESSION['authenticatedUser'])) {
                $message = "Bra jobbat! Du har nu administratörsrollen i Mölndal. Titta gärna runt och återkoppla till Daniel med dina erfarenheter!";
            } else {
                $message = "Något har gått fel.";
            }
            break;
		case "accountDeleted":
			$message = "Ditt konto har nu raderats. Välkommen åter!";
			break;
    }
}

if (isset($_POST['login'])) {
	// User trying to log in.
	// Reject DoS attacks by throttling
	$stmt = $db->query("SELECT * FROM logins WHERE INET_NTOA(IP)='{$_SERVER['REMOTE_ADDR']}' AND TIMESTAMPDIFF(SECOND, timestamp, NOW()) < {$cfg['DoSDelay']} AND NOT success");
	if ($stmt->rowCount() > $cfg['DoSCount']) {
		// Too many attempts. We do not even bother to log this to login log.
		$message = "För många inloggningsförsök. Försök igen om {$cfg['DoSDelay']} sekunder.";
	} else {
	    if ($_SESSION['authenticatedUser'] = $FF->authenticateUser($_POST['id'], $_POST['password'])) {
			$u = new User($_SESSION['authenticatedUser']);
			if (!$u->updateLastLogin()) die("Cannot update user.");
	        $db->exec("INSERT INTO logins (ip, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 1)");
            // If requested, set persistent login cookie
            if (isset($_POST['rememberMe'])) createPersistentAuth($_POST['id']);
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

if (isset($_REQUEST['logout'])) {
	// Remove persistent login cookie
	removePersistentAuth($_SESSION['authenticatedUser']);
	// Remove session
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(), "", 0, "/");
}


if (isset($_REQUEST['t'])) {
	// Token handling.
	// Figure out if the given token is still valid, and what it shall be used for.
	$stmt = $db->query("SELECT UNIX_TIMESTAMP(timestamp) AS unixtime, tokens.* FROM tokens WHERE token='" . sha1($_REQUEST['t']) . "'");
	if (!$row = $stmt->fetch(PDO::FETCH_ASSOC)) $message = "Ogiltig kod.";
	elseif (time() > $row['unixtime']+$row['ttl']) $message = "Koden har förfallit.";
	else {
		switch ($row['useFor']) {
		}
	}
}

if ($_SESSION['authenticatedUser']) {
    $currentUser = new User($_SESSION['authenticatedUser']);
    if (!$currentUser->name || !$currentUser->mail || !$currentUser->phone) {
    	// We are missing contact details for this user. Redirect to page where he/she must supply them.
    	// (We don't allow to use the system without contact data.)
    	header("Location: userdata.php?first_login=1");
    	die();
    }
}

if (isset($_REQUEST['message'])) $message .= "<br>".$_REQUEST['message'];

?><!DOCTYPE html>
<html>
<head>
	<?php htmlHeaders("Friluftsfrämjandets resursbokning") ?>

	<script>
	$( document ).on( "mobileinit", function() {
		<?php if (isset($message)) { ?>
		$( document ).on( "pagecontainershow", function( event, ui ) {
			setTimeout(function() {
				$("#popupMessage").popup('open');
			}, 500); // We need some delay here to make this work on Chrome.
		} );
		<?php } ?>
	});
	</script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>


<body>
<div data-role="page" id="page_start">
	<?= head("Resursbokning", $currentUser) ?>
	<div role="main" class="ui-content">

	<div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
		<p><?= isset($message) ? $message : "" ?></p>
		<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
	</div>

	<img src="resources/liggande-bla.png" width="100%">

	<p class="ui-body ui-body-b">Välkommen till testplattformen för FFs framtida resursbokning!<br>Här kan du följa utvecklingen av projektet och testa. Var inte rädd för att förstöra något, utan försök gärna att utmana funktionerna och hitta svaga punkter!</p>

	<div data-role='collapsibleset' data-inset='false'>
		<?php if ($_SESSION['authenticatedUser']) { ?>
		<div data-role='collapsible' data-collapsed='false'>
			<h3>Boka som medlem</h3>
			<p class="ui-body ui-body-a"><i>Jobbar mest med detta just nu :)</i></p>
			<?php
			// Show a list of all sections with categories where user may book resources
			$sectionList = "";
			foreach ($FF->getAllSections($currentUser->sectionId) as $section) {
			    if ($section->showFor($currentUser)) {
					$sectionList .= "<a href='book.php?sectionId={$section->id}' class='ui-btn'>{$section->name}</a>";
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
				$sectionList .= "<a href='admin/?sectionId={$section->id}' class='ui-btn' data-ajax='false'>{$section->name}</a>";
			}
		}
		if ($sectionList) echo "<div data-role='collapsible' data-collapsed='true'><h3>Administrera</h3><p class='ui-body ui-body-a'>Här fungerar det mesta nu. Testa gärna och återkom med synpunkter!</p>$sectionList</div>";

		// TODO: This is for testing only. Remove before switching to production!
		$molndal = new Section(52);
		if (!($molndal->getAccess($currentUser) & FFBoka::ACCESS_SECTIONADMIN)) { ?>
		    <form data-ajax="false">
		    	<p>Under testfasen kan du ge dig själv administratörs-behörighet i LA Mölndal för att testa:</p>
		    	<input type="hidden" name="action" value="make me admin">
		    	<input data-theme="b" type="submit" value="Gör mig till admin i LA Mölndal">
		    </form><?php
		}
        } ?>
		

		<div data-role='collapsible' data-collapsed='true'>
			<h3>Boka som gäst</h3>
			<p class="ui-body ui-body-a"><i>Jobbar mest med detta just nu :)</i></p>
			<?php // List of sections with categories open for guests
			foreach ($FF->getAllSections() as $section) {
				if ($section->showFor(new User(0))) {
					echo "<a href='book.php?sectionId={$section->id}&guest' data-ajax='false' class='ui-btn'>{$section->name}</a>";
				}
			} ?>
		</div>
	</div><!-- /collapsibleset -->

	<?php if (!($_SESSION['authenticatedUser'])) { ?>
		<form id="formLogin" style="padding:10px 20px;" data-ajax="false" method="POST" action="index.php">
			<h3>Inloggning <a href="#popup-help-login" data-rel="popup" class="tooltip ui-btn ui-alt-icon ui-nodisc-icon ui-btn-inline ui-icon-info ui-btn-icon-notext">Hjälp</a></h3>
			<div data-role="popup" id="popup-help-login" class="ui-content">
				<p>Du loggar in med samma lösenord som i aktivitetshanteraren.</p>
				<p class="ui-body ui-body-b">Kopplingarna till FFs centrala användarhantering är inte helt klar. Därför verifieras inte ditt lösenord än, och du hamnar än så länge under LA Mölndal.</p>
			</div>
			<input type="hidden" name="redirect" id="loginRedirect" value="">
			<input name="id" value="" placeholder="medlems- eller personnr" required>
			<input name="password" value="" placeholder="Lösenord" type="password">
			<div id="div-remember-me" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>"><label><input data-mini='true' name='rememberMe' value='1' type='checkbox'> Kom ihåg mig</label></div>
			<button name="login" value="login" class="ui-btn ui-shadow ui-btn-b ui-btn-icon-right ui-icon-user">Logga in</button>
		</form>
	<?php } ?>
	
	</div><!--/main-->

</div><!--/page-->
</body>
</html>
