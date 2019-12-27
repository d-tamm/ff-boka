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
    protected $id;
    protected $catId;
    
    /**
     * Initialize item with ID and get some static properties.
     * @param int $id ID of requested item. If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     */
    public function __construct($id){
        if ($id) { // Try to return an existing item from database
            $stmt = self::$db->prepare("SELECT itemId, catId FROM items WHERE itemId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->itemId;
                $this->catId = $row->catId;
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
     * @param string|int|Image $value
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
     * @return string Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "catId":
                return $this->$name;
                
            case "caption":
            case "description":
            case "active":
            case "imageId":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM items WHERE itemId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
                
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
     * Get a linear representation of free-busy information for one week
     * @param mixed $params Associative array of parameters. Supported elements are:<br>
     * <b>start</b> (int) First day of week to show, unix timestamp<br>
     * <b>scale</b> (bool) Whether to include the weekday scale. Default: False.<br>
     * <b>days</b> (int) Number of days to show. Default: 7 days (1 week)
     * @return string HTML code showing blocks of free and busy times
     */
    function freebusyBar($params=[]) {
        $start = 0;
        $scale = FALSE;
        $days = 7;
        extract($params, EXTR_IF_EXISTS);
		// Store start date as user defined variable because it is used multiple times
		$secs = $days * 24 * 60 * 60;
		$stmt = self::$db->prepare("SET @start = :start");
		$stmt->execute(array(":start"=>$start));
		// Get freebusy information.
        $stmt = self::$db->query("SELECT bookingId, subbookingId, bookedItemId, status, bufferAfterBooking, DATE_SUB(start, INTERVAL bufferAfterBooking HOUR) start, UNIX_TIMESTAMP(start) unixStart, DATE_ADD(end, INTERVAL bufferAfterBooking HOUR) end, UNIX_TIMESTAMP(end) unixEnd FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>=" . FFBoka::STATUS_PREBOOKED . " AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start+$secs) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start+$secs) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@start+$secs))");

        $ret = "";
        if ($scale) $ret .= self::freebusyWeekends($start, $days);
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            if ($row->bufferAfterBooking) {
                $ret .= "<div class='freebusy-blocked' style='left:" . (($row->unixStart-$start-$row->bufferAfterBooking*3600)/$secs*100) . "%; width:" . (($row->unixEnd - $row->unixStart + 2*$row->bufferAfterBooking*3600)/$secs*100) . "%' title='ej bokbar'></div>";
            }
            $ret .= "<div class='freebusy-busy" . ($row->status==FFBoka::STATUS_PREBOOKED ? " unconfirmed" : "") . "' data-booking-id='{$row->bookingId}' data-subbooking-id='{$row->subbookingId}' data-booked-item-id='{$row->bookedItemId}' style='left:" . (($row->unixStart - $start) / $secs * 100) . "%; width:" . (($row->unixEnd - $row->unixStart) / $secs * 100) . "%;' title='{$row->start} till {$row->end}'></div>";
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
        $style = "border-left:none;";
        for ($day=0; $day<$days; $day++) {
            $ret .= "<div class='freebusy-tic' style='$style left:" . (100/$days*$day) . "%;'>{$dayNames[$day]}</div>";
            $style = "";
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
        $date->setTimezone(new \DateTimeZone("Europe/Stockholm"));
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
     * @param int $ignoreSubId Optional, don't count this subbooking as conflicting.
     * @return boolean False if there is any subbooking partly or completely inside the given range
     */
    public function isAvailable(int $start, int $end, int $ignoreSubId=0) {
        $stmt = self::$db->prepare("SET @start = :start, @end = :end");
        $stmt->execute(array(":start"=>$start, ":end"=>$end));
        $stmt = self::$db->query("SELECT subbookingId FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>=" . FFBoka::STATUS_PREBOOKED . " AND subbookingId!=$ignoreSubId AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<=@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>=@end) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>=@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@end) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<=@end))");
        return ($stmt->rowCount()===0);
    }
}

