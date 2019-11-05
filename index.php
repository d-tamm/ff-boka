<?php
session_start();
require("common.php");
global $db, $cfg;

if (isset($_POST['login'])) {
	// User trying to log in.
	// Reject DoS attacks by throttling
	$stmt = $db->query("SELECT * FROM logins WHERE INET_NTOA(IP)='{$_SERVER['REMOTE_ADDR']}' AND TIMESTAMPDIFF(SECOND, timestamp, NOW()) < {$cfg['DoSDelay']} AND NOT success");
	if ($stmt->rowCount() > $cfg['DoSCount']) {
		// Too many attempts. We do not even bother to log this to login log.
		$message = "För många inloggningsförsök.";
	} else {
		// Check member ID and password via API
		if (login($_POST['ID'], $_POST['password'])) {
			$db->exec("INSERT INTO logins (IP, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 1)");
			// If requested, set persistent login cookie
			if (isset($_POST['rememberme'])) createPersistentAuth($_SESSION['user']['userID']);
			// Redirect if requested by login form
			if ($_POST['redirect']) {
				header("Location: {$_POST['redirect']}");
				die();
			}
		}
		else {
			// Password wrong.
			$message = "Fel medlemsnummer eller lösenord.";
			$db->exec("INSERT INTO logins (IP, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 0)");
		}
	}
}

if (isset($_REQUEST['logout'])) {
	// Remove persistent login cookie
	removePersistentAuth($_SESSION['user']['userID']);
	// Remove session
	session_unset();
	session_destroy();
	session_write_close();
	setcookie(session_name(), "", 0, "/");
	session_regenerate_id(true);
	header("Location: index.php");
	die();
}


if (isset($_REQUEST['t'])) {
	// Token handling.
	// Figure out if the given token is still valid, and what it shall be used for.
	$stmt = $db->query("SELECT UNIX_TIMESTAMP(timestamp) AS unixtime, tokens.* FROM tokens WHERE token='" . sha1($_REQUEST['t']) . "'");
	if (!$row = $stmt->fetch(PDO::FETCH_ASSOC)) $message = "Ogiltig kod.";
	elseif (time() > $row['unixtime']+$row['ttl']) $message = "Koden har förfallit.";
	else {
		switch ($row['usefor']) {
		}
	}
}


if ($_SESSION['user']['userID'] && (!isset($_SESSION['user']['name']) || !isset($_SESSION['user']['mail']) || !isset($_SESSION['user']['phone']))) {
	// First time user logs in. Redirect to page where he/she must supply some contact data
	header("Location: userdata.php?first_login=1");
	die();
}

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
	<?= head("Resursbokning") ?>
	<div role="main" class="ui-content">

	<div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
		<p><?= isset($message) ? $message : "" ?></p>
		<?= isset($dontShowOK) ? "" : "<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>" ?>
	</div>

	<img src="resources/liggande-bla.png" width="100%">

	<div data-role='collapsibleset' data-inset='false'>
		<?php if (isset($_SESSION['user']['userID'])) { ?>
		<div data-role='collapsible' data-collapsed='false'>
			<h3>Boka som medlem</h3>
			<?php
			// Make a list of all sections with categories where user may book equipment
			$sectionList = "";
			$stmt = $db->query("SELECT sectionID, name FROM sections ORDER BY sectionID={$_SESSION['user']['sectionID']} DESC, name");
			while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
				if (secHasAccessibleCats($row['sectionID'])) {
					$sectionList .= "<a href='book.php?sectionID={$row['sectionID']}' class='ui-btn'>{$row['name']}</a>";
				}
			}
			if ($sectionList) echo $sectionList;
			else echo "<p>Det finns inga resurser du kan boka som medlem i din lokalavdelning.</p>";
			?>
		</div>

		<?php
		// Make a list of all sections where user has admin role
		$stmt = $db->query("SELECT sectionID, name FROM sections ORDER BY name");
		$sectionList = "";
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if (isSectionAdmin($row['sectionID'])) {
				$sectionList .= "<a href='admin.php?sectionID={$row['sectionID']}' class='ui-btn'>{$row['name']}</a>";
			}
		}
		if ($sectionList) echo "<div data-role='collapsible' data-collapsed='true'><h3>Administrera</h3>$sectionList</div>";
		} ?>

		<div data-role='collapsible' data-collapsed='true'>
			<h3>Boka som gäst</h3>
			<?php // List of sections with categories open for guests
			$stmt = $db->query("SELECT sections.* FROM categories INNER JOIN sections USING (sectionID) WHERE access_external GROUP BY sections.name");
			if (!$stmt->rowCount()) echo "<p>Det finns inget att boka som gäst just nu.</p>";
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				echo "<a href='book.php?sectionID={$row['sectionID']}&guest' class='ui-btn'>{$row['name']}</a>";
			} ?>
		</div>
	</div><!-- /collapsibleset -->

	<?php if (!isset($_SESSION['user']['userID'])) { ?>
		<form id="formLogin" style="padding:10px 20px;" data-ajax="false" method="POST" action="index.php">
			<h3>Inloggning</h3>
			<p>Du loggar in med samma lösenord som i aktivitetshanteraren.</p>
			<p class="ui-body ui-body-b">Under testfasen räcker det med ditt medlemsnummer. Inget lösenord behövs. Men du kan ange numret till en lokalavdelning som lösenord för att simulera att du tillhör den lokalavdelningen (testa 52=Mölndal). Då tilldelas du även uppdraget Ordförande i den lokalavdelningen, så att du kan komma åt admin-gränssnittet.</p>
			<input type="hidden" name="redirect" id="loginRedirect" value="">
			<input name="ID" value="" placeholder="medlems- eller personnr" required>
			<input name="password" value="" placeholder="Lösenord" type="password">
			<div id="divRememberme" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>"><label><input data-mini='true' name='rememberme' value='1' type='checkbox'> Kom ihåg mig</label></div>
			<button name="login" value="login" class="ui-btn ui-shadow ui-btn-b ui-btn-icon-right ui-icon-user">Logga in</button>
		</form>
	<?php } ?>
	
	</div><!--/main-->

</div><!--/page-->
</body>
</html>
