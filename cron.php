<?php
use FFBoka\Category;
use FFBoka\User;
use FFBoka\FFBoka;

/**
 * Cron tasks for resource booking system
 * To be called once every 60 minutes via cron or webcron
 */

require("inc/common.php");
global $db, $cfg, $FF;

/**
 * Hourly cron jobs
 */
echo "Executing hourly jobs...\n";
// e.g. booking reminders?
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
        echo "Done.\n";
    }

    // Record last execution time
    $db->exec("UPDATE config SET value=UNIX_TIMESTAMP() WHERE name='last monthly cron run'");
    echo "Monthly jobs finished.\n\n";
}