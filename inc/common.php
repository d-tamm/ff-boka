<?php
require_once __DIR__ . "/../vendor/autoload.php";
spl_autoload_register(function($class) {
    include __DIR__ . "/" . strtolower(str_replace("\\", "/", $class)) . ".php";
});

require_once __DIR__ . "/config.php";
// Calculate the installation path
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? "https" : "http");
$instPath = realpath(__DIR__ . "/..");
$docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$cfg['url'] = $scheme . "://" . $_SERVER['SERVER_NAME'] . substr($instPath, strlen($docRoot));
if (substr($cfg['url'], -1, 1) !== "/") $cfg['url'] = $cfg['url'] . "/";
global $cfg;

require __DIR__ . "/version.php";

// Set locale
setlocale(LC_ALL, $cfg['locale']);
date_default_timezone_set ( $cfg['timezone'] );

// $message is used on several pages. Good to initialise.
$message = "";

// Load mail functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use FFBoka\FFBoka;
use FFBoka\User;

// Connect to database
$db = connectDb($cfg['dbhost'], $cfg['dbname'], $cfg['dbuser'], $cfg['dbpass'], $dbVersion, $cfg['dbport']);
// Switch off strict mode
$db->exec("SET SESSION sql_mode = ''");

// Create FF object
$FF = new FFBoka($cfg['ff-api'], $db, $cfg['sectionAdmins'], $cfg['timezone']);

// Check if there is a persistent login cookie
//https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
if (!isset($_SESSION['authenticatedUser']) && !empty($_COOKIE['remember'])) {
    User::restorePersistentLogin($_COOKIE['remember'], $cfg['TtlPersistentLogin']);
}

/**
 * Connect to database.
 * If the db does not exist, dies with an error describing what to do.
 * If the db is empty, tries to install all tables and static content. 
 * Checks that the db is on the required version. If not, tries to upgrade it.
 * @param string $host Database host name
 * @param string $dbname Database name
 * @param string $user Database user name
 * @param string $pass Password of that user
 * @param int $reqVer The required DB version
 * @param int $port Database port
 * @return PDO $db Connection to the database 
 */
function connectDb(string $host, string $dbname, string $user, string $pass, int $reqVer, int $port=3306) {
    // Try to connect to the database
    try {
        $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    } catch (PDOException $e) {
        die("<html><body><h1>Can't Connect to Database</h1><p>If this is a fresh installation, create a database named <tt>$dbname</tt> on host <tt>$host</tt>, and create a user named <tt>$user</tt> with complete access to that database. Set the user's password in <tt>config.php</tt>. You can also change the database and user name there.</p><p>When done, <a href='javascript:location.reload();'>reload this page</a> to continue installation.</p></body></html>");
    }
    $stmt = $db->query("SELECT value FROM config WHERE name='db-version'");
    if ($stmt === FALSE) {
        // No tables? Try to install base skeleton
        echo(str_pad("<html><body><h1>Empty Database Found</h1><p>The database seems to be empty. Trying to install base skeleton. Please wait...</p>",4096)); flush();
        if ($db->exec(file_get_contents(__DIR__ . "/../resources/db/skeleton.sql"))===FALSE) {
            die("<p>It seems that this did not work. :(</p>");
        }
        die("<p>Finished.</p><p>You may <a href='javascript:location.reload();'>reload this page</a> to continue.</p></body></html>");
    }
    // Continue checking the db version
    $row = $stmt->fetch(PDO::FETCH_OBJ);
    $curVer = (int)$row->value;
    if ($curVer > $reqVer) {
        die("<html><body><h1>Wrong Database Version</h1><p>The current database version (v $curVer) is higher than the expected one (v $reqVer). I cannot downgrade the database.</p></body></html>");
    } elseif ($curVer < $reqVer) {
        echo(str_pad("<html><body><h1>Database Upgrade</h1><p>The current database version (v $curVer) is lower than the expected one (v $reqVer). I will now try to upgrade the database to v $reqVer. Please wait...</p>", 4096)); flush();
        while ($curVer < $reqVer) {
            // Check that upgrade sql file exists
            $curVer++;
            if (!is_readable(__DIR__ . "/../resources/db/$curVer.sql")) die("<p><b>Oops... Cannot find database upgrade file to v $curVer.</b> Please take contact with the maintainers of the repository who should have supplied the file /resources/db/$curVer.sql.</p><p><a href='javascript:location.reload();'>Retry</a></p></body></html>");
            echo(str_pad("<h3>Upgrading from v " . ($curVer-1) . " to v $curVer...</h3>", 4096)); flush();
            // If exists, include php code related to db upgrade
            if (is_readable(__DIR__ . "/../resources/db/$curVer.php")) {
                // Setting flag which should be evaluated in the included script in order to ensure
                // that the script does not get executed from other calls.
                $_SESSION['dbUpgradeToVer'] = $curVer;
                include(__DIR__ . "/../resources/db/$curVer.php");
            }
            // Apply SQL upgrade
            if ($db->exec(file_get_contents(__DIR__ . "/../resources/db/$curVer.sql"))===FALSE) {
                die("<p><b>It seems that this did not work. :(</b></p>");
            }
            // Double check success by checking DB version number
            $stmt = $db->query("SELECT value FROM config WHERE name='db-version'");
            $ver = $stmt->fetch(PDO::FETCH_OBJ);
            if ($ver->value == $curVer) {
                echo "<p>Successful upgrade to version $curVer.</p>";
            } else {
                echo "<p><b>Upgrade to version $curVer has failed.</b></p>";
            }
        }
        die("<p>Finished.</p><p>If you do not see any error messages, you may <a href='javascript:location.reload();'>reload this page</a> to continue.</p></body></html>");
    }
    return $db;
}

