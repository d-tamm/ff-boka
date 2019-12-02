<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/class.ffboka.php";
require_once __DIR__ . "/class.item.php";
require_once __DIR__ . "/class.user.php";
require_once __DIR__ . "/class.section.php";
require_once __DIR__ . "/class.image.php";
require_once __DIR__ . "/class.booking.php";
require_once __DIR__ . "/class.question.php";
require_once __DIR__ . "/config.php";
global $cfg;

// Set locale
setlocale(LC_ALL, $cfg['locale']);
setlocale(LC_NUMERIC, "en_US.utf8");

// Load mail functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use FFBoka\FFBoka;
use FFBoka\User;
//require 'PHPMailer/src/Exception.php';
//require 'PHPMailer/src/PHPMailer.php';
//require 'PHPMailer/src/SMTP.php';

// Connect to database
$db = new PDO("mysql:host={$cfg['dbhost']};dbname={$cfg['dbname']};charset=utf8", $cfg['dbuser'], $cfg['dbpass']);

// Create FF object
$FF = new FFBoka($cfg['apiUrl'], $db, $cfg['sectionAdmins']);

// Check if there is a persistent login cookie
//https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
if (!$_SESSION['authenticatedUser'] && !empty($_COOKIE['remember'])) {
	list($selector, $authenticator) = explode(':', $_COOKIE['remember']);
	$stmt = $db->prepare("SELECT * FROM tokens WHERE useFor='persistent login' AND token=?");
	$stmt->execute(array($selector));
	if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (hash_equals($row['data'], hash('sha256', base64_decode($authenticator)))) {
			// User authenticated.
			$_SESSION['authenticatedUser'] = $row['forId'];
			// Regenerate login token
			createPersistentAuth($row['forId']);
		}
	}
}


function createPersistentAuth($userId) {
	//https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
	global $db, $cfg;
	// Remove old token
	removePersistentAuth($userId);
	// Create token
	$selector = base64_encode(random_bytes(15));
	$authenticator = random_bytes(40);
	// Send token as cookie to browser
	setcookie(
		'remember',
		$selector.':'.base64_encode($authenticator),
		time() + $cfg['persistLogin'],
		dirname($_SERVER['SCRIPT_NAME']),
		$_SERVER['SERVER_NAME'],
		true, // TLS-only
		true  // http-only
	);
	// Save token to database
	$stmt = $db->prepare("INSERT INTO tokens (token, data, forId, ttl, usefor) VALUES (:token, :data, :forId, :ttl, 'persistent login')");
	$stmt->execute(array(
		":token"=>$selector,
		":data"=>hash('sha256', $authenticator),
		":forId"=>$userId,
		":ttl"=>$cfg['persistLogin']
	));
}

function removePersistentAuth($userId) {
	// Removes cookie and database token for persistent login ("Remember me")
	global $db;
	setcookie(
		'remember',
		'',
		time() - 3600,
		dirname($_SERVER['SCRIPT_NAME']),
		$_SERVER['SERVER_NAME'],
		true, // TLS-only
		true  // http-only
	);
	$db->exec("DELETE FROM tokens WHERE usefor='persistent login' AND forId=$userId");
}	


function createToken($use, $forId, $data="", $ttl=86400) {
	// Creates a new one-time token
	global $db;
	for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a{$x}, $i++);
	$stmt = $db->prepare("REPLACE INTO tokens SET token=SHA1('$token'), ttl=$ttl, usefor='$use', forId=$forId, data=:data");
	if (!$stmt->execute(array(":data"=>$data))) {
		$e = $db->errorInfo();
		return("Ett fel har uppstått." . $e[2]);
	}
	return($token);
}


/**
 * Output the file headers for HTML pages (title, meta tags, common stylesheets, jquery)
 * @param string $title
 */
function htmlHeaders(string $title) { 
	// output meta tags and include stylesheets, jquery etc	?>
	<title><?= $title ?></title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" />
	<link rel="stylesheet" href="/css/themes/ff-boka.css" />
	<link rel="stylesheet" href="/css/ff-boka.css" />
	<link rel="stylesheet" href="/css/themes/jquery.mobile.icons.min.css" />
	<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<?php
}

/**
 * Output HTML code for the common page heading and side panel
 * @param string $caption
 * @param User $currentUser The currently logged in user
 */
