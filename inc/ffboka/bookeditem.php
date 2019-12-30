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
     * Get the booking the item belongs to
     * @return \FFBoka\Booking
     */
    public function booking() {
        $stmt = self::$db->query("SELECT bookingId FROM subbookings WHERE subbookingId={$this->subbookingId}");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return new Booking($row->bookingId);
    }
    
    /**
     * Get the subbooking the item belongs to
     * @return \FFBoka\Subbooking
     */
    public function subbooking() {
        return new Subbooking($this->subbookingId);
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
        if ($stmt->execute(array($status))) return $status;
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
