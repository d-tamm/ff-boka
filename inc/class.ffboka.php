<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class FFBoka
 * Base factory class for getting valid objects to the booking system
 */
class FFBoka {
    /** Access constants, valid for an object and its children */
    /** No access */
    const ACCESS_NONE = 0;
    /** User may see items without free/busy information, and send inquiries */
    const ACCESS_READASK = 1;
    /** User may see free/busy information and make pre-bookings */
    const ACCESS_PREBOOK = 2;
    /** User may place own binding bookings */
    const ACCESS_BOOK = 4;
    /** User may confirm inquiries/prebookings and change other's bookings */
    const ACCESS_CONFIRM = 8;
    /** Full access to category */
    const ACCESS_CATADMIN = 16;
    /** Full access to section */
    const ACCESS_SECTIONADMIN = 32;
    
    /** URL to Friluftsfrämjandet's API */
    protected static $apiUrl;
    /** GUID in API indicating sections */
    const TYPE_SECTION = 478880001;
    
    /** Database connection */
    protected static $db;

    /** string[] Assignments from API giving section admin access. */
    protected static $sectionAdmins;
    
    /**
     * Initialize framework with API address and database connection.
     * These will also be used in the inherited classes 
     * @param PDO::Database $db
     */
    function __construct($apiUrl, $db, $sectionAdmins) {
        self::$apiUrl = $apiUrl;
    	self::$db = $db;
    	self::$sectionAdmins = $sectionAdmins;
    }
    

