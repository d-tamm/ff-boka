<?php
use FFBoka\Category;
use FFBoka\User;
use FFBoka\FFBoka;

/**
 * Cron tasks for resource booking system
 * To be called regularly via cron or webcron. Since the sending of emails is handled here,
 * call this at least every 5 minutes or so.
 */

require(__DIR__."/inc/common.php");
global $db, $cfg, $FF;

$stmt = $db->query("SELECT value FROM config WHERE name='last hourly cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$last = new DateTime("@".$row->value);
$since = $last->diff(new DateTime(), true);
logger(__METHOD__.sprintf("Executing cron jobs. Last cron execution was %s ago.", $since->format("%H:%I:%S")));

/**
 * Cron jobs executed whenever this script is called
 */

// Send queued mails
$FF->sendQueuedMails($cfg['mailFrom'], $cfg['mailFromName'], $cfg['mailReplyTo'], $cfg['SMTP']);

// Record last execution time
$db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last hourly cron run'");


/**
 * Daily cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last daily cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$midnight = new DateTime("midnight");
if ((int)$row->value < $midnight->getTimestamp() && date("G") >= $cfg['cronDaily']) {
    logger(__METHOD__." Time to execute daily jobs...");

    // Lookup one missing (new) user agent (if there is any)
    fetchUA($db);

    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last daily cron run'");
}


/**
 * Weekly cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last weekly cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$monday = new DateTime("monday this week");
if ((int)$row->value < $monday->getTimestamp() && date("N") >= $cfg['cronWeekly']) {
    logger(__METHOD__." Time to execute weekly jobs...");
    
    $FF->updateSectionList();
    $FF->updateAssignmentList();

    // Update some incomplete user agents
    fetchUA($db, 5);
    
    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last weekly cron run'");
    logger(__METHOD__." Weekly jobs finished.");
}

/**
 * Monthly cron jobs
 */
$stmt = $db->query("SELECT value FROM config WHERE name='last monthly cron run'");
$row = $stmt->fetch(PDO::FETCH_OBJ);
$first = new DateTime("first day of");
if ((int)$row->value < $first->getTimestamp() && date("j") >= $cfg['cronMonthly']) {
    logger(__METHOD__." Time to execute monthly jobs...");

    $numDeleted = $db->exec("DELETE FROM persistent_logins WHERE expires < NOW()");
    logger(__METHOD__." $numDeleted expired persistent login tokens deleted.");

    $numDeleted = $db->exec("DELETE FROM tokens WHERE DATE_ADD(timestamp, INTERVAL ttl SECOND)<NOW()");
    logger(__METHOD__." $numDeleted other tokens deleted.");
    
    logger(__METHOD__." Deleting records from cat_admin_noalert which do not any more belong to a user with admin rights...");
    $stmt = $db->query("SELECT * FROM cat_admin_noalert");
    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        $cat = new Category($row->catId);
        $user = new User($row->userId);
        if ($cat->getAccess($user, FALSE) < FFBoka::ACCESS_CONFIRM) {
            logger(__METHOD__." Removing record for user {$row->userId}, cat {$row->catId}");
            $db->exec("DELETE FROM cat_admin_noalert WHERE userId={$row->userId} AND catId={$row->catId}");
        }
    }
    
    logger(__METHOD__." Garbage collection: Delete orphaned full size images...");
    foreach (glob(__DIR__ . "/img/cat/*") as $file) {
        if (!is_dir($file)) {
            $stmt = $db->query("SELECT catId FROM categories WHERE catId=" . basename($file));
            if ($stmt->rowCount()==0) {
                if (unlink($file)) logger(__METHOD__." Deleted category image $file");
                else logger(__METHOD__." Failed to delete orphaned category image $file", E_ERROR);
            }
        }
    }
    foreach (glob(__DIR__ . "/img/item/*") as $file) {
        if (!is_dir($file)) {
            $stmt = $db->query("SELECT imageId FROM item_images WHERE imageId=" . basename($file));
            if ($stmt->rowCount()==0) {
                if (unlink($file)) logger(__METHOD__." Deleted item image $file");
                else logger(__METHOD__." Failed to delete orphaned item image $file", E_ERROR);
            }
        }
    }
    foreach (glob(__DIR__ . "/uploads/*") as $file) {
        if (!is_dir($file)) {
            $stmt = $db->query("SELECT fileId FROM cat_files WHERE fileId=" . basename($file));
            if ($stmtm->rowCount()==0) {
                if (unlink($file)) logger(__METHOD__." Deleted attachment file $file");
                else logger(__METHOD__." Failed to delete orphaned attachment file $file", E_ERROR);
            }
        }
    }
    logger(__METHOD__." Done removing orphaned images.");

    // Number of sections with items
    $db->exec("INSERT INTO stats SET `key`='sections', `value`=(SELECT COUNT(DISTINCT sectionId) FROM sections JOIN categories USING (sectionId) JOIN items USING (catId))");
    // Total number of active items
    $db->exec("INSERT INTO stats SET `key`='items', `value`=(SELECT COUNT(*) FROM items WHERE active)");
    // Number of activated users
    $db->exec("INSERT INTO stats SET `key`='active users', `value`=(SELECT COUNT(*) FROM users WHERE name!='')");
    // Number of active users last month
    $db->exec("INSERT INTO stats SET `key`='recent users', `value`=(SELECT COUNT(*) FROM users WHERE name!='' AND ADDDATE(lastLogin, INTERVAL 1 MONTH)>NOW())");
    // Number of bookings last month
    $db->exec("INSERT INTO stats SET `key`='bookings', `value`=(SELECT COUNT(*) FROM bookings WHERE ADDDATE(timestamp, INTERVAL 1 MONTH)>NOW())");
    foreach ($FF->getAllSections() as $section) {
        // Number of active items
        $db->exec("INSERT INTO stats SET sectionId={$section->id}, `key`='items', `value`=(SELECT COUNT(*) FROM items INNER JOIN categories ON (catId) WHERE sectionId={$section->id} AND active)");
        // Number of activated users
        $db->exec("INSERT INTO stats SET sectionId={$section->id}, `key`='active users', `value`=(SELECT COUNT(*) FROM users WHERE sectionId={$section->id} AND name!='')");
        // Number of active users last month
        $db->exec("INSERT INTO stats SET sectionId={$section->id}, `key`='recent users', `value`=(SELECT COUNT(*) FROM users WHERE sectionId={$section->id} AND name!='' AND ADDDATE(lastLogin, INTERVAL 1 MONTH)>NOW())");
        // Number of bookings last month
        $db->exec("INSERT INTO stats SET sectionId={$section->id}, `key`='bookings', `value`=(SELECT COUNT(*) FROM bookings WHERE sectionId={$section->id} AND ADDDATE(timestamp, INTERVAL 1 MONTH)>NOW())");
        // 10 most frequently booked items last year
        $stmt = $db->query("SELECT items.caption item, categories.caption category, COUNT(*) bookings FROM `booked_items` INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE ADDDATE(bookings.timestamp, INTERVAL 12 MONTH)>NOW() AND categories.sectionId={$section->id} GROUP BY items.itemId ORDER BY bookings DESC LIMIT 10");
        $db->exec("INSERT INTO stats SET sectionId={$section->id}, `key`='favorite items', `value`='" . json_encode($stmt->fetchAll(PDO::FETCH_OBJ)) . "'" );
    }
    logger(__METHOD__." Saved monthly statistics.");
    
    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last monthly cron run'");
}


