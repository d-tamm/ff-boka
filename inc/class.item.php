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
     * @param int $start First day of week to show, unix timestamp
     * @param bool $scale Whether to include the weekday scale
     * @return string HTML code showing blocks of free and busy times
     */
    function freebusyBar($start, bool $scale=FALSE) {
		// Store start date as user defined variable because it is used multiple times
		$stmt = self::$db->prepare("SET @start = :start");
		$stmt->execute(array(":start"=>$start));
		// Get freebusy information. 604800 seconds per week.
        $stmt = self::$db->query("SELECT bufferAfterBooking, DATE_SUB(start, INTERVAL bufferAfterBooking HOUR) start, UNIX_TIMESTAMP(start) unixStart, DATE_ADD(end, INTERVAL bufferAfterBooking HOUR) end, UNIX_TIMESTAMP(end) unixEnd FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>=" . FFBoka::STATUS_PREBOOKED . " AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start+604800) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start+604800) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@start+604800))");

        $ret = "";
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            if ($row->bufferAfterBooking) {
                $ret .= "<div class='freebusy-blocked' style='left:" . (($row->unixStart-$start-$row->bufferAfterBooking*3600) / 6048) . "%; width:" . (($row->unixEnd - $row->unixStart + 2*$row->bufferAfterBooking*3600)/6048) . "%' title='ej bokbar'></div>";
            }
            $ret .= "<div class='freebusy-busy' style='left:" . (($row->unixStart - $start) / 6048) . "%; width:" . (($row->unixEnd - $row->unixStart) / 6048) . "%;' title='Upptaget {$row->start} till {$row->end}'></div>";
        }
        if ($scale) $ret .= self::freebusyScale();
        return $ret;
    }

    /**
     * Get vertical lines for freebusyBar
     * @param bool $weekdays Also display weekday abbreviations
     * @return string HTML code
     */
    public static function freebusyScale(bool $weekdays = FALSE) {
        $dayNames = $weekdays ? array("<span>mån</span>","<span>tis</span>","<span>ons</span>","<span>tor</span>","<span>fre</span>","<span>lör</span>","<span>sön</span>") : array_fill(0,7,"");
        $ret = "<div class='freebusy-tic' style='border-left:none; left:0;'>{$dayNames[0]}</div>";
        for ($day=1; $day<7; $day++) {
            $ret .= "<div class='freebusy-tic' style='left:" . (100/7*$day) . "%;'>{$dayNames[$day]}</div>";
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
     * @return boolean False if there is any subbooking partly or completely inside the given range
     */
    public function isAvailable(int $start, int $end) {
        $stmt = self::$db->prepare("SET @start = :start, @end = :end");
        $stmt->execute(array(":start"=>$start, ":end"=>$end));
        $stmt = self::$db->query("SELECT subbookingId FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>=" . FFBoka::STATUS_PREBOOKED . " AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@end) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@end) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@end))");
        return ($stmt->rowCount()===0);
    }
}




/**
 * Class BookedItem
 * Item in a subbooking
 */
class BookedItem extends Item {
    /** The ID of the item's booking (not to be confused with the item ID) */
    public $bookedItemId;
    /** The ID of the subbooking the item belongs to */
    public $subbookingId;
    
    /**
     * Initialize bookedItem with ID and get some static properties.
     * @param int $bookedItemId ID of requested bookedItem. This is not the same ID as for "normal" items!
     * If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     */
    public function __construct($bookedItemId){
        if ($bookedItemId) { // Try to return an existing item from database
            $stmt = self::$db->prepare("SELECT bookedItemId, subbookingId, itemId, catId FROM booked_items INNER JOIN items USING (itemId) WHERE bookedItemId=?");
            $stmt->execute(array($bookedItemId));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->bookedItemId = $row->bookedItemId;
                $this->subbookingId = $row->subbookingId;
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
     * Remove bookedItem from its subbooking
     * @throws \Exception
     * @return boolean True on success
     */
    public function delete() {
        if (self::$db->exec("DELETE FROM booked_items WHERE bookedItemId={$this->bookedItemId}")) {
            return TRUE;
        } else {
            throw new \Exception("Failed to remove item from subbooking.");
        }
    }
    
    /**
     * Set the booking status of the item
     * @param int $status One of the FFBoka::STATUS_xx constants
     * @return int|boolean Returns the set value, or false on failure
     */
    public function setStatus(int $status) {
        $stmt = self::$db->prepare("UPDATE booked_items SET status=? WHERE bookedItemId={$this->bookedItemId}");
        if ($stmt->execute(array($status))) return $status;
        else return FALSE;
    }
    
    /**
     * Get the booking status of the item
     * @return int $status
     */
    public function getStatus() {
        $stmt = self::$db->query("SELECT status FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row->status;
    }
}
