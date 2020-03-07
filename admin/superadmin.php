<?php
use FFBoka\FFBoka;
use FFBoka\User;

session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $db;

// This page may only be accessed by superadmins
if (!in_array($_SESSION['authenticatedUser'], $cfg['superAdmins'])) {
    die();
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


switch ($_REQUEST['action']) {
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
        // save local config
        if (!rename("../inc/config.php", "../config.php")) die(json_encode([ "error"=>"Kan inte flytta config.php till huvudmappen." ]));
        foreach (scandir("../update/ff-boka-master/") as $filename) {
            if ($filename=="." || $filename=="..") continue;
            if (!rename("../update/ff-boka-master/$filename", "../$filename")) die(json_encode([ "error"=>"Kan inte ersätta ".basename($filename) ]));
            $ret[] = $filename;
        }
        // restore local config
        if (!rename("../config.php", "../inc/config.php")) die(json_encode([ "error"=>"Kan inte flytta tillbaka config.php till inc." ]));
        die(json_encode([ "status"=>implode("</li><li>", $ret) ]));
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
            <h3>Konfiguration</h3>
            <pre><?= print_r($cfg, TRUE) ?></pre>
            <h3>Senaste inloggningar</h3>
            <table class="alternate-rows">
            <tr><th>timestamp</th><th>ip</th><th>userId</th><th>succ</th><th>userAgent</th></tr>
            <?php
            $stmt = $db->query("SELECT timestamp, INET_NTOA(ip) ip, userId, success, userAgent FROM logins ORDER BY timestamp DESC LIMIT 50");
            while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                echo "<tr><td>{$row->timestamp}</td>
                    <td>{$row->ip}</td>
                    <td>{$row->userId}</td>
                    <td>{$row->success}</td>
                    <td>{$row->userAgent}</td></tr>";
            }
            ?></table>
        </div>

        <div data-role="collapsible">
            <h2>Uppgradering</h2>
            <p>Med knappen nedan kan du hämta senaste versionen från master-grenen på Github och installera den.</p>
            <button class='ui-btn ui-btn-c' onClick="systemUpgrade(1);">Uppgradera systemet</button>
            <ul id="upgrade-progress"></ul>
        </div>

    </div><!--/collapsibleset-->

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
