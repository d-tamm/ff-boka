<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once "config.php";

// Set locale
setlocale(LC_ALL, $cfg['locale']);

// Load mail functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//require 'PHPMailer/src/Exception.php';
//require 'PHPMailer/src/PHPMailer.php';
//require 'PHPMailer/src/SMTP.php';

// Connect to database
$db = new PDO("mysql:host={$cfg['dbhost']};dbname={$cfg['dbname']};charset=utf8", $cfg['dbuser'], $cfg['dbpass']);


// Check if there is a persistent login cookie
//https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
// TODO: adapt to FF's API (update data from API each time)
if (empty($_SESSION['user']['ID']) && !empty($_COOKIE['remember'])) {
	list($selector, $authenticator) = explode(':', $_COOKIE['remember']);
	$stmt = $db->prepare("SELECT * FROM tokens WHERE usefor='persistent login' AND token = ?");
	$stmt->execute(array($selector));
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (hash_equals($row['data'], hash('sha256', base64_decode($authenticator)))) {
		$_SESSION['user']['ID'] = $row['forID'];
		// Regenerate login token
		createPersistentAuth($row['forID']);
	}
}


function login($ID, $password) {
	// Checks user data via aktivitetshanteraren's API.
	// Returns true on success, false otherwise.
	// On success, sets $_SESSION['user']
	global $db;
	if (true) { // TODO: call API
		// User authenticated.
		// Get access level from local DB
		$stmt = $db->query("SELECT * FROM users WHERE ID=$ID"); // TODO: change to ID from API
		if ($stmt->rowCount()) { $access = $stmt->fetch(PDO::FETCH_ASSOC); }
		// Get additional LAs from local DB
		$la = ["Mölndal"]; // TODO: change to LA from API
		$stmt = $db->query("SELECT * FROM user_la WHERE userID=$ID"); // TODO: change to ID from API
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $la[] = $row['laID']; }
		// Remember data
		$_SESSION['user'] = array(
			name => "Daniel Tamm",
			ID   => 864015,
			LA   => $la,
			mail => "daniel.tamm@friluftsframjandet.se",
			role => "admin"//$access['role']
		);
		return true;
	}
	else {
		$_SESSION['user'] = array();
		return false;
	}
}


function createPersistentAuth($userID) {
	//https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
	global $db, $cfg;
	// Remove old token
	removePersistentAuth($userID);
	// Create token
	$selector = base64_encode(random_bytes(15));
	$authenticator = random_bytes(40);
	// Send token as cookie to browser
	setcookie(
		'remember',
		$selector.':'.base64_encode($authenticator),
		time() + $cfg['persistLogin'],
		dirname($_SERVER['SCRIPT_NAME']) . "/",
		$_SERVER['SERVER_NAME'],
		true, // TLS-only
		true  // http-only
	);
	// Save token to database
	$stmt = $db->prepare("INSERT INTO tokens (token, data, forID, ttl, usefor) VALUES (:token, :data, :forID, :ttl, 'persistent login')");
	$stmt->execute(array(
		":token"=>$selector,
		":data"=>hash('sha256', $authenticator),
		":forID"=>$userID,
		":ttl"=>$cfg['persistLogin']
	));
}

function removePersistentAuth($userID) {
	// Removes cookie and database token for persistent login ("Remember me")
	global $db;
	setcookie(
		'remember',
		'',
		time() - 3600,
		dirname($_SERVER['SCRIPT_NAME']) . "/",
		$_SERVER['SERVER_NAME'],
		true, // TLS-only
		true  // http-only
	);
	$db->exec("DELETE FROM tokens WHERE usefor='persistent login' AND forID=$userID");
}	


function createToken($use, $forID, $data="", $ttl=86400) {
	// Creates a new one-time token
	global $db;
	for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a{$x}, $i++);
	$stmt = $db->prepare("REPLACE INTO tokens SET token=SHA1('$token'), ttl=$ttl, usefor='$use', forID=$forID, data=:data");
	if (!$stmt->execute(array(":data"=>$data))) {
		$e = $db->errorInfo();
		return("Ett fel har uppstått." . $e[2]);
	}
	return($token);
}


function htmlHead($title) { 
	// output meta tags and include stylesheets, jquery etc	?>
	<title><?= $title ?></title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" />
	<link rel="stylesheet" href="css/themes/ff-boka.css" />
	<link rel="stylesheet" href="css/themes/jquery.mobile.icons.min.css" />
	<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
	<?php
}


