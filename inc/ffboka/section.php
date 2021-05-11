<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Section
 * Represents the sections (lokalavdelningar) in FF.
 */
class Section extends FFBoka {
    private $id;
    private $name;
    
    /**
     * On section instantiation, get static properties.
     * @param int $id Section ID. If ID does not exist in database, id property in returned object will be unset.
     */
    function __construct($id){
        $stmt = self::$db->prepare("SELECT sectionId, name FROM sections WHERE sectionId=?");
        $stmt->execute(array($id));
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $this->id = $row->sectionId;
            $this->name = $row->name;
        } else {
            throw new \Exception("Cannot instantiate section. Section with ID $id not found in database.");
        }
    }
    
    /**
     * Getter function for Section properties.
     * @param string $name Name of the property to retrieve
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
        case "id":
            return $this->id;
        case "name":
            return $this->name;
        case "lat":
        case "lon":
            $stmt = self::$db->query("SELECT $name FROM sections WHERE sectionId={$this->id}");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->$name;
        default:
            throw new \Exception("Use of undefined Section property $name");
        }
    }

        /**
     * Setter function for section properties
     * @param string $name Property name
     * @param int|string|NULL $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "lon":
            case "lat":
                if (is_numeric($value)) {
                    $stmt = self::$db->prepare("UPDATE sections SET $name=:value WHERE sectionId={$this->id}");
                    $stmt->bindValue(":value", $value);
                    if (!$stmt->execute()) die ($stmt->errorInfo()[2]);
                    return $value;
                }
                break;
            default:
                throw new \Exception("Trying to set undefined Section property $name");
        }
        return false;
    }

    /**
     * Gets all admin members IDs of the section.
     * @return int[] Admin member IDs
     */
    public function getAdmins(){
        $stmt = self::$db->query("SELECT userId FROM section_admins WHERE sectionId={$this->id}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Add admin access for user.
     * @param $userId int UserID of new admin
     * @return bool TRUE on success, FALSE on failure.
     */
    public function addAdmin($userId){
        $user = new User($userId); // This will add user to database if not already there.
        if (!$user->id) return FALSE;
        $stmt = self::$db->prepare("INSERT INTO section_admins SET sectionId={$this->id}, userId=?");
        return $stmt->execute(array($userId));
    }
    
    /**
     * Revoke admin access
     * @param int $userId UserID of user to remove
     * @return bool True on success
     */
    public function removeAdmin($userId) {
        $stmt = self::$db->prepare("DELETE FROM section_admins WHERE sectionId={$this->id} AND userId=?");
        return $stmt->execute(array($userId));
    }
    
    /**
     * Get all first level categories.
     * @return boolean|Category[] Array of categories
     */
    public function getMainCategories() {
        if (!$stmt = self::$db->query("SELECT catId FROM categories WHERE sectionId={$this->id} AND parentId IS NULL")) return false;
        $ret = array();
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $ret[] = new Category($row->catId);
        }
        return $ret;
    }

    /**
     * Add new category to section
     * @param int $parentId ID of parent category, defaults to NULL=no parent
     * @return Category Created category
     */
    public function createCategory($parentId = NULL) {
        $stmt = self::$db->prepare("INSERT INTO categories SET sectionId={$this->id}, parentId=:parentId, caption='Ny kategori'");
        $stmt->bindValue("parentId", $parentId); // Use bindValue so even NULL can be passed.
        if ($stmt->execute()) {
            return new Category(self::$db->lastInsertId());
        } else {
            throw new \Exception("Failed to create category. " . $stmt->errorInfo()[2]);
        }
    }
    
    /**
     * Check whether the section contains any items visible to the given user
     * @param User $user May be empty dummy user for external access
     * @param int $minAccess Look for categories with at least this access level. May be set to ACCESS_CONFIRM to get admin visibility. 
     * @return boolean
     */
    public function showFor(User $user, int $minAccess=FFBoka::ACCESS_READASK) {
        // Section admins see everything.
        if ($this->getAccess($user)) return true;
        // Go down into categories
        foreach ($this->getMainCategories() as $cat) {
            if ($cat->showFor($user, $minAccess)) return true;
        }
        return false;
    }

    /**
     * Get the granted access level for given user in this section
     * @param \FFBoka\User $user
     * @return int Bitfield of access levels for user in this section
     */
    public function getAccess(User $user) {
        if (in_array($user->id, $this->getAdmins())) return FFBoka::ACCESS_SECTIONADMIN;
        else return FFBoka::ACCESS_NONE;
    }
    
    /**
     * Add a booking question template to this section
     * @return boolean|\FFBoka\Question
     */
    public function addQuestion() {
        if (!self::$db->exec("INSERT INTO questions SET sectionId={$this->id}")) return FALSE;
        return new Question(self::$db->lastInsertId());
    }
    
    /**
     * Get all question templates in section
     * @return \FFBoka\Question[]
     */
    public function questions() {
        $questions = array();
        $stmt = self::$db->query("SELECT questionId FROM questions WHERE sectionId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $questions[] = new Question($row->questionId);
        }
        return $questions;
    }
    
    /**
     * Return all unconfirmed items in the section
     * @return \FFBoka\Item[]
     */
    public function getUnconfirmedItems() {
        $stmt = self::$db->query("SELECT bookedItemId FROM bookings INNER JOIN booked_items USING (bookingId) WHERE status>" . \FFBoka\FFBoka::STATUS_PENDING . " AND status<" . \FFBoka\FFBoka::STATUS_CONFIRMED . " AND sectionId={$this->id}");
        $ret = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ret[] = new Item($row->bookedItemId, TRUE);
        }
        return $ret;
    }

    /**
     * Looks for categories with captions which are similar to the search string.
     * @param string $search The term to look for
     * @param User $user Used to determine the applicable access rights. Only categories where the user has access will be searched.
     * @param int[] $matches Will be populated with an array containing matching category captions as keys and the corresponding distance as value.
     * @return int Returns the least distance from a perfect match (0).
     */
    public function contains(string $search, User $user, &$matches) {
        $minDistance = 100000;
        foreach ($this->getMainCategories() as $cat) {
            if ($cat->showFor($user)) {
                $minDistance = min($minDistance, $cat->contains($search, $user, $matches));
            }
        }
        return $minDistance;
    }

    /**
     * Returns some statistics on bookings in this section
     * @param $year If given, will return all bookings during that year. Otherwise, returns all bookings for all years.
     * @param $month If given, will return all bookings placed that month. Otherwise, returns all bookings for the whole year(s)
     * @return array An array with the following members:
     *  int bookings - totoal number of bookings in this section
     *  int items - total number of items in those bookings
     *  int duration - total time as a sum of all items [hours]
     */
    public function usageOverview($year=null, $month=null) {
        // Sanitize parameters
        if (!is_null($year)) $year = (int)$year;
        if (!is_null($month)) $month = (int)$month;
        // Number of bookings
        $stmt = self::$db->query("SELECT COUNT(*) bookings FROM bookings WHERE sectionId={$this->id}" . (is_null($year) ? "" : " AND YEAR(timestamp)=$year") . (is_null($month) ? "" : " AND MONTH(timestamp)=$month"));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $ret = array( "bookings" => $row->bookings );
        // Number of booked items, and total length of booked items
        $stmt = self::$db->query("SELECT COUNT(*) items, SUM(UNIX_TIMESTAMP(end)-UNIX_TIMESTAMP(start))/3600 duration FROM bookings INNER JOIN booked_items USING (bookingId) WHERE sectionId={$this->id}" . (is_null($year) ? "" : " AND YEAR(timestamp)=$year") . (is_null($month) ? "" : " AND MONTH(timestamp)=$month"));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $ret["items"] = $row->items;
        $ret["duration"] = (int)$row->duration;
        return $ret;
    }

    public function usageDetails($year=null, $month=null) {
        $query = "SELECT catId, categories.caption category, items.itemId, items.caption item, COUNT(booked_items.bookedItemId) bookings, SUM(UNIX_TIMESTAMP(`end`)-UNIX_TIMESTAMP(`start`))/3600 duration FROM items INNER JOIN categories USING (catId) LEFT JOIN booked_items ON items.itemId=booked_items.itemId";
        if (!is_null($year)) $query .= " AND YEAR(`start`)=$year";
        if (!is_null($month)) $query .= " AND MONTH(`start`)=$month";
        $query .= " WHERE sectionId={$this->id} GROUP BY itemId";
        $stmt = self::$db->query($query);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
