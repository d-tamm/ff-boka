<?php
session_start();
require("common.php");

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
			if (isset($_POST['rememberme'])) createPersistentAuth($_SESSION['user']['ID']);
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


if ($_SESSION['user']['ID'] && (!isset($_SESSION['user']['name']) || !isset($_SESSION['user']['mail']) || !isset($_SESSION['user']['phone']))) {
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

	<?php if (isset($_SESSION['user']['ID'])) { ?>
		
		<div data-role='collapsibleset' data-inset='false'>
		
			<div data-role='collapsible' data-collapsed='false'>
			<h3>Boka</h3>
			<?php
			// Make a list of all sections where user may book equipment
			
			?>
			<a href="boka.php" class="ui-btn">Boka</a>
			</div>
			
			<?php
			// Make a list of all sections where user has admin role
			if ($_SESSION['user']['assignments']) {
				$where = array();
				$union = "";
				foreach ($_SESSION['user']['assignments'] as $ass) {
					if ($ass['typeID']==478880001) { $where[] = "(name='{$ass['party']}' AND ass_name='{$ass['name']}')"; }
					// Add result rows for section admin by cfg setting
					if ($ass['typeID']==478880001 && in_array($ass['name'], $cfg['sectionAdmins'])) {
						$union .= " UNION DISTINCT SELECT sectionID, name FROM sections WHERE name='{$ass['party']}'";
					}
				}
				$stmt = $db->query("SELECT DISTINCTROW sectionID, name FROM section_admins INNER JOIN sections USING (sectionID) WHERE " . implode(" OR ", $where) . "$union ORDER BY name");
				if ($stmt->rowCount()) {
					echo "<div data-role='collapsible' data-collapsed='true'>";
					echo "<h3>Administrera</h3>";
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						echo "<a href='admin.php?sectionID={$row['sectionID']}&sectionName={$row['name']}' class='ui-btn'>{$row['name']}</a>";
					}
					echo "</div>";
				}
			} ?>
		</div><!-- /collapsibleset -->

		<p><pre><?php print_r($_SESSION['user']); ?></pre></p>

	<?php } else { ?>

		<form id="formLogin" style="padding:10px 20px;" data-ajax="false" method="POST" action="index.php">
			<h3>Inloggning</h3>
			<p>Du loggar in med samma lösenord som i aktivitetshanteraren.</p>
			<input type="hidden" name="redirect" id="loginRedirect" value="">
			<input name="ID" value="" placeholder="medlems- eller personnr" required>
			<input name="password" value="" placeholder="Lösenord" type="password">
			<div id="divRememberme" style="<?= empty($_COOKIE['cookiesOK']) ? "display:none;" : "" ?>"><label><input data-mini='true' name='rememberme' value='1' type='checkbox'> Kom ihåg mig</label></div>
			<button name="login" value="login" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-right ui-icon-user">Logga in</button>
			<button name="guest" value="guest" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-right ui-icon-arrow-r">Boka som gäst</button>
		</form>

	<?php } ?>
	
	</div><!--/main-->

</div><!--/page>
</body>
</html>
