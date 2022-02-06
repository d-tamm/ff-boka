<?php
// Handle ajax requests for non-admin pages

use FFBoka\Booking;
use FFBoka\FFBoka;
use FFBoka\Item;
use FFBoka\Question;
use FFBoka\User;

session_start();
require( __DIR__."/inc/common.php" );
global $cfg, $FF;

if ( !isset( $_REQUEST[ 'action' ] ) ) { http_response_code( 400 ); die(); } // Bad request

// Check permissions and set some basic objects
$currentUser = new User( $_SESSION[ 'authenticatedUser' ] ?? 0 );

if ( in_array( $_REQUEST[ 'action' ], [ "confirmBooking", "repeatCreate", "deleteBooking", "setPaid", "removeItem", "repeatPreview", "unlinkBooking", "unlinkSeries", "deleteSeries", "getSeries" ] ) ) {
    if ( !isset( $_SESSION[ 'bookingId' ] ) ) {
        http_response_code( 406 ); // Not acceptable
        die( "bookingId not set." );
    }
    $booking = new Booking( $_SESSION[ 'bookingId' ] );
}

switch ( $_REQUEST[ 'action' ] ) {
    case "repeatCreate":
    case "unlinkBooking":
        if ( !isset( $_SESSION[ 'authenticatedUser' ] ) ) {
            http_response_code( 401 ); die( "Booking series are only available for authenticated users." );  // Unauthorized
        }
    case "confirmBooking":
    case "deleteBooking":
    case "repeatPreview":
    case "unlinkSeries":
    case "deleteSeries":
    case "getSeries":
        // Check permissions: Either booking owner, correct token or admin
        if ( !(
            ( isset( $_SESSION[ 'token' ] ) && ( $_SESSION[ 'token' ] == $booking->token ) ) || // correct token
            ( $_SESSION[ 'authenticatedUser' ] && ( $booking->userId == $_SESSION[ 'authenticatedUser' ] ) ) // same user
        ) ) {
            if ( !$_SESSION[ 'authenticatedUser' ] ) { http_response_code( 401 ); die(); } // Unauthorized
            // Last access check: current user must be admin of some category
            if ( !$booking->section()->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) { http_response_code( 401 ); die(); } // Unauthorized
        }
        // Check if booking collides with existing ones or with itself
        $unavail = array();
        $conflicts = array();
        $overlap = $booking->getOverlappingItems();
        foreach ( $booking->items() as $item ) {
            if ( $item->status !== FFBoka::STATUS_REJECTED && !$item->isAvailable( $item->start, $item->end ) ) {
                if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_PREBOOK ) {
                    // User can see freebusy information. Let them change booking.
                    $unavail[] = $item->bookedItemId;
                } else {
                    // User can't see freebusy information. Flag as conflict upon confirmation.
                    $conflicts[] = $item->bookedItemId;
                }
            }
        }
        if ( $_POST[ 'extMail' ] && !filter_var( $_POST[ 'extMail' ], FILTER_VALIDATE_EMAIL ) ) {
            http_response_code( 400 ); // Bad request
            die( "Epostadressen har ett ogiltigt format." );
        }
        break;
    case "freebusyItem":
        break;
    case "setItemPrice":
        if ( !isset( $_POST[ 'bookedItemId' ] ) ) { http_response_code( 400 ); die( "bookedItemId missing" ); } // Bad request
        $item = new Item( $_POST[ 'bookedItemId' ], TRUE );
        // Only admins for this item may set price
        if ( $item->category()->getAccess( $currentUser ) < FFBoka::ACCESS_CONFIRM ) {
            http_response_code( 403 ); die( "Du har inte behörighet att sätta priset." ); // Forbidden
        }
        break;
    case "setPaid":
        // User needs to have some admin assignment
        if ( !$booking->section()->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) {
            http_response_code( 403 ); die( "Du har inte behörighet att sätta värdet." ); // Forbidden
        }
        break;
    case "removeItem":
        if ( !isset( $_POST[ 'bookedItemId' ] ) ) { http_response_code( 400 ); die( "bookedItemId missing" ); } // Bad request
        // Require original user, right token or be booking admin
        $item = new Item( $_POST[ 'bookedItemId' ], TRUE );
        if ( $item->category()->getAccess( $currentUser ) < FFBoka::ACCESS_CONFIRM && ( $booking->userId && $booking->userId !== $_SESSION[ 'authenticatedUser' ] ) && $_SESSION[ 'token' ] != $booking->token ) {
            http_response_code( 403 ); // forbidden
            die( "Du har inte behörighet att ta bort resursen." );
        }
        break;
    case "handleBookedItem":
        if ( !isset( $_POST[ 'bookedItemId' ] ) ) { http_response_code( 400 ); die( "bookedItemId missing" ); } // Bad request
        if ( !isset( $_POST[ 'status' ] ) ) { http_response_code( 400 ); die( "status missing" ); } // Bad request
        // Require booking admin
        $item = new Item( $_POST[ 'bookedItemId' ], TRUE );
        if ( $item->category()->getAccess( $currentUser ) < FFBoka::ACCESS_CONFIRM ) {
            http_response_code( 403 ); // forbidden
            die( "Du har inte behörighet att bekräfta resursen." );
        }
        break;
    default:
        http_response_code( 405 ); // Method not allowed
        die();
}



