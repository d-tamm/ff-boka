<?php
use FFBoka\User;
use FFBoka\Item;
use FFBoka\FFBoka;

session_start();
require( "inc/common.php" );
global $cfg;

if ( isset( $_REQUEST[ 'action' ] ) ) {
    switch ( $_REQUEST[ 'action' ] ) {
    case "help":
        echo <<<EOF
    Här visas detaljer till resursen.
    EOF;
        die();
    }
}

if ( $_SESSION['authenticatedUser']) $currentUser = new User($_SESSION['authenticatedUser']);
else $currentUser = new User(0);
if ( $_REQUEST[ 'itemId' ] ) {
    $item = new Item( $_REQUEST[ 'itemId' ] );
} else {
    header( "HTTP/1.1 400 Bad Request" );
    die( "Expecting itemId" );
}

$cat = $item->category();
$access = $cat->getAccess( $currentUser );
if ( $access < FFBoka::ACCESS_READASK ) {
    header( "HTTP/1.1 403 Forbidden" );
    die();
}
?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-book-part">
    <?= head( $item->caption, $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">
    
    <?php echo $Parsedown->text( $item->description );
    
    if ( $access >= FFBoka::ACCESS_CONFIRM && $item->note ) echo "<p class='ui-body ui-body-a'><i>" . htmlspecialchars( $item->note ) . "</i></p>";
    
    foreach ( $item->images() as $img ) {
        echo "<div class='item-image'><img src='image.php?type=itemImage&id={$img->id}'><label>" . htmlspecialchars( $img->caption ) . "</label></div>";
    }
    if ( $access >= FFBoka::ACCESS_PREBOOK ) { // show coming bookings
        $bookedItems = $item->upcomingBookings();
        echo "<div class='ui-body ui-body-a'><h3>Kommande bokningar</h3>\n<ul>\n";
        foreach ( $bookedItems as $bi ) {
            $b = $bi->booking();
            echo "<li>" . FFBoka::formatDateSpan( $bi->start, $bi->end, true );
            if ( $b->okShowContactData == 1 && $_SESSION[ 'authenticatedUser' ] ) echo "<br>Bokad av " . htmlspecialchars( $b->userName . " (" . $b->userPhone . ", " . $b->userMail . ")" );
            echo "</li>\n";
        }
        if ( !count( $bookedItems ) ) echo "<li>Det finns inga kommande bokningar i systemet.</li>\n";
        echo "</ul>\n</div>\n";
    }
    if ( $access >= FFBoka::ACCESS_CATADMIN ) {
        echo "<a class='ui-btn' href='{$cfg[ 'url' ]}admin/item.php?catId={$cat->id}&itemId={$item->id}'>Bearbeta resursen</a>";
    } ?>

    </div>
</div><!-- /page -->

</body>
</html>
