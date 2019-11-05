<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once "config.php";
global $cfg;

// Set locale
setlocale(LC_ALL, $cfg['locale']);
setlocale(LC_NUMERIC, "en_US.utf8");

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
if (empty($_SESSION['user']['userID']) && !empty($_COOKIE['remember'])) {
	list($selector, $authenticator) = explode(':', $_COOKIE['remember']);
	$stmt = $db->prepare("SELECT * FROM tokens WHERE usefor='persistent login' AND token=?");
	$stmt->execute(array($selector));
	if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (hash_equals($row['data'], hash('sha256', base64_decode($authenticator)))) {
			// User authenticated. Get additional user data from database
			$stmt = $db->query("SELECT * FROM users WHERE userID={$row['forID']}");
			if ($stmt->rowCount()) $_SESSION['user'] = $stmt->fetch(PDO::FETCH_ASSOC);
			else $_SESSION['user'] = array("userID"=>$row['forID']);
			// Get user's home section
			$_SESSION['user']['sectionID'] = getUserHome($row['forID']);
			// Get assignments from API.
			$_SESSION['user']['assignments'] = getUserAssignments($row['forID']);
			// Regenerate login token
			createPersistentAuth($row['forID']);
		}
	}
}

/* function getApiToken($userID, $password) {
	// Fetches a new user token from API. Returns an array.
	// On success (correct credentials): [ access_token, expires_in, userName ], where userName is the memberID
	// On failure (e.g. wrong credentials): [ error="invalid_grant", error_description ]
	// TODO: This method is deprecated since it fetches a token from the API giving elevated access
	// to user data at FF. Here, we just want to verify the password, don't need the token.
	global $cfg;
	$data = array(
		'username' => $userID,
		'password' => $password,
		'grant_type' => 'password'
	);
	$options = array('http' => array(
		'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
		'method'  => 'POST',
		'content' => http_build_query($data)
	));
	$context  = stream_context_create($options);
	$result = file_get_contents($cfg['apiUrl']."/token", false, $context);
	// Sample response: {"access_token":"xxxx","token_type":"bearer","expires_in":28799,"userName":"864015","contactId":"xxxx","impersonateAs":"xxxx","authorizationLevel":"1",".issued":"Thu, 10 Oct 2019 12:29:05 GMT",".expires":"Thu, 10 Oct 2019 20:29:05 GMT"}
	// If wrong credentials: {"error":"invalid_grant","error_description":"The user name or password is incorrect."}
	if ($result === FALSE) { 
		return array("error"=>"Generic error", "error_description"=>"Failed to verify credentials. Please try again later.");
	} else {
		return json_decode($result, true);
	}
} */


function authenticateByAPI($userID, $password) {
	// Authenticates the given user data by querying the FF API.
	// Returns false on failure, and the member number on success.
	// TODO: Currently, there is no such function in the API. Hope this comes in early 2020.
	//global $cfg;
	return $userID;
	return false;
}

function getUserHome($userID) {
	// Returns the home section ID of the user from the FF API.
	// If user is unknown in the API, returns 0.
	// TODO: replace by call to API feed (which does not yet exist?).
	//global $cfg;
	return 52;
}

function getUserAssignments($userID) {
	// Returns the user's assignments from the FF API as an array(name, sectionID, typeID)
	global $cfg;
	$assignments = array();
	$data = json_decode(file_get_contents("{$cfg['apiUrl']}/api/feed/Pan_Extbokning_GetAssingmentByMemberNoOrSocSecNo?MNoSocnr=$userID"));
	foreach ($data->results as $ass) {
		$assignments[] = array(
			'name'  => $ass->cint_assignment_type_id->name,
			'sectionID' => $ass->section__cint_nummer,
			'typeID'  => $ass->cint_assignment_party_type->value,
		);
	}
	return $assignments;
}

