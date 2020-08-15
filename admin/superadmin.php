<?php
use FFBoka\User;
use FFBoka\Section;
use FFBoka\Poll;

session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $db, $FF;

// This page may only be accessed by superadmins
if (!isset($_SESSION['authenticatedUser']) || !in_array($_SESSION['authenticatedUser'], $cfg['superAdmins'])) {
    header("Location: {$cfg['url']}?redirect=" . urlencode("admin/superadmin.php"));
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
case "ajaxMakeMeAdmin":
    header("Content-Type: application/json");
    if (is_numeric($_REQUEST['sectionId'])) {
        $section = new Section($_REQUEST['sectionId']);
        if ($section->addAdmin($_SESSION['authenticatedUser'])) {
            die(json_encode([ "sectionId" => $section->id ]));
        } else {
            die(json_encode([ "error" => "Något har gått fel." ]));
        }
    } else die(json_encode([ "error" => "Wrong argument type." ]));
    break;
case "ajaxImpersonate":
    header("Content-Type: application/json");
    if (is_numeric($_REQUEST['userId'])) {
        $_SESSION['impersonate_realUserId'] = $_SESSION['authenticatedUser'];
        $_SESSION['authenticatedUser'] = $_REQUEST['userId'];
        die(json_encode([ "userId" => $_SESSION['authenticatedUser'] ]));
    } else {
        die(json_encode([ "error" => "Du ska ange ett numeriskt medlemsnummer." ]));
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
        require(__DIR__."/../inc/version.php");
        $ret[] = "Den aktuella DB-versionen är nu $dbVersion";
        die(json_encode([ "status"=>implode("</li><li>", $ret) ]));
    }
    
case "ajaxAddPoll":
case "ajaxGetPoll":
    if ($_REQUEST['action']=="ajaxAddPoll") $poll = $FF->addPoll();
    else $poll = new Poll($_GET['id']);
    die(json_encode([
        "id" => $poll->id,
        "question" => $poll->question,
        "choices" => $poll->choices,
        "expires" => $poll->expires,
        "votes" => $poll->votes,
        "voteMax" => $poll->voteMax
    ]));
    
case "savePoll":
    $poll = new Poll($_REQUEST['id']);
    if (isset($_REQUEST['submit']) && $_REQUEST['submit']=="Ta bort") {
        $poll->delete();
    } else {
        if ($poll->question != $_REQUEST['question']) $poll->question = $_REQUEST['question'];
        if ($poll->choices != array_map('trim', explode("\n", $_REQUEST['choices']))) $poll->choices = array_map('trim', explode("\n", $_REQUEST['choices']));
        if ($_REQUEST['expires']=="") $_REQUEST['expires'] = NULL;
        if ($poll->expires != $_REQUEST['expires']) $poll->expires = $_REQUEST['expires'];
    }
    $expand = "polls";
    break;

}
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Superadmin", $cfg['url']) ?>
</head>


<body>
<div data-role="page" id="page-super-admin">
    <?= head("Super-Admin", $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-super-admin" class="ui-content">
        <p id="msg-page-super-admin"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role="popup" data-overlay-theme="b" id="popup-super-admin-poll" class="ui-content">
        <h3>Bearbeta enkät</h3>
        <form action='superadmin.php' data-ajax='false' method='POST'>
	        <input type="hidden" name="action" value="savePoll">
	        <input type="hidden" name="id" id="super-admin-poll-id">
            <div class="ui-field-contain">
                <label for="super-admin-poll-question">Fråga<br><small>Här kan du använda valfri HTML-kod.</small></label>
                <textarea name="question" id="super-admin-poll-question"></textarea>
            </div>
            <div class="ui-field-contain">
                <label for="super-admin-poll-choices">Svarsalternativ<br><small>1 alternaiv per rad</small></label>
                <textarea name="choices" id="super-admin-poll-choices"></textarea>
            </div>
            <div class="ui-field-contain">
                <label for="super-admin-poll-expires">Aktiv t.o.m.<br><small>Tomt = inget slutdatum</small></label>
                <input name="expires" type="date" id="super-admin-poll-expires">
            </div>
        	<input data-inline='true' data-icon='delete' data-corners='false' data-theme='c' type="submit" name="submit" value="Ta bort">
        	<input data-inline='true' data-icon='check' data-corners='false' data-theme='b' type="submit" value="Spara">
        </form>
    </div>

    <div data-role="popup" data-overlay-theme="b" id="popup-super-admin-pollresults" class="ui-content">
        <h3>Enkätresultat</h3>
        <p>Fråga:<br><span id="super-admin-pollresults-question"></span></p>
        <table id="super-admin-pollresults-votes" style="width:100%;"></table>
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
                    <td class='superadmin-login-post'" . ($row->name ? " title='Login: {$row->login}, medlemsnr: {$row->userId}'" : "") . " data-userid='{$row->userId}'>" . ($row->name ? htmlspecialchars($row->name) : (is_null($row->userId) ? $row->login : $row->userId)) . "</td>
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

        <div data-role="collapsible" id="admin-section-misc">
            <h2>Diverse</h2>
            <h4>Ta LA-admin-rollen:</h4>
            <select name="sectionId" id="sectionadmin-sectionlist">
                <option>Välj lokalavdelning</option><?php
                $stmt = $db->query("SELECT * FROM sections ORDER BY name");
                while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                    echo "<option value='{$row->sectionId}'>{$row->name}</option>";
                } ?>
            </select>
            <hr>
            <h4>Imitera annan användare:</h4>
            <fieldset class="ui-grid-a">
            	<div class="ui-block-a"><input id="admin-impersonate-userId" placeholder="medlemsnummer"></div>
            	<div class="ui-block-b"><button id="admin-impersonate-start">OK</button></div>
        	</fieldset>
        </div>

        <div data-role="collapsible" data-collapsed="<?= (isset($expand) && $expand=="polls") ? "false" : "true" ?>">
            <h2>Enkäter</h2>
            <ul data-role='listview' data-split-icon='edit'>
                <?php
                foreach ($FF->polls() as $poll) {
                    echo "<li><a href='#' onClick='showPollResults({$poll->id});'>" . htmlspecialchars($poll->question) . "</a>
                        <a href='#' onClick='editPoll({$poll->id});'></a></li>";
                }
                ?>
                <li data-icon='plus' id='add-poll'><a href='#'>Lägg till ny enkät</a></li>
            </ul>
        </div>

    </div><!--/collapsibleset-->

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
