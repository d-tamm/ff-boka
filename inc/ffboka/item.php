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
    private $id;
    private $catId;
    
    /** The following properties are only applicable if used in booking context.
     * ID of the item's booking (not to be confused with the item ID) */
    private $bookedItemId;
    /** The ID of the booking the item belongs to */
    private $bookingId;
    
    /**
     * Initialize item with ID and get some static properties.
     * @param int $id ID of requested item. If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     * @param bool $bookedItem If TRUE, item will belong to a booking and $id is interpreted as bookedItemId instead
     */
    public function __construct($id, $bookedItem=FALSE){
        if ($id) { // Try to return an existing item from database
            if ($bookedItem) $stmt = self::$db->prepare("SELECT bookedItemId, bookingId, itemId, catId FROM booked_items INNER JOIN items USING (itemId) WHERE bookedItemId=?");
            else $stmt = self::$db->prepare("SELECT itemId, catId FROM items WHERE itemId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->itemId;
                $this->catId = $row->catId;
                if ($bookedItem) {
                    $this->bookedItemId = $row->bookedItemId;
                    $this->bookingId = $row->bookingId;
                }
            } else {
                $this->id = 0;
            }
        } else { // Return an empty object without link to database
            $this->id = 0;
        }
    }	
	
    /**
     * Setter function for item properties
     * @param string $name
     * @param string|int $value
     * @throws \Exception
     * @return string|boolean
     */
    public function __set($name, $value) {
        switch ($name) {
            case "catId":
                // May only be set on dummy Item
                if ($this->id) throw new \Exception("Cannot change category for existing item.");
                $this->catId = $value;
                return $value;
            case "caption":
            case "description":
            case "active":
            case "imageId":
                if (!$this->id) throw new \Exception("Cannot set property $name on dummy item.");
                $stmt = self::$db->prepare("UPDATE items SET $name=? WHERE itemId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
                
            case "price":
            case "status":
            case "start": // int|string Start time as Unix timestamp or string
            case "end": // int|string Start time as Unix timestamp or string
                if ($this->bookedItemId) {
                    if (($name=="start" || $name=="end") && is_numeric($value)) $stmt = self::$db->prepare("UPDATE booked_items SET $name=FROM_UNIXTIME(?) WHERE bookedItemId={$this->bookedItemId}");
                    else $stmt = self::$db->prepare("UPDATE booked_items SET $name=? WHERE bookedItemId={$this->bookedItemId}");
                    if ($stmt->execute(array($value))) return $this->$name;
                    else return FALSE;
                } else throw new \Exception("Cannot set $name property on item without bookedItemId.");
                
            default:
                throw new \Exception("Use of undefined Item property $name");
        }
        return false;
    }

    /**
     * Set the representative image of the item
     * @param Image $img
     */
    public function setFeaturedImage(Image $img) {
        if ($img->itemId != $this->id) throw \Exception("Cannot set an image to featured image which does not belong to the item.");
        $this->imageId = $img->id;
    }
    
    /**
     * Getter function for item properties
     * @param string $name Name of the property
     * @throws \Exception
     * @return string|int Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "catId":
            case "bookedItemId":
                return $this->$name;
                
            case "caption":
            case "description":
            case "active":
            case "imageId":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM items WHERE itemId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
                
            case "price":
            case "status":
            case "start": // booking start time (as unix timestamp) of booked item
            case "end": // booking end time (as unix timestamp) of booked item
                if ($this->bookedItemId) {
                    if ($name=="start" || $name=="end") $stmt = self::$db->query("SELECT UNIX_TIMESTAMP($name) $name FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
                    else $stmt = self::$db->query("SELECT $name FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
                    $row = $stmt->fetch(PDO::FETCH_OBJ);
                    return $row->$name;
                } else throw new \Exception("Cannot get $name property on item without bookedItemId.");

            default:
                throw new \Exception("Use of undefined Item property $name");
        }
    }

    /**
     * Get category to which the item belongs
     * @return \FFBoka\Category
     */
    public function category() {
        return new Category($this->catId);
    }

    /**
     * Get the booking the item belongs to (only applicable for bookedItems)
     * @return \FFBoka\Booking|false
     */
    public function booking() {
        if ($this->bookingId) return new Booking($this->bookingId);
        else return FALSE;
    }

    /**
     * Get all bookings for the next time
     * @param int $days Number of days in future to return bookings for. If set to 0, all (even past) bookings are returned
     * @return array of objects { bookingId, bookedItemId, unixtimestamp start, unixtimestamp end, status }
     */
    public function upcomingBookings(int $days=60) {
        // Get freebusy information.
        if ($days) $timeConstraint = "AND (
            (start>NOW() AND start<DATE_ADD(NOW(), INTERVAL :days DAY)) OR (end>NOW() AND end<DATE_ADD(NOW(), INTERVAL :days DAY))
            )";
        $stmt = self::$db->prepare("
            SELECT bookingId, bookedItemId, UNIX_TIMESTAMP(start)-bufferAfterBooking*3600 AS start, UNIX_TIMESTAMP(end)+bufferAfterBooking*3600 AS end, status
            FROM booked_items
            INNER JOIN bookings USING (bookingId)
            INNER JOIN items USING (itemId)
            INNER JOIN categories USING (catId)
            WHERE
                itemId={$this->id}
            $timeConstraint
            ORDER BY start");
        $stmt->execute(array( ":days"=>$days ));
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
    
    /**
     * Remove item from database
     * @throws \Exception
     * @return boolean True on success
     */
    public function delete() {
        if (self::$db->exec("DELETE FROM items WHERE itemId={$this->id}")) {
            return TRUE;
        } else {
            throw new \Exception("Failed to delete item.");
        }
    }
    
    /**
     * Remove bookedItem from its booking
     * @throws \Exception
     * @return boolean True on success
     */
    public function removeFromBooking() {
        if (self::$db->exec("DELETE FROM booked_items WHERE bookedItemId={$this->bookedItemId}")) {
            unset($this->bookedItemId);
            unset($this->bookingId);
            return TRUE;
        } else {
            throw new \Exception("Failed to remove item from booking.");
        }
    }
    
    /**
     * Make a copy of the item.
     * The copy will be inactive, and the featured image will not be set.
     * @return \FFBoka\Item The newly created item
     */
    public function copy() {
        self::$db->exec("INSERT INTO items (catId, caption, description) SELECT catID, caption, description FROM items WHERE itemID={$this->id}");
        $newItemId = self::$db->lastInsertId();
        // copy the associated item images
        self::$db->exec("INSERT INTO item_images (itemId, image, thumb, caption) SELECT $newItemId, image, thumb, caption FROM item_images WHERE itemId={$this->id}");
        $newItem = new Item($newItemId);
        $newItem->caption = $newItem->caption . " (kopia)";
        return $newItem;
    }
    
    /**
     * Create a new image
     * @throws \Exception
     * @return \FFBoka\Image
     */
    public function addImage() {
        if (self::$db->exec("INSERT INTO item_images SET itemId={$this->id}")) {
            return new Image(self::$db->lastInsertId());
        } else {
            throw new \Exception("Failed to create item image.");
        }
    }
    
    /**
     * Get all images of the item
     * @return \FFBoka\Image[]
     */
    public function images() {
        $images = array();
        $stmt = self::$db->query("SELECT imageId FROM item_images WHERE itemId={$this->id}");
        while ($image = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $images[] = new Image($image->imageId);
        }
        return $images;
    }
    
    /**
     * Get the representative image of the item 
     * @return \FFBoka\Image
     */
    public function getFeaturedImage() {
        return new Image($this->imageId);
    }
    
    /**
     * Get a linear representation of free-busy information
     * @param mixed $params Associative array of parameters. Supported elements are:<br>
     * <b>start</b> (int) First day of week to show, unix timestamp<br>
     * <b>scale</b> (bool) Whether to include the weekday scale. Default: False.<br>
     * <b>days</b> (int) Number of days to show. Default: 7 days (1 week)<br>
     * <b>minStatus</b> (int) Don't show bookings with lower status than this. Defaults to STATUS_PREBOOKED.<br>
     * <b>includeTokens</b> Include data-token properties, default:false<br>
     * <b>showPrice</b> Show the price in title of blocked bars
     * @return string HTML code showing blocks of free and busy times
     */
    function freebusyBar($params=[]) {
        $start = 0;
        $scale = FALSE;
        $days = 7;
        $minStatus = FFBoka::STATUS_PREBOOKED;
        $includeTokens = FALSE;
        $showPrice = FALSE;
        extract($params, EXTR_IF_EXISTS);
		// Store start date as user defined variable because it is used multiple times
		$secs = $days * 24 * 60 * 60;
		$stmt = self::$db->prepare("SET @start = :start");
		$stmt->execute(array(":start"=>$start));
		// Get freebusy information.
        $stmt = self::$db->query("
            SELECT bookingId, bookedItemId, status, price, token, bufferAfterBooking, DATE_SUB(start, INTERVAL bufferAfterBooking HOUR) start, UNIX_TIMESTAMP(start) unixStart, DATE_ADD(end, INTERVAL bufferAfterBooking HOUR) end, UNIX_TIMESTAMP(end) unixEnd 
            FROM booked_items 
            INNER JOIN bookings USING (bookingId) 
            INNER JOIN items USING (itemId) 
            INNER JOIN categories USING (catId) 
            WHERE 
                itemId={$this->id} " . 
                (isset($this->bookedItemId) ? "AND bookedItemId != {$this->bookedItemId} " : "") . " 
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
                )");

        $ret = "";
        if ($scale) $ret .= self::freebusyWeekends($start, $days);
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            if ($row->bufferAfterBooking) {
                $ret .= "<div class='freebusy-blocked' style='left:" . (($row->unixStart-$start-$row->bufferAfterBooking*3600)/$secs*100) . "%; width:" . (($row->unixEnd - $row->unixStart + 2*$row->bufferAfterBooking*3600)/$secs*100) . "%' title='ej bokbar'></div>";
            }
            $class = "freebusy-busy";
            if ($row->status==FFBoka::STATUS_PREBOOKED) $class .= " unconfirmed";
            if ($row->status==FFBoka::STATUS_CONFLICT) $class .= " conflict";
            if ($showPrice && $row->price) $class .= " has-price";
            $title = strftime("%F kl %H:00", $row->unixStart) . " till " . strftime("%F kl %H:00", $row->unixEnd);
            if ($showPrice) $title .= is_null($row->price) ? "\nInget pris satt" : "\nPris: {$row->price} kr";
            $ret .= "<div class='$class' data-booking-id='{$row->bookingId}' data-booked-item-id='{$row->bookedItemId}' " . ($includeTokens ? "data-token='{$row->token}' " : "") . "style='left:" . (($row->unixStart - $start) / $secs * 100) . "%; width:" . (($row->unixEnd - $row->unixStart) / $secs * 100) . "%;' title='$title'></div>";
        }
        if ($scale) $ret .= self::freebusyScale(false, $days);
        return $ret;
    }

    /**
     * Get vertical lines for freebusyBar
     * @param bool $weekdays Also display weekday abbreviations
     * @param int $days Number of days to show
     * @return string HTML code
     */
    public static function freebusyScale(bool $weekdays = FALSE, int $days=7) {
        $dayNames = $weekdays ? array("<span>mån</span>","<span>tis</span>","<span>ons</span>","<span>tor</span>","<span>fre</span>","<span>lör</span>","<span>sön</span>") : array_fill(0,$days,"");
        $noborder = "border-left:none;";
        for ($day=0; $day<$days; $day++) {
            $ret .= "<div class='freebusy-tic' data-day='$day' style='$noborder " . ($weekdays ? "width:" . (100/$days) . "%; " : "") . "left:" . (100/$days*$day) . "%;'>{$dayNames[$day]}</div>";
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
    public static function freebusyWeekends(int $start, int $days=7) {
        $date = new \DateTime("@$start");
        $date->setTimezone(new \DateTimeZone(self::$timezone));
        $ret = "";
        for ($day=0; $day<$days; $day++) {
            if ($date->format('N') > 5) $ret .= "<div class='freebusy-weekend' style='left:" . (100/$days*$day) . "%; width:" . (100/$days) . "%;'></div>"; 
            $date->add(new \DateInterval("P1D"));
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
    public function isAvailable(int $start, int $end) {
        $stmt = self::$db->prepare("SET @start = :start, @end = :end");
        $stmt->execute(array(":start"=>$start, ":end"=>$end));
        $stmt = self::$db->query("
            SELECT bookingId FROM booked_items 
                INNER JOIN bookings USING (bookingId) 
                INNER JOIN items USING (itemId)
                INNER JOIN categories USING (catId)
            WHERE
                itemId={$this->id} " . 
                (isset($this->bookedItemId) ? "AND bookedItemId != {$this->bookedItemId} " : "") . 
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
                )");
        return ($stmt->rowCount()===0);
    }
}

