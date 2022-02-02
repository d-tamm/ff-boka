<?php
case "ajaxFreebusyItem":
        // Get freebusy bars for current booked item
        $item = new Item( $_SESSION[ 'bookedItemId' ], TRUE );
        header( "Content-Type: application/json" );
        if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_PREBOOK ) {
            $freebusyBar = $item->freebusyBar( [ 'start' => $_REQUEST[ 'start' ] ] );
        }
        die( json_encode( [
            "freebusyBar" => $freebusyBar,
        ] ) );

        case "confirmBooking":
            case "repeatCreate":
                saveBookingFields( $booking, $conflicts, $unavail, $currentUser );
                if ( count( $unavail ) > 0 || count( $overlap ) > 0 ) {
                    if ( $_REQUEST[ 'action' ] == "repeatCreate" ) $message .= "Serien kan inte skapas eftersom inte alla resurser i originalet är tillgängliga.";
                    break;
                }
                if ( $booking->extMail && !filter_var( $booking->extMail, FILTER_VALIDATE_EMAIL ) ) {
                    $message = "Epostadressen har ett ogiltigt format.";
                    break;
                }
                $booking->sendNotifications( $cfg[ 'mail' ], $cfg[ 'url' ] );
                $result = $booking->sendConfirmation( $cfg[ 'mail' ], $cfg[ 'url' ] );
                if ( $result !== TRUE ) {
                    $message .= $result;
                } elseif ( $_REQUEST[ 'action' ] == "confirmBooking" ) {
                    unset( $_SESSION[ 'bookingId' ] );
                    header( "Location: index.php?action=bookingConfirmed&mail=" . urlencode( is_null( $booking->userId ) ? $_REQUEST[ 'extMail' ] : $booking->user()->mail ) );
                    die();
                } else {
                    // Create a booking series
                    // Get a new series ID and mark the current booking as part of this series
                    $booking->repeatId = $repeatId = $FF->getNextRepeatId();
                    // Create the copies
                    for ( $i=1; $i < $_REQUEST[ 'repeat-count'  ]; $i++ ) {
                        switch ( $_REQUEST[ 'repeat-type' ] ) {
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
                            if ( $admin->getNotifyAdminOnNewBooking( $cat ) == "yes" ) $adminsToNotify[ $adm[ 'userId' ] ][] = $item->bookedItemId;
                        }
                    }
                    foreach ( $adminsToNotify as $id=>$itemIds ) {
                        if ( is_numeric( $id ) ) {
                            if ( isset( $_SESSION[ 'authenticatedUser' ] ) && $id == $_SESSION[ 'authenticatedUser' ]) continue; // Don't send notification to current user
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
                                    "{{count}}" => $_REQUEST[ 'repeat-count' ],
                                    "{{user}}" => $booking->user()->name,
                                    "{{bookingLink}}" => "{$cfg[ 'url' ]}book-sum.php?bookingId={$booking->id}",
                                ),
                                [], // attachments
                                $cfg[ 'mail' ],
                                true // delayed sending
                            );
                        }
                    }
                    $message .= "Din bokning har nu sparats och bokningsserien har skapats. En bekräftelse har skickats till epostadressen {$booking->user()->mail}.";
                }
                break;
                    
            case "ajaxDeleteBooking":
                header( "Content-Type: application/json" );
                // Check permissions: Only the original user and section admin can delete whole bookings
                if ( $section->getAccess( $currentUser ) < FFBoka::ACCESS_SECTIONADMIN && ( !isset( $_SESSION[ 'authenticatedUser' ] ) || $booking->userId !== $_SESSION[ 'authenticatedUser' ]) && $_SESSION[ 'token' ] != $booking->token ) {
                    logger( __METHOD__ . " User {$currentUser->id} tried to delete booking {$booking->id} without appropriate permissions.", E_WARNING );
                    die( json_encode( [ "error"=>"Du har inte behörighet att ta bort bokningen." ] ) );
                }
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
                            $alert = trim( $alert );
                            $adminsToNotify[ $alert ][] = $item->bookedItemId;
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
                    try {
                        $FF->sendMail(
                            is_null( $booking->userId ) ? $booking->extMail : $booking->user()->mail, // to
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
                        );
                    } catch( Exception $e ) {
                        $message = "Kunde inte skicka bekräftelsen till dig:" . $e;
                    }
                    // Send notifications to admins
                    if ( is_null( $booking->userId ) ) {
                        $contactData = "Detta är en gästbokning.<br>Namn: " . htmlspecialchars( $booking->extName ) . "<br>Telefon: " . htmlspecialchars( $booking->extPhone ) . "<br>Mejl: " . htmlspecialchars( $booking->extMail );
                    } else {
                        $contactData = "Namn: " . htmlspecialchars( $booking->user()->name ) . "<br>Telefon: " . htmlspecialchars( $booking->user()->phone ) . "<br>Mejl: " . htmlspecialchars( $booking->user()->mail );
                    }
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
                die( json_encode( [ "status" => "OK" ] ) );
                
            case "ajaxSetItemPrice":
                header( "Content-Type: application/json" );
                // Check permissions: Only admins for this item may set price
                $item = new Item( $_REQUEST[ 'bookedItemId' ], TRUE );
                if ( $item->category()->getAccess( $currentUser ) >= FFBoka::ACCESS_CONFIRM ) {
                    if ( is_numeric( $_REQUEST[ 'price' ] ) ) $item->price = $_REQUEST[ 'price' ];
                    elseif ( $_REQUEST[ 'price' ] === "" ) $item->price = NULL;
                    else die( json_encode( [ "status" => "error", "error" => "Ogiltig inmatning." ] ) );
                    die( json_encode( [ "status" => "OK" ] ) );
                } else {
                    die( json_encode( [ "status" => "error", "error" => "Du har inte behörighet att sätta pris." ] ) );
                }
                
            case "ajaxSetPaid":
                header( "Content-Type: application/json" );
                // Check permissions: User needs to have some admin assignment
                if ( $section->showFor( $currentUser, FFBoka::ACCESS_CONFIRM ) ) {
                    if ( is_numeric( $_REQUEST[ 'paid' ] ) ) $booking->paid = $_REQUEST[ 'paid' ];
                    elseif ( $_REQUEST[ 'paid' ] === "" ) $booking->paid = NULL;
                    else die( json_encode( [ "status" => "error", "error" => "Ogiltig inmatning." ] ) );
                    die( json_encode( [ "status" => "OK" ] ) );
                } else {
                    die( json_encode( [ "status" => "error", "error" => "Du har inte behörighet att sätta värdet."] ) );
                }
                
            case "ajaxRemoveItem":
                header( "Content-Type: application/json" );
                // Check permissions
                $item = new Item( $_REQUEST[ 'bookedItemId' ], TRUE );
                if ( $item->category()->getAccess( $currentUser ) < FFBoka::ACCESS_CONFIRM && ( $booking->userId && $booking->userId !== $_SESSION[ 'authenticatedUser' ] ) && $_SESSION[ 'token' ] != $booking->token ) {
                    die( json_encode( [ "error" => "Du har inte behörighet att ta bort resursen." ] ) );
                }
                if ( $item->removeFromBooking() ) die( json_encode( [ "status" => "OK" ] ) );
                else die( json_encode( [ "error" => "Oväntat fel: Kan inte ta bort resursen. Kontakta systemadministratören." ] ) );
                
            case "ajaxRepeatPreview":
                header( "Content-Type: application/json" );
                // check availability for every item and each date
                $unavail = array_fill( 0, $_REQUEST[ 'count' ], array());
                switch ( $_REQUEST[ 'type' ] ) {
                    case "day": $interval = new DateInterval( "P1D" ); break;
                    case "week": $interval = new DateInterval( "P1W" ); break;
                    case "month": $interval = new DateInterval( "P1M" ); break;
                }
                foreach ( $booking->items() as $item ) {
                    // Get start and end time for the original booked item
                    $start = new DateTime( "@{$item->start}" );
                    $end = new DateTime( "@{$item->end}" );
                    for ( $i=0; $i < $_REQUEST[ 'count' ]; $i++ ) {
                        if ( !$item->isAvailable( $start->getTimestamp(), $end->getTimestamp() ) ) $unavail[ $i ][] = htmlspecialchars( $item->caption );
                        // From the 1st copy an onwards, use a generic item in order to also get collisions with the original one.
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
                die( json_encode( [ "html" => "<ul>" . implode( "", $html ) . "</ul>" ] ) );
                
            case "ajaxUnlinkBooking":
                header( "Content-Type: application/json" );
                // If there will only be one occurrence left in the series, remove even that one.
                $series = $booking->getBookingSeries();
                if ( count( $series ) == 1 ) $series[ 0 ]->repeatId = NULL;
                // Remove this occurrence from the series
                $booking->repeatId = NULL;
                die( json_encode( [ "html" => showBookingSeries( $booking ) ] ) );
                
            case "ajaxUnlinkSeries":
                header( "Content-Type: application/json" );
                foreach ( $booking->getBookingSeries( TRUE ) as $b ) $b->repeatId = NULL;
                die( json_encode( [ "html" => showBookingSeries( $booking ) ] ) );
                
            case "ajaxDeleteSeries":
                header( "Content-Type: application/json" );
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
                if ( count( $first->getBookingSeries( TRUE ) ) == 1 ) $first->repeatId=NULL;
                if ( $gotoFirst ) die( json_encode( [ "gotoBookingId" => $first->id ] ) );
                else die( json_encode( [ "html" => showBookingSeries($booking) ] ) );
                
            case "ajaxConfirmBookedItem":
            case "ajaxRejectBookedItem":
                header( "Content-Type: application/json" );
                $item = new Item( $_REQUEST[ 'bookedItemId' ], TRUE );
                if ( $item->category()->getAccess( $currentUser ) < FFBoka::ACCESS_CONFIRM && $booking->userId !== $_SESSION[ 'authenticatedUser' ] && $booking->token != $_SESSION[ 'token' ] ) {
                    die( json_encode( [ "error" => "Du har inte behörighet att bekräfta resursen." ] ) );
                }
                if ( $_REQUEST[ 'action' ] == "ajaxConfirmBookedItem" ) $item->status = FFBoka::STATUS_CONFIRMED;
                else $item->status = FFBoka::STATUS_REJECTED;
                // Check if this was the last item in the booking to be managed (confirmed/rejected)
                $allManaged = TRUE;
                foreach ( $item->booking()->items() as $it ) {
                    if ( $it->status != FFBoka::STATUS_CONFIRMED && $it->status != FFBoka::STATUS_REJECTED ) {
                        $allManaged = FALSE;
                        break;
                    }
                }
                die( json_encode( [
                    'status'=>'OK',
                    'allManaged'=>$allManaged
                ] ) );
        