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
     * @throws \Exception if $id is given but not valid.
     */
    public function __construct($id){
        if ($id) { // Try to return an existing category from database
            $stmt = self::$db->prepare("SELECT catId, sectionId FROM categories WHERE catId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->catId;
                $this->sectionId = $row->sectionId;
            } else {
                logger(__METHOD__." Tried to get non-existing category with ID $id", E_ERROR);
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
            case "showContactWhenBooking":
            case "accessExternal":
            case "accessMember":
            case "accessLocal":
            case "hideForExt":
                if (!$this->id) {
                    logger(__METHOD__." Cannot set property $name on dummy category.", E_ERROR);
                    return false;
                }
                // For contact data, only allow either member as contact person, or single data
                if ($name=="contactName" || $name=="contactPhone" || $name=="contactMail") {
                    self::$db->exec("UPDATE categories SET contactUserId=NULL WHERE catId={$this->id}");
                } elseif ($name=="contactUserId") {
                    self::$db->exec("UPDATE categories SET contactName='', contactPhone='', contactMail='' WHERE catId={$this->id}");
                }
                $stmt = self::$db->prepare("UPDATE categories SET $name=:value WHERE catId={$this->id}");
                $stmt->bindValue(":value", $value); // Use bindValue so contactUserId can be set to null
                if ($stmt->execute()) return $value;
                logger(__METHOD__." Failed to set Category property $name. " . $stmt->errorInfo()[2], E_ERROR);
                break;
            default:
                logger(__METHOD__." Use of undefined Category property $name.", E_ERROR);
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
        if (!$this->id) {
            logger(__METHOD__." Trying to set image on dummy category.", E_ERROR);
            return "Cannot set image on dummy category.";
        }
        if (!file_exists(__DIR__."/../../img/cat")) {
            if (!mkdir(__DIR__."/../../img/cat", 0777, true)) {
                logger(__METHOD__." Failed to create directory to save category image.", E_ERROR);
                return "Kan inte spara bilden. Kan inte skapa mappen för kategoribilder (./img/cat). Kontakta administratören.";
            }
        }
        $images = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        if ($images['error']) return $images['error'];
        // Save thumb to database
        $stmt = self::$db->prepare("UPDATE categories SET thumb=? WHERE catID={$this->id}");
        if (!$stmt->execute(array($images['thumb']))) {
            logger(__METHOD__." Failed to save thumbnail to database. " . $stmt->errorInfo()[2], E_ERROR);
            return "Kan inte spara miniaturbilden i databasen.";
        }
        // Save full size image to file system
        if (file_put_contents(__DIR__ . "/../../img/cat/{$this->id}", $images['image'])===FALSE) return "Kan inte spara originalbilden på ./img/cat. Är mappen skrivskyddad? Kontakta administratören.";
        return TRUE;
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
            case "parentId":
            case "caption":
            case "prebookMsg":
            case "postbookMsg":
            case "bufferAfterBooking":
            case "sendAlertTo":
            case "contactUserId": // don't care about inherited IDs
            case "contactName":
            case "contactPhone":
            case "contactMail":
            case "showContactWhenBooking":
            case "thumb":
            case "accessExternal":
            case "accessMember":
            case "accessLocal":
            case "hideForExt":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM categories WHERE catId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "contactType":
                if (!is_null($this->contactUserId)) return "user";
                if ($this->contactName!="" || $this->contactPhone!="" || $this->contactMail!="") return "manual";
                if (is_null($this->parentId) || $this->parent()->contactType=="unset") return "unset";
                return "inherited";
            case "itemCount":
                if (!$this->id) return 0;
                $stmt = self::$db->query("SELECT itemId FROM items WHERE catId={$this->id}");
                return $stmt->rowCount();
            default:
                logger(__METHOD__." Use of undefined Category property $name.", E_ERROR);
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
     * @return string If member is set as contact user, the member's data is returned, otherwise the name,
     *      mail and phone set in category. If nothing is set, the parent's contact data is returned.
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
        // Full size images will be removed from file system by cron
        if (self::$db->exec("DELETE FROM categories WHERE catId={$this->id}")) {
            return TRUE;
        } else {
            logger(__METHOD__." Failed to delete category {$this->id}.", E_ERROR);
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
        $stmt->bindValue("questionId", $id, \PDO::PARAM_INT);
        $stmt->bindValue(":required", $required, \PDO::PARAM_BOOL);
        if ($stmt->execute()) return true;
        logger(__METHOD__." Failed to add question $id to category {$this->id}. " . $stmt->errorInfo()[2], E_ERROR);
        return false;
    }
    
    /**
     * Remove a question from the category
     * @param int $id
     * @return boolean
     */
    public function removeQuestion(int $id) {
        $stmt = self::$db->prepare("DELETE FROM cat_questions WHERE questionId=? AND catId={$this->id}");
        if ($stmt->execute(array($id))) return true;
        logger(__METHOD__." Failed to remove question $id from category {$this->id}. " . $stmt->errorInfo()[2], E_ERROR);
        return false;
    }
    
    
    /**
     * Add an attachment file to the category. The caption will be set to the file name.
     * @param $_FILES[x] $file A member of the $_FILES array
     * @param array $allowedFileTypes Associative array of $extension=>$icon_filename pairs.
     * @param int $maxSize The maximum accepted file size in bytes. Defaults to 0 (no limit)
     * @throws \Exception if trying to upload files with unallowed file types, too big files, or if this is a dummy category.
     * @return int ID of the added file
     */
    public function addFile($file, $allowedFileTypes, int $maxSize=0) {
        if (!$this->id) {
            logger(__METHOD__." Cannot add file to dummy category.", E_ERROR);
            throw new \Exception("Internt fel.");
        }
        if ($maxSize && filesize($file['tmp_name'])>$maxSize) { 
            throw new \Exception("Filen är för stor. Största tillåtna storleken är " . self::formatBytes($maxSize) . ".");
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            logger(__METHOD__." Trying to set non-uploaded file as attachment.", E_WARNING);
            throw new \Exception("This is not an uploaded file.");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowedFileTypes)) {
            throw new \Exception("Du kan inte ladda upp filer av typen $ext. Bara följande filtyper tillåts: " . implode(", ", array_keys($allowedFileTypes)));
        }
        $md5 = md5_file($file['tmp_name']);
        // Add post to database
        $stmt = self::$db->prepare("INSERT INTO cat_files SET catId={$this->id}, filename=:filename, caption=:caption, md5='$md5'");
        if (!$stmt->execute(array( ":filename"=>$file['name'], ":caption"=>$file['name'] ))) {
            unlink($file['tmp_name']);
            throw new \Exception("Filen kunde inte sparas, eftersom samma fil redan har laddats upp till denna kategori.");
        }
        $newId = self::$db->lastInsertId();
        // Move file
        if (!is_dir(__DIR__."/../../uploads")) {
            if (!mkdir(__DIR__."/../../uploads")) {
                logger(__METHOD__." Failed to create directory for uploaded files.", E_ERROR);
                throw new \Exception("Kan inte spara filen. Kontakta systemadministratören.");
            }
        }
        if (!move_uploaded_file($file['tmp_name'], __DIR__."/../../uploads/$newId")) {
            logger(__METHOD__." Failed to save uploaded file.", E_ERROR);
            throw new \Exception("Kunde inte spara filen.");
        }
        return $newId;
    }
    
    /**
     * Set property for an attached file
     * @param int $fileId
     * @param string $name The name of the property to set. Must be one of caption|filename|displayLink|attachFile
     * @param mixed $value The value of the property to set
     * @throws \Exception if trying to set an invalid property.
     * @return bool True on success, or FALSE on failure
     */
    public function setFileProp(int $fileId, string $name, $value) {
        switch ($name) {
        case "caption":
        case "filename":
        case "displayLink":
        case "attachFile":
            $stmt = self::$db->prepare("UPDATE cat_files SET $name=:$name WHERE fileId=:fileId AND catId={$this->id}");
            if ($name=="displayLink" || $name=="attachFile") $stmt->bindValue(":$name", $value, \PDO::PARAM_BOOL);
            else $stmt->bindValue(":$name", $value, \PDO::PARAM_STR);
            $stmt->bindValue(":fileId", $fileId, \PDO::PARAM_INT);
            if ($stmt->execute()) return true;
            logger(__METHOD__." Failed to set File property $name on file $fileId. " . $stmt->errorInfo()[2], E_ERROR);
            return false;
            break;
        default: 
            logger(__METHOD__." Trying to set invalid property $name on file attachment {$this->id}", E_ERROR);
            throw new \Exception("Cannot set property $name on file attachment.");
        }
    }
    
    /**
     * Delete an uploaded category attachment file 
     * @param int $fileId ID of the file to be deleted
     * @return bool True on success, False on failure
     */
    function removeFile(int $fileId) {
        if (unlink(__DIR__."/../../uploads/$fileId")) {
            if (self::$db->exec("DELETE FROM cat_files WHERE catId={$this->id} AND fileId=$fileId")) return true;
            logger(__METHOD__." Failed to delete attachment record $fileId from DB. " . self::$db->errorInfo()[2], E_WARNING);
        }
        logger(__METHOD__." Failed to unlink attachment file " . realpath(__DIR__."/../../uploads/$fileId"), E_WARNING);
        return FALSE;
    }
    
    /**
     * Get all attachments for the category
     * @param bool $includeParents Whether to also return attachments of superordinate categories
     * @return array of objects with the following members: fileId, catId, filename, md5, 
     *      displayLink, attachFile. The array keys are the md5 checksums, so no double files
     *      should be returned.
     */
    public function files(bool $includeParents=FALSE) {
        if ($includeParents) $ret = $this->parent()->files(TRUE);
        else $ret = array();
        $stmt = self::$db->query("SELECT * FROM cat_files WHERE catId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ret[$row->md5] = $row;
        }
        return $ret;
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
        // Get group permissions for this category
        $access = $access | $this->accessExternal;
        if ($user->id) {
            $access = $access | $this->accessMember;
            if ($user->sectionId==$this->sectionId) $access = $access | $this->accessLocal;
            // Add permissions for assignment groups
            foreach ($this->groupPerms(TRUE) as $groupPerm) {
                if (in_array($groupPerm['assName'], $_SESSION['assignments'][$this->sectionId])) {
                    $access = $access | $groupPerm['access'];
                }
            }
            // Add individual permissions
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
     * Set personal and assignment based access rights to category
     * @param int|string $id Either a numeric user id, or the name of an assignment
     * @param int $access Access constant, e.g. FFBoka::ACCESS_CATADMIN, FFBoka::ACCESS_CONFIRM.
     *  If set to FFBoka::ACCESS_NONE, access is revoked
     * @return boolean
     */ 
    public function setAccess($id, $access) {
        if (is_numeric($id)) {
            $user = new User($id); // This will add user to database if not already there.
            if (!$user->id) return FALSE;
        }
        if ($access == FFBoka::ACCESS_NONE) {
            if (is_numeric($id)) {
                // Revoke permission for single user
                $stmt = self::$db->prepare("DELETE FROM cat_admins WHERE catId={$this->id} AND userId=?");
            } else {
                // Revoke permission for user group with assignment
                $stmt = self::$db->prepare("DELETE FROM cat_perms WHERE catId={$this->id} AND assName=?");
            }
            if ($stmt->execute([ $id ])) return true;
            logger(__METHOD__." Failed to revoke access. " . $stmt->errorInfo()[2], E_ERROR);
            return false;
        }
        if (is_numeric($id)) {
            // Set permission for single user
            $stmt = self::$db->prepare("INSERT INTO cat_admins SET catId={$this->id}, userId=:id, access=:access ON DUPLICATE KEY UPDATE access=VALUES(access)");
        } else {
            // Set permission for user group with assignment
            $stmt = self::$db->prepare("INSERT INTO cat_perms SET catId={$this->id}, assName=:id, access=:access ON DUPLICATE KEY UPDATE access=VALUES(access)");
        }
        if ($stmt->execute([ ":id"=>$id, ":access"=>$access ])) return true;
        logger(__METHOD__." Failed to set access. " . $stmt->errorInfo()[2], E_ERROR);
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
     * Retrieve all group permissions for category
     * @param bool $inherit Even return permissions inherited from parents
     * @return array['assName', 'access']
     */
    public function groupPerms(bool $inherit=FALSE) {
        if (!$this->id) return array();
        $stmt = self::$db->query("SELECT assName, access FROM cat_perms WHERE catId={$this->id} ORDER BY assName");
        $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($inherit && $this->parentId) {
            // Tie in permissions from parent category
            foreach ($this->parent()->groupPerms(TRUE) as $inh) {
                if (!in_array($inh['assName'], array_column($perms, "assName"))) $perms[] = $inh;
            }
        }
        return $perms;
    }
    
    /**
     * Check whether category or some subordinate item shall be shown to user
     * @param \FFBoka\User $user
     * @param int $minAccess Ignore access settings lower than this level.
     * Set to ACCESS_CONFIRM to check for visibility for admins.
     * @return boolean Returns TRUE for fake categories, and if the user has access to this or any subordinate category.
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
     * @return array|\FFBoka\Item[] Items sorted by caption
     */
    public function items() {
        if (!$this->id) return array();
        $stmt = self::$db->query("SELECT itemId FROM items WHERE catId={$this->id}");
        $items = array();
        while ($item = $stmt->fetch(PDO::FETCH_OBJ)) {
            $items[] = new Item($item->itemId);
        }
        // Sort
        usort($items, function($a, $b) { return strnatcasecmp($a->caption, $b->caption); });
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
            logger(__METHOD__." Failed to add item to category {$this->id}. " . self::$db->errorInfo()[2], E_ERROR);
            throw new \Exception("Failed to create item.");
        }
    }

    /**
     * Look for search string in category caption and child categories accessible to the given user.
     * @param string $search The search string
     * @param User $user Used to determine access rights. Only categories where the user has read access will be searched.
     * @param int[] $matches Will be populated with an array containing matching category captions as keys and the corresponding distance as value.
     * @return int The distance for a similar match. 0=perfect match.
     */
    public function contains(string $search, User $user, &$matches) {
        $minDistance = 100000;
        foreach ($this->children() as $child) {
            if ($child->showFor($user)) {
                $minDistance = min($minDistance, $child->contains($search, $user, $matches));
            }
        }
        if ($this->getAccess($user) >= self::ACCESS_READASK) {
            $pos = stripos($this->caption, $search);
            if ($pos === false) { // No direct match. Calculate Levenshtein distance with low delete cost.
                $dist = levenshtein($this->caption, $search, 200, 200, 20);
            } elseif ($pos === 0) { // Best match: caption starts with $search
                $dist = 0;
            } else { // Medium match: caption contains $search
                $dist = 50;
            }
            $matches[$this->caption] = $dist;
            $minDistance = min($minDistance, $dist);
        }
        return $minDistance;
    }
}
