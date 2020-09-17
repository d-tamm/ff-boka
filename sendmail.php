<?php
if (isset($_GET['mailqId'])) {
    require "inc/common.php";
    sleep(2);
    if ($FF->sendQueuedMail($_GET['mailqId'], $cfg['mailFrom'], $cfg['mailFromName'], $cfg['mailReplyTo'], $cfg['SMTP'])) echo "Message sent.";
    else echo "Failed to send message.";
} else echo "Please provide mailqId parameter.";