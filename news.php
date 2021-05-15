<?php

session_start();
require("inc/common.php");
global $cfg;

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Nyheter", $cfg['url']) ?>
</head>

<body>
<div data-role="page" id="page-news">
    <?= head("Nyhetsarkiv", $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <?php
    $stmt = $db->query("SELECT * FROM news ORDER BY date DESC");
    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) { ?>
        <div class='ui-body ui-body-a'>
            <p><small><?= $row->date ?></small></p>
            <h3><?= $row->caption ?></h3>
            <p><?= $row->body ?></p>
        </div><br><?php
    } ?>

    </div><!--/main-->

</div><!--/page-->

</body>
</html>
