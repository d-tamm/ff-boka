<?php
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Booking;
global $cfg, $message, $FF;
session_start();
require( "inc/common.php" );

/**
 * Displays a nested view of categories with their items. Steps down into child categories.
 * @param Category $cat Starting category
 * @param User $user User whose access rights shall be used
 * @param int $fbStart Timestamp for date from which to start showing freebusy information
 * @param string[] $fileTypes Array of allowed file types for attachments ($extension=>$icon_filename)
 * @return int Number of items displayed, including those of child categories. 
 */
function displayCat( Category $cat, $user, $fbStart, $fileTypes = [] ) {
    $numItems = 0;
    if ( $cat->showFor( $user ) ) {
        $access = $cat->getAccess( $user );
        echo "<div id='book-cat-{$cat->id}' data-role='collapsible'";
        if ( isset( $_GET[ 'selectItemId' ] ) ) {
            // Expand category of selected item. The item itself will be selected by javascript.
            $i = new Item( $_GET[ 'selectItemId' ] );
            if ( $i->isBelowCategory( $cat ) ) echo " data-collapsed='false'";
        }
        echo " data-inset='false'>";
        echo "<h3><div class='cat-list-img'>" . embedImage( $cat->thumb ) . "</div>" . htmlspecialchars( $cat->caption ) . "</h3>";
        if ( $cat->prebookMsg ) echo "<p>" . str_replace( "\n", "<br>", htmlspecialchars( $cat->prebookMsg ) ) . "</p>";
        if ( $cat->showContactWhenBooking && $cat->contactData() ) echo "<p class='contact-data'>Vid frågor, kontakta:<br>{$cat->contactData()}</p>";
        $files = "";
        foreach ( $cat->files() as $file ) {
            if ( $file->displayLink ) {
                $files .= "<p><a href='attment.php?fileId={$file->fileId}' target='_blank' data-ajax='false' title='Ladda ner " . htmlspecialchars( $file->filename ) . "'>";
                $ext = strtolower( pathinfo( $file->filename, PATHINFO_EXTENSION ) );
                if ( array_key_exists( $ext, $fileTypes ) ) {
                    $files .= "<img src='resources/{$fileTypes[ $ext ]}' style='vertical-align:middle; width:32px;'> ";
                } else {
                    $files .= "<img src='resources/document.svg' style='vertical-align:middle; width:32px;'> ";
                }
                $files .= htmlspecialchars( $file->caption ) . "</a></p>";
            }
        }
        if ( $files ) echo "<h4>Dokument till denna kategori:</h4>$files";
        if ( $access ) {
            echo "<ul data-role='listview' data-split-icon='info' data-split-theme='b'>";
            foreach ( $cat->items() as $item ) {
                if ( $item->active ) {
                    $numItems++;
                    echo "<li data-catid='{$cat->id}' class='book-item' id='book-item-{$item->id}'><a href=\"javascript:toggleItem({$item->id});\">";
                    echo embedImage( $item->getFeaturedImage()->thumb );
                    echo "<h4>" . htmlspecialchars( $item->caption ) . "</h4>";
                    if ( $cat->getAccess( $user ) >= FFBoka::ACCESS_PREBOOK ) echo "<div class='freebusy-bar'><div id='freebusy-item-{$item->id}'></div>" . Item::freebusyScale() . "</div>";
                    echo "</a><a href='javascript:popupItemDetails({$item->id})'></a>";
                    echo "</li>";
                }
            }
            echo "<br></ul>";
        }
        foreach ( $cat->children() as $child ) {
            $numItems += displayCat( $child, $user, $fbStart, $fileTypes );
        }
        echo "</div>";
    }
    return $numItems;
}