switch ( $_REQUEST[ 'action' ] ) {
    case "freebusyItem":
        // Get freebusy bars for current booked item
        if ( !isset( $_SESSION[ 'bookedItemId' ] ) ) { http_response_code( 400 ); die( "Unknown bookedItemId" ); }
        if ( !isset( $_GET[ 'start' ] ) ) { http_response_code( 400 ); die( "start missing" ); }
        $item = new Item( $_SESSION[ 'bookedItemId' ], TRUE );
        if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_PREBOOK ) {
            die( $item->freebusyBar( [ 'start' => $_GET[ 'start' ] ] ) );
        }
        http_response_code( 403 ); // Forbidden
        die();

    case "confirmBooking":
    case "repeatCreate":
        // Refuse saving if any item is unavailable
        if ( count( $unavail ) + count( $overlap ) > 0 ) {
            http_response_code( 409 ); // Conflict
            die( "Kan inte spara eftersom inte alla resurser är tillgängliga." );
        }
        // Compile least access level for any items. Used to decide on usage dirty flag upon changes.
        $items = $booking->items();
        $leastAccess = FFBoka::ACCESS_CATADMIN;
        foreach ( $items as $item ) {
            $leastAccess = min( $minAccess, $item->category()->getAccess( $currentUser ) );
        }
        $booking->ref = $_POST[ 'ref' ];
        if ( $booking->commentCust !== $_POST[ 'commentCust' ] && $leastAccess <= FFBoka::ACCESS_PREBOOK ) $booking->dirty = true;
        $booking->commentCust = $_POST[ 'commentCust' ];
        if ( isset( $_POST[ 'commentIntern' ] ) ) $booking->commentIntern = $_POST[ 'commentIntern' ];
        $booking->extName = $_POST[ 'extName' ];
        $booking->extPhone = $_POST[ 'extPhone' ];
        $booking->extMail = $_POST[ 'extMail' ];
        $booking->okShowContactData = $_POST[ 'okShowContactData' ];
        // Check whether there are new, changed booking answers so we need to set the dirty flag
        if ( isset( $_POST[ 'questionId' ] ) && $leastAccess <= FFBoka::ACCESS_PREBOOK ) {
            $QA = $booking->answers();
            foreach ( $_POST[ 'questionId' ] as $id ) {
                $question = new Question( $id );
                if (
                    $QA[ $id ]->question !== $question->caption ||
                    $QA[ $id ]->answer !== implode( ", ", isset( $_POST[ "answer-$id" ] ) ? $_POST[ "answer-$id" ] : array() )
                ) $booking->dirty = true;
            }
        }
        // remove old answers previously saved with booking and save new answers to questions
        $booking->clearAnswers();
        if ( isset( $_POST[ 'questionId' ] ) ) {
            foreach ( $_POST[ "questionId" ] as $id ) {
                $question = new Question( $id );
                $booking->addAnswer( $question->caption, implode( ", ", isset( $_POST[ "answer-$id" ] ) ? $_POST[ "answer-$id" ] : array() ) );
            }
        }
        // Set status of pending items, except those which are openly unavailable
        foreach ( $booking->items() as $item ) {
            if ( $item->status == FFBoka::STATUS_PENDING && !in_array( $item->bookedItemId, $unavail ) ) {
                if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_BOOK ) $item->status = FFBoka::STATUS_CONFIRMED;
                elseif ( in_array( $item->bookedItemId, $conflicts ) ) $item->status = FFBoka::STATUS_CONFLICT;
                else $item->status = FFBoka::STATUS_PREBOOKED;
            }
        }

        $booking->sendNotifications( $cfg[ 'mail' ], $cfg[ 'url' ] );
        $result = $booking->sendConfirmation( $cfg[ 'mail' ], $cfg[ 'url' ] );
        if ( $result !== TRUE ) {
            http_response_code( 500 ); // Internal Server Error
            die( $result );
        }
        if ( $_POST[ 'action' ] == "confirmBooking" ) {
            unset( $_SESSION[ 'bookingId' ] );
            unset( $_SESSION[ 'token' ] );
            die( "Din bokning är nu klar. En bekräftelse har skickats till epostadressen " . htmlspecialchars( $booking->userMail ) . ".");
        }

        // Now, we are all set for normal bookings. Let's continue with creating a booking series
        // Get a new series ID and mark the current booking as part of this series
        $booking->repeatId = $repeatId = $FF->getNextRepeatId();
        // Create the copies
        for ( $i = 1; $i < $_POST[ 'repeat-count'  ]; $i++ ) {
            switch ( $_POST[ 'repeat-type' ] ) {
                case "day": $interval = new DateInterval( "P{$i}D" ); break;
                case "week": $interval = new DateInterval( "P{$i}W" ); break;
                case "month": $interval = new DateInterval( "P{$i}M" ); break;
            }
            $booking->copy( $interval );
        }
        // Send alerts
        $adminsToNotify = array();
        foreach ( $booking->items() as $item ) {
            $cat = $item->category();
            // Collect functional email addresses to notify:  array[userId][itemId1, itemId2...]
            $alerts = $cat->sendAlertTo;
            if ( $alerts ) {
                foreach ( explode( ",", $alerts ) as $alert ) $adminsToNotify[ trim( $alert ) ][] = $item->bookedItemId;
            }
            // collect admins to notify
            foreach ( $cat->admins( FFBoka::ACCESS_CONFIRM, TRUE ) as $adm ) {
                $admin = new User( $adm[ 'userId' ] );
                if ( $admin->getNotifyAdminOnNewBooking( $cat ) === "yes" ) $adminsToNotify[ $adm[ 'userId' ] ][] = $item->bookedItemId;
            }
        }
        foreach ( $adminsToNotify as $id=>$itemIds ) {
            if ( is_numeric( $id ) ) {
                if ( $id === $_SESSION[ 'authenticatedUser' ]) continue; // Don't send notification to current user
                $adm = new User( $id );
                $mail = $adm->mail;
                $name = $adm->name;
            } else {
                $mail = $id;
                $name = "";
            }
            if ( $mail ) { // can only send if admin has email address
                $FF->sendMail(
                    $mail, // to
                    "Återkommande bokning har skapats", // subject
                    "alert_series_created", // template
                    array(
                        "{{name}}" => $name,
                        "{{count}}" => $_POST[ 'repeat-count' ],
                        "{{user}}" => $booking->user()->name,
                        "{{bookingLink}}" => "{$cfg[ 'url' ]}book-sum.php?bookingId={$booking->id}",
                    ),
                    [], // attachments
                    $cfg[ 'mail' ],
                    true // delayed sending
                );
            }
        }
        die( "Din bokning har nu sparats och bokningsserien har skapats. En bekräftelse har skickats till epostadressen " . htmlspecialchars( $booking->userMail ) . ".");
                    
    case "deleteBooking":
        // Send confirmation to user
        $mailItems = "<tr><th>Resurs</th><th>Datum</th></tr>";
        $adminsToNotify = array();
        $maxStatus = FFBoka::STATUS_PENDING;
        foreach ( $booking->items() as $item ) {
            $maxStatus = max( $maxStatus, $item->status );
            $cat = $item->category();
            // Collect functional email addresses to notify
            $alerts = $cat->sendAlertTo;
            if ( $alerts ) {
                foreach ( explode( ",", $alerts ) as $alert ) {
                    $adminsToNotify[ trim( $alert ) ][] = $item->bookedItemId;
                }
            }
            // collect admins to notify about booking: array[userId][itemId1, itemId2...]
            foreach ( $cat->admins( FFBoka::ACCESS_CONFIRM, TRUE ) as $adm ) {
                $admin = new User( $adm[ 'userId' ] );
                if ( $admin->getNotifyAdminOnNewBooking( $cat ) == "yes" ) $adminsToNotify[ $adm[ 'userId' ] ][] = $item->bookedItemId;
            }
            // Table with booked items
            $mailItems .= "<tr>
                <td>" . htmlspecialchars( $item->caption ) . "</td>
                <td>" . strftime( "%a %F kl %k:00", $item->start ) . " till " . strftime( "%a %F kl %k:00", $item->end ) . "</td>
                </tr>";
        }
        if ( $booking->userId == $currentUser->id ) $statusText = "Din bokning <i>" . htmlspecialchars( $booking->ref ) . "</i> har nu raderats.";
        else $statusText = "Din bokning <i>" . htmlspecialchars( $booking->ref ) . "</i> har raderats av bokningsansvarig (" . htmlspecialchars( $currentUser->name . ", " . $currentUser->mail ) . "). Om detta är felaktigt, vänligen ta kontakt med " . htmlspecialchars( $currentUser->name ) . " omgående för att reda ut vad som har hänt.";
        if ( $maxStatus > FFBoka::STATUS_PENDING ) {
            // Send confirmation mail to booking owner
            if (!$FF->sendMail(
                $booking->userMail, // to
                "Bokning #{$booking->id} har raderats", // subject
                "booking_deleted", // template name
                array( // replace.
                    "{{name}}"    => htmlspecialchars( is_null( $booking->userId ) ? $booking->extName : $booking->user()->name ),
                    "{{items}}"   => $mailItems,
                    "{{status}}"  => $statusText,
                    "{{commentCust}}" => $booking->commentCust ? str_replace( "\n", "<br>", htmlspecialchars( $booking->commentCust ) ) : "(ingen kommentar har lämnats)",
                ),
                [], // attachments
                $cfg[ 'mail' ],
                true // delayed sending
            )) {
                http_response_code( 500 ); // Internal server error
                die( "Kunde inte skicka bekräftelsen till " . htmlspecialchars( $booking->userMail ) . "." );
            }
            // Send notifications to admins
            $contactData = is_null( $booking->userId ) ? "Detta är en gästbokning.<br>" : "";
            $contactData .= "Namn: " . htmlspecialchars( $booking->userName ) . "<br>Telefon: " . htmlspecialchars( $booking->userPhone ) . "<br>Mejl: " . htmlspecialchars( $booking->userMail );
            foreach ( $adminsToNotify as $id=>$itemIds ) {
                if ( is_numeric( $id ) ) {
                    if ( isset( $_SESSION[ 'authenticatedUser' ] ) && $id == $_SESSION[ 'authenticatedUser' ] ) continue; // Don't send notification to current user
                    $adm = new User( $id );
                    $mail = $adm->mail;
                    $name = $adm->name;
                } else {
                    $mail = $id;
                    $name = "";
                }
                if ( $mail ) { // can only send if admin has email address
                    $FF->sendMail(
                        $mail, // to
                        "FF Bokning #{$booking->id} raderad", // subject
                        "booking_deleted", // template
                        array( // replace
                            "{{name}}"    => $name,
                            "{{items}}"   => $mailItems,
                            "{{status}}"  => "Bokningen nedan har raderats.",
                            "{{commentCust}}" => str_replace( "\n", "<br>", htmlspecialchars( $booking->commentCust ) ) . "<br><br>$contactData",
                        ),
                        [], // attachments
                        $cfg[ 'mail' ]
                    );
                }
            }
        }
        // If the booking belongs to a series and only one occurrence would be left, remove the series.
        if ( !is_null( $booking->repeatId ) ) {
            $series = $booking->getBookingSeries();
            if ( count( $series ) == 1 ) $series[ 0 ]->repeatId = NULL;
        }
        $booking->delete();
        unset( $_SESSION[ 'bookingId' ] );
        unset( $_SESSION[ 'token' ] );
        die();
                
    case "setItemPrice":
        if ( !isset( $_POST[ 'price' ] ) ) { http_response_code( 400 ); die( "price missing" ); } // Bad request
        if ( is_numeric( $_POST[ 'price' ] ) ) $item->price = $_POST[ 'price' ];
        elseif ( $_POST[ 'price' ] === "" ) $item->price = NULL;
        else {
            http_response_code( 400 );
            die( "Ogiltig inmatning." );
        }
        die();
        
    case "setPaid":
        if ( !isset( $_POST[ 'paid' ] ) ) { http_response_code( 400 ); die( "paid missing" ); } // Bad request
        if ( is_numeric( $_POST[ 'paid' ] ) ) $booking->paid = $_REQUEST[ 'paid' ];
        elseif ( $_POST[ 'paid' ] === "" ) $booking->paid = NULL;
        else {
            http_response_code( 400 );
            die( "Ogiltig inmatning." );
        }
        die();
        
    case "removeItem":
        if ( $item->removeFromBooking() ) die();
        http_response_code( 500 ); // Internal Server Error
        die( "Oväntat fel: Kan inte ta bort resursen. Kontakta systemadministratören." );
                
    case "repeatPreview":
        // check availability for every item and each date
        $unavail = array_fill( 0, $_REQUEST[ 'count' ], array());
        switch ( $_GET[ 'type' ] ) {
            case "day": $interval = new DateInterval( "P1D" ); break;
            case "week": $interval = new DateInterval( "P1W" ); break;
            case "month": $interval = new DateInterval( "P1M" ); break;
        }
        foreach ( $booking->items() as $item ) {
            // Get start and end time for the original booked item
            $start = new DateTime( "@{$item->start}" );
            $end = new DateTime( "@{$item->end}" );
            for ( $i = 0; $i < $_GET[ 'count' ]; $i++ ) {
                if ( !$item->isAvailable( $start->getTimestamp(), $end->getTimestamp() ) ) $unavail[ $i ][] = htmlspecialchars( $item->caption );
                // From the 1st copy and onwards, use a generic item in order to also get collisions with the original one.
                $item = new Item( $item->id );
                $start->add( $interval );
                $end->add( $interval );
            }
        }
        $start = new DateTime( "@" . $booking->start() );
        $html = [];
        for ( $i=0; $i < $_REQUEST[ 'count' ]; $i++ ) {
            if ( count( $unavail[ $i ] ) ) $html[] = "<li styles='color:var(--FF-orange);' class='repeat-unavail'>" . $start->format( "Y-m-d" ) . ": " . implode( ", ", $unavail[ $i ] ) . " ej tillgänglig</li>";
            else $html[] = "<li>" . $start->format( "Y-m-d" ) . ": alla resurser tillgängliga</li>";
            $start->add( $interval );
        }
        die( "<ul>" . implode( "", $html ) . "</ul>" );
        
    case "unlinkBooking":
        // If there will only be one occurrence left in the series, remove even that one.
        $series = $booking->getBookingSeries();
        if ( count( $series ) == 1 ) $series[ 0 ]->repeatId = NULL;
        // Remove this occurrence from the series
        $booking->repeatId = NULL;
        die( showBookingSeries( $booking ) );
        
    case "unlinkSeries":
        foreach ( $booking->getBookingSeries( TRUE ) as $b ) $b->repeatId = NULL;
        die( showBookingSeries( $booking ) );
        
    case "deleteSeries":
        $first = NULL;
        $gotoFirst = false;
        foreach ( $booking->getBookingSeries( TRUE ) as $b ) {
            if ( is_null( $first ) ) { // keep the first instance and switch to it
                $first = $b;
                continue;
            }
            if ( $b->start() > time() ) { // Booking in the future, delete it
                if ( $b->id == $booking->id ) $gotoFirst = true;
                $b->delete();
            }
        }
        // If only one occurence is left, remove the series ID
        if ( count( $first->getBookingSeries( TRUE ) ) == 1 ) $first->repeatId = NULL;
        if ( $gotoFirst ) die( "{$cfg[ 'url' ]}book-sum.php?bookingId={$first->id}" );
        else die();
        
    case "getSeries":
        die( showBookingSeries( $booking ) );

    case "handleBookedItem":
        if ( $_POST[ 'status' ] == FFBoka::STATUS_CONFIRMED || $_POST[ 'status' ] == FFBoka::STATUS_REJECTED ) $item->status = $_POST[ 'status' ];
        else { http_response_code( 400 ); die( "Invalid status." ); } // Bad Request
        die();

    default:
}


