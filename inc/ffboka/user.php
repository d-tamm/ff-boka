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
    private $assignments;
    
    /**
     * On user instatiation, get some static properties.
     * If user does not yet exist in database, create a record.
     * @param int $id User ID. An $id=(empty|0) will result in an empty user with unset id property.
     * @param int|string $section Id or name of section the user belongs to
     */
    function __construct($id, $section=0) {
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
        if ($section) {
            $stmt = self::$db->prepare("UPDATE users SET sectionId=(SELECT sectionId FROM sections WHERE sectionId=:sectionId OR name=:name) WHERE userId=:userId");
            $stmt->execute(array(
                ":sectionId"=> $section,
                ":name"     => $section,
                ":userId"   => $this->id
            ));
        }
        // Get user's assignments from the FF API as an array[sectionId][names] (only assignments on section level)
        $this->assignments = array();
        $data = (object)array("results"=>array());
        if (self::$apiAssUrl) { // API URL for assignments is set. Try to get user's assignments
            $data = @file_get_contents(self::$apiAssUrl . "?MNoSocnr={$this->id}");
            if ($data === FALSE) { // no answer
                $this->assignments[0][] = "Kunde inte läsa in uppdrag från API.";
            } else { // Got an answer
                $data = json_decode($data);
                foreach ($data->results as $ass) {
                    if ($ass->cint_assignment_party_type->value == FFBoka::TYPE_SECTION) {
                        // This will sort the assignments on section ID
                        $this->assignments[$ass->section__cint_nummer][] = $ass->cint_assignment_type_id->name;
                    }
                }
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
                return $this->$name;
            case "section":
                return new Section($this->sectionId);
            case "sectionId":
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
            case "sectionId":
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
    
    /**
     * Set the timestamp for the user to current time
     * @return bool
     */
    public function updateLastLogin() {
        return self::$db->exec("UPDATE users SET lastLogin=NULL WHERE userId='{$this->id}'");
    }
    
    /**
     * Get an HTML formatted string with contact data
     * @return string
     */
    public function contactData() {
        $ret = array();
        if ($this->name) $ret[] = htmlspecialchars($this->name);
        if ($this->phone) $ret[] = "☎ " . htmlspecialchars($this->phone);
        if ($this->mail) $ret[] = "✉ " . htmlspecialchars($this->mail);
        return implode("<br>", $ret);
    }
    
    /**
     * Create a new booking for this user
     * @param int $sectionId ID of section which this booking belongs to
     * @return \FFBoka\Booking
     */
    public function addBooking(int $sectionId) {
        // Create token
        for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a{$x}, $i++);
        if ($this->id) $stmt = self::$db->prepare("INSERT INTO bookings SET sectionId=?, userId={$this->id}, token='$token'");
        else $stmt = self::$db->prepare("INSERT INTO bookings SET sectionId=?, token='$token'");
        $stmt->execute(array($sectionId));
        return new Booking(self::$db->lastInsertId());
    }
    
    /**
     * Get booking IDs of all the user's bookings, incl up to 1 year old ones
     * @return int[] IDs of bookings no older than 1 year
     */
    public function bookingIds() {
        $stmt = self::$db->query("SELECT bookingId FROM bookings WHERE userId={$this->id} AND timestamp>DATE_SUB(CURDATE(), INTERVAL 1 YEAR) ORDER BY timestamp DESC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Get booking IDs of bookings which the user has initiated but not completed
     * @return int[] booking IDs
     */
    public function unfinishedBookings() {
        $stmt = self::$db->query("SELECT bookingId FROM booked_items INNER JOIN bookings USING (bookingId) WHERE userId={$this->id} AND status=" . FFBoka::STATUS_PENDING);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Find sections where user has booking admin roles
     * @return Section[]
     */
    public function bookingAdminSections() {
        $admSections = array();
        foreach ($this->getAllSections() as $section) {
            if ($section->showFor($this, FFBoka::ACCESS_CONFIRM)) {
                $admSections[] = $section;
            }
        }
        return $admSections;
    }
    
    /**
     * Returns whether user as a booking admin shall receive mails on new bookings in given category
     * @param Category $cat Category for which to return the information
     * @return string yes|confirmOnly|no If confirmOnly, the user shall only be notified on new
     * bookings that need to be confirmed. 
     */
    public function getNotifyAdminOnNewBooking(Category $cat) {
        $stmt = self::$db->query("SELECT notify FROM cat_admin_noalert WHERE userId={$this->id} AND catId={$cat->id}");
        if ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            return $row->notify;
        } else {
            return "yes";
        }
    }

    /**
     * Set whether to notify a booking admin user on new bookings in given category
     * @param int $catId Category ID for which to set the information
     * @param string $value no|confirmOnly|yes
     * @throws \Exception if $value is not one of the allowed values.
     * @return boolean|int On success, returns the remaining number of admins receiving
     * notifications in this category. Returns FALSE on failure
     */
    public function setNotifyAdminOnNewBooking(int $catId, string $value) {
        if ($value=="yes") {
            if (self::$db->exec("DELETE FROM cat_admin_noalert WHERE userId={$this->id} AND catId=$catId")===FALSE) return FALSE;
        } elseif ($value=="no" || $value=="confirmOnly") {
            if (self::$db->exec("INSERT INTO cat_admin_noalert SET userId={$this->id}, catId=$catId, notify='$value' ON DUPLICATE KEY UPDATE notify='$value'")===FALSE) return FALSE;
        } else {
            throw new \Exception("$value is not a valid value for admin notification.");
        }
        $cat = new Category($catId);
        $remaining = 0;
        foreach ($cat->admins(FFBoka::ACCESS_CONFIRM, TRUE) as $admin) {
            $u = new User($admin['userId']);
            if ($u->getNotifyAdminOnNewBooking($cat)!='no') $remaining++;
        }
        return $remaining;
        
    }
}
