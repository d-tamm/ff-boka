<?php
session_start();
require("common.php");

if (isset($_POST['login'])) {
	// User trying to log in.
	// Reject DOS attacks by throttling
	$stmt = $db->query("SELECT * FROM logins WHERE INET_NTOA(IP)='{$_SERVER['REMOTE_ADDR']}' AND TIMESTAMPDIFF(SECOND, timestamp, NOW()) < {$cfg['DoSDelay']} AND NOT success");
	if ($stmt->rowCount() > $cfg['DoSCount']) {
		// Too many attempts. We do not even bother to log this to login log.
		$message = "För många inloggningsförsök.";
	} else {
		// Check member ID (or personnummer) and password via API
		if (login($_POST['ID'], $_POST['password'])) {
			$db->exec("INSERT INTO logins (IP, success) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), 1)");
			// If requested, set persistent login cookie
			if (isset($_POST['rememberme'])) createPersistentAuth($_SESSION['user']['ID']);
			// Redirect if requested by login form
			if ($_POST['redirect']) {
				header("Location: {$_POST['redirect']}");
				die();
			} else {
				// TODO: go through user's roles and make a list of the lokalavdelningar s/he is engaged in.
				// If it's only one LA and without admin role, redirect to the booking page of that LA.
				// If it's several LAs and/or with admin role, show a list of choices (see further down).
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
	removePersistentAuth($user['ID']);
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


?><!DOCTYPE html>
<html>
<head>
	<?php htmlHead("Friluftsfrämjandets resursbokning") ?>

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
</head>


<body>
<div data-role="page" id="start">
	<?= head("Resursbokning") ?>

	<div data-role="popup" data-overlay-theme="b" id="popupMessage" class="ui-content">
		<p><?= isset($message) ? $message : "" ?></p>
		<?= isset($dontShowOK) ? "" : "<a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>" ?>
	</div>

	<?php
	if (isset($_SESSION['user']['ID'])) { ?>
		<a href="boka.php" class="ui-btn">Boka utrustning</a>
		<?= $_SESSION['user']['role'] != "user" ? "<a href='la.php' class='ui-btn' data-ajax='false'>Hantera LA</a>" : "" ?>
		<?= $_SESSION['user']['role'] == "admin" ? "<a href='admin.php' class='ui-btn' data-ajax='false'>Admin</a>" : "" ?>
	<?php }

	else { ?>
		<form id="formLogin" style="padding:10px 20px;" data-ajax="false" method="POST" action="index.php">
			<h3>Inloggning</h3>
			<input type="hidden" name="redirect" id="loginRedirect" value="">
			<input name="ID" value="" placeholder="medlems- eller personnr" required>
			<input name="password" value="" placeholder="Lösenord" type="password">
			<div id="divRememberme" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>"><label><input data-mini='true' name='rememberme' value='1' type='checkbox'> Kom ihåg mig</label></div>
			<button name="login" value="login" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">Logga in</button>
		</form>
	<?php } ?>

</div>
</body>
</html>
