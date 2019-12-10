<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class User
 * Represents a user of the system (both admins and normal users)
 */
class User extends FFBoka {
    private $id;
    private $sectionId;
    private $assignments;
    
    /**
     * On user instatiation, get some static properties.
     * If user does not yet exist in database, create a record.
     * @param int $id User ID. An $id=(empty|0) will result in an empty user with unset id property.
     */
    function __construct($id) {
        if (!$id) return;
        if (!is_numeric($id)) return;
        // Check if user with that member ID exists in the database
        $stmt = self::$db->prepare("SELECT userId FROM users WHERE userId=?");
        $stmt->execute(array($id));
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) { // Return existing user
            $this->id = $row->userId;
        } else { // Create a new database entry
            $stmt = self::$db->prepare("INSERT INTO users SET userId=?");
            $stmt->execute(array($id));
            $this->id = (int)$id;
        }
        // Get home section for user
        $this->sectionId = 52; // TODO: get real section from API
        // Get user's assignments from the FF API as an array[sectionId][names] (only assignments on section level)
        $this->assignments = array();
        if (self::$apiUrl) $data = json_decode(file_get_contents(self::$apiUrl . "/api/feed/Pan_Extbokning_GetAssingmentByMemberNoOrSocSecNo?MNoSocnr={$this->id}"));
        else $data = (object)array("results"=>array()); // API not set (e.g. development environment)
        foreach ($data->results as $ass) {
            if ($ass->cint_assignment_party_type->value == FFBoka::TYPE_SECTION) {
                // This will sort the assignments on section ID
                $this->assignments[$ass->section__cint_nummer][] = $ass->cint_assignment_type_id->name;
            }
        }
    }
    
    /**
     * Getter function for User properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string|\FFBoka\Section|array
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "sectionId":
                return $this->$name;
            case "section":
                return new Section($this->sectionId);
            case "name":
            case "mail":
            case "phone":
                $stmt = self::$db->query("SELECT $name FROM users WHERE userId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "assignments":
                return $this->assignments;
            default:
                throw new \Exception("Use of undefined User property $name");
        }
    }
    
    /**
     * Setter function for User properties
     * @param string $name Property name
     * @param string $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "name":
            case "mail":
            case "phone":
                $stmt = self::$db->prepare("UPDATE users SET $name=? WHERE userId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined category property $name");
        }
        return false;
    }
    
    /**
     * Deletes the user and all related data from the database
     * @return boolean TRUE on success, FALSE otherwise
     */
    public function delete() {
        return self::$db->exec("DELETE FROM users WHERE userID={$this->id}");
    }
    
    public function updateLastLogin() {
        return self::$db->exec("UPDATE users SET lastLogin=NULL WHERE userId='{$this->id}'");
    }
    
    /**
     * Create a new booking for this user
     * @return \FFBoka\Booking
     */
    public function addBooking() {
        if ($this->id) self::$db->exec("INSERT INTO bookings SET userId={$this->id}");
        else self::$db->exec("INSERT INTO bookings () VALUES ()");
        return new Booking(self::$db->lastInsertId());
    }
    
    /**
     * Get booking IDs of bookings which the user has initiated but not completed
     * @return int[] booking IDs
     */
    public function unfinishedBookings() {
        $stmt = self::$db->query("SELECT bookingId FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) WHERE userId={$this->id} AND status=" . FFBoka::STATUS_PENDING);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
}
