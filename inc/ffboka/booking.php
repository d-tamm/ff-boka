<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Booking
 * Class containing complete booking.
 */
class Booking extends FFBoka {
    
    private $id;
    private $userId;
    private $sectionId;
    
    /**
     * Booking instantiation. 
     * @param int $id ID of the booking
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT bookingId, sectionId, userId FROM bookings WHERE bookingId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->bookingId;
                $this->sectionId = $row->sectionId;
                $this->userId = $row->userId;
            } else {
                throw new \Exception("Can't instatiate Booking with ID $id.");
            }
        } else {
            throw new \Exception("Can't instatiate Booking without ID.");
        }
    }
    
    /**
     * Getter function for Booking properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "userId":
            case "sectionId":
                return $this->$name;
            case "timestamp":
            case "commentCust":
            case "commentIntern":
            case "paid":
            case "extName":
            case "extPhone":
            case "extMail":
            case "token":
                $stmt = self::$db->query("SELECT $name FROM bookings WHERE bookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "price":
                $stmt = self::$db->query("SELECT SUM(price) price FROM booked_items WHERE bookingId={$this->id} AND NOT price IS NULL");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->price;
            default:
                throw new \Exception("Use of undefined Booking property $name");
        }
    }
    
    /**
     * Setter function for Booking properties
     * @param string $name Property name
     * @param string $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "commentCust":
            case "commentIntern":
            case "status":
            case "paid":
            case "extName":
            case "extPhone":
            case "extMail":
                $stmt = self::$db->prepare("UPDATE bookings SET $name=? WHERE bookingId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Booking property $name");
        }
        return false;
    }
    
    /**
     * Get the user of the booking
     * @return \FFBoka\User empty User for external bookings
     */
    public function user() {
        return new User(is_null($this->userId) ? 0 : $this->userId);
    }
    
    /**
     * Get the section the booking belongs to
     * @return \FFBoka\Section
     */
    public function section() {
            return new Section($this->sectionId);
    }

    /**
     * Remove the whole booking
     * @return bool Success
     */
    public function delete() {
        return self::$db->exec("DELETE FROM bookings WHERE bookingId={$this->id}");
    }
    
    /**
     * Get the status of the booking. 
     * @return int The least status of all items in the booking
     */
    public function status() {
        $leastStatus = FFBoka::STATUS_CONFIRMED;
        foreach ($this->items() as $item) {
            $leastStatus = $leastStatus & $item->status;
        }
        return $leastStatus;
    }
    
    /**
     * Add an item to the booking.
     * @param int $itemId ID of the item to add
     * @return Item|bool BookedItemID of added item on success, false on failure
     */
    public function addItem(int $itemId) {
        $stmt = self::$db->prepare("INSERT INTO booked_items SET bookingId={$this->id}, itemId=?");
        if ($stmt->execute(array( $itemId ))) return new Item(self::$db->lastInsertId(), TRUE);
        else return FALSE;
    }

    /**
     * Remove an item from the booking
     * @param int $bookedItemId Booking ID of the item to be removed
     * @return bool True on success
     */
    public function removeItem(int $bookedItemId) {
        $stmt = self::$db->prepare("DELETE FROM booked_items WHERE bookedItemId=?");
        return $stmt->execute(array( $bookedItemId ));
    }
    
    /**
     * Get all items contained in this booking
     * @return Item[]
     */
    public function items() {
        $stmt = self::$db->query("SELECT bookedItemId, status FROM booked_items WHERE bookingId={$this->id}");
        $items = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $items[] = new Item($row->bookedItemId, TRUE);
        }
        return $items;
    }

    /**
     * Add the answer to a booking question to the booking
     * @param string $question The asked question
     * @param string $answer The answer given by the booker
     * @return int|bool ID of inserted answer, FALSE on failure
     */
    public function addAnswer(string $question, string $answer) {
        $stmt = self::$db->prepare("INSERT INTO booking_answers SET bookingId={$this->id}, question=:question, answer=:answer");
        if (!$stmt->execute(array(
            ":question"=>$question,
            ":answer"=>$answer,
        ))) return FALSE;
        return self::$db->lastInsertId();
    }
    
    /**
     * Deletes all answers to booking questions
     * @return bool TRUE on success
     */
    public function clearAnswers() {
        return self::$db->exec("DELETE FROM booking_answers WHERE bookingId={$this->id}");
    }
    
    /**
     * Get all booking questions and answers
     * @return array( id => { string question, string answer }, ... )
     */
    public function answers() {
        $ans = array();
        $stmt = self::$db->query("SELECT answerId, question, answer FROM booking_answers WHERE bookingId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ans[$row->answerId] = $row; 
        }
        return $ans;
    }
}

