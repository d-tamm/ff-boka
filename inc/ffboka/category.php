<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Category
 * Categories in the booking system, containing items.
 */
class Category extends FFBoka {
    private $id;
    private $sectionId;
    
    /**
     * Initialize category with ID and get some static properties.
     * @param int $id ID of requested category. If 0|FALSE|"" returns a dummy cateogory with id=0.
     */
    public function __construct($id){
        if ($id) { // Try to return an existing category from database
            $stmt = self::$db->prepare("SELECT catId, sectionId FROM categories WHERE catId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->catId;
                $this->sectionId = $row->sectionId;
            } else {
                throw new \Exception("Can't instatiate category with ID $id.");
            }
        } else { // Return an empty object without link to database
            $this->id = 0;
            return;
        }
    }
    
    /**
     * Setter function for category properties
     * @param string $name Property name
     * @param string|NULL $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "sectionId":
                // May only be set on dummy category
                if ($this->id) throw new \Exception("Cannot change section for existing category.");
                $this->sectionId = $value;
                return $value;
            case "parentId":
            case "caption":
            case "prebookMsg":
            case "postbookMsg":
            case "bufferAfterBooking":
            case "sendAlertTo":
            case "contactName":
            case "contactPhone":
            case "contactMail":
            case "contactUserId":
            case "accessExternal":
            case "accessMember":
            case "accessLocal":
            case "hideForExt":
                if (!$this->id) throw new \Exception("Cannot set property $name on dummy category.");
                // For contact data, only allow either member as contact person, or single data
                if ($name=="contactName" || $name=="contactPhone" || $name=="contactMail") {
                    self::$db->exec("UPDATE categories SET contactUserId=NULL WHERE catId={$this->id}");
                } elseif ($name=="contactUserId") {
                    self::$db->exec("UPDATE categories SET contactName='', contactPhone='', contactMail='' WHERE catId={$this->id}");
                }
                $stmt = self::$db->prepare("UPDATE categories SET $name=:value WHERE catId={$this->id}");
                $stmt->bindValue(":value", $value); // Use bindValue so contactUserId can be set to null
                if ($stmt->execute()) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Category property $name");
        }
        return false;
    }

    /**
     * Set the category image and thumbnail from uploaded image file
     * @param $_FILES[x] $imgFile Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of thumbnail
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @return boolean|string True on success, error message on failure
     */
    public function setImage($imgFile, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!$this->id) throw new \Exception("Cannot set image on dummy category.");
        $images = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        if ($images['error']) return $images['error'];
        $stmt = self::$db->prepare("UPDATE categories SET image=:image, thumb=:thumb WHERE catID={$this->id}");
        return $stmt->execute(array(
            ":image"=>$images['image'],
            ":thumb"=>$images['thumb'],
        ));
    }

    /**
     * Getter function for category properties
     * @param string $name Name of the property
     * @return string|array Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
                return $this->id;
            case "sectionId":
                return $this->sectionId;
            case "contactUserId": // can be inherited from parent
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT contactUserId, contactName, contactPhone, contactMail FROM categories WHERE catId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_null($row['contactUserId']) && $row['contactName']=="" && $row['contactPhone']=="" && $row['contactMail']=="" && $this->parentId) return $this->parent()->contactUserId;
                else return $row['contactUserId'];
            case "parentId":
            case "caption":
            case "prebookMsg":
            case "postbookMsg":
            case "bufferAfterBooking":
            case "sendAlertTo":
            case "contactName":
            case "contactPhone":
            case "contactMail":
            case "image":
            case "thumb":
            case "accessExternal":
            case "accessMember":
            case "accessLocal":
            case "hideForExt":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM categories WHERE catId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row[$name];
            case "itemCount":
                if (!$this->id) return 0;
                $stmt = self::$db->query("SELECT itemId FROM items WHERE catId={$this->id}");
                return $stmt->rowCount();
            default:
                throw new \Exception("Use of undefined Category property $name");
        }
    }

    /**
     * Get all pre-booking messages of this and any parent categories.
     * @return [ string ] Array of strings containing any pre-booking messages of this and parent categories
     */
    public function prebookMsgs() {
        if (is_null($this->parentId)) {
            if ($this->prebookMsg) return array($this->prebookMsg);
            else return array();
        } else {
            $ret = $this->parent()->prebookMsgs();
            if ($this->prebookMsg) $ret[] = $this->prebookMsg;
            return $ret;
        }
    }

    /**
     * Get all post-booking messages of this and any parent categories.
     * @return [ string ] Array of strings containing any post-booking messages of this and parent categories
     */
    public function postbookMsgs() {
        if (is_null($this->parentId)) {
            if ($this->postbookMsg) return array($this->postbookMsg);
            else return array();
        } else {
            $ret = $this->parent()->postbookMsgs();
            if ($this->postbookMsg) $ret[] = $this->postbookMsg;
            return $ret;
        }
    }
    
    /**
     * Get the section this category belongs to
     * @return \FFBoka\Section
     */
    public function section() {
        return new Section($this->sectionId);
    }

    /**
     * Get the contact user of the category
     * @return \FFBoka\User
     */
    public function contactUser() {
        return new User($this->contactUserId);            
    }

    /**
     * Get HTML formatted, safe string with contact information
     * @return string If member is set as contact user, the member's data is returned, otherwise the name, mail and phone set in category
     */
    public function contactData() {
        if (is_null($this->contactUserId)) {
            if ($this->contactName=="" && $this->contactPhone=="" && $this->contactMail=="" && !is_null($this->parentId)) return $this->parent()->contactData();
            $ret = array();
            if ($this->contactName) $ret[] = htmlspecialchars($this->contactName);
            if ($this->contactPhone) $ret[] = "☎ " . htmlspecialchars($this->contactPhone);
            if ($this->contactMail) $ret[] = "✉ " . htmlspecialchars($this->contactMail);
            return implode("<br>", $ret);
        } else {
            return $this->contactUser()->contactData();
        }
    }
    
    /**
     * Get the parent category if exists
     * @return \FFBoka\Category|NULL
     */
    public function parent() {
        if ($pId = $this->parentId) return new Category($pId);
        else return NULL;            
    }
    
    /**
     * Remove category
     * @throws \Exception
     * @return boolean TRUE on success, throws an exception otherwise.
     */
    public function delete() {
        if (self::$db->exec("DELETE FROM categories WHERE catId={$this->id}")) {
            return TRUE;
        } else {
            throw new \Exception("Failed to delete category.");
        }
    }
    
    /**
     * Get all chosen booking questions, including the ones specified for parent objects.
     * @param bool $inherited Mark questions on this cat level as inherited.
     * Questions from parent objects will always be marked as inherited.
     * @return Array of {inherited, required} where the key is the ID of the question.
     * If a question is set on several levels, the lowest level setting is returned.
     */
    public function getQuestions(bool $inherited=FALSE) {
        if ($this->parentId) $ret = $this->parent()->getQuestions(TRUE);
        else $ret = array();
        $stmt = self::$db->query("SELECT questionId, required FROM cat_questions WHERE catId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ret[$row->questionId] = (object) ["inherited"=>$inherited, "required"=>(bool)$row->required];
        }
        return $ret;
    }
    
    /**
     * Add to or update a question in the category
     * @param int $id ID of the question to add or update
     * @param bool $required Whether the used is required to answer the question
     * @return boolean False on failure
     */
    public function addQuestion(int $id, bool $required=FALSE) {
        $stmt = self::$db->prepare("INSERT INTO cat_questions SET questionId=:questionId, catId={$this->id}, required=:required ON DUPLICATE KEY UPDATE required=VALUES(required)");
        return $stmt->execute(array(
            ":questionId"=>$id,
            ":required"=>$required,
        ));
    }
    
    /**
     * Remove a question from the category
     * @param int $id
     * @return boolean
     */
    public function removeQuestion(int $id) {
        $stmt = self::$db->prepare("DELETE FROM cat_questions WHERE questionId=? AND catId={$this->id}");
        return $stmt->execute(array($id));
    }
    
    /**
     * Get the granted access level for given user, taking into account inherited access.
     * @param \FFBoka\User $user
     * @param bool $tieInSectionAdmin Whether to also include admin role set on section level
     * @return int Bitfield of granted access rights. For an empty (fake) category, returns ACCESS_CATADMIN.
     */
    public function getAccess(User $user, bool $tieInSectionAdmin=TRUE) {
        // On fake category, assume full cat access and don't go further
        if (!$this->id) return FFBoka::ACCESS_CATADMIN;
        $access = FFBoka::ACCESS_NONE;
        // Get group access for this category
        $access = $access | $this->accessExternal;
        if ($user->id) {
            $access = $access | $this->accessMember;
            if ($user->sectionId==$this->sectionId) $access = $access | $this->accessLocal;
            // Get access rules for individuals
            $stmt = self::$db->prepare("SELECT access FROM cat_admins WHERE catId={$this->id} AND userId=?");
            $stmt->execute(array($user->id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) $access = $access | $row->access;
        }
        if ($this->parentId) {
            // Tie in access rules from parent category
            $access = $access | $this->parent()->getAccess($user, $tieInSectionAdmin);
        } elseif ($tieInSectionAdmin) {
            // Tie in access rules from section
            $access = $access | $this->section()->getAccess($user, $tieInSectionAdmin);
        }
        return $access;
    }
    
    /**
     * Set personal access rights to category
     * @param int $userId
     * @param int $access Access constant, e.g. FFBoka::ACCESS_CATADMIN, FFBoka::ACCESS_CONFIRM. If set to FFBoka::ACCESS_NONE, access is revoked
     * @return boolean
     */ 
    public function setAccess($userId, $access) {
        $user = new User($userId); // This will add user to database if not already there.
        if (!$user->id) return FALSE;
        if ($access == FFBoka::ACCESS_NONE) {
            $stmt = self::$db->prepare("DELETE FROM cat_admins WHERE catId={$this->id} AND userId=?");
            return $stmt->execute(array($userId));
        }
        $stmt = self::$db->prepare("INSERT INTO cat_admins SET catId={$this->id}, userId=:userId, access=:access ON DUPLICATE KEY UPDATE access=VALUES(access)");
        return $stmt->execute(array(
            ":userId"=>$userId,
            ":access"=>$access,
        ));
    }
    
    /**
     * Retrieve all admins for category
     * @param int $access Return all entries with at least this access level.
     * @param bool $inherit Even return admins from superordinate categories
     * @return array [userId, name, access]
     */
    public function admins(int $access=FFBoka::ACCESS_READASK, bool $inherit=FALSE) {
        if (!$this->id) return array();
        $stmt = self::$db->prepare("SELECT userId, name, access FROM cat_admins INNER JOIN users USING (userId) WHERE catId={$this->id} AND access>=? ORDER BY users.name");
        $stmt->execute(array($access));
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($inherit && $this->parentId) {
            // Tie in admins from parent category
            foreach ($this->parent()->admins($access, TRUE) as $inh) {
                if (!in_array($inh['userId'], array_column($admins, "userId"))) $admins[] = $inh;
            }
        }
        return $admins;
    }
    
    /**
     * Check whether category or some subordinate item shall be shown to user
     * @param \FFBoka\User $user
     * @param int $minAccess Ignore access settings lower than this level. Set to ACCESS_CONFIRM to check for visibility for admins.
     * @return boolean
     */
    public function showFor(User $user, int $minAccess=FFBoka::ACCESS_READASK) {
        if (!$this->id) return TRUE;
        if ($this->getAccess($user) >= $minAccess) return TRUE;
        foreach ($this->children() as $child) {
            if ($child->showFor($user, $minAccess)) return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Get all direct sub-categories ordered by caption
     * @return \FFBoka\Category[]
     */
    public function children() {
        if (!$this->id) return array();
        $stmt = self::$db->query("SELECT catId FROM categories WHERE parentId={$this->id} ORDER BY caption");
        $children = array();
        while ($child = $stmt->fetch(PDO::FETCH_OBJ)) {
            $children[] = new Category($child->catId);
        }
        return $children;
    }

    /**
     * Check whether the category has a subordinate category with given ID
     * @param int $childId
     * @return boolean
     */
    function hasChild(int $childId) {
        foreach ($this->children() as $child) {
            if ($child->id==$childId) return true;
            if ($child->hasChild($childId)) return true;
        }
        return false;
    }
    
    /**
     * Get the path from section level
     * @return array[int id, string caption] Array with category IDs and strings representing superordinate elements
     */
    function getPath() {
        if ($this->parentId) $ret = $this->parent()->getPath();
        else $ret = array([ 'id'=>0, 'caption'=>"LA " . $this->section()->name ]);
        $ret[] = [ 'id'=>$this->id, 'caption'=>$this->caption ];
        return $ret;
    }

    /**
     * Get all items in the current category
     * @return array|\FFBoka\Item[]
     */
    public function items() {
        if (!$this->id) return array();
        $stmt = self::$db->query("SELECT itemId FROM items WHERE catId={$this->id} ORDER BY caption");
        $items = array();
        while ($item = $stmt->fetch(PDO::FETCH_OBJ)) {
            $items[] = new Item($item->itemId);
        }
        return $items;
    }
    
    /**
     * Add new resource to category
     * @throws \Exception
     * @return \FFBoka\Item
     */
    public function addItem() {
        if (self::$db->exec("INSERT INTO items SET catId={$this->id}, caption='Ny resurs'")) {
            return new Item(self::$db->lastInsertId());
        } else {
            throw new \Exception("Failed to create item.");
        }
    }
}