/**
 * Resolve some userAgents if missing any
 * @param PDO $db
 * @param int $updateIncomplete If set and >0, this many incomplete posts are queried for update. Otherwise, 1 new post is looked up.
 */
function fetchUA(PDO $db, int $updateIncomplete = 0) {
    if ($updateIncomplete>0) $stmt = $db->query("SELECT userAgent, uaHash FROM user_agents WHERE platform='' OR platform_version='' OR platform_bits='' OR version='' OR device_type='' ORDER BY lookups LIMIT $updateIncomplete");
    else $stmt = $db->query("SELECT userAgent, uaHash FROM user_agents WHERE browser='' ORDER BY lookups LIMIT 1");
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
    if (count($rows)) {
        $stmt1 = $db->prepare("UPDATE user_agents SET browser=:browser, version=:version, platform=:platform, platform_version=:platform_version, platform_bits=:platform_bits, device_type=:device_type WHERE uaHash=:uaHash");
        foreach ($rows as $row) {
            logger(__METHOD__." Resolving user agent {$row->userAgent}");
            $options = array(
                'http' => array(
                    'header'  => 'Content-Type: application/x-www-form-urlencoded',
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
                // Increase lookup counter which will lower the priority of this post next time
                $db->exec("UPDATE user_agents SET lookups=lookups+1 WHERE uaHash='{$row->uaHash}'");
            } else {
                logger(__METHOD__." Failed to resolve user agent: {$row->userAgent}", E_WARNING);
            }
        }
    }
}
