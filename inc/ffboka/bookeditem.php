<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;


/**
 * Class BookedItem
 * Item in a subbooking
 */
class BookedItem extends Item {
    /** The ID of the item's booking (not to be confused with the item ID) */
    private $bookedItemId;
    /** The ID of the booking the item belongs to */
    private $bookingId;
    
    /**
     * Initialize bookedItem with ID and get some static properties.
     * @param int $bookedItemId ID of requested bookedItem. This is not the same ID as for "normal" items!
     * If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     */
    public function __construct($bookedItemId){
        if ($bookedItemId) { // Try to return an existing item from database
            $stmt = self::$db->prepare("SELECT bookedItemId, bookingId, itemId, catId FROM booked_items INNER JOIN items USING (itemId) WHERE bookedItemId=?");
            $stmt->execute(array($bookedItemId));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->bookedItemId = $row->bookedItemId;
                $this->bookingId = $row->bookingId;
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
     * Get booking start time of item
     * @return int Unix timestamp of start time
     */
    public function start() {
        $stmt = self::$db->query("SELECT UNIX_TIMESTAMP(start) start FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row->start;
    }

    /**
     * Set the booking start time of the item
     * @param int|string $start Start time as Unix timestamp or string
     * @return int|bool Unix timestamp of start time, or false on failure
     */
    public function setStart($start) {
        if (is_numeric($start)) $stmt = self::$db->prepare("UPDATE booked_items SET start=FROM_UNIXTIME(?) WHERE bookedItemId={$this->bookedItemId}");
        else $stmt = self::$db->prepare("UPDATE booked_items SET start=? WHERE bookedItemId={$this->bookedItemId}");
        if ($stmt->execute(array($start))) return $this->start();
        else return FALSE;
    }
    
    /**
     * Set the booking end time of the item
     * @param int|string $end End time as Unix timestamp or string
     * @return int|bool Unix timestamp of end time, or false on failure
     */
    public function setEnd($end) {
        if (is_numeric($end)) $stmt = self::$db->prepare("UPDATE booked_items SET end=FROM_UNIXTIME(?) WHERE bookedItemId={$this->bookedItemId}");
        else $stmt = self::$db->prepare("UPDATE booked_items SET end=? WHERE bookedItemId={$this->bookedItemId}");
        if ($stmt->execute(array($end))) return $this->end();
        else return FALSE;
    }
    
    /**
     * Get booking end time of item
     * @return int Unix timestamp of end time
     */
    public function end() {
        $stmt = self::$db->query("SELECT UNIX_TIMESTAMP(end) end FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row->end;
    }
    
    /**
     * Get the booking the item belongs to
     * @return \FFBoka\Booking
     */
    public function booking() {
        return new Booking($this->bookingId);
    }
    
    /**
     * Remove bookedItem from its booking
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
     * @return int Status of the item
     */
    public function getStatus() {
        $stmt = self::$db->query("SELECT status FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row->status;
    }
    
    /**
     * Set the price for the item
     * @param int $price
     * @return bool True on success
     */
    public function setPrice(int $price) {
        $stmt = self::$db->prepare("UPDATE booked_items SET price=? WHERE bookedItemId={$this->bookedItemId}");
        if ($stmt->execute(array($price))) return $price;
        else return FALSE;
    }
    
    /**
     * Unset the price for the item
     */
    public function unsetPrice() {
        return self::$db->exec("UPDATE booked_items SET price=null WHERE bookedItemId={$this->bookedItemId}");
    }
    
    /**
     * Get the booking status of the item
     * @return int Price for the item, NULL if not set
     */
    public function getPrice() {
        $stmt = self::$db->query("SELECT price FROM booked_items WHERE bookedItemId={$this->bookedItemId}");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row->price;
    }
}