    /**
     * Get a list of all sections in FF
     * @param int $showFirst If set, this section will be returned as the first element
     * @return Section[] Array of sections in alphabetical order
     */
    public function getAllSections($showFirst=0) {
        $stmt = self::$db->prepare("SELECT sectionId FROM sections ORDER BY sectionId!=?, name");
        $stmt->execute(array($showFirst));
        $sections = array();
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $sections[] = new Section($row->sectionId);
        }
        return $sections;
    }
    

    /**
     * Authenticate the given user data by querying the API
     * @param string $userId
     * @param string $password
     * @return int/bool Member ID on success 
     */
    public function authenticateUser($userId, $password) {
        // TODO: Currently, there is no such function in the API. Hope this comes in early 2020.
        return $userId;
        return false;
    }

	/**
	 * Get a list of all users without creating User objects.
	 * This avoids sending many queries to the API
	 * @return array[[id, name], ...]
	 */
	public function getAllUsers() {
		$stmt = self::$db->query("SELECT userId, name FROM users ORDER BY name");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
    /**
     * Get all users complying to a search term. Name and member ID will be searched.
     * @param string|int $q Search term
	 * @return array[[id, name], ...] Returns an array with IDs and names rather than User objects, avoiding many API requests
     */
    public function findUser($q) {
        $stmt = self::$db->prepare("SELECT userId, name FROM users WHERE userId LIKE ? OR name LIKE ?");
        $stmt->execute(array("%$q%", "%$q%"));
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gets an uploaded image file, resizes it, makes a thumbnail, and returns both versions as strings.
     * @param $_FILES[x] $file Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of the thumbnail.
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @return [string $image, string $thumb] String representations of a full-size and a thumbnail version of the image.
     */
    protected function imgFileToString($file, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!is_uploaded_file($file['tmp_name'])) {
            return array(NULL,NULL);
        }
        // reject files that are too big
        if ($maxFileSize) {
            if (filesize($file['tmp_name'])>$maxFileSize) return array(NULL,NULL);
        }
        // Get the picture and its size
        $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
        $size = getimagesize($file['tmp_name']);
        $ratio = $size[0]/$size[1];
        if ($maxSize && ($size[0]>$maxSize || $size[1]>$maxSize)) { // Rescale
            if ($ratio > 1) { // portrait
                $tmp = imagecreatetruecolor($maxSize, $maxSize/$ratio);
                imagecopyresampled($tmp, $src, 0, 0, 0, 0, $maxSize, $maxSize/$ratio, $size[0], $size[1]);
            } else { // landscape
                $tmp = imagecreatetruecolor($maxSize*$ratio, $maxSize);
                imagecopyresampled($tmp, $src, 0, 0, 0, 0, $maxSize*$ratio, $maxSize, $size[0], $size[1]);
            }
        } else {
            $tmp = $src;
        }
        // Get rescaled jpeg picture as string
        ob_start();
        imagejpeg($tmp);
        $image = ob_get_clean();
        // Make a square thumbnail
        $tmp = imagecreatetruecolor($thumbSize, $thumbSize);
        $bg = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
        imagefill($tmp, 0, 0, $bg);
        if ($ratio>1) imagecopyresampled($tmp, $src, $thumbSize/2*(1-1*$ratio), 0, 0, 0, $thumbSize*$ratio, $thumbSize, $size[0], $size[1]);
        else imagecopyresampled($tmp, $src, 0, $thumbSize/2*(1-1/$ratio), 0, 0, $thumbSize, $thumbSize/$ratio, $size[0], $size[1]);
        // Get thumbnail as string
        ob_start();
        imagepng($tmp);
        $thumb = ob_get_clean();
        return array("image"=>$image, "thumb"=>$thumb);
    }
    
    /**
     * Fetch a new user token from API.
     * TODO: This method is deprecated since it fetches a token from the API giving elevated access
     * to user data at FF. Here, we just want to verify the password, don't need the token.
     * @deprecated
     * @param int $userId
     * @param string $password
     * @return string[]|mixed On success (correct credentials): [ access_token, expires_in, userName ], where userName is the memberId. On failure (e.g. wrong credentials): [ error="invalid_grant", error_description ]
     */
    function getApiToken($userId, $password) {
        global $cfg;
        $data = array(
            'username' => $userId,
            'password' => $password,
            'grant_type' => 'password'
        );
        $options = array('http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ));
        $context  = stream_context_create($options);
        $result = file_get_contents($cfg['apiUrl']."/token", false, $context);
        // Sample response: {"access_token":"xxxx","token_type":"bearer","expires_in":28799,"userName":"864015","contactId":"xxxx","impersonateAs":"xxxx","authorizationLevel":"1",".issued":"Thu, 10 Oct 2019 12:29:05 GMT",".expires":"Thu, 10 Oct 2019 20:29:05 GMT"}
        // If wrong credentials: {"error":"invalid_grant","error_description":"The user name or password is incorrect."}
        if ($result === FALSE) {
            return array("error"=>"Generic error", "error_description"=>"Failed to verify credentials. Please try again later.");
        } else {
            return json_decode($result, true);
        }
    }
}




class User extends FFBoka {
    private $id;
    private $sectionId;
    private $assignments;

    /**
     * On user instatiation, get some static properties.
     * If user does not yet exist in database, create a record.
     * @param int $id User ID. An $id=(empty|0) will result in an invalid user with unset id property.
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
        $data = json_decode(file_get_contents(self::$apiUrl . "/api/feed/Pan_Extbokning_GetAssingmentByMemberNoOrSocSecNo?MNoSocnr={$this->id}"));
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
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
            case "id":
                return $this->id;
            case "sectionId":
                return $this->sectionId;
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
		return self::$db->exec("UPDATE users SET lastLogin=NULL WHERE userId={$this->id}");
	}
}




/**
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
        $stmt = self::$db->prepare("INSERT INTO categories SET sectionId={$this->id}, parentId=:parentId");
        $stmt->bindValue("parentId", $parentId); // Use bindValue so even NULL can be passed.
        if ($stmt->execute()) {
            return new Category(self::$db->lastInsertId());
        } else {
            throw new \Exception("Failed to create category.");
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
        // Check if user has an admin assignment stated in config
        if ($user->assignments[$this->id]) {
            if (array_intersect($user->assignments[$this->id], self::$sectionAdmins)) {
                return FFBoka::ACCESS_SECTIONADMIN;
            }
        }
        // Check for admin assignments by member ID
        if (in_array($user->id, $this->getAdmins())) return FFBoka::ACCESS_SECTIONADMIN;
    }
}




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
     * @param string $value Property value
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
            case "bookingMsg":
            case "contactUserId":
            case "accessExternal":
            case "accessMember":
            case "accessLocal":
            case "hideForExt":
                if (!$this->id) throw new \Exception("Cannot set property $name on dummy category.");
                $stmt = self::$db->prepare("UPDATE categories SET $name=? WHERE catId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
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
     * @return boolean Success
     */
    public function setImage($imgFile, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!$this->id) throw new \Exception("Cannot set image on dummy category.");
        $images = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        $stmt = self::$db->prepare("UPDATE categories SET image=:image, thumb=:thumb WHERE catID={$this->id}");
        return $stmt->execute(array(
            ":image"=>$images['image'],
            ":thumb"=>$images['thumb'],
        ));
    }

    /**
     * Getter function for category properties
     * @param string $name Name of the property
     * @return string Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
                return $this->id;
            case "sectionId":
                return $this->sectionId;
            case "parentId":
            case "caption":
            case "bookingMsg":
            case "contactUserId":
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
     * Get the parent category if exists
     * @return \FFBoka\Category|NULL
     */
    public function parent() {
        if ($pId = $this->parentId) return new Category($pId);
        else return NULL;            
    }
    
    /**
     * Remove category
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
     * Get the granted access level for given user, taking into account inherited access.
     * @param \FFBoka\User $user
     * @return int Bitfield of granted access rights. For an empty (fake) category, returns ACCESS_CATADMIN.
     */
    public function getAccess(User $user) {
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
            $access = $access | $this->parent()->getAccess($user);
        } else {
            // Tie in access rules from section
            $access = $access | $this->section()->getAccess($user);
        }
        return $access;
    }
    
    /**
     * Retrieve all admins for category
     * @return array [userId, name, access]
     */
    public function admins() {
        if (!$this->id) return array();
        $stmt = self::$db->query("SELECT userId, name, access FROM cat_admins INNER JOIN users USING (userId) WHERE catId={$this->id} ORDER BY users.name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

	public function items() {
		if (!$this->id) return array();
		$stmt = self::$db->query("SELECT itemId FROM items WHERE catId={$this->id} ORDER BY caption");
		$items = array();
		while ($item = $stmt->fetch(PDO::FETCH_OBJ)) {
			$items[] = new Item($item->itemId);
		}
		return $items;
	}
}




/**
 * Class item
 * Bookable items in the booking system.
 */
class Item extends FFBoka {
    // TODO: constructor, getter, setter
    private $id;
    private $catId;
    
    /**
     * Initialize item with ID and get some static properties.
     * @param int $id ID of requested item. If 0|FALSE|"" returns a dummy item with id=0.
     */
    public function __construct($id){
        if ($id) { // Try to return an existing item from database
            $stmt = self::$db->prepare("SELECT itemId, catId FROM items WHERE itemId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->itemId;
                $this->catId = $row->catId;
            } else {
                throw new \Exception("Can't instatiate item with ID $id.");
            }
        } else { // Return an empty object without link to database
            $this->id = 0;
            return;
        }
    }	
	
    /**
     * Setter function for item properties
     * @param string $name
     * @param string|int|Image $value
     * @throws \Exception
     * @return string|boolean
     */
    public function __set($name, $value) {
        switch ($name) {
            case "catId":
                // May only be set on dummy Item
                if ($this->id) throw new \Exception("Cannot change category for existing item.");
                $this->catId = $value;
                return $value;
            case "caption":
            case "description":
            case "active":
            case "imageId":
                if (!$this->id) throw new \Exception("Cannot set property $name on dummy item.");
                $stmt = self::$db->prepare("UPDATE items SET $name=? WHERE itemId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Item property $name");
        }
        return false;
    }

    /**
     * Set the representative image of the item
     * @param Image $img
     */
    public function setFeaturedImage(Image $img) {
        if ($img->itemId != $this->id) throw \Exception("Cannot set an image to featured image which does not belong to the item.");
        $this->imageId = $img->id;
    }
    
    /**
     * Getter function for item properties
     * @param string $name Name of the property
     * @return string Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "catId":
                return $this->$name;
                
            case "caption":
            case "description":
            case "active":
            case "imageId":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM items WHERE itemId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
                
            default:
                throw new \Exception("Use of undefined Item property $name");
        }
    }
    
    /**
     * Get the representative image of the item 
     * @return \FFBoka\Image
     */
    public function getFeaturedImage() {
        return new Image($this->imageId);
    }
    
    /**
     * Get a linear representation of free-busy information
     * TODO: adapt function to OOP
     * @param int $itemId
     * @param int $start
     * @param string $range
     * @return string
     */
    function bookingBar($itemId, $start, $range="week") {
        $stmt = $db->query("SELECT UNIX_TIMESTAMP(start) start, UNIX_TIMESTAMP(end) end FROM `booked_items` INNER JOIN subbookings USING (subbookingId) WHERE itemId=$itemId"); // TODO: limit query to only relevant rows.
        $ret = "<div style='width:100%; height:20px; position:relative; background-color:#D0BA8A; font-weight:normal; font-size:small;'>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ret .= "<div style='position:absolute; top:0px; height:100%; left:" . (($row['start']-$start)/6048) . "%; width:" . (($row['end']-$row['start'])/6048) . "%; background-color:#E84F1C;'></div>\n";
        }
        for ($day=1; $day<7; $day++) {
            $ret .= "<div style='position:absolute; top:0px; height:100%; left:" . (100/7*$day) . "%; border-left:1px solid #54544A;'></div>\n";
        }
        $ret .= "</div>";
        return $ret;
    }
}



