<?php

use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;

session_start();
require(__DIR__."/../inc/common.php");
global $cfg, $FF;

// Set current section
if (!$_SESSION['sectionId']) {
    header("Location: {$cfg['url']}");
    die();
}
$section = new Section($_SESSION['sectionId']);

// This page may only be accessed by registered users
if (!$_SESSION['authenticatedUser']) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för {$section->name}"));
    die();
}
// Only allow users which have at least some admin role in this section
$currentUser = new User($_SESSION['authenticatedUser']);
if (
    !$section->showFor($currentUser, FFBoka::ACCESS_CATADMIN) && 
    (!isset($_SESSION['assignments'][$section->id]) || !array_intersect($_SESSION['assignments'][$section->id], $cfg['sectionAdmins']))
) {
    header("Location: {$cfg['url']}?action=accessDenied&to=" . urlencode("administrationssidan för {$section->name}"));
    die();
}
$userAccess = $section->getAccess($currentUser);

if (isset($_REQUEST['message'])) $message = $_REQUEST['message'];

if (isset($_REQUEST['action'])) {
switch ($_REQUEST['action']) {
    case "help":
        echo "<p>Här visas användningsstatistiken för din lokalavdelning. Använd knapparna längst upp för att bläddra mellan åren. Du kan även klicka på kolumnhuvuden för att sortera tabellen enligt dina önskemål.</p>
        <p>Summan på sista raden visar antalet bokningar. Detta är inte summan av värdena i kolumnen eftersom varje bokning kan innehålla flera resurser. Summan i sista kolumnen (bokad tid) är dock summan av alla resurser.</p>";
        die();
    }
}

$year = $_GET['year'] ?? strftime("%Y");
$sum = $section->usageOverview($year);

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders("Friluftsfrämjandets resursbokning - Användningsstatistik ".$section->name, $cfg['url']) ?>
    <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script src="//cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
</head>


<body>
<div data-role="page" id="page-admin-usage">
    <?= head("Användningsstatistik", $cfg['url'], $cfg['superAdmins']) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-overlay-theme="b" id="popup-msg-page-admin-usage" class="ui-content">
        <p id="msg-page-admin-usage"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <div data-role="navbar" style="margin-bottom:2em;">
        <ul>
            <li><a href='index.php' data-icon='home'>Tillbaka</a></li>
            <li><a href='?year=<?= $year-1 ?>' data-icon='carat-l'><?= $year-1 ?></a></li>
            <li><a href='#' data-icon='calendar' class='ui-btn-active'><?= $year ?></a></li>
            <li><a href='?year=<?= $year+1 ?>' data-icon='carat-r'><?= $year+1 ?></a></li>
        </ul>
    </div>

    <table id='stat-details'>
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Resurs</th>
                <th>Antal bokningar</th>
                <th>Bokad tid [h]</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($section->usageDetails($year) as $item) { ?>
            <tr>
                <td><a href="category.php?catId=<?= $item->catId ?>"><?= $item->category ?></a></td>
                <td><a href="item.php?catId=<?= $item->catId ?>&amp;itemId=<?= $item->itemId ?>"><?= $item->item ?></a></td>
                <td style="text-align:center;"><?= $item->bookings ?></td>
                <td style="text-align:center;"><?= (int)$item->duration ?></td>
            </tr>
        <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan=2 style="font-weight:bold;">Summa bokningar <?= $year ?></td>
                <td style="text-align:center;"><?= $sum['bookings'] ?></td>
                <td style="text-align:center;"><?= $sum['duration'] ?></td>
            </tr>
        </tfoot>
    </table>

    </div><!--/main-->

</div><!--/page-->
</body>
</html>