/**
 * Show a list of bookings belonging to this series. If this is not (yet) a series, show form elements to create a series
 * @param \FFBoka\Booking $booking
 * @return HTML formatted list
 */
function showBookingSeries( \FFBoka\Booking $booking ) {
    if( is_null( $booking->repeatId ) ) {
        return <<<EOF
        <p style='margin:0;'>Skapa serie med <span style='display:inline-block; width:4em;'><input type='number' name='repeat-count' id='repeat-count' value='2' min='2' max='40'></span> tillfällen</p>
        <fieldset data-role='controlgroup' data-type='horizontal' data-mini='true'>
            <legend>Upprepning varje</legend>
            <label><input type='radio' name='repeat-type' value='day' onClick="repeatType=this.value; repeatPreview($('#repeat-count').val(), repeatType);">dag</label>
            <label><input type='radio' name='repeat-type' value='week' onClick="repeatType=this.value; repeatPreview($('#repeat-count').val(), repeatType);">vecka</label>
            <label><input type='radio' name='repeat-type' onClick="repeatType=this.value; repeatPreview($('#repeat-count').val(), repeatType);" value='month'>månad</label>
        </fieldset>
        <div id='repeat-preview'><i>När du har valt antal och typ så kommer du här se ifall resurserna är tillgängliga vid respektive tillfälle.</i></div>
        <p><i>Icke-tillgängliga resurser kommer inte att bokas.</i></p>
        <button disabled='' class='ui-btn' id='repeat-create' onClick='repeatCreate()'>Skapa serien</button>
        EOF;
	} else {
	    $ret = "<p>Den här bokningen är del av en bokningsserie.</p>";
        $series = $booking->getBookingSeries();
        if ( count( $series ) == 0 ) {
            $ret .= "<p>Det finns inga fler tillfällen än det här.</p>";
        } else {
            $ret .= "<p>Nedan finns länkar till övriga tillfällen i serien:</p><ul data-role='listview' data-inset='true'>";
            foreach ( $series as $b ) {
                $ret .= "<li><a href='book-sum.php?bookingId={$b->id}'>" . ( is_null( $start = $b->start() ) ? "(bokning utan resurser)" : strftime( "%F", $start ) ) . "</a></li>";
            }
            $ret .= "</ul>";
        }
        $ret .= "<div data-role='controlgroup'>
            <a href='#' onClick='unlinkBooking()' class='ui-btn ui-mini ui-icon-action ui-btn-icon-left'>Lyft ut det här tillfället</a>
            <a href='#' onClick='unlinkSeries()' class='ui-btn ui-mini ui-icon-bars ui-btn-icon-left'>Lös upp serien</a>
            <a href='#' onClick='deleteSeries()' class='ui-btn ui-mini ui-btn-c ui-icon-delete ui-btn-icon-left'>Radera serien</a>
            </div>";
	}
    return $ret;
}