/**
 * Output the file headers for HTML pages (title, meta tags, common stylesheets, jquery)
 * @param string $title
 * @param string $baseUrl Base URL of the installation
 * @param string $mode mobile|desktop
 */
function htmlHeaders(string $title, string $baseUrl, string $mode="mobile") { 
    // output meta tags and include stylesheets, jquery etc    ?>
    <title><?= $title ?></title>
    <meta charset="UTF-8">
    <?php if ($mode=="mobile") { ?>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <link rel="stylesheet" href="<?= $baseUrl ?>inc/jquery.mobile-1.4.5.min.css" />
        <link rel="stylesheet" href="<?= $baseUrl ?>css/themes/ff-boka.css" />
        <link rel="stylesheet" href="<?= $baseUrl ?>css/themes/jquery.mobile.icons.min.css" />
        <script src="<?= $baseUrl ?>inc/jquery-1.11.1.min.js"></script>
        <script src="<?= $baseUrl ?>inc/jquery.mobile-1.4.5.min.js"></script>
    <?php } else { ?>
        <script src="<?= $baseUrl ?>inc/pace.min.js"></script>
        <link rel="stylesheet" href="<?= $baseUrl ?>vendor/jquery-ui-1.12.1/jquery-ui.min.css">
        <script src="<?= $baseUrl ?>vendor/jquery-ui-1.12.1/external/jquery/jquery.js"></script>
        <script src="<?= $baseUrl ?>vendor/jquery-ui-1.12.1/jquery-ui.min.js"></script>
        <link rel="stylesheet" href="<?= $baseUrl ?>vendor/fontawesome/css/all.css">        
    <?php } ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>css/ff-boka.css" />
    <script>
        // Lift in some constants from PHP
        const ACCESS_NONE = <?= FFBoka::ACCESS_NONE ?>;
        const ACCESS_READASK = <?= FFBoka::ACCESS_READASK ?>;
        const ACCESS_PREBOOK = <?= FFBoka::ACCESS_PREBOOK ?>;
        const ACCESS_BOOK = <?= FFBoka::ACCESS_BOOK ?>;
        const ACCESS_CONFIRM = <?= FFBoka::ACCESS_CONFIRM ?>;
        const ACCESS_CATADMIN = <?= FFBoka::ACCESS_CATADMIN ?>;
        const ACCESS_SECTIONADMIN = <?= FFBoka::ACCESS_SECTIONADMIN ?>;
        const STATUS_PENDING = <?= FFBoka::STATUS_PENDING ?>;
        const STATUS_CONFLICT = <?= FFBoka::STATUS_CONFLICT ?>;
        const STATUS_PREBOOKED = <?= FFBoka::STATUS_PREBOOKED ?>;
        const STATUS_CONFIRMED = <?= FFBoka::STATUS_CONFIRMED ?>;
    </script>
    <script src="https://canvasjs.com/assets/script/jquery.canvasjs.min.js"></script>
    <script src="<?= $baseUrl ?>inc/ff-boka.js"></script>
    <?php
}

/**
 * Output HTML code for the common page heading and side panel
 * @param string $caption
 * @param string $baseUrl Base URL of the installation
 * @param array[int] $superAdmins Member IDs giving superadmin access 
 */
