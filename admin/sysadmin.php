<?php

use FFBoka\FFBoka;
use FFBoka\User;
use FFBoka\Section;
use FFBoka\Poll;

session_start();
require( __DIR__ . "/../inc/common.php" );
global $cfg, $db, $FF;

// This page may only be accessed by sysadmins
if ( !isset( $_SESSION[ 'authenticatedUser' ] ) || !in_array( $_SESSION[ 'authenticatedUser' ], $cfg[ 'sysAdmins' ] ) ) {
    header( "Location: {$cfg[ 'url' ]}?redirect=" . urlencode( "admin/sysadmin.php" ) );
}
$currentUser = new User( $_SESSION[ 'authenticatedUser' ] );


/**
 * Recursively removes a directory
 * @param string $dir
 * @return boolean
 */
function deleteDirectory( $dir ) {
    if ( !file_exists( $dir ) ) return true;
    if ( !is_dir( $dir ) ) return unlink( $dir );
    foreach ( scandir( $dir ) as $item ) {
        if ( $item == '.' || $item == '..' ) continue;
        if ( !deleteDirectory( $dir . DIRECTORY_SEPARATOR . $item ) ) return false;        
    }    
    return rmdir( $dir );
}

if ( isset( $_REQUEST[ 'action' ] ) ) {
switch ( $_REQUEST[ 'action' ] ) {
case "help":
    die( "Finns ingen hjälp till denna sida." );
case "ajaxMakeMeAdmin":
    header( "Content-Type: application/json" );
    if ( is_numeric( $_REQUEST[ 'sectionId' ] ) ) {
        $section = new Section( $_REQUEST[ 'sectionId' ] );
        if ( $section->addAdmin( $_SESSION[ 'authenticatedUser' ] ) ) {
            die( json_encode( [ "sectionId" => $section->id ] ) );
        } else {
            die( json_encode( [ "error" => "Något har gått fel." ] ) );
        }
    } else die( json_encode( [ "error" => "Wrong argument type." ] ) );
    break;
case "ajaxImpersonate":
    header( "Content-Type: application/json" );
    if ( is_numeric( $_REQUEST[ 'userId' ] ) ) {
        $_SESSION[ 'impersonate_realUserId' ] = $_SESSION[ 'authenticatedUser' ];
        $_SESSION[ 'authenticatedUser' ] = $_REQUEST[ 'userId' ];
        $currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
        $currentUser->getAssignments();
        die( json_encode( [ "userId" => $_SESSION[ 'authenticatedUser' ] ] ) );
    } else {
        die( json_encode( [ "error" => "Du ska ange ett numeriskt medlemsnummer." ] ) );
    }
    
case "ajaxDeleteSection":
    // Delete a whole section. Intended for sections which are no longer present in the central database.
    $stmt = $db->prepare( "DELETE FROM sections WHERE sectionId=?" );
    if ( $stmt->execute( [ $_GET[ 'sectionId' ] ] ) ) {
        if ( $stmt->rowCount() == 1 ) die( json_encode( [ "status"=>"OK", "sectionId"=>$_GET[ 'sectionId' ] ] ) );
        else die( json_encode( [ "status"=>"error", "error"=>$stmt->rowCount() . " LAs har raderats, istället för 1." ] ) );
    } else die( json_encode( [ "status"=>"error", "error"=>$db->errorInfo()[ 2 ] ] ) );

case "ajaxAddPoll":
case "ajaxGetPoll":
    if ( $_REQUEST[ 'action' ] == "ajaxAddPoll" ) $poll = $FF->addPoll();
    else $poll = new Poll( $_GET[ 'id' ] );
    die( json_encode( [
        "id" => $poll->id,
        "question" => $poll->question,
        "choices" => $poll->choices,
        "expires" => $poll->expires,
        "targetGroup" => $poll->targetGroup,
        "votes" => $poll->votes,
        "voteMax" => $poll->voteMax
    ] ) );
    
case "savePoll":
    $poll = new Poll( $_REQUEST[ 'id' ] );
    if ( isset( $_REQUEST[ 'submit' ] ) && $_REQUEST[ 'submit' ] == "Ta bort" ) {
        $poll->delete();
    } else {
        if ( $poll->question != $_REQUEST[ 'question' ] ) $poll->question = $_REQUEST[ 'question' ];
        if ( $poll->choices != array_map( 'trim', explode( "\n", $_REQUEST[ 'choices' ] ) ) ) $poll->choices = array_map( 'trim', explode( "\n", $_REQUEST[ 'choices' ] ) );
        if ( $_REQUEST[ 'expires' ] == "") $_REQUEST[ 'expires' ] = NULL;
        if ( $poll->targetGroup != $_REQUEST[ 'targetGroup' ] ) $poll->targetGroup = $_REQUEST[ 'targetGroup' ];
        if ( $poll->expires != $_REQUEST[ 'expires' ]) $poll->expires = $_REQUEST[ 'expires' ];
    }
    $expand = "polls";
    break;

case "currentLog":
    header( 'Content-Type: text/html' );
    if ( is_readable( $cfg[ 'logFile' ] ) && !is_dir( $cfg[ 'logFile' ] ) ) {
        echo "<p>Show <a href='?action=currentLog&level=1'>ERRORs</a> <a href='?action=currentLog&level=2'>WARNINGs</a> <a href='?action=currentLog&level=8'>NOTICEs</a></p>";
        $file = file( $cfg[ 'logFile' ] );
        switch ( $_GET[ 'level' ] ) {
            case E_NOTICE: $reg = "NOTICE|WARNING|ERROR"; break;
            case E_WARNING: $reg = "WARNING|ERROR"; break;
            default: $reg = "ERROR";
        }
        echo "<pre>" . implode( preg_grep( "/ ($reg) /", $file ) ) . "</pre>";
    }
    else echo "Det går inte att visa loggfilen.";
    die();
}
}

