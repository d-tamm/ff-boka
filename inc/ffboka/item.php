<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class item
 * Bookable items in the booking system.
 */
class Item extends FFBoka {
    private $_id;
    private $_catId;
    
    /** The following properties are only applicable if used in booking context.
     * ID of the item's booking (not to be confused with the item ID) */
    private $_bookedItemId;
    /** The ID of the booking the item belongs to */
    private $_bookingId;
    
    /**
     * Initialize item with ID and get some static properties.
     * @param int $id ID of requested item. If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     * @param bool $bookedItem If TRUE, item will belong to a booking and $id is interpreted as bookedItemId instead
     */
    public function __construct( $id, $bookedItem = FALSE ) {
        if ( $id ) { // Try to return an existing item from database
            if ( $bookedItem ) $stmt = self::$db->prepare( "SELECT bookedItemId, bookingId, itemId, catId FROM booked_items INNER JOIN items USING (itemId) WHERE bookedItemId=?" );
            else $stmt = self::$db->prepare( "SELECT itemId, catId FROM items WHERE itemId=?" );
            $stmt->execute( [ $id ] );
            if ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                $this->_id = $row->itemId;
                $this->_catId = $row->catId;
                if ( $bookedItem ) {
                    $this->_bookedItemId = $row->bookedItemId;
                    $this->_bookingId = $row->bookingId;
                }
            } else {
                $this->_id = 0;
            }
        } else { // Return an empty object without link to database
            $this->_id = 0;
        }
    }    
    
    /**
     * Setter function for item properties
     * @param string $name
     * @param string|int $value
     * @throws \Exception
     * @return string|boolean
     */
    public function __set( $name, $value ) {
        switch ( $name ) {
            case "catId":
                // May only be set on dummy Item
                if ( $this->id ) throw new \Exception( "Cannot change category for existing item." );
                $this->_catId = $value;
                return $value;
            case "caption":
            case "description":
            case "postbookMsg":
            case "active":
            case "note":
            case "imageId":
                if ( !$this->id ) throw new \Exception( "Cannot set property $name on dummy item." );
                $stmt = self::$db->prepare( "UPDATE items SET $name=? WHERE itemId={$this->id}" );
                if ( $stmt->execute( [ $value ] ) ) return $value;
                logger( __METHOD__ . " Failed to set Item property $name to $value. " . $stmt->errorInfo()[ 2 ], E_ERROR );
                break;
                
            case "price":
            case "status":
            case "start": // int|string Start time as Unix timestamp or string
            case "end": // int|string Start time as Unix timestamp or string
            case "remindersSent": // string[]
                if ( $this->bookedItemId ) {
                    if ( $name == "remindersSent" ) $value = json_encode( $value );
                    if ( ( $name == "start" || $name == "end" ) && is_numeric( $value ) ) $stmt = self::$db->prepare( "UPDATE booked_items SET $name=FROM_UNIXTIME(?) WHERE bookedItemId={$this->bookedItemId}" );
                    else $stmt = self::$db->prepare( "UPDATE booked_items SET $name=? WHERE bookedItemId={$this->bookedItemId}" );
                    if ( $stmt->execute( [ $value ] ) ) return $this->$name;
                    logger( __METHOD__ . " Failed to set Item property $name to $value. " . $stmt->errorInfo()[ 2 ], E_ERROR );
                } else throw new \Exception( "Cannot set $name property on item without bookedItemId." );
                
            default:
                logger( __METHOD__ . " Use of undefined Item property $name", E_WARNING );
                throw new \Exception( "Use of undefined Item property $name" );
        }
        return false;
    }

    /**
     * Set the representative image of the item
     * @param Image $img
     * @throws \Exception if the image does not belong to the item
     */
    public function setFeaturedImage( Image $img ) {
        if ( $img->itemId == $this->id ) {
            logger( __METHOD__ . " Trying to set an image to be the featured image of an item it does not belong to.", E_WARNING );
            throw new \Exception( "Cannot set an image to featured image which does not belong to the item." );
        }
        $this->imageId = $img->id;
    }
    
    /**
     * Getter function for item properties
     * @param string $name Name of the property
     * @throws \Exception
     * @return string|int Value of the property.
     */
    public function __get( $name ) {
        switch ( $name ) {
            case "id":
                return $this->_id;
            case "catId":
                return $this->_catId;
            case "bookedItemId":
                return $this->_bookedItemId;
            case "bookingId":
                return $this->_bookingId;
                
            case "caption":
            case "description":
            case "postbookMsg":
            case "active":
            case "note":
            case "imageId":
                if ( !$this->id ) return "";
                $stmt = self::$db->query( "SELECT $name FROM items WHERE itemId={$this->id}" );
                $row = $stmt->fetch( PDO::FETCH_OBJ );
                return $row->$name;
                
            case "price":
            case "status":
            case "start": // booking start time (as unix timestamp) of booked item
            case "end": // booking end time (as unix timestamp) of booked item
            case "remindersSent": // string[]
                if ( $this->bookedItemId ) {
                    if ( $name == "start" || $name == "end" ) $stmt = self::$db->query("SELECT UNIX_TIMESTAMP($name) $name FROM booked_items WHERE bookedItemId={$this->bookedItemId}" );
                    else $stmt = self::$db->query( "SELECT $name FROM booked_items WHERE bookedItemId={$this->bookedItemId}" );
                    $row = $stmt->fetch( PDO::FETCH_OBJ );
                    if ( $name == "remindersSent" ) return json_decode( $row->$name, true );
                    return $row->$name;
                } else {
                    logger( __METHOD__ . " Trying to get item property $name for item which is not a booked item.", E_WARNING );
                    throw new \Exception( "Cannot get $name property on item without bookedItemId." );
                }
            default:
                logger( __METHOD__ . " Use of undefined Item property $name", E_WARNING );
                throw new \Exception( "Use of undefined Item property $name" );
        }
    }

    /**
     * Get category to which the item belongs
     * @return \FFBoka\Category
     */
    public function category() {
        return new Category( $this->catId );
    }
    

    /**
     * Get the granted access level for given user, taking into account inherited access.
     * 
     * @param \FFBoka\User $user
     * @param bool $tieInSectionAdmin Whether to also include admin role set on section level
     * @return integer Bitfield of granted access rights. For an empty (fake) category, returns ACCESS_CATADMIN.
     */
    public function getAccess( User $user, bool $tieInSectionAdmin = TRUE ) : int {
        return $this->category()->getAccess( $user, $tieInSectionAdmin );
    }


    /**
     * Is this item placed under the specified category or its child categories?
     * @param Category $cat
     * @return bool True if the item is in this category or a child category of this category
     */
    public function isBelowCategory( Category $cat ) {
        $childCat = $this->category();
        do {
            if ( $childCat->id === $cat->id ) return TRUE;
            $childCat = $childCat->parent();
        } while ( !is_null( $childCat ) );
        return FALSE;
    }
    
    /**
     * Move the item to another category
     * @param Category $cat Category to move the item to.
     * @return bool True on success, false on failure
     */
    public function moveToCat( Category $cat ) {
        $stmt = self::$db->prepare( "UPDATE items SET catId=? WHERE itemId={$this->id}" );
        if ( $stmt->execute( [ $cat->id ] ) ) return true;
        logger( __METHOD__ . " Failed to move item {$this->id} to category {$cat->id}. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    }

    /**
     * Get the booking the item belongs to (only applicable for bookedItems)
     * @return \FFBoka\Booking|false
     */
    public function booking() {
        if ( $this->bookingId ) return new Booking( $this->bookingId );
        else return FALSE;
    }

    /**
     * Get all bookings for the next time
     * @param int $days Number of days in future to return bookings for. If set to 0, all (even past) bookings are returned
     * @return Item[] Array of bookedItems.
     */
    public function upcomingBookings( int $days = 365 ) {
        // Get freebusy information.
        if ( $days ) $timeConstraint = "AND (
            (start>NOW() AND start<DATE_ADD(NOW(), INTERVAL :days DAY)) OR (end>NOW() AND end<DATE_ADD(NOW(), INTERVAL :days DAY)) OR (start<NOW() AND end>DATE_ADD(NOW(), INTERVAL :days DAY))
            )";
        else $timeConstraint = "";
        $stmt = self::$db->prepare( "
            SELECT bookedItemId
            FROM booked_items
            WHERE
                itemId={$this->id}
                AND status>0
                $timeConstraint
            ORDER BY start" );
        if ( $days ) $stmt->execute( array( ":days" => $days ) );
        else $stmt->execute( [] );
        $ret = [];
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $ret[] = new Item( $row->bookedItemId, TRUE );
        }
        return $ret;
    }
    
    /**
     * Remove item from database
     * @return boolean True on success, false on failure
     */
    public function delete() {
        // Full size image will be removed from file system by cron
        if (self::$db->exec( "DELETE FROM items WHERE itemId={$this->id}" ) ) {
            return TRUE;
        } else {
            logger( __METHOD__ . " Failed to delete item. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            return false;
        }
    }
    
    /**
     * Remove bookedItem from its booking
     * @return boolean True on success, false on failure
     */
    public function removeFromBooking() {
        if ( self::$db->exec( "DELETE FROM booked_items WHERE bookedItemId={$this->bookedItemId}" ) ) {
            unset( $this->_bookedItemId );
            unset( $this->_bookingId );
            return TRUE;
        } else {
            logger( __METHOD__ ." Failed to remove item from booking. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            return false;
        }
    }
    
    /**
     * Make a copy of the item. The copy will be inactive.
     * If the caption ends on "(n)", the copy will end on (n+1).
     * Otherwise, "(kopia)" will be appended to the caption of the copy.
     * @return \FFBoka\Item The newly created item
     */
    public function copy() {
        $newItem = $this->category()->addItem();
        // If old caption ends on "(nn)", increase nn. Otherwise, add (kopia) to the caption.
        if ( preg_match( '/(.*\(?)([0-9]+)(\)?)$/', $this->caption, $matches ) ) $newItem->caption = $matches[ 1 ] . ( $matches[ 2 ] + 1 ) . $matches[ 3 ];
        else $newItem->caption = $this->caption . " (kopia)";
        $newItem->description = $this->description;
        $newItem->postbookMsg = $this->postbookMsg;
        $newItem->note = $this->note;
        // copy the associated item images
        foreach ( $this->images() as $image ) {
            if ( !self::$db->exec( "INSERT INTO item_images (itemId, thumb, caption) SELECT {$newItem->id}, thumb, caption FROM item_images WHERE imageId={$image->id}" ) ) logger( __METHOD__ . " Failed to copy item image. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            $newImageId = self::$db->lastInsertId();
            // Copy full size image file
            copy( __DIR__ . "/../../img/item/{$image->id}", __DIR__ . "/../../img/item/$newImageId" );
            if ( $image->id == $this->imageId ) { // set featured image
                if ( !self::$db->exec( "UPDATE items SET imageId=$newImageId WHERE itemId={$newItem->id}" ) ) logger( __METHOD__ . " Failed to set featured image in copied item. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            }
        }
        return $newItem;
    }
    
    /**
     * Create a new image
     * @throws \Exception
     * @return \FFBoka\Image
     */
    public function addImage() {
        if ( self::$db->exec( "INSERT INTO item_images SET itemId={$this->id}" ) ) {
            return new Image( self::$db->lastInsertId() );
        } else {
            logger( __METHOD__ . " Failed to create item image. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            throw new \Exception( "Failed to create item image." );
        }
    }
    
    /**
     * Get all images of the item
     * @return \FFBoka\Image[]
     */
    public function images() {
        $images = [];
        $stmt = self::$db->query( "SELECT imageId FROM item_images WHERE itemId={$this->id}" );
        while ( $image = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $images[] = new Image( $image->imageId );
        }
        return $images;
    }
    
    /**
     * Get the featured image of the item 
     * @return \FFBoka\Image
     */
    public function getFeaturedImage() {
        return new Image( $this->imageId );
    }
    
    /**
     * Get a linear representation of free-busy information
     * @param mixed $params Associative array of parameters. Supported elements are:<br>
     * <b>start</b> (int) First day of week to show, unix timestamp<br>
     * <b>scale</b> (bool) Whether to include the weekday scale. Default: False.<br>
     * <b>days</b> (int) Number of days to show. Default: 7 days (1 week)<br>
     * <b>minStatus</b> (int) Don't show bookings with lower status than this. Defaults to STATUS_PREBOOKED.<br>
     * <b>includeTokens</b> Include data-token properties, default:false<br>
     * <b>adminView</b> If true, shows the price in title of blocked bars and adds classes for unconfirmed and conflict. 
     * @return string HTML code showing blocks of free and busy times
     */
    function freebusyBar( $params = [] ) {
        $start = 0;
        $scale = FALSE;
        $days = 7;
        $minStatus = FFBoka::STATUS_PREBOOKED;
        $includeTokens = FALSE;
        $adminView = FALSE;
        extract( $params, EXTR_IF_EXISTS );
        // Store start date as user defined variable because it is used multiple times
        $secs = $days * 24 * 60 * 60;
        $stmt = self::$db->prepare( "SET @start = :start" );
        $stmt->execute( [ ":start" => $start ] );
        // Get freebusy information.
        $stmt = self::$db->query( "
            SELECT bookingId, bookedItemId, status, ref, price, paid, token, bufferAfterBooking, DATE_SUB(start, INTERVAL bufferAfterBooking HOUR) start, UNIX_TIMESTAMP(start) unixStart, DATE_ADD(end, INTERVAL bufferAfterBooking HOUR) end, UNIX_TIMESTAMP(end) unixEnd, users.name username, extName 
            FROM booked_items 
            INNER JOIN bookings USING (bookingId) 
            INNER JOIN items USING (itemId) 
            INNER JOIN categories USING (catId)
            LEFT JOIN users using (userId)
            WHERE 
                itemId={$this->id} " . 
                ( isset( $this->bookedItemId ) ? "AND bookedItemId != {$this->bookedItemId} " : "" ) . " 
            AND 
                booked_items.status>=$minStatus 
            AND (
                    (
                        UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start+$secs
                    ) OR (
                        UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start+$secs
                    ) OR (
                        UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@start+$secs
                    )
                )" );

        $ret = "";
        if ( $scale ) $ret .= self::freebusyWeekends( $start, $days );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            if ( $row->bufferAfterBooking ) {
                $ret .= "<div class='freebusy-blocked' style='left:" . number_format( ( $row->unixStart - $start - $row->bufferAfterBooking * 3600 ) / $secs * 100, 2, ".", "" ) . "%; width:" . number_format( ( $row->unixEnd - $row->unixStart + 2 * $row->bufferAfterBooking * 3600 ) / $secs * 100, 2, ".", "" ) . "%' title='ej bokbar'></div>";
            }
            $class = "freebusy-busy";
            if ( $adminView ) {
                if ( $row->status == FFBoka::STATUS_PREBOOKED ) $class .= " unconfirmed";
                if ( $row->status == FFBoka::STATUS_CONFLICT ) $class .= " conflict";
                if ( $row->price ) {
                    $class .= " has-price";
                    $stmtPrice = self::$db->query( "SELECT SUM(price) price FROM booked_items WHERE bookingId='{$row->bookingId}' AND NOT price IS NULL" );
                    $rowPrice = $stmtPrice->fetch( PDO::FETCH_OBJ );
                    if ( $rowPrice->price <= $row->paid ) $class .= " paid";
                }
            }
            $title = date( "Y-m-d \k\l H:00", $row->unixStart ) . " till " . date( "Y-m-d \k\l H:00", $row->unixEnd );
            if ( $adminView ) $title .= "\n" . htmlspecialchars( $row->extName ? $row->extName : $row->username ) . "\n" . htmlspecialchars( $row->ref ) . ( is_null( $row->price ) ? "\nInget pris satt" : "\nPris: {$row->price} kr" );
            $ret .= "<div class='$class' data-booking-id='{$row->bookingId}' data-booked-item-id='{$row->bookedItemId}' " . ( $includeTokens ? "data-token='{$row->token}' " : "" ) . "style='left:" . number_format( ( $row->unixStart - $start ) / $secs * 100, 2, ".", "" ) . "%; width:" . number_format( ( $row->unixEnd - $row->unixStart ) / $secs * 100, 2, ".", "" ) . "%;' title='$title'></div>";
        }
        if ( $scale ) $ret .= self::freebusyScale( false, $days );
        return $ret;
    }

    /**
     * Get vertical lines for freebusyBar
     * @param bool $weekdays Also display weekday abbreviations
     * @param int $days Number of days to show
     * @return string HTML code
     */
    public static function freebusyScale( bool $weekdays = FALSE, int $days = 7 ) {
        $dayNames = $weekdays ? array( "<span>mån</span>", "<span>tis</span>", "<span>ons</span>", "<span>tor</span>", "<span>fre</span>", "<span>lör</span>", "<span>sön</span>" ) : array_fill( 0, $days, "" );
        $noborder = "border-left:none;";
        $ret = "";
        for ( $day = 0; $day < $days; $day++ ) {
            $ret .= "<div class='freebusy-tic' data-day='$day' style='$noborder " . ( $weekdays ? "width:" . number_format( 100 / $days, 2 ) . "%; " : "" ) . "left:" . number_format( 100 / $days * $day, 2 ) . "%;'>{$dayNames[ $day ]}</div>";
            $noborder = "";
        }
        return $ret;
    }
    
    /**
     * Get weekend shades for freebusy bar
     * @param int $start Unix timestamp of start time
     * @param int $days Number of days in freebusy bar
     * @return string HTML code
     */
    public static function freebusyWeekends( int $start, int $days = 7 ) {
        $date = new \DateTime( "@$start" );
        $date->setTimezone( new \DateTimeZone( self::$timezone ) );
        $ret = "";
        for ( $day=0; $day < $days; $day++ ) {
            if ( $date->format( 'N' ) > 5 ) $ret .= "<div data-day='" . ( $day + 1 ) . "' class='freebusy-weekend' style='left:" . number_format( 100 / $days * $day, 2 ) . "%; width:" . number_format( 100 / $days, 2 ) . "%;'></div>";
            else $ret .= "<div data-day='" . ( $day + 1 ) . "' class='freebusy-week' style='left:" . number_format( 100 / $days * $day, 2 ) . "%; width:" . number_format( 100 / $days, 2 ) . "%;'></div>";
            $date->add( new \DateInterval( "P1D" ) );
        }
        return $ret;
    }
    
    /**
     * Get graphical representation showing that freebusy information is not available to user.
     * @return string HTML block showing unavailable information.
     */
    public static function freebusyUnknown() {
        return "<div class='freebusy-unknown'></div>";
    }
    
    
    /**
     * Check whether the item is available in the given range.
     * @param int $start Unix timestamp
     * @param int $end Unix timestamp
     * @return boolean False if there is any booking partly or completely inside the given range
     */
    public function isAvailable( int $start, int $end ) {
        $stmt = self::$db->prepare( "SET @start = :start, @end = :end" );
        $stmt->execute( [ ":start" => $start, ":end" => $end ] );
        $stmt = self::$db->query( "
            SELECT COUNT(*) FROM booked_items 
                INNER JOIN bookings USING (bookingId) 
                INNER JOIN items USING (itemId)
                INNER JOIN categories USING (catId)
            WHERE
                itemId={$this->id} " . 
                ( $this->bookedItemId ? "AND bookedItemId != {$this->bookedItemId} " : "" ) . 
                "AND booked_items.status>=" . FFBoka::STATUS_PREBOOKED . " 
                AND (
                    (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<=@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>=@end) 
                    OR (
                        UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>=@start 
                        AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@end
                    ) OR (
                        UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start 
                        AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<=@end
                    )
                )" );
        return $stmt->fetchColumn() === 0;
    }

    /**
     * Get all reminders of this item.
     *
     * @param bool $includeInherited If set to true, also include reminders inherited from category level.
     * @return array Array of objects { int id, int itemId|catId, int offset, string anchor, string message }, where id is a unique
     *  item|category reminder identifier, offset is the number of seconds before (positive) or after (negative values)
     *  the anchor (start|end) when the reminder shall be sent, and message is the text to be sent.
     */
    public function reminders( bool $includeInherited = false ) : array {
        $reminders = [];
        if ( $includeInherited ) $reminders = $this->category()->reminders( true );
        $stmt = self::$db->query( "SELECT * FROM item_reminders WHERE itemId={$this->id}" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $reminders[] = $row;
        }
        return $reminders;
    }

    /**
     * Get the item reminder with ID $id.
     *
     * @param integer $id
     * @return array|bool Returns an array with the members [ id, catId, message, anchor, offset ] or FALSE if the reminder does not exist.
     */
    public function getReminder( int $id ) {
        $stmt = self::$db->prepare( "SELECT * from item_reminders WHERE id=?" );
        $stmt->execute( [ $id ] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Edit the properties of a reminder or create a new reminder.
     *
     * @param integer $id The id of the reminder to change. If 0, add a new reminder.
     * @param integer $offset Number of hours before (+) or after (-) start of booking when the reminder shall be sent
     * @param string $anchor Where to anchor the offset, either "start" or "end" of the booking
     * @param integer $message The new message to send with the reminder.
     * @return int|bool The id of the reminder, false on failure.
     */
    public function editReminder( int $id, int $offset, string $anchor, string $message ) {
        if ( $id==0 ) { // add a new reminder
            self::$db->exec( "INSERT INTO item_reminders SET itemId={$this->id}" );
            $id = self::$db->lastInsertId();
        }
        if ( !in_array( $anchor, [ "start", "end" ] ) ) {
            logger( __METHOD__ . " Tried to save a reminder with invalid anchor $anchor", E_ERROR );
            return false;
        }
        // Save changes
        $stmt = self::$db->prepare( "UPDATE item_reminders SET `offset`=:offset, `anchor`=:anchor, `message`=:message WHERE itemId={$this->id} AND id=:id" );
        if ( !$stmt->execute( [
            ":offset" => $offset,
            ":anchor" => $anchor,
            ":message" => $message,
            ":id" => $id
        ] ) ) {
            logger( __METHOD__ . " Failed to change or create item reminder. " . $stmt->errorInfo()[ 2 ], E_ERROR );
            return false;
        }
        // Adjust existing bookedItems
        $stmt = self::$db->query( "SELECT bookedItemId, UNIX_TIMESTAMP($anchor) anchor FROM booked_items WHERE itemId={$this->id}" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $bookedItem = new Item( $row->bookedItemId, true );
            // Mark the reminder as not being sent if sending time has not passed yet.

            $bookedItem->setReminderSent( $id, 'item',  $row->anchor + $offset < time() );
        }
        return $id;
    }

    /**
     * Delete an item reminder
     *
     * @param integer $id The id of the reminder to delete
     * @return bool True on success, false on failure
     */
    public function deleteReminder( int $id ) : bool {
        // Remove sent flag from any bookedItems
        $stmt = self::$db->query( "SELECT bookedItemId FROM booked_items WHERE remindersSent LIKE '%\"item$id\"%'" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $item = new Item( $row->bookedItemId, true );
            $item->setReminderSent( $id, "item", false );
        }
        $stmt = self::$db->prepare( "DELETE FROM item_reminders WHERE itemId={$this->id} AND id=?" );
        if ( $stmt->execute( [ $id ] ) ) return true;
        logger ( __METHOD__ . " Failed to delete item reminder. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    } 
    
    /**
     * Mark a reminder as sent. Only applicable for bookedItems
     * 
     * @param int $id The id of the reminder which has been sent.
     * @param string $prefix e.g. "cat" or "item"
     * @param bool $sent If true, mark the reminder as sent. If false, remove the mark.
     * @return bool False if method failed, e.g. because this is not a booked item
     */
    public function setReminderSent( int $id, string $prefix, bool $sent=true ) : bool {
        if ( !$this->bookedItemId ) return false;
        if ( !in_array( $prefix, [ "cat", "item" ] ) ) return false;
        $reminders = $this->remindersSent;
        if ( $sent && !in_array( "$prefix$id", $reminders ) ) $reminders[] = "$prefix$id";
        elseif ( !$sent && in_array( "$prefix$id", $reminders )) unset( $reminders[ array_search( "$prefix$id", $reminders ) ] );
        $this->remindersSent = array_values( array_unique( $reminders ) );
        return true;
    }
}