function login($ID, $password) {
	// Checks user data via aktivitetshanteraren's API.
	// Returns true on success, false otherwise.
	// On success, sets $_SESSION['user']
	global $db;
	if ($apiResult = authenticateByAPI($ID, $password)) {
		// User authenticated. Get additional user data from database
		$stmt = $db->query("SELECT * FROM users WHERE userID=$apiResult");
		if ($stmt->rowCount()) $_SESSION['user'] = $stmt->fetch(PDO::FETCH_ASSOC);
		else $_SESSION['user'] = array("userID"=>$apiResult);
		// Get user's home section
		$_SESSION['user']['sectionID'] = getUserHome($apiResult);
		$_SESSION['user']['sectionID'] = (int)$password+0; // TODO: remove this line after test phase.
		// Get assignments from API.
		$_SESSION['user']['assignments'] = getUserAssignments($apiResult);
		if ((int)$password) $_SESSION['user']['assignments'][] = array('name'=>'Ordförande','sectionID'=>(int)$password,'typeID'=>478880001);  // TODO: remove this line after test phase.
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
		dirname($_SERVER['SCRIPT_NAME']),
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
		dirname($_SERVER['SCRIPT_NAME']),
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


function isSectionAdmin($sectionID) {
	// Checks if user has admin permission in current section
	global $db, $cfg;
	if (!$_SESSION['user']['assignments']) return false; // user must have assignments
	if (!count($assInSection = userAssignmentsInSection($sectionID))) return false; // User does not have any assignments in this section.
	if (count(array_intersect($assInSection, $cfg['sectionAdmins']))) return true; // User is section admin by cfg setting
	$stmt = $db->query("SELECT sectionID FROM section_admins WHERE sectionID=$sectionID AND (ass_name='" . implode("' OR ass_name='", $assInSection) . "')");
	if ($stmt->rowCount()) return true; // User has at least one assignment which is set as admin assignment for this section.
	return false; // None of the user's assignments is set as admin assignment for this section.
}


function secHasAccessibleCats($sectionID) {
	// Returns whether the section has categories where the current user has at least read access
	global $db;
	$assInSection = userAssignmentsInSection($sectionID);
	// Go through categories in section
	$stmt = $db->query("SELECT catID, access_member, access_local_member FROM categories WHERE sectionID=$sectionID");
	while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if ($cat['access_member']) return true;
		if ($cat['access_local_member'] && $sectionID==$_SESSION['user']['sectionID']) return true;
		$stmt2 = $db->query("SELECT * FROM cat_access WHERE catID={$cat['catID']} AND (ass_name='" . implode("' OR ass_name='", $assInSection) . "')");
		if ($stmt2->rowCount()) return true;
	}
	return false;
}


function catAccess($catID) {
	// Returns the access level of this category
	global $db;
	// Go through categories in section
	$stmt = $db->query("SELECT sectionID, access_external, access_member, access_local_member FROM categories WHERE catID=$catID");
	$cat = $stmt->fetch(PDO::FETCH_ASSOC);
	$maxAccess = $cat['access_external'];
	if ($_SESSION['user']['userID']) $maxAccess = max($maxAccess, $cat['access_member']);
	if ($cat['sectionID']==$_SESSION['user']['sectionID']) $maxAccess = max($maxAccess, $cat['access_local_member']);
	$assInSection = userAssignmentsInSection($cat['sectionID']);
	$stmt2 = $db->query("SELECT cat_access FROM cat_access WHERE catID=$catID AND (ass_name='" . implode("' OR ass_name='", $assInSection) . "')");
	while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
		$maxAccess = max($maxAccess, $row['cat_access']);
	}
	return $maxAccess;
}


function userAssignmentsInSection($sectionID) {
	// Get all assignments the current user has in the section
	if (!isset($_SESSION['user']['assignments'])) return array();
	return array_column(
		array_filter($_SESSION['user']['assignments'], function($ass) use ($sectionID) {
			return $ass['typeID']==TYPE_SECTION && $ass['sectionID']==$sectionID;
		}),
		'name'
	);
}

function sectionName($sectionID) {
	global $db;
	$stmt = $db->query("SELECT name FROM sections WHERE sectionID=$sectionID");
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row['name'];
}

function htmlHeaders($title) { 
	// output meta tags and include stylesheets, jquery etc	?>
	<title><?= $title ?></title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" />
	<link rel="stylesheet" href="css/themes/ff-boka.css" />
	<link rel="stylesheet" href="css/ff-boka.css" />
	<link rel="stylesheet" href="css/themes/jquery.mobile.icons.min.css" />
	<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<?php
}