function head(string $caption, string $baseUrl, $superAdmins=array()) {
    // Declare side panel
    ?>
    <div data-role="panel" data-theme="b" data-position-fixed="true" data-display="push" id="navpanel">
        <ul data-role="listview">
            <li data-icon="home"><a href="<?= $baseUrl ?>index.php" data-transition='slide' data-direction='reverse' data-rel="close">Startsida</a></li><?php
            if (isset($_SESSION['authenticatedUser'])) { ?>
                <li data-icon="user"><a href="<?= $baseUrl ?>userdata.php" data-transition='slide' data-rel="close">Min sida</a></li>
                <li data-icon="power"><a href="<?= $baseUrl ?>index.php?logout" data-rel="close">Logga ut</a></li><?php
                if (in_array($_SESSION['authenticatedUser'], $superAdmins)) {
                    echo "<li data-icon='alert'><a href='{$baseUrl}admin/superadmin.php' data-transition='slide' data-rel='close'>Super-Admin</a></li>";
                }
                if (isset($_SESSION['impersonate_realUserId'])) {
                    echo "<li data-icon='action'><a href='{$baseUrl}?action=exit_impersonate' data-transition='slide' data-rel='close' data-ajax='false'>Sluta imitera {$_SESSION['authenticatedUser']}</a></li>";
                }
            } ?>
            <li data-icon="info"><a href="<?= $baseUrl ?>cookies.php" data-transition='slide' data-rel="close">Om kakor (cookies)</a></li>
        </ul>
    </div><!-- /panel -->
    
    <div data-role="header">
        <H1><?= $caption ?></H1>
        <a href='#navpanel' data-rel='popup' data-transition='pop' data-role='button' data-icon='bars' data-iconpos='notext' class='ui-btn-left ui-nodisc-icon ui-alt-icon'>Meny</a>
        <a href='javascript:showHelp();' data-transition='slide' data-rel='popup' data-role='button' data-icon='help' data-iconpos='notext' class='ui-btn-right ui-nodisc-icon ui-alt-icon'>Hjälp</a>
        <?php
        if (!isset($_COOKIE['cookiesOK'])) { // Display cookie chooser ?>
            <div id="divCookieConsent" data-theme='b' class='ui-bar ui-bar-b' style='font-weight:normal;'>
                För att vissa funktioner på denna webbplats ska fungera använder vi kakor. <a href='<?= $baseUrl ?>cookies.php' data-role='none'>Läs mer om kakor.</a><br>
                <button onClick="var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='cookiesOK=1; expires='+d.toUTCString()+'; Path=/'; $('#divCookieConsent').hide(); $('#div-remember-me').show();">Tillåt kakor</button>
                <button onClick="document.cookie='cookiesOK=0; path=/'; $('#divCookieConsent').hide();$('#div-remember-me').hide();">Avböj kakor</button>
            </div>
        <?php } 
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') || strpos($_SERVER['HTTP_USER_AGENT'], 'Trident/7')) echo "<div class='ui-bar ui-bar-b' style='font-weight:normal;'><b>Dags att uppgradera din webbläsare.</b> Du använder Internet Explorer, en föråldrad webbläsare som aldrig har följt några webbstandarder. Vi har valt att inte lägga vår tid på att stödja den, och bokningen kommer inte att fungera med den. Vänligen använd en annan webbläsare. Vi rekommenderar Firefox eller Chrome.</div>";
        ?>
    </div>
    
    <div data-role="popup" id="popup-help" class="ui-content" data-overlay-theme="b">
    	<a href='#' data-rel='back' class='ui-btn ui-icon-delete ui-btn-icon-left'>Stäng inforutan</a>
    	<div id="help-content"></div>
    	<a href='#' data-rel='back' class='ui-btn ui-icon-delete ui-btn-icon-left'>Stäng inforutan</a>
    </div>
    <?php
}


/**
 * Get html code for an embedded image tag. 
 * @param string $data Image data
 * @param string $overlay Name of overlay image file (accepted|rejected|new)
 * @return string HTML img tag with embedded base64 encoded data
 */
function embedImage($data, $overlay="") {
    // Returns string for embedded img tag.
    if (!in_array($overlay, array("accepted", "rejected", "new"))) $overlay="";
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

/**
 * Lookup a userAgent string and return a human readable version of it
 * @param string $userAgent
 * @param PDO $db Database object to use for storing userAgent information
 * @param string $format How to return the information, similar to strftime function.
 *        The following tags will be replaced: %browser% %version% %platform% %platform_version% %platform_bits% %device_type% 
 * @return string
 */
function resolveUserAgent(string $userAgent, PDO $db, string $format='%browser% %version% på %platform% %platform_version%, %platform_bits% bits (%device_type%)') {
    $ret = $format;
    $stmt = $db->prepare("SELECT * FROM user_agents WHERE uaHash=?");
    $stmt->execute(array(sha1($userAgent)));
    if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        // Found userAgent in database. Return a readable representation of it
        if ($row->browser=="") $row->browser = "Okänd webbläsare"; 
        if ($row->platform=="") $row->platform = "Okänd plattform";
        if ($row->platform_bits=="") $row->platform_bits = "?";
        if ($row->device_type=="") $row->device_type = "-";
        $ret = str_replace("%browser%", $row->browser, $ret);
        $ret = str_replace("%version%", $row->version, $ret);
        $ret = str_replace("%platform%", $row->platform, $ret);
        $ret = str_replace("%platform_version%", $row->platform_version, $ret);
        $ret = str_replace("%platform_bits%", $row->platform_bits, $ret);
        $ret = str_replace("%device_type%", $row->device_type, $ret);
        return $ret;
    } else {
        // This userAgent is not yet known. Save it in database an let it be resolved by cron job later.
        $stmt = $db->prepare("INSERT INTO user_agents SET uaHash=:hash, userAgent=:ua");
        $stmt->execute(array(
            ":hash" => sha1($userAgent),
            ":ua" => $userAgent
        ));
        return $userAgent;
    }
}