function head($caption) {
	// Declare side panel
	?>
	<div data-role="panel" data-theme="b" data-position-fixed="true" data-display="push" id="navpanel">
		<ul data-role="listview">
			<li data-icon="home"><a href="index.php" data-rel="close">Startsida</a></li><?php
			if (isset($_SESSION['user']['ID'])) { ?>
				<li>Inloggad som <?= $_SESSION['user']['name'] ?></li>
				<li data-icon="bullets"><a href="myitems.php" data-rel="close" data-ajax="false">Min utrustning</a></li>
				<?= $_SESSION['user']['role']==="la_admin" || $_SESSION['user']['role']==="admin" ? "<li data-icon='lock'><a href='la_admin.php' data-rel='close' data-ajax='false'>LA-Admin</a></li>" : "" ?>
				<?= $_SESSION['user']['role']==="admin" ? "<li data-icon='lock'><a href='admin.php' data-rel='close' data-ajax='false'>Admin</a></li>" : "" ?>
				<li data-icon="power"><a href="index.php?logout" data-rel="close" data-ajax="false">Logga ut</a></li><?php
			} ?>
			<li data-icon="info"><a href="help.php" data-rel="close" data-ajax="false">Hjälp</a></li>
			<li data-icon="info"><a href="cookies.php" data-rel="close" data-ajax="false">Om kakor (cookies)</a></li>
		</ul>
	</div><!-- /panel -->
	
	<div data-role="header">
		<H1><?= $caption ?></H1>
		<a href="#navpanel" data-rel="popup" data-transition="pop" data-role="button" data-icon="bars" data-iconpos="notext" class="ui-btn-left ui-nodisc-icon ui-alt-icon">Menu</a>
		<a href="index.php" data-ajax="false" data-role="button" data-icon="home" data-iconpos="notext" class="ui-btn-right ui-nodisc-icon ui-alt-icon"></a>
		<?php if (!isset($_COOKIE['cookiesOK'])) { ?>
			<div id="divCookieConsent" data-theme='b' class='ui-bar ui-bar-b' style='font-weight:normal;'>
				För att vissa funktioner på denna webbplats ska fungera använder vi kakor. <a href='cookies.php' data-role='none'>Läs mer om kakor.</a><br>
				<button onClick="var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='cookiesOK=1; expires='+d.toUTCString()+'; Path=/'; $('#divCookieConsent').hide(); $('#divRememberme').show();">Tillåt kakor</button>
				<button onClick="document.cookie='cookiesOK=0; path=/'; $('#divCookieConsent').hide();$('#divRememberme').hide();">Avböj kakor</button>
			</div>
		<?php } ?>
	</div>
	
	<div role="main" class="ui-content">
	<?php
}



function sendmail($to, $subject, $template, $search=NULL, $replace=NULL) {
	// Sends an email based on a template file.
	global $cfg;
	if (is_readable("templates/$template.html")) {
		// Get template content
		$body = file_get_contents("templates/$template.html");
		$altBody = is_readable("templates/$template.txt")
			? file_get_contents("templates/$template.txt")
			: str_replace(array("</p>", "<br>"), array("</p>\r\n\r\n", "\r\n"), strip_tags($body));
		// Replace placeholders
		$body = str_replace($search, $replace, $body);
		$altBody = str_replace($search, $replace, $altBody);
	}
	else $body = $template;
	// Send mail
	$mail = new PHPMailer(true);
	try {
		//Server settings
		$mail->SMTPDebug = 0;
		$mail->isSMTP();
		$mail->Host = $cfg['SMTPHost'];
		$mail->Port = $cfg['SMTPPort'];
		$mail->SMTPAuth = true;
		$mail->Username = $cfg['SMTPUsername'];
		$mail->Password = $cfg['SMTPPassword'];
		$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
		// Message content
		$mail->CharSet ="UTF-8";
		$mail->setFrom($cfg['mailFrom']);
		$mail->addAddress($to);
		$mail->addReplyTo($cfg['mailReplyTo']);
		if (isset($altBody)) $mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $body;
		if (isset($altBody)) $mail->AltBody = $altBody;
		$mail->send();
		return true;
	} catch (Exception $e) {
		return "Mailer Error: ".$mail->ErrorInfo;
	}
}


function embed_image($data, $overlay="") {
	// Returns string for embedded img tag.
	if (!in_array($overlay, array("accepted", "rejected", "new"))) $overlay="";
	if (!$data) $data = file_get_contents("img/noimage.png");
	$info = getimagesizefromstring($data);
	if ($overlay) {
		$imgOverlay = imagecreatefrompng("img/overlay_$overlay.png");
		$image = imagecreatefromstring($data);
		imagecopy($image, $imgOverlay, 0, 0, 0, 0, $info[0], $info[1]);
		// Convert to string
		ob_start();
		imagepng($image, NULL);
		$data = ob_get_contents();
		ob_end_clean();		
	}
	return("<img src='data:" . $info['mime'] . ";base64," . base64_encode($data) . "'>");
}


function obfuscated_maillink($to, $subject="") {
	// Obfuscates email addresses.
	$id = "obfmail".substr(sha1($to), 0, 8);
	return "<span id='$id'></span><script>$('#$id').html(\"<a href='mailto:\" + atob('" . base64_encode($to) . "') + \"" . ($subject ? "?subject=".rawurlencode($subject) : "") . "'>\"+atob('" . base64_encode($to) . "')+\"</a>\");</script>";
}
