<?php
use FFBoka\User;
use FFBoka\Section;

session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $db;

// This page may only be accessed by superadmins
if (!in_array($_SESSION['authenticatedUser'], $cfg['superAdmins'])) {
    die("Försök inte!");
}
$currentUser = new User($_SESSION['authenticatedUser']);


/**
 * Recursively removes a directory
 * @param string $dir
 * @return boolean
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;        
    }    
    return rmdir($dir);
}

if (isset($_REQUEST['action'])) {
switch ($_REQUEST['action']) {
case "help":
    die("Finns ingen hjälp till denna sida.");
case "make me admin":
    if (is_numeric($_REQUEST['sectionId'])) {
        $section = new Section($_REQUEST['sectionId']);
        if ($section->addAdmin($_SESSION['authenticatedUser'])) {
            header("Location: index.php?sectionId={$section->id}");
        } else {
            $message = "Något har gått fel.";
        }
    }
case "ajaxUpgrade":
    header("Content-Type: application/json");
    switch ($_REQUEST['step']) {
    case "1":
        try {
            if (!deleteDirectory("../update")) die(json_encode([ "error"=>"FEL: Kan inte ta bort gamla filer." ]));
            if (!mkdir("../update")) die(json_encode([ "error"=>"FEL: Kan inte skapa mappen /update." ]));
            if (file_put_contents('../update/ff-boka-master.zip', fopen($cfg['upgradeUrl'], 'r'))===FALSE) die(json_encode([ "error"=>"FEL: Kan inte hämta arkivet {$cfg['upgradeUrl']}" ]));
        } catch (Exception $e) {
            die(json_encode([ "error"=>"Något har gått fel. ".print_r($e, TRUE) ]));
        }
        die(json_encode([ "status"=>"ok" ]));
    case "2":
        try {
            $zip = new ZipArchive;
            if ($zip->open('../update/ff-boka-master.zip') === TRUE) {
                if ($zip->extractTo('../update/')===FALSE) die(json_encode([ "error"=>"Kan inte packa upp arkivet." ]));
                $zip->close();
            } else die(json_encode([ "error"=>"Kan inte öppna arkivet för att packa upp det." ]));
        } catch (Exception $e) {
            die(json_encode([ "error"=>"Något har gått fel. ".print_r($e, TRUE) ]));
        }
        if (!unlink('../update/ff-boka-master.zip')) die(json_encode([ "error"=>"Kan inte ta bort arkivfilen." ]));
        die(json_encode([ "status"=>"ok" ]));
    case "3":
        chdir("..") || die(json_encode([ "error"=>"Kan inte gå till huvudmappen." ]));
        // save local config
        if (!copy("inc/config.php", "config.php")) die(json_encode([ "error"=>"Kan inte skapa säkerhetskopia på config.php i huvudmappen." ]));
        else $ret[] = "Har skapat säkerhetskopia av config.php i huvudmappen.";
        foreach (scandir("update/ff-boka-master/") as $filename) {
            if ($filename=="." || $filename=="..") continue;
            if (!deleteDirectory($filename)) $ret[] = "<b>Kunde inte ta bort gamla versionen av $filename.</b>";
            else $ret[] = "Har tagit bort gamla versionen av $filename.";
            if (!rename("update/ff-boka-master/$filename", $filename)) $ret[] = "<b>Kunde inte flytta nya versionen på $filename på plats.</b>";
            else $ret[] = "Har ersatt $filename med ny version.";
        }
        // restore local config
        if (!rename("config.php", "inc/config.php")) $ret[] = "<b>Kunde inte flytta tillbaka config.php från huvudmappen till inc-mappen.</b>";
        else $ret[] = "Har flyttat tillbaka config.php till inc-mappen.";
        // remove all update files
        if (!deleteDirectory("update")) die(json_encode([ "error"=>"FEL: Kan inte ta bort gamla filer." ]));
        else $ret[] = "Har rensat tillfälliga update-filer.";
        include(__DIR__."/../inc/common.php");
        $ret[] = "Den aktuella DB-versionen är nu $dbVersion";
        die(json_encode([ "status"=>implode("</li><li>", $ret) ]));
    }
}
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Superadmin", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-super-admin">
    <?= head("Super-Admin", $cfg['url'], $currentUser, $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-super-admin" class="ui-content">
        <p id="msg-page-super-admin"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role="collapsibleset" data-inset="false">
        
        <div data-role="collapsible">
            <h2>Systeminfo</h2>
            <h3>Cron <?php
            $stmt = $db->query("SELECT value FROM config WHERE name='last hourly cron run'");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            $last = (int)$row->value;
            if ($last==0 || $last < time()-3600) echo "<span style='color:var(--FF-orange);'>■</span>";
            else echo "<span style='color:var(--FF-green);'>■</span>"; ?></h3>
            <p><?= $last==0 ? "Cron har aldrig utförts" : "Cron utfördes senast för " . (int)((time()-$last)/60) . " minuter sedan" ?>.</p>
            
            <h3>Statistik</h3>
			<?php
			// Show some statistics
			$stmt = $db->query("SELECT COUNT(*) users FROM users");
			$row = $stmt->fetch(PDO::FETCH_OBJ);
			echo "<ul><li>{$row->users} registrerade användare</li>";
			
			$stmt = $db->query("SELECT COUNT(DISTINCT sectionId) sections FROM sections JOIN categories USING (sectionId) JOIN items USING (catId)");
			$row = $stmt->fetch(PDO::FETCH_OBJ);
			echo "<li>{$row->sections} aktiva lokalavdelningar</li>";

			$stmt = $db->query("SELECT COUNT(*) items FROM items");
			$row = $stmt->fetch(PDO::FETCH_OBJ);
			echo "<li>{$row->items} resurser upplagda</li></ul>"; ?>
        </div>

        <div data-role="collapsible">
            <h2>Konfiguration</h2>
            <pre><?= print_r($cfg, TRUE) ?></pre>
        </div>

        <div data-role="collapsible">
            <h2>Senaste inloggningar</h2>
            <table class="alternate-rows">
            <tr><th>timestamp</th><th>IP</th><th>user</th><th>LA</th><th>succ</th><th>userAgent</th></tr>
            <?php
            $stmt = $db->query("SELECT logins.timestamp timestamp, INET_NTOA(ip) ip, login, userId, users.name name, sections.name section, success, userAgent FROM logins LEFT JOIN users USING (userId) LEFT JOIN sections USING (sectionId) ORDER BY timestamp DESC LIMIT 50");
            while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                echo "<tr><td>{$row->timestamp}</td>
                    <td>{$row->ip}</td>
                    <td" . ($row->name ? " title='Login: {$row->login}, medlemsnr: {$row->userId}'" : "") . ">" . ($row->name ? htmlspecialchars($row->name) : $row->userId) . "</td>
                    <td title='" . htmlspecialchars($row->section) . "'>" . substr(htmlspecialchars($row->section), 0, 10) . "</td>
                    <td>{$row->success}</td>
                    <td>" . resolveUserAgent($row->userAgent, $db) . "</td></tr>";
            }
            ?></table>
        </div>

        <div data-role='collapsible'>
            <h2>Session data</h2>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>

        <div data-role="collapsible">
            <h2>Uppgradering</h2>
            <p>Aktuell DB-version är <?php 
            $stmt = $db->query("SELECT value FROM config WHERE name='db-version'");
            $ver = $stmt->fetch(PDO::FETCH_OBJ);
            echo $ver->value; ?></p>
            <p>Med knappen nedan kan du hämta senaste versionen från master-grenen på Github och installera den.</p>
            <button class='ui-btn ui-btn-c' onClick="systemUpgrade(1);">Uppgradera systemet</button>
            <ul id="upgrade-progress"></ul>
        </div>

        <div data-role="collapsible">
            <h2>Gör mig till LA-admin</h2>
            <form>
                <p>Här kan du ge dig själv administratörs-behörighet i valfri lokalavdelning.</p>
                <input type="hidden" name="action" value="make me admin">
                <select name="sectionId">
                    <option>Välj lokalavdelning</option><?php
                    $stmt = $db->query("SELECT * FROM sections ORDER BY name");
                    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                        echo "<option value='{$row->sectionId}'>{$row->name}</option>";
                    } ?>
                </select>
                <input data-theme="b" type="submit" data-corners="false" value="Gör mig till admin">
            </form>
        </div>

    </div><!--/collapsibleset-->

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