/**
 * Class for handling item pictures
 * @author Daniel Tamm
 *
 */
class Image extends FFBoka {
    private $id;
    private $itemId;
    
    /**
     * Initialize the image with id and itemId
     * @param int $id
     * @throws \Exception
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing image from database
            $stmt = self::$db->prepare("SELECT imageId, itemId FROM item_images WHERE imageId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->imageId;
                $this->itemId = $row->itemId;
            } else {
                throw new \Exception("Can't instatiate image with ID $id.");
            }
        } else { // Return an empty object without link to database
            $this->id = 0;
            return;
        }
    }
    
    /**
     * Set the image and thumbnail from uploaded image file
     * @param $_FILES[x] $imgFile Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of thumbnail
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @throws \Exception
     * @return boolean Success
     */
    public function setImage($imgFile, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!$this->id) throw new \Exception("Cannot set image on dummy category.");
        list($image, $thumb) = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        $stmt = self::$db->prepare("UPDATE item_images SET image=:image, thumb=:thumb WHERE imageID={$this->id}");
        return $stmt->execute(array(
            ":image"=>$image,
            ":thumb"=>$thumb,
        ));       
    }
    
    /**
     * Getter function for image properties
     * @param string $name Name of the property
     * @return string Value of the property.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "itemId":
                return $this->$name;
            case "caption":
            case "image":
            case "thumb":
                if (!$this->id) return "";
                $stmt = self::$db->query("SELECT $name FROM item_images WHERE imageId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            default:
                throw new \Exception("Use of undefined Category property $name");
        }
    }
    
}