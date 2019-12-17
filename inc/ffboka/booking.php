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
 * Class containing complete booking, with one or more subbookings.
 */
class Booking extends FFBoka {
    
    private $id;
    private $userId;
    
    /**
     * Booking instantiation. 
     * @param int $id ID of the booking
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT bookingId, userId FROM bookings WHERE bookingId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->bookingId;
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
                return $this->$name;
            case "timestamp":
            case "commentCust":
            case "commentIntern":
            case "payed": //Datetime field
            case "extName":
            case "extPhone":
            case "extMail":
                $stmt = self::$db->query("SELECT $name FROM bookings WHERE bookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "sectionId":
                // We just follow the path of one item in the booking to get to the section (all belong to same section)
                $stmt = self::$db->query("SELECT sectionId FROM subbookings INNER JOIN booked_items USING (subbookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE bookingId={$this->id}");
                return $stmt->fetch(\PDO::FETCH_OBJ)->sectionId;
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
            case "payed": //Datetime field
            case "extName":
            case "extPhone":
            case "extMail":
                $stmt = self::$db->prepare("UPDATE bookings SET $name=? WHERE userId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Booking property $name");
        }
        return false;
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
        foreach ($this->subbookings() as $sub) {
            foreach ($sub->items() as $item) {
                $leastStatus = $leastStatus & $item->getStatus();
            }
        }
        return $leastStatus;
    }
    
    /**
     * Create a new subbooking
     * @return \FFBoka\Subbooking
     */
    public function addSubbooking() {
        self::$db->exec("INSERT INTO subbookings SET bookingId={$this->id}");
        return new Subbooking(self::$db->lastInsertId());
    }

    /**
     * Get all subbookings belonging to this booking.
     * @return \FFBoka\Subbooking[]
     */
    public function subbookings() {
        $stmt = self::$db->query("SELECT subbookingId FROM subbookings WHERE bookingId={$this->id}");
        $subs = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $subs[] = new Subbooking($row->subbookingId);
        }
        return $subs;
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