function head($caption) {
	// Declare side panel
	?>
	<div data-role="panel" data-theme="b" data-position-fixed="true" data-display="push" id="navpanel">
		<ul data-role="listview">
			<li data-icon="home"><a href="index.php" data-rel="close">Startsida</a></li><?php
			if (isset($_SESSION['user']['userID'])) { ?>
				<li data-icon="user"><a href="userdata.php" data-rel="close" data-ajax="false"><?= $_SESSION['user']['name'] ?></a></li>
				<li data-icon="bullets"><a href="myitems.php" data-rel="close" data-ajax="false">Min utrustning</a></li>
				<?= $_SESSION['user']['role']==="la_admin" || $_SESSION['user']['role']==="admin" ? "<li data-icon='lock'><a href='la_admin.php' data-rel='close' data-ajax='false'>LA-Admin</a></li>" : "" ?>
				<?= $_SESSION['user']['superAdmin'] ? "<li data-icon='lock'><a href='admin.php' data-rel='close' data-ajax='false'>Admin</a></li>" : "" ?>
				<li data-icon="power"><a href="index.php?logout" data-rel="close" data-ajax="false">Logga ut</a></li><?php
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
		case "/category.php": $href="admin.php"; $transition="slidedown"; $icon="back"; break;
		case "/item.php": $href="category.php?expand=items"; $transition="slidedown"; $icon="back"; break;
		default: $href="index.php"; $icon="home"; $transition="slidedown";
		}
		echo "<a href='$href' data-transition='$transition' data-ajax='true' data-role='button' data-icon='$icon' data-iconpos='notext' class='ui-btn-right ui-nodisc-icon ui-alt-icon'></a>";
		if (!isset($_COOKIE['cookiesOK'])) { // Display cookie chooser ?>
			<div id="divCookieConsent" data-theme='b' class='ui-bar ui-bar-b' style='font-weight:normal;'>
				För att vissa funktioner på denna webbplats ska fungera använder vi kakor. <a href='cookies.php' data-role='none'>Läs mer om kakor.</a><br>
				<button onClick="var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='cookiesOK=1; expires='+d.toUTCString()+'; Path=/'; $('#divCookieConsent').hide(); $('#divRememberme').show();">Tillåt kakor</button>
				<button onClick="document.cookie='cookiesOK=0; path=/'; $('#divCookieConsent').hide();$('#divRememberme').hide();">Avböj kakor</button>
			</div>
		<?php } ?>
	</div>
	
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

function getUploadedImage($file, $maxsize=800, $thumbsize=80) {
    global $cfg;
	// Gets an uploaded image file, resizes it, makes a thumbnail, and returns both versions as strings.
	if (is_uploaded_file($file['tmp_name'])) {
		// reject files that are too big
		if (filesize($file['tmp_name'])>$cfg['uploadMaxFileSize']) return false;
		// Get the picture and its size
		$src = imagecreatefromstring(file_get_contents($file['tmp_name']));
		$size = getimagesize($file['tmp_name']);
		$ratio = $size[0]/$size[1];
		// Rescale to max. $maxsize px
		if ($ratio > 1) {
			$tmp = imagecreatetruecolor($maxsize, $maxsize/$ratio);
			imagecopyresampled($tmp, $src, 0, 0, 0, 0, $maxsize, $maxsize/$ratio, $size[0], $size[1]);
		}
		else {
			$tmp = imagecreatetruecolor($maxsize*$ratio, $maxsize);
			imagecopyresampled($tmp, $src, 0, 0, 0, 0, $maxsize*$ratio, $maxsize, $size[0], $size[1]);
		}
		// Get rescaled jpeg picture as string
		ob_start();
		imagejpeg($tmp);
		$image = ob_get_clean();
		// Make a square thumbnail
		$tmp = imagecreatetruecolor($thumbsize, $thumbsize);
		$bg = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
		imagefill($tmp, 0, 0, $bg);
		if ($ratio>1) imagecopyresampled($tmp, $src, $thumbsize/2*(1-1*$ratio), 0, 0, 0, $thumbsize*$ratio, $thumbsize, $size[0], $size[1]);
		else imagecopyresampled($tmp, $src, 0, $thumbsize/2*(1-1/$ratio), 0, 0, $thumbsize, $thumbsize/$ratio, $size[0], $size[1]);
		// Get thumbnail as string
		ob_start();
		imagepng($tmp);
		$thumb = ob_get_clean();
		return array("image"=>$image, "thumb"=>$thumb);
	} else {
		return false;
	}
}


function embedImage($data, $overlay="") {
	// Returns string for embedded img tag.
	if (!in_array($overlay, array("accepted", "rejected", "new"))) $overlay="";
	if (!$data) $data = file_get_contents("resources/noimage.png");
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

function bookingBar($itemID, $start, $range="week") {
	global $db;
	$stmt = $db->query("SELECT UNIX_TIMESTAMP(start) start, UNIX_TIMESTAMP(end) end FROM `booked_items` INNER JOIN subbookings USING (subbookingID) WHERE itemID=$itemID"); // TODO: limit query to only relevant rows.
	$ret = "<div style='width:100%; height:20px; position:relative; background-color:#D0BA8A; font-weight:normal; font-size:small;'>";
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ret .= "<div style='position:absolute; top:0px; height:100%; left:" . (($row['start']-$start)/6048) . "%; width:" . (($row['end']-$row['start'])/6048) . "%; background-color:#E84F1C;'></div>\n";		
	}
	for ($day=1; $day<7; $day++) {
		$ret .= "<div style='position:absolute; top:0px; height:100%; left:" . (100/7*$day) . "%; border-left:1px solid #54544A;'></div>\n";
	}
	$ret .= "</div>";
	return $ret;
}

function obfuscatedMaillink($to, $subject="") {
	// Obfuscates email addresses.
	$id = "obfmail".substr(sha1($to), 0, 8);
	return "<span id='$id'></span><script>$('#$id').html(\"<a href='mailto:\" + atob('" . base64_encode($to) . "') + \"" . ($subject ? "?subject=".rawurlencode($subject) : "") . "'>\"+atob('" . base64_encode($to) . "')+\"</a>\");</script>";
}
