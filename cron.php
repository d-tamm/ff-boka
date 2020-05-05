<?php
use FFBoka\Category;
use FFBoka\User;
use FFBoka\FFBoka;

/**
 * Cron tasks for resource booking system
 * To be called once every 60 minutes via cron or webcron
 */

require(__DIR__."/inc/common.php");
global $db, $cfg, $FF;

/**
 * Hourly cron jobs (executed whenever this script is called)
 */
echo "Executing hourly jobs...\n";
// Get missing user agents
fetchUA($db);
// Keep login API alive
//$FF->authenticateUser("0", "0");
// Record last execution time
$db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last hourly cron run'");
echo "Hourly jobs finished.\n\n";

/**
 * Daily cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last daily cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$midnight = new DateTime("midnight");
if ((int)$row->value < $midnight->getTimestamp() && date("G") >= $cfg['cronDaily']) {
    echo "Time to execute daily jobs...\n";
    // ...
    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last daily cron run'");
    echo "Daily jobs finished.\n\n";
}

/**
 * Weekly cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last weekly cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$monday = new DateTime("monday this week");
if ((int)$row->value < $monday->getTimestamp() && date("N") >= $cfg['cronWeekly']) {
    echo "Time to execute weekly jobs...\n";
    
    $FF->updateSectionList(TRUE);

    // Update incomplete user agents
    fetchUA($db, 5);
    
    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last weekly cron run'");
    echo "Weekly jobs finished.\n\n";
}

/**
 * Monthly cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last monthly cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$first = new DateTime("first day of");
if ((int)$row->value < $first->getTimestamp() && date("j") >= $cfg['cronMonthly']) {
    echo "Time to execute monthly jobs...\n";

    echo "Deleting expired persistent login tokens... ";
    $numDeleted = $db->exec("DELETE FROM persistent_logins WHERE expires < NOW()");
    echo "$numDeleted tokens deleted.\n";
    
    echo "Deleting records from cat_admin_noalert which do not any more belong to a user with admin rights...\n";
    $stmt = $db->query("SELECT * FROM cat_admin_noalert");
    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        $cat = new Category($row->catId);
        $user = new User($row->userId);
        if ($cat->getAccess($user, FALSE) < FFBoka::ACCESS_CONFIRM) {
            echo "Removing record for user {$row->userId}, cat {$row->catId}\n";
            $db->exec("DELETE FROM cat_admin_noalert WHERE userId={$row->userId} AND catId={$row->catId}");
        }
    }
    echo "Done.\n";
    
    echo "Garbage collection: Remove orphaned full size images...\n";
    // TODO: remove orphaned attachment files
    foreach (glob(__DIR__ . "/img/cat/*") as $file) {
        if (!is_dir($file)) {
            $stmt = $db->query("SELECT catId FROM categories WHERE catId=" . basename($file));
            if ($stmt->rowCount()==0) {
                if (unlink($file)) echo "Removed category image $file\n";
                else echo "ERROR: Failed to remove orphaned category image $file\n";
            }
        }
    }
    foreach (glob(__DIR__ . "/img/item/*") as $file) {
        if (!is_dir($file)) {
            $stmt = $db->query("SELECT imageId FROM item_images WHERE imageId=" . basename($file));
            if ($stmt->rowCount()==0) {
                if (unlink($file)) echo "Removed item image $file\n";
                else echo "ERROR: Failed to remove orphaned item image $file\n";
            }
        }
    }
    echo "Done.\n";
    
    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last monthly cron run'");
    echo "Monthly jobs finished.\n\n";
}


/**
 * Resolve some userAgents if missing any
 * @param PDO $db
 * @param int $updateIncomplete If >0, this many incomplete posts are queried for update
 */
function fetchUA(PDO $db, int $updateIncomplete = 0) {
    if ($updateIncomplete>0) $stmt = $db->query("SELECT userAgent, uaHash FROM user_agents WHERE platform='' OR platform_version='' OR platform_bits='' OR version='' OR device_type='' LIMIT $updateIncomplete");
    elseif ($updateIncomplete==0) $stmt = $db->query("SELECT userAgent, uaHash FROM user_agents WHERE browser='' LIMIT 1");
    else return;
    if ($stmt->rowCount()) {
        echo "Resolving user agents\n";
        $stmt1 = $db->prepare("UPDATE user_agents SET browser=:browser, version=:version, platform=:platform, platform_version=:platform_version, platform_bits=:platform_bits, device_type=:device_type WHERE uaHash=:uaHash");
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $options = array(
                'http' => array(
                    'method'  => 'POST',
                    'content' => http_build_query([ 'action' => 'parse', 'format' => 'json', 'string' => $row->userAgent ])
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents('https://user-agents.net/parser', false, $context);
            if ($result !== FALSE) {
                $result = json_decode($result);
                $stmt1->execute(array(
                    ":browser" => $result->browser,
                    ":version" => $result->version,
                    ":platform" => $result->platform,
                    ":platform_version" => $result->platform_version,
                    ":platform_bits" => $result->platform_bits,
                    ":device_type" => $result->device_type,
                    ":uaHash" => $row->uaHash
                ));
                echo "Resolved user agent: {$row->userAgent}\n";
            } else {
                echo "Failed to resolve user agent: {$row->userAgent}\n";
            }
        }
    }
}