function head(string $caption, $currentUser=NULL) {
	// Declare side panel
	?>
	<div data-role="panel" data-theme="b" data-position-fixed="true" data-display="push" id="navpanel">
		<ul data-role="listview">
			<li data-icon="home"><a href="/index.php" data-ajax="false" data-rel="close">Startsida</a></li><?php
			if ($_SESSION['authenticatedUser']) { ?>
				<li data-icon="user"><a href="/userdata.php" data-rel="close" data-ajax="false"><?= htmlspecialchars($currentUser->name) ?></a></li>
				<li data-icon="power"><a href="/index.php?logout" data-rel="close" data-ajax="false">Logga ut</a></li><?php
			} ?>
			<li data-icon="info"><a href="help.php" data-rel="close">Hjälp</a></li>
			<li data-icon="info"><a href="cookies.php" data-rel="close">Om kakor (cookies)</a></li>
		</ul>
	</div><!-- /panel -->
	
	<div data-role="header">
		<H1><?= $caption ?></H1>
		<a href="#navpanel" data-rel="popup" data-transition="pop" data-role="button" data-icon="bars" data-iconpos="notext" class="ui-btn-left ui-nodisc-icon ui-alt-icon">Menu</a>
		<?php 
		switch ($_SERVER['PHP_SELF']) {
		case "/admin/category.php": $href="/admin"; $transition="slidedown"; $icon="back"; break;
		case "/admin/item.php": $href="/admin/category.php?expand=items"; $transition="slidedown"; $icon="back"; break;
		case "/subbooking.php": $href="javascript:history.back();"; $transition="slidedown"; $icon="back"; break;
		default: $href="/index.php"; $icon="home"; $transition="slidedown";
		}
		echo "<a href='$href' data-transition='$transition' data-ajax='false' data-role='button' data-icon='$icon' data-iconpos='notext' class='ui-btn-right ui-nodisc-icon ui-alt-icon'></a>";
		if (!isset($_COOKIE['cookiesOK'])) { // Display cookie chooser ?>
			<div id="divCookieConsent" data-theme='b' class='ui-bar ui-bar-b' style='font-weight:normal;'>
				För att vissa funktioner på denna webbplats ska fungera använder vi kakor. <a href='cookies.php' data-role='none'>Läs mer om kakor.</a><br>
				<button onClick="var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='cookiesOK=1; expires='+d.toUTCString()+'; Path=/'; $('#divCookieConsent').hide(); $('#div-remember-me').show();">Tillåt kakor</button>
				<button onClick="document.cookie='cookiesOK=0; path=/'; $('#divCookieConsent').hide();$('#div-remember-me').hide();">Avböj kakor</button>
			</div>
		<?php } ?>
	</div>
	
	<?php
}


/**
 * Send an email basen on a template file
 * @param string $from
 * @param string $to
 * @param string $replyTo If empty, the $from address will be used.
 * @param string $subject
 * @param string[] $options Options for SMTP connection: array(host, port, user, pass)
 * @param string $template Name of template file to use. The file must be in the templates folder.
 * There must be at least a file named $template.html. Optionally, $template.txt (if exists) will
 * be used as non-HTML body. Otherwise, the function will try to strip off the tags from the html file.
 * @param array $replace [ search=>replace ] Array of strings to be replaced
 * @return boolean|string TRUE on success, error message on failure
 */
function sendmail(string $from, string $to, string $replyTo, string $subject, $options, $template, $replace=NULL) {
	if (is_readable("templates/$template.html")) {
		// Get template content
		$body = file_get_contents("templates/$template.html");
		$altBody = is_readable("templates/$template.txt")
			? file_get_contents("templates/$template.txt")
			: str_replace(array("</p>", "<br>"), array("</p>\r\n\r\n", "\r\n"), strip_tags($body)); // TODO: this is probably buggy
		// Replace placeholders
		if (!is_null($replace)) {
		    foreach ($replace as $s=>$r) {
        		$body = str_replace($s, $r, $body);
                $altBody = str_replace($s, $r, $altBody); // TODO: what about html code in $replace?
		    }
	    }
	}
	else $body = $template;
	// Send mail
	$mail = new PHPMailer(true);
	try {
		//Server settings
		$mail->SMTPDebug = 0;
		$mail->isSMTP();
		$mail->Host = $options['host'];
		$mail->Port = $options['port'];
		$mail->SMTPAuth = true;
		$mail->Username = $options['user'];
		$mail->Password = $options['pass'];
		$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
		// Message content
		$mail->CharSet ="UTF-8";
		$mail->setFrom($from);
		$mail->addAddress($to);
		if ($replyTo) $mail->addReplyTo($replyTo);
		if (isset($altBody)) $mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $body;
		if (isset($altBody)) $mail->AltBody = $altBody;
		$mail->send();
		return true;
	} catch (Exception $e) {
		throw \Exception("Mailer Error: ".$mail->ErrorInfo);
	}
}

/**
 * Get html code for an embedded image tag. 
 * @param string $data Image data
 * @param string $overlay Name of overlay image file
 * @return string HTML img tag with embedded base64 encoded data
 */
function embedImage($data, $overlay="") {
	// Returns string for embedded img tag.
	if (!in_array($overlay, array("accepted", "rejected", "new"))) $overlay=""; // TODO: This comes from another project. If not needed, remove it.
	if (!$data) $data = file_get_contents(__DIR__."/../resources/noimage.png");
	$info = getimagesizefromstring($data);
	if ($overlay) {
		$imgOverlay = imagecreatefrompng(__DIR__."/img/overlay_$overlay.png");
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

/**
 * Get an html href mailto link where address is obfuscated for spam protection.
 * @param string $to Mailto target address
 * @param string $subject Subject to pass to the mail program
 * @return string HTML A href mailto link
 */
function obfuscatedMaillink(string $to, string $subject="") {
	// Obfuscates email addresses.
	$id = "obfmail".substr(sha1($to), 0, 8);
	return "<span id='$id'></span><script>$('#$id').html(\"<a href='mailto:\" + atob('" . base64_encode($to) . "') + \"" . ($subject ? "?subject=".rawurlencode($subject) : "") . "'>\"+atob('" . base64_encode($to) . "')+\"</a>\");</script>";
}