if ( isset( $_GET[ 'sectionName' ] ) ) {
    // Page was called via direct link with clear text section name and a rewrite rule.
    // Find the corresponding section
    $_REQUEST[ 'sectionId' ] = $FF->getSectionIdByName( $_GET[ 'sectionName' ] );
    if ( $_REQUEST[ 'sectionId' ] === FALSE ) {
        header( "Location: {$cfg[ 'url' ]}index.php?message=" . urlencode( "Adressen du änvände är ogiltig." ) );
        die();
    }
}

if ( isset( $_REQUEST[ 'sectionId' ] ) ) {
    $_SESSION[ 'sectionId' ] = $_REQUEST[ 'sectionId' ];
    unset( $_SESSION[ 'bookingId' ] );
}
if ( !$_SESSION[ 'sectionId' ] ) {
    header( "Location: index.php?action=sessionExpired" );
    die();
}

$section = new Section( $_SESSION[ 'sectionId' ] );

if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
    $currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
    if ( !$currentUser->name || !$currentUser->mail || !$currentUser->phone ) {
        // We are missing contact details for this user.
        header( "Location: userdata.php?first_login=1" );
        die();
    }
} else $currentUser = new User( 0 );


if ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == "help" ) {
    echo <<<END
    <h4>Hur bokar jag?</h4>
    <p>Här visas alla resurser i lokalavdelningen som du har tillgång till. Klicka på de resurser du vill boka. När du har valt resurserna går du vidare till nästa steg där du väljer start- och sluttid.</p>
    <p>För varje resurs visas tillgängligheten under en vecka i taget.<br>
        <span class='freebusy-free' style='display:inline-block; width:2em;'>&nbsp;</span> tillgänglig tid<br>
        <span class='freebusy-busy' style='display:inline-block; width:2em;'>&nbsp;</span> upptagen tid<br>
        <span class='freebusy-blocked' style='display:inline-block; width:2em;'>&nbsp;</span> ej bokbar tid<br>
        <span class='freebusy-unknown' style='display:inline-block; width:2em;'>&nbsp;</span> ingen information tillgänglig<br>
        Med knapparna längst ned kan du bläddra bak och fram i tiden.
        Administratören kan för vissa poster ha valt att inte visa upptaget-information.
        I dessa fall blir din bokning bara en förfrågan där du anger önskad start- och sluttid i nästa steg.</p>
    <p>Du kan se mer information om varje resurs genom att klicka på info-knappen till höger.</p>
    <p>Om du vill göra en bokning där olika resurser behövs olika länge delar du upp bokningen. Börja med att boka alla resurser som ska ha samma tid. Sedan får du möjlighet att lägga till fler delbokningar med andra tider och/eller resurser.</p>
    END;
    die();
}

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-book-part">
    <?= head( "Lägg till resurser", $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-book-part" class="ui-content">
        <p id="msg-page-book-part"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-corner-all ui-icon-check'>OK</a>
    </div>

    <h4>Lokalavdelning: <?= htmlspecialchars( $section->name ) ?></h4>
    <p><i>Välj de resurser du vill boka genom att klicka på dem. Längst ner på skärmen finns det kontroller där du kan bläddra bak och fram i tiden för att se tillgängligheten. Start- och sluttid på din bokning väljer du i steg 2.</i></p>

    <?php
    if ( !isset( $_SESSION[ 'authenticatedUser' ] ) ) echo "<p class='ui-body ui-body-a'>Du bokar som gäst. Om du är medlem i Friluftsfrämjandet och vill boka som medlem så behöver du <a href='index.php?redirect=" . urlencode( $_SERVER[ 'REQUEST_URI' ] ) . "'>logga in först</a>.</p>";
    
    if ( isset( $_SESSION[ 'bookingId' ] ) ) echo "<p class='ui-body ui-body-a'>Du har en påbörjad bokning. Resurserna du väljer nedan kommer att läggas till bokningen.<a data-transition='slide' class='ui-btn' href='book-sum.php'>Visa bokningen</a></p>";
    ?>

    <h3 class="ui-bar ui-bar-a">Steg 1. Välj resurser</h3>
    <?php
    $numItems = 0;
    foreach ( $section->getMainCategories() as $cat ) {
        if ( $cat->active ) {
            $numItems += displayCat( $cat, $currentUser, strtotime( "last sunday +1 day" ), $cfg[ 'allowedAttTypes' ] );
        }
    }
    if ( $numItems == 0 ) {
        echo "<p><i>I den här lokalavdelningen finns det inte några resurser som du kan boka. Det kan bero på att lokalavdelningen inte (ännu) använder systemet, eller att du inte har behörighet att boka de resurser som har lagts upp.</i></p>";
    }
    ?>


    <div id="book-step2" style="display:none">
        <h3 class="ui-bar ui-bar-a">Steg 2. Välj tid</h3>
        <p>Nedan visas en sammanfattning av tiderna då dina valda poster är tillgängliga / bokade.</p>
        <div id='book-access-msg'></div>

        <div class='freebusy-bar' style='height:50px;'>
            <div id='book-combined-freebusy-bar'></div>
            <div id='book-chosen-timeframe'></div>
            <?= Item::freebusyScale( true ) ?>
        </div>
        <div id='book-warning-conflict'>Den valda tiden krockar med befintliga bokningar.</div>
        <div id='book-date-chooser-next-click'>Klicka på önskat startdatum.</div>

        <div class='ui-field-contain'>
            <label for='book-time-start'>Vald bokningstid från:</label>
            <div data-role='controlgroup' data-type='horizontal'>
                <input type='date' id='book-date-start' data-wrapper-class='controlgroup-textinput ui-btn'>
                <select name='book-time-start' id='book-time-start'><?php for ( $h = 0; $h < 24; $h++ ) echo "<option value='$h'>$h:00</option>"; ?></select>
            </div>
        </div>
        <div class='ui-field-contain'>
            <label for='book-time-end'>Till:</label>
            <div data-role='controlgroup' data-type='horizontal'>
                <input type='date' id='book-date-end' data-wrapper-class='controlgroup-textinput ui-btn'>
                <select name='book-time-end' id='book-time-end'><?php for ( $h = 0; $h < 24; $h++ ) echo "<option value='$h'>$h:00</option>"; ?></select>
            </div>
        </div>
        
        <button id="book-btn-save-part" disabled="disabled" onClick="checkTimes(true);">Gå vidare</button>
    </div><!-- /#book-step2 -->
    
    </div><!--/main-->
    

    <div data-role="footer" data-position="fixed" data-theme="a">
        <div class="footer-button-left">
            <a href="javascript:scrollDate(-28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-leftleft ui-nodisc-icon">-4 veckor</a>
            <a href="javascript:scrollDate(-7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-left ui-nodisc-icon ui-alt-icon">-1 vecka</a>
        </div>
        <h2 id="book-current-range-readable"></h2>
        <div class="footer-button-right">
            <a href="javascript:scrollDate(7);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-right ui-nodisc-icon ui-alt-icon">+1 vecka</a>
            <a href="javascript:scrollDate(28);" class="ui-btn ui-corner-all ui-btn-icon-notext ui-icon-rightright ui-nodisc-icon ui-alt-icon">+10 veckor</a>
        </div>
    </div><!--/footer-->
    
    <div data-role="popup" id="popup-item-details" class="ui-content" data-overlay-theme="b">
        <h3 id='item-caption'></h3>
        <div id='item-details'></div>
    </div>
    
    <div data-role="popup" id="popup-items-unavail" class="ui-content" data-overlay-theme="b">
        <h3>Kan inte boka</h3>
        <p>Tiden du har valt är inte tillgänglig. Följande resurser har redan bokningar som krockar med dina valda tider:</p>
        <ul id='ul-items-unavail'></ul>
        <a href="#" data-rel="back" class="ui-btn">OK</a>
    </div>

</div><!-- /page -->

</body>
</html>
