<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;
use stdClass;

/**
 * Class Section
 * Represents the sections (lokalavdelningar) in FF.
 */
class Section extends FFBoka {
    private $id;
    private $name;
    
    /**
     * On section instantiation, get static properties.
     * @param int $id Section ID.
     * @throws \Exception if $id is invalid.
     */
    function __construct($id){
        $stmt = self::$db->prepare("SELECT sectionId, name FROM sections WHERE sectionId=?");
        $stmt->execute(array($id));
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $this->id = $row->sectionId;
            $this->name = $row->name;
        } else {
            logger(__METHOD__." Tried to instantiate section with invalid ID $id.", E_WARNING);
            throw new \Exception("Cannot instantiate section. Section with ID $id not found in database.");
        }
    }
    
    /**
     * Getter function for Section properties.
     * 
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
        case "registeredUsers": // number of registered users in this section
            $stmt = self::$db->query("SELECT COUNT(*) users FROM users WHERE sectionID={$this->id}");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->users;
        case "activeUsers": // number of users which have been active during the last 12 months
            $stmt = self::$db->query("SELECT COUNT(*) activeUsers FROM users WHERE sectionID={$this->id} AND ADDDATE(lastLogin, INTERVAL 12 MONTH)>NOW()");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->activeUsers;
        case "activeItems": // number of active items
            $stmt = self::$db->query("SELECT COUNT(*) items FROM items INNER JOIN categories USING (catId) WHERE sectionId={$this->id} AND active=1");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->items;
        case "inactiveItems": // number of inactive items
            $stmt = self::$db->query("SELECT COUNT(*) items FROM items INNER JOIN categories USING (catId) WHERE sectionId={$this->id} AND active=0");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->items;
        case "numberOfCategories":
            $stmt = self::$db->query("SELECT COUNT(*) cats FROM categories WHERE sectionId={$this->id}");
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row->cats;
        default:
            logger(__METHOD__." Use of undefined Section propterty $name.", E_ERROR);
            throw new \Exception("Use of undefined Section property $name");
        }
    }

        /**
     * Setter function for section properties
     * 
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
                    if ($stmt->execute()) return $value;
                    logger(__METHOD__." Failed to set Section property $name to $value. " . $stmt->errorInfo()[2], E_ERROR);
                }
                break;
            default:
                logger(__METHOD__." Use of undefined Section propterty $name.", E_ERROR);
                throw new \Exception("Trying to set undefined Section property $name");
        }
        return false;
    }

    /**
     * Gets all admin members IDs of the section.
     * 
     * @return int[] Admin member IDs
     */
    public function getAdmins() : array {
        $stmt = self::$db->query("SELECT userId FROM section_admins WHERE sectionId={$this->id}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Add admin access for user.
     * 
     * @param int $userId UserID of new admin
     * @return bool TRUE on success, FALSE on failure (e.g. if the user already is admin).
     */
    public function addAdmin(int $userId) : bool {
        $user = new User($userId); // This will add user to database if not already there.
        if (!$user->id) return FALSE;
        $stmt = self::$db->prepare("INSERT INTO section_admins SET sectionId={$this->id}, userId=?");
        if($stmt->execute(array($userId))) return true;
        logger(__METHOD__." Failed to add admin, probably because he/she already is admin. " . $stmt->errorInfo()[2], E_WARNING);
        return false;
    }
    
    /**
     * Revoke admin access.
     * 
     * @param int $userId UserID of user to remove
     * @return bool True on success
     */
    public function removeAdmin(int $userId) : bool {
        $stmt = self::$db->prepare("DELETE FROM section_admins WHERE sectionId={$this->id} AND userId=?");
        if($stmt->execute(array($userId))) return true;
        logger(__METHOD__." Failed to revoke admin access. " . $stmt->errorInfo()[2], E_ERROR);
        return false;
    }
    
    /**
     * Get all first level categories.
     * 
     * @return boolean|Category[] Array of categories
     */
    public function getMainCategories() : array {
        if (!$stmt = self::$db->query("SELECT catId FROM categories WHERE sectionId={$this->id} AND parentId IS NULL")) return false;
        $ret = array();
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $ret[] = new Category($row->catId);
        }
        return $ret;
    }

    /**
     * Add new category to section.
     * 
     * @param int $parentId ID of parent category, defaults to NULL=no parent
     * @return Category Created category
     */
    public function createCategory(?int $parentId = NULL) : Category {
        $stmt = self::$db->prepare("INSERT INTO categories SET sectionId={$this->id}, parentId=:parentId, caption='Ny kategori'");
        $stmt->bindValue("parentId", $parentId); // Use bindValue so even NULL can be passed.
        if ($stmt->execute()) {
            return new Category(self::$db->lastInsertId());
        } else {
            logger(__METHOD__." Failed to create category. " . $stmt->errorInfo()[2], E_ERROR);
            throw new \Exception("Failed to create category. " . $stmt->errorInfo()[2]);
        }
    }
    
    /**
     * Check whether the section contains any items visible to the given user.
     * 
     * @param User $user May be empty dummy user for external access
     * @param int $minAccess Look for categories with at least this access level. May be set to ACCESS_CONFIRM to get admin visibility. 
     * @return boolean
     */
    public function showFor(User $user, int $minAccess=FFBoka::ACCESS_READASK) : bool {
        // Section admins see everything.
        if ($this->getAccess($user)) return true;
        // Go down into categories
        foreach ($this->getMainCategories() as $cat) {
            if ($cat->showFor($user, $minAccess)) return true;
        }
        return false;
    }

    /**
     * Get the granted access level for given user in this section.
     * 
     * @param User $user
     * @return int Bitfield of access levels for user in this section
     */
    public function getAccess(User $user) : int {
        if (in_array($user->id, $this->getAdmins())) return FFBoka::ACCESS_SECTIONADMIN;
        else return FFBoka::ACCESS_NONE;
    }
    
    /**
     * Add a booking question template to this section.
     * 
     * @return boolean|\FFBoka\Question
     */
    public function addQuestion() {
        if (self::$db->exec("INSERT INTO questions SET sectionId={$this->id}")) return new Question(self::$db->lastInsertId());
        logger(__METHOD__." Failed to add Question. " . self::$db->errorInfo()[2], E_ERROR);
        return FALSE;
    }
    
    /**
     * Get all question templates in section
     * 
     * @return \FFBoka\Question[]
     */
    public function questions() : array {
        $questions = array();
        $stmt = self::$db->query("SELECT questionId FROM questions WHERE sectionId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $questions[] = new Question($row->questionId);
        }
        return $questions;
    }
    
    /**
     * Return all bookings with unconfirmed items or dirty flag in the section.
     * 
     * @param User $user Only return bookings which this user can confirm
     * @return int[] Array of bookingIds
     */
    public function getUnconfirmedBookings( User $user ) : array {
        $ret = array();
        $stmt = self::$db->query( "SELECT bookingId FROM bookings INNER JOIN booked_items USING (bookingId) WHERE ((status>" . FFBoka::STATUS_REJECTED . " AND status<" . FFBoka::STATUS_CONFIRMED . ") OR dirty) AND sectionId={$this->id}" );
        while ( $row = $stmt->fetch( \PDO::FETCH_OBJ ) ) {
            $item = new Item( $row->bookedItemId, TRUE );
            if ( $item->category()->getAccess( $user ) >= FFBoka::ACCESS_CONFIRM ) $ret[] = $row->bookingId;
        }
        return array_unique( $ret );
    }

    /**
     * Looks for categories with captions or item captions which are similar to the search string.
     * 
     * @param string $search The term to look for
     * @param User $user Used to determine the applicable access rights. Only categories where the user has access will be searched.
     * @param int $minScore The lowest score needed to include results (0-100).
     * @return int[] Array containing matching category and item captions as keys and the corresponding score (0-100) as value.
     */
    public function contains(string $search, User $user, int $minScore) : array {
        $matches = array();
        foreach ($this->getMainCategories() as $cat) {
            if ($cat->showFor($user)) {
                $cat->contains($search, $user, $minScore, $matches);
            }
        }
        return $matches;
    }

    /**
     * Returns usage totals for this section.
     * 
     * @param int $year If given, will return all bookings during that year. Otherwise, returns all bookings for all years.
     * @param int $month [1..12] If given, will return all bookings placed that month. Otherwise, returns all bookings for the whole year(s)
     * @return stdClass An object with the following members:
     *  bookings - totoal number of bookings in this section
     *  bookedItems - total number of items in those bookings
     *  duration - total time as a sum of all items [hours]
     */
    public function usageOverview(int $year=null, int $month=null) : stdClass {
        // Sanitize parameters
        if (!is_null($year)) $year = (int)$year;
        if (!is_null($month)) $month = (int)$month;
        $ret = new stdClass;
        // Number of bookings
        $stmt = self::$db->query("SELECT COUNT(*) bookings FROM bookings WHERE sectionId={$this->id}" . (is_null($year) ? "" : " AND YEAR(timestamp)=$year") . (is_null($month) ? "" : " AND MONTH(timestamp)=$month"));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $ret->bookings = $row->bookings;
        // Number of booked items, and total length of booked items
        $stmt = self::$db->query("SELECT COUNT(*) bookedItems, SUM(UNIX_TIMESTAMP(end)-UNIX_TIMESTAMP(start))/3600 duration FROM bookings INNER JOIN booked_items USING (bookingId) WHERE sectionId={$this->id}" . (is_null($year) ? "" : " AND YEAR(timestamp)=$year") . (is_null($month) ? "" : " AND MONTH(timestamp)=$month"));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $ret->bookedItems = $row->bookedItems;
        $ret->duration = (int)$row->duration;
        return $ret;
    }

    /**
     * Returns usage details for the section.
     * 
     * @param int $year If set, will return statistics for that year only. Otherwise, returns statistics for all years.
     * @param int $month If set, will return statistics for bookings in that month only; otherwise for the whole year.
     * @return array of objects, one object for each resource in this section, with the following members:
     *  * catId (category ID)
     *  * category (category caption)
     *  * itemId (item ID)
     *  * item (item caption)
     *  * bookings (number of times the item has been booked)
     *  * duration (total number of hours the item has been booked)
     */
    public function usageDetails(int $year=null, int $month=null) : array {
        $query = "SELECT catId, categories.caption category, items.itemId, items.caption item, COUNT(booked_items.bookedItemId) bookings, SUM(UNIX_TIMESTAMP(`end`)-UNIX_TIMESTAMP(`start`))/3600 duration FROM items INNER JOIN categories USING (catId) LEFT JOIN booked_items ON items.itemId=booked_items.itemId";
        if (!is_null($year)) $query .= " AND YEAR(`start`)=$year";
        if (!is_null($month)) $query .= " AND MONTH(`start`)=$month";
        $query .= " WHERE sectionId={$this->id} GROUP BY itemId";
        $stmt = self::$db->query($query);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