// Check last cron execution
$stmt = $db->query( "SELECT value FROM config WHERE name='last cron job finished'" );
$lastCron =  $stmt->fetchColumn();
$cronDelayed = $lastCron == 0 || $lastCron < time() - 3600;

// Check that config file is complete
$currentCfg = $cfg;
unset( $cfg );
require "../inc/config.sample.php";
$cfgMissing = diff_key_recursive( $cfg, $currentCfg );
$cfgObsolete = diff_key_recursive( $currentCfg, $cfg );
$cfg = $currentCfg;

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning - Systemadmin", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-sys-admin">
    <?= head( "System-Admin", $cfg[ 'url' ], $cfg[ 'sysAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-sys-admin" class="ui-content">
        <p id="msg-page-sys-admin"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role="popup" data-overlay-theme="b" id="popup-sys-admin-poll" class="ui-content">
        <h3>Bearbeta enkät</h3>
        <form action='sysadmin.php' data-ajax='false' method='POST'>
	        <input type="hidden" name="action" value="savePoll">
	        <input type="hidden" name="id" id="sys-admin-poll-id">
            <div class="ui-field-contain">
                <label for="sys-admin-poll-question">Fråga<br><small>Här kan du använda valfri HTML-kod.</small></label>
                <textarea name="question" id="sys-admin-poll-question"></textarea>
            </div>
            <div class="ui-field-contain">
                <label for="sys-admin-poll-choices">Svarsalternativ<br><small>1 alternativ per rad</small></label>
                <textarea name="choices" id="sys-admin-poll-choices"></textarea>
            </div>
            <div class="ui-field-contain">
                <label for="sys-admin-poll-expires">Aktiv t.o.m.<br><small>Tomt = inget slutdatum</small></label>
                <input name="expires" type="date" id="sys-admin-poll-expires">
            </div>
            <div class="ui-field-contain">
                <label for="sys-admin-poll-targetgroup">Målgrupp</label>
                <select name="targetGroup" id="sys-admin-poll-targetgroup">
                    <option value="<?= FFBoka::ACCESS_BOOK ?>">Alla inloggade användare</option>
                    <option value="<?= FFBoka::ACCESS_CONFIRM ?>">Bokningsansvariga</option>
                    <option value="<?= FFBoka::ACCESS_CATADMIN ?>">Kategori-admin</option>
                    <option value="<?= FFBoka::ACCESS_SECTIONADMIN ?>">LA-admin</option>
                </select>
            </div>
        	<input data-inline='true' data-icon='delete' data-corners='false' data-theme='c' type="submit" name="submit" value="Ta bort">
        	<input data-inline='true' data-icon='check' data-corners='false' data-theme='b' type="submit" value="Spara">
        </form>
    </div>

    <div data-role="popup" data-overlay-theme="b" id="popup-sys-admin-pollresults" class="ui-content">
        <h3>Enkätresultat</h3>
        <p>Fråga:<br><span id="sys-admin-pollresults-question"></span></p>
        <table id="sys-admin-pollresults-votes" style="width:100%;"></table>
    </div>

    <?php
    if ( $cronDelayed || $cfgMissing || $cfgObsolete ) {
        echo "<div class='ui-body ui-body-c'><p>Det finns några problem:</p><ul>";
        if ( $cronDelayed ) echo "<li><a href='#' onclick=\"$('#sysadmin-systeminfo').collapsible('expand');\">Cron</a> utförs inte.</li>";
        if ( $cfgMissing || $cfgObsolete ) echo "<li><a href='#' onclick=\"$('#sysadmin-config').collapsible('expand');\">Konfigurationen</a> är inte uppdaterad.</li>";
        echo "<ul></div>";
    } ?>
    
    <div data-role="collapsibleset" data-inset="false">
        
        <div data-role="collapsible" id="sysadmin-systeminfo">
            <h2>Systeminfo</h2>
            <?= $cronDelayed ? "<div style='float:left; font-size:3em; color:var(--FF-orange);'>⚠ </div>" : "" ?>
            <h3>Cron <span style='color:var(<?= $cronDelayed ? "--FF-orange" : "--FF-green" ?>);'>■</span></h3>
            <p><?= $lastCron == 0 ? "Cron har aldrig utförts" : "Cron utfördes senast för " . (int)( ( time() - $lastCron ) / 60 ) . " minuter sedan" ?>.</p>
            
            <?php
            if ( is_readable( $cfg[ 'logFile' ] ) && !is_dir( $cfg[ 'logFile' ] ) ) { ?>
            <h3>Systemlogg</h3>
            <a target="_blank" class="ui-btn ui-btn-a" href="?action=currentLog">Visa systemloggen</a>
            <?php } ?>

            <h3>Installerade moduler</h3>
            <ul><?php
                echo class_exists( "PDO" ) ? "" : "<li style='color:red;'><strong>PDO saknas.</strong> Behövs för databasen</li>";
                echo extension_loaded( "pdo_mysql" ) ? "" : "<li style='color:red;'><strong>pdo_mysql saknas.</strong> Behövs för databasen</li>";
                echo class_exists( "\PHPMailer\PHPMailer\PHPMailer" ) ? "" : "<li style='color:red;'><strong>PHPMailer saknas.</strong> Används för att skicka mejl</li>";
                foreach ( get_loaded_extensions() as $ext ) echo "<li>$ext</li>\n";
                ?>
            </ul>

            <h3>Statistik</h3>
			<ul><?php
			// Show some statistics
			$stmt = $db->query( "SELECT COUNT(*) users FROM users" );
			$row = $stmt->fetch( PDO::FETCH_OBJ );
			echo "<li>{$row->users} registrerade användare</li>";
		
			$stmt = $db->query( "SELECT COUNT(*) users FROM users WHERE mail!=''" );
			$row = $stmt->fetch( PDO::FETCH_OBJ );
			echo "<li>{$row->users} aktiverade användare</li>";
			
			$stmt = $db->query( "SELECT COUNT(DISTINCT sectionId) sections FROM sections JOIN categories USING (sectionId) JOIN items USING (catId)" );
			$row = $stmt->fetch( PDO::FETCH_OBJ );
			echo "<li>{$row->sections} aktiva lokalavdelningar</li>";

			$stmt = $db->query( "SELECT COUNT(*) items FROM items" );
			$row = $stmt->fetch( PDO::FETCH_OBJ );
			echo "<li>{$row->items} resurser upplagda</li>"; ?>
		</ul>
        </div>

        <div data-role="collapsible" id="sysadmin-config">
            <h2>Konfiguration</h2>
            
            <?php
            if ( $cfgMissing || $cfgObsolete ) {
                echo "<div class='ui-body ui-body-a' style='color:red;'>";
                if ( $cfgMissing ) {
                    echo "<p>Följande konfigurationsparametrar saknas:</p><ul>";
                    foreach ( $cfgMissing as $key=>$value ) echo "<li>\$cfg['$key'] = " . var_export( $value, true ) . "</li>";
                    echo "</ul>";
                }
                if ( $cfgObsolete ) {
                    echo "<p>Följande konfigurationsparametrar används inte längre:</p><ul>";
                    foreach ( $cfgObsolete as $key=>$value ) echo "<li>\$cfg['$key'] = " . var_export( $value, true ) . "</li>";
                    echo "</ul>";
                }
                echo "<p>Se filen inc/config.sample.php.</p></div>";
            } else echo "<p>Konfigurationsfilen är komplett.</p>";
            
            // Get SHA checksum for current commit (try git first, then git-ftp)
            $sha = @exec( "git log --pretty=format:'%H' -n 1" );
            if ( $sha ) $shaSource = "git";
            elseif ( file_exists( "../.git-ftp.log" ) ) {
                $sha = trim( file_get_contents( "../.git-ftp.log" ) );
                $shaSource = ".git-ftp.log";
            }
            if ( $sha ) {
                // See if there is any buffered information about the current code version
                $stmt = $db->query( "SELECT value FROM config WHERE name='gitInfo'" );
                $gitInfo = unserialize( $stmt->fetch( PDO::FETCH_OBJ )->value );
                if ( !isset( $gitInfo[ 'sha' ] ) || ( time() - $gitInfo[ 'lastChecked' ] > 60 * 60 ) || $gitInfo[ 'sha' ] != $sha || !$gitInfo[ 'date' ] ) {
                    // No or outdated buffered info. Get it from Github.
                    $gitInfo[ 'sha' ] = $sha;
                    ini_set( 'user_agent', 'ff-boka' ); // Need to set User Agent in order to get an answer from Github
                    $commit = json_decode( file_get_contents( "https://api.github.com/repos/d-tamm/ff-boka/commits/$sha" ) );
                    $gitInfo[ 'date' ] = $commit->commit->committer->date;
                    $gitInfo[ 'message' ] = $commit->commit->message;
                    $gitInfo[ 'branches' ] = [];
                    $gitInfo[ 'lastChecked' ] = time();
                    foreach ( json_decode( file_get_contents( "https://api.github.com/repos/d-tamm/ff-boka/branches" ) ) as $branch ) {
                        $cmp = json_decode( file_get_contents( "https://api.github.com/repos/d-tamm/ff-boka/compare/{$branch->name}...$sha" ) );
                        $gitInfo[ 'branches' ][ $branch->name ] = [ "status"=>$cmp->status, "ahead_by"=>$cmp->ahead_by, "behind_by"=>$cmp->behind_by ];
                    }
                    // Save gathered info for future calls to this page
                    $stmt = $db->prepare( "REPLACE INTO config SET name='gitInfo', value=?" );
                    $stmt->execute( [ serialize( $gitInfo ) ] );
                }
                echo "<p>Kodversion på den här installationen är (enligt $shaSource) commit <a href='https://github.com/d-tamm/ff-boka/commit/$sha' target='_blank'>" . substr( $sha, 0, 7 ) . "</a> från {$gitInfo[ 'date' ]} ({$gitInfo[ 'message' ]}).</p><ul>";
                foreach ( $gitInfo[ 'branches' ] as $name=>$b ) {
                    switch ( $b[ 'status' ] ) {
                        case "identical": echo "<li>Identisk med grenen <b>$name</b></li>"; break;
                        case "behind": echo "<li>{$b[ 'behind_by' ]} commits efter grenen $name</li>"; break;
                        case "ahead": echo "<li>{$b[ 'ahead_by' ]} commits före grenen $name</li>"; break;
                        default: echo "<li>{$b[ 'ahead_by' ]} commits före och {$b[ 'behind_by' ]} commits efter grenen $name</li>";
                    }
                }
                echo "</ul>";
            } else echo "<p>Okänd kodversion av installationen.</p>";
            ?>

            <p>Aktuell DB-version är <?php 
            $stmt = $db->query( "SELECT value FROM config WHERE name='db-version'" );
            $ver = $stmt->fetch( PDO::FETCH_OBJ );
            echo $ver->value; ?>.</p>

            <h3>Aktuell konfiguration</h3>
            <pre><?= strip_tags( print_r( $cfg, TRUE ) ) ?></pre>
        </div>

        <div data-role="collapsible">
            <h2>Senaste inloggningar</h2>
            <table class="alternate-rows">
            <tr><th>timestamp</th><th>IP</th><th>user</th><th>LA</th><th>succ</th><th>userAgent</th></tr>
            <?php
            $stmt = $db->query( "SELECT logins.timestamp timestamp, ip, login, userId, users.name name, sections.name section, success, userAgent FROM logins LEFT JOIN users USING (userId) LEFT JOIN sections USING (sectionId) ORDER BY timestamp DESC LIMIT 50" );
            while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                echo "<tr><td>{$row->timestamp}</td>
                    <td>{$row->ip}</td>
                    <td class='sysadmin-login-post'" . ( $row->name ? " title='Login: {$row->login}, medlemsnr: {$row->userId}'" : "" ) . " data-userid='{$row->userId}'>" . ( $row->name ? htmlspecialchars($row->name) : ( is_null( $row->userId ) ? $row->login : $row->userId ) ) . "</td>
                    <td title='" . htmlspecialchars( $row->section ) . "'>" . substr( htmlspecialchars( $row->section ), 0, 10 ) . "</td>
                    <td>{$row->success}</td>
                    <td>" . resolveUserAgent( $row->userAgent, $db ) . "</td></tr>";
            }
            ?></table>
        </div>

        <div data-role='collapsible'>
            <h2>Session data</h2>
            <pre><?php print_r( $_SESSION ); ?></pre>
        </div>

        <div data-role="collapsible" id="admin-section-sections">
            <h2>Lokalavdelningar</h2>
            <ul data-role="listview" data-inset="true" data-filter="true" data-split-icon="delete" data-split-theme="c"><?php
                $updated = -1;
                $stmt = $db->query( "SELECT *, DATEDIFF(NOW(), timestamp) < 35 AS updated FROM sections ORDER BY updated, name" );
                while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                    $section = new Section( $row->sectionId );
                    if ( $row->updated == 0 && $updated < 0 ) {
                        $updated = 0;
                        echo "<li data-role='list-divider' class='wrap' data-theme='c'><b>Ej uppdaterade LA</b><p>Följande LA har inte fått uppdateringar från centralregistret på sistone. De kanske inte är aktuella längre. Inaktuella LA utan kategorier kan raderas.</p></li>";
                    }
                    if ( $row->updated == 1 && $updated == 0 ) {
                        $updated = 1;
                        echo "<li data-role='list-divider' data-theme='c'>Aktuella LA</li>";
                    }
                    echo "<li class='wrap' id='admin-section-{$row->sectionId}'><a href=\"javascript:gotoSection({$row->sectionId}, '{$row->name}');\">";
                    echo "<h2>{$row->name} ({$row->sectionId})</h2>";
                    echo "<p>{$section->registeredUsers} registrerade användare, därav {$section->activeUsers} aktiva.";
                    echo "<br>{$section->activeItems} aktiverade resurser i {$section->numberOfCategories} kategorier.";
                    if (!$row->updated) echo "<br>Inte uppdaterad sedan {$row->timestamp}.";
                    echo "</p></a>";
                    if ( !$row->updated && $section->numberOfCategories == 0 ) echo "<a href=\"javascript:deleteSection({$row->sectionId}, '{$row->name}');\" title='Ta bort LA {$row->name}'></a>";
                    echo "</li>";
                }
            ?></ul>
        </div>

        <div data-role="collapsible" id="admin-section-misc">
            <h2>Diverse</h2>
            <h4>Ta LA-admin-rollen:</h4>
            <select name="sectionId" id="sectionadmin-sectionlist">
                <option>Välj lokalavdelning</option><?php
                $stmt = $db->query( "SELECT * FROM sections ORDER BY name" );
                while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
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

        <div data-role="collapsible" data-collapsed="<?= ( isset( $expand ) && $expand == "polls" ) ? "false" : "true" ?>">
            <h2>Enkäter</h2>
            <ul data-role='listview' data-split-icon='edit'>
                <?php
                foreach ( $FF->polls() as $poll ) {
                    echo "<li><a href='#' onClick='showPollResults( {$poll->id} );'>" . htmlspecialchars( $poll->question ) . "</a>
                        <a href='#' onClick='editPoll( {$poll->id} );'></a></li>";
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
