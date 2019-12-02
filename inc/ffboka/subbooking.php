<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;


/**
 * Class Subbooking
 * Contains a portion of a booking whith items having the same start and end time.
 */
class Subbooking extends FFBoka {
    private $id;
    private $bookingId;
    
    /**
     * Subbooking instantiation.
     * @param int $id ID of the subbooking
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT subbookingId, bookingId FROM subbookings WHERE subbookingId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->subbookingId;
                $this->bookingId = $row->bookingId;
            } else {
                throw new \Exception("Can't instatiate Subbooking with ID $id.");
            }
        } else {
            throw new \Exception("Can't instatiate Subbooking without ID.");
        }
    }
    
    /**
     * Getter function for Subbooking properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "bookingId":
                return $this->$name;
            case "start":
            case "end":
                $stmt = self::$db->query("SELECT UNIX_TIMESTAMP($name) $name FROM subbookings WHERE subbookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "price":
                $stmt = self::$db->query("SELECT $name FROM subbookings WHERE subbookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            default:
                throw new \Exception("Use of undefined Subbooking property $name");
        }
    }
    
    /**
     * Setter function for Subbooking properties
     * @param string $name Property name
     * @param int|string $value Property value. For datetime properties,
     * Unix timestamp and string representations are supported.
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "start":
            case "end":
                if (is_numeric($value)) $stmt = self::$db->prepare("UPDATE subbookings SET $name=FROM_UNIXTIME(?) WHERE subbookingId={$this->id}");
                else $stmt = self::$db->prepare("UPDATE subbookings SET $name=? WHERE subbookingId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            case "price":
                $stmt = self::$db->prepare("UPDATE subbookings SET $name=? WHERE subbookingId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Subbooking property $name");
        }
        return false;
    }
    
    /**
     * Add an item to the subbooking.
     * @param int $itemId ID of the item to add
     * @return bool True on success
     */
    public function addItem(int $itemId) {
        $stmt = self::$db->prepare("INSERT INTO booked_items SET subbookingId={$this->id}, itemId=?");
        return $stmt->execute(array( $itemId ));
    }
    
    /**
     * Remove the whole subbooking
     * @return bool Success
     */
    public function delete() {
        return self::$db->exec("DELETE FROM subbookings WHERE subbookingId={$this->id}");
    }
    
    /**
     * Remove an item from the subbooking
     * @param int $bookedItemId Booking ID of the item to be removed
     * @return bool True on success
     */
    public function removeItem(int $bookedItemId) {
        $stmt = self::$db->prepare("DELETE FROM booked_items WHERE bookedItemId=?");
        return $stmt->execute(array( $bookedItemId ));
    }
    
    /**
     * Get all items contained in this subbooking
     * @return \FFBoka\BookedItem[]
     */
    public function items() {
        $stmt = self::$db->query("SELECT bookedItemId, status FROM booked_items WHERE subbookingId={$this->id}");
        $items = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $items[] = new BookedItem($row->bookedItemId);
        }
        return $items;
    }
    
    /**
     * Set the booking status of an item in subbooking
     * @param int $itemId ID of the item
     * @param int $status
     * @return bool True on success
     */
    public function setStatus(int $itemId, int $status) {
        $stmt = self::$db->prepare("UPDATE booked_items SET status=:status WHERE subbookingId={$this->id} AND itemId=:itemId");
        return $stmt->execute(array(":status"=>$status, ":itemId"=>$itemId));
    }
    
    /**
     * Get the booking status of an item in subbooking
     * @param int $itemId ID of the item
     * @return int The booking status of the item
     */
    public function getStatus(int $itemId) {
        $stmt = self::$db->prepare("SELECT status FROM booked_items WHERE subbookingId={$this->id} AND itemId=?");
        $stmt->execute(array($itemId));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row['status'];
    }
}
