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
        default:
            throw new \Exception("Use of undefined Section property $name");
        }
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
        $stmt = self::$db->exec("SELECT bookedItemId FROM bookings INNER JOIN booked_items USING (bookingId) WHERE status>" . \FFBoka\FFBoka::STATUS_PENDING . " AND status<" . \FFBoka\FFBoka::STATUS_CONFIRMED . " AND sectionId={$this->id}");
        $ret = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ret[] = new Item($row->bookedItemId, TRUE);
        }
        return $ret;
    }
}
