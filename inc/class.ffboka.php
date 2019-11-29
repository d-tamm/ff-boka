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

    /** Booking status constants */
    /** Booking is being added by user, but has not yet been sent */
    const STATUS_PENDING=0;
    /** Booking has been placed, but needs to be confirmed */
    const STATUS_PREBOOKED=1;
    /** Booking has been placed and is confirmed */
    const STATUS_CONFIRMED=2;
    
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
	 * @return array[[int id, string name], ...]
	 */
	public function getAllUsers() {
		$stmt = self::$db->query("SELECT userId, name FROM users ORDER BY name");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
    /**
     * Get all users complying to a search term. Name and member ID will be searched.
     * @param string|int $q Search term
	 * @return array[[int id, string name], ...] Returns an array with IDs and names rather than User objects, avoiding many API requests
     */
    public function findUser($q) {
        $stmt = self::$db->prepare("SELECT userId, name FROM users WHERE userId LIKE ? OR name LIKE ?");
        $stmt->execute(array("%$q%", "%$q%"));
        $ret = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ret[] = array("userId"=>$row['userId'], "name"=>htmlspecialchars($row['name']));
        }
        return $ret;
    }
    
    /**
     * Takes an uploaded image file, resizes it, makes a thumbnail, and returns both versions as strings.
     * @param $_FILES[x] $file Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of the thumbnail.
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @throws \Exception If file is not an uploaded file.
     * @return ['image'=>string, 'thumb'=>string, 'error'=>string] String representations
     *  of a full-size and a thumbnail version of the image.
     */
    protected function imgFileToString($file, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \Exception("Error: Image file must be an uploaded file.");
        }
        // reject files that are too big
        if ($maxFileSize) {
            if (filesize($file['tmp_name'])>$maxFileSize) return array("error"=>"Filen är för stor. Maximal accepterad storlek är " . round($maxFileSize/1024/1024, 0) . " kB.");
        }
        // Get the picture and its size
        $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$src) return array("error"=>"Filformatet stöds inte. Försök med en jpg- eller png-bild.");
        $size = getimagesize($file['tmp_name']);
        $ratio = $size[0]/$size[1];
        if ($maxSize && ($size[0]>$maxSize || $size[1]>$maxSize)) { // Resize
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
        $stmt = self::$db->prepare("INSERT INTO categories SET sectionId={$this->id}, parentId=:parentId, caption='Ny kategori'");
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
        else return 0;
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
            case "bufferAfterBooking":
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
            case "bookingMsg":
            case "bufferAfterBooking":
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
     * Get all booking messages of this and any parent categories.
     * @return [ string ] Array of strings containing any booking messages of this and parent categories
     */
    public function bookingMsgs() {
        if (is_null($this->parentId)) {
            if ($this->bookingMsg) return array($this->bookingMsg);
            else return array();
        } else {
            $ret = $this->parent()->bookingMsgs();
            if ($this->bookingMsg) $ret[] = $this->bookingMsg;
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
    
    /**
     * Get the path from section level
     * @return [string] Array of strings representing superordinate elements
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




/**
 * Class item
 * Bookable items in the booking system.
 */
class Item extends FFBoka {
    private $id;
    private $catId;
    
    /**
     * Initialize item with ID and get some static properties.
     * @param int $id ID of requested item. If 0|FALSE|"" or invalid, returns a dummy item with id=0.
     */
    public function __construct($id){
        if ($id) { // Try to return an existing item from database
            $stmt = self::$db->prepare("SELECT itemId, catId FROM items WHERE itemId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->itemId;
                $this->catId = $row->catId;
            } else {
                $this->id = 0;
            }
        } else { // Return an empty object without link to database
            $this->id = 0;
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
     * Get category to which the item belongs
     * @return \FFBoka\Category
     */
    public function category() {
        return new Category($this->catId);
    }
    
    /**
     * Remove item from database
     * @throws \Exception
     * @return boolean True on success
     */
    public function delete() {
        if (self::$db->exec("DELETE FROM items WHERE itemId={$this->id}")) {
            return TRUE;
        } else {
            throw new \Exception("Failed to delete item.");
        }
    }
    
    /**
     * Make a copy of the item.
     * The copy will be inactive, and the featured image will not be set.
     * @return \FFBoka\Item The newly created item
     */
    public function copy() {
        self::$db->exec("INSERT INTO items (catId, caption, description) SELECT catID, caption, description FROM items WHERE itemID={$this->id}");
        $newItemId = self::$db->lastInsertId();
        // copy the associated item images
        self::$db->exec("INSERT INTO item_images (itemId, image, thumb, caption) SELECT $newItemId, image, thumb, caption FROM item_images WHERE itemId={$this->id}");
        $newItem = new Item($newItemId);
        $newItem->caption = $newItem->caption . " (kopia)";
        return $newItem;
    }
    
    /**
     * Create a new image
     * @throws \Exception
     * @return \FFBoka\Image
     */
    public function addImage() {
        if (self::$db->exec("INSERT INTO item_images SET itemId={$this->id}")) {
            return new Image(self::$db->lastInsertId());
        } else {
            throw new \Exception("Failed to create item image.");
        }
    }
    
    /**
     * Get all images of the item
     * @return \FFBoka\Image[]
     */
    public function images() {
        $images = array();
        $stmt = self::$db->query("SELECT imageId FROM item_images WHERE itemId={$this->id}");
        while ($image = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $images[] = new Image($image->imageId);
        }
        return $images;
    }
    
    /**
     * Get the representative image of the item 
     * @return \FFBoka\Image
     */
    public function getFeaturedImage() {
        return new Image($this->imageId);
    }
    
    /**
     * Get a linear representation of free-busy information for one week
     * @param int $start First day of week to show, unix timestamp
     * @param bool $scale Whether to include the weekday scale
     * @return string HTML code showing blocks of free and busy times
     */
    function freebusyBar($start, bool $scale=FALSE) {
		// Store start date as user defined variable because it is used multiple times
		$stmt = self::$db->prepare("SET @start = :start");
		$stmt->execute(array(":start"=>$start));
		// Get freebusy information. 604800 seconds per week.
        $stmt = self::$db->query("SELECT bufferAfterBooking, DATE_SUB(start, INTERVAL bufferAfterBooking HOUR) start, UNIX_TIMESTAMP(start) unixStart, DATE_ADD(end, INTERVAL bufferAfterBooking HOUR) end, UNIX_TIMESTAMP(end) unixEnd FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>" . FFBoka::STATUS_PENDING . " AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start+604800) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start+604800) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@start+604800))");

        $ret = "";
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            if ($row->bufferAfterBooking) {
                $ret .= "<div class='freebusy-blocked' style='left:" . (($row->unixStart-$start-$row->bufferAfterBooking*3600) / 6048) . "%; width:" . (($row->unixEnd - $row->unixStart + 2*$row->bufferAfterBooking*3600)/6048) . "%' title='ej bokbar'></div>";
            }
            $ret .= "<div class='freebusy-busy' style='left:" . (($row->unixStart - $start) / 6048) . "%; width:" . (($row->unixEnd - $row->unixStart) / 6048) . "%;' title='Upptaget {$row->start} till {$row->end}'></div>";
        }
        if ($scale) $ret .= self::freebusyScale();
        return $ret;
    }

    /**
     * Get vertical lines for freebusyBar
     * @param bool $weekdays Also display weekday abbreviations
     * @return string HTML code
     */
    public static function freebusyScale(bool $weekdays = FALSE) {
        $dayNames = $weekdays ? array("<span>mån</span>","<span>tis</span>","<span>ons</span>","<span>tor</span>","<span>fre</span>","<span>lör</span>","<span>sön</span>") : array_fill(0,7,"");
        $ret = "<div class='freebusy-tic' style='border-left:none; left:0;'>{$dayNames[0]}</div>";
        for ($day=1; $day<7; $day++) {
            $ret .= "<div class='freebusy-tic' style='left:" . (100/7*$day) . "%;'>{$dayNames[$day]}</div>";
        }
        return $ret;
    }
    
    /**
     * Get graphical representation showing that freebusy information is not available to user.
     * @return string HTML block showing unavailable information.
     */
    public static function freebusyUnknown() {
        return "<div class='freebusy-unknown'></div>";
    }
    
    /**
     * Check whether the item is available in the given range.
     * @param int $start Unix timestamp
     * @param int $end Unix timestamp
     * @return boolean False if there is any subbooking partly or completely inside the given range
     */
    public function isAvailable(int $start, int $end) {
        $stmt = self::$db->prepare("SET @start = :start, @end = :end");
        $stmt->execute(array(":start"=>$start, ":end"=>$end));
        $stmt = self::$db->query("SELECT subbookingId FROM booked_items INNER JOIN subbookings USING (subbookingId) INNER JOIN bookings USING (bookingId) INNER JOIN items USING (itemId) INNER JOIN categories USING (catId) WHERE itemId={$this->id} AND booked_items.status>" . FFBoka::STATUS_PENDING . " AND ((UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@end) OR (UNIX_TIMESTAMP(start)-bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(start)-bufferAfterBooking*3600<@end) OR (UNIX_TIMESTAMP(end)+bufferAfterBooking*3600>@start AND UNIX_TIMESTAMP(end)+bufferAfterBooking*3600<@end))");
        return ($stmt->rowCount()===0);
    }
}


/**
 * Class ItemBooked: Item in a subbooking
 * @author eltern
 */
class ItemBooked extends Item {
    /** The ID of the subbooking the item belongs to */
    public $subbookingId;
    
    /**
     * Set the booking status of the item
     * @param int $status One of the FFBoka::STATUS_xx constants
     * @return int|boolean Returns the set value, or false on failure
     */
    public function setStatus(int $status) {
        $stmt = self::$db->prepare("UPDATE booked_items SET status=? WHERE subbookingId={$this->subbookingId} AND itemId={$this->id}");
        if ($stmt->execute(array($status))) return $status;
        else return FALSE;
    }
    
    /**
     * Get the booking status of the item
     * @return int $status
     */
    public function getStatus() {
        $stmt = self::$db->query("SELECT status FROM booked_items WHERE subbookingId={$this->subbookingId} AND itemId={$this->id}");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row->status;
    }
}



/**
 * Class for handling item pictures
 * @author Daniel Tamm
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
     * @return boolean|array True on success, ["error"=>"errMsg"] on failure
     */
    public function setImage($imgFile, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        $images = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        if ($images['error']) return $images;
        $stmt = self::$db->prepare("UPDATE item_images SET image=:image, thumb=:thumb WHERE imageID={$this->id}");
        return $stmt->execute(array(
            ":image"=>$images['image'],
            ":thumb"=>$images['thumb'],
        ));       
    }
    
    /**
     * Setter function for image properties
     * @param string $name
     * @param mixed $value
     * @return mixed|boolean Returns the set value on success, and FALSE on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "caption":
                $stmt = self::$db->prepare("UPDATE item_images SET $name=? WHERE imageId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Image property $name");
        }
        return false;
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
                throw new \Exception("Use of undefined Image property $name");
        }
    }
    
    public function delete() {
        if (self::$db->exec("DELETE FROM item_images WHERE imageId={$this->id}")) {
            return TRUE;
        } else {
            throw new \Exception("Failed to delete item image.");
        }
    }
    
}




/**
 * Class containing complete booking, with one or more subbookings.
 * @author Daniel Tamm
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
     * @return bool True on success
     */
    public function addAnswer(string $question, string $answer) {
        $stmt = self::$db->prepare("INSERT INTO booking_answers SET bookingId={$this->id}, question=:question, answer=:answer");
        return $stmt->execute(array(
            ":question"=>$question,
            ":answer"=>$answer,
        ));
    }
    
    /**
     * Get all booking questions and answers
     * @return [ { string question, string answer }, ... ]
     */
    public function answers() {
        $stmt = self::$db->query("SELECT question, answer FROM booking_answers WHERE bookingId={$this->id}");
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}




/**
 * Subbooking class, containing a portion of a booking whith items having the same start and end time. 
 * @author Daniel Tamm
 */
class Subbooking extends FFBoka {
    private $id;
    private $bookingId;
    
    /**
     * Subbooking instantiation.
     * @param int $id ID of the subbooking
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT subbookingId, bookingId FROM subbookings WHERE subbookingId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->subbookingId;
                $this->bookingId = $row->bookingId;
            } else {
                throw new \Exception("Can't instatiate Subbooking with ID $id.");
            }
        } else {
            throw new \Exception("Can't instatiate Subbooking without ID.");
        }
    }
    
    /**
     * Getter function for Subbooking properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "bookingId":
                return $this->$name;
            case "start":
            case "end":
                $stmt = self::$db->query("SELECT UNIX_TIMESTAMP($name) $name FROM subbookings WHERE subbookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "price":
                $stmt = self::$db->query("SELECT $name FROM subbookings WHERE subbookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            default:
                throw new \Exception("Use of undefined Subbooking property $name");
        }
    }
    
    /**
     * Setter function for Subbooking properties
     * @param string $name Property name
     * @param int|string $value Property value. For datetime properties,
     * Unix timestamp and string representations are supported.
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "start":
            case "end":
                if (is_numeric($value)) $stmt = self::$db->prepare("UPDATE subbookings SET $name=FROM_UNIXTIME(?) WHERE subbookingId={$this->id}");
                else $stmt = self::$db->prepare("UPDATE subbookings SET $name=? WHERE subbookingId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            case "price":
                $stmt = self::$db->prepare("UPDATE subbookings SET $name=? WHERE subbookingId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Subbooking property $name");
        }
        return false;
    }
    
    /**
     * Add an item to the subbooking.
     * @param int $itemId ID of the item to add
     * @return bool True on success
     */
    public function addItem(int $itemId) {
        $stmt = self::$db->prepare("INSERT INTO booked_items SET subbookingId={$this->id}, itemId=?");
        return $stmt->execute(array( $itemId ));
    }
    
    /**
     * Get all items contained in this subbooking
     * @return \FFBoka\ItemBooked[]
     */
    public function items() {
        $stmt = self::$db->query("SELECT itemId, status FROM booked_items WHERE subbookingId={$this->id}");
        $items = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $item = new ItemBooked($row->itemId);
            $item->subbookingId = $this->id;
            $item->setStatus($row->status);
            $items[] = $item;
        }
        return $items;
    }
	
	/**
	 * Set the booking status of an item in subbooking
	 * @param int $itemId ID of the item
	 * @param int $status
	 * @return bool True on success
	 */
	public function setStatus(int $itemId, int $status) {
		$stmt = self::$db->prepare("UPDATE booked_items SET status=:status WHERE subbookingId={$this->id} AND itemId=:itemId");
		return $stmt->execute(array(":status"=>$status, ":itemId"=>$itemId));
	}
	
	/**
	 * Get the booking status of an item in subbooking
	 * @param int $itemId ID of the item
	 * @return int The booking status of the item
	 */
	public function getStatus(int $itemId) {
		$stmt = self::$db->prepare("SELECT status FROM booked_items WHERE subbookingId={$this->id} AND itemId=?");
		$stmt->execute(array($itemId));
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		return $row['status'];
	}
}



/**
 * Class for storing question templates
 * @author Daniel Tamm
 */
class Question extends FFBoka {
    private $id;
    private $sectionId;
    
    /**
     * Question instantiation.
     * @param int $id ID of the question
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT questionId, sectionId FROM questions WHERE questionId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->questionId;
                $this->sectionId = $row->sectionId;
            } else {
                throw new \Exception("Can't instatiate Question with ID $id.");
            }
        } else {
            throw new \Exception("Can't instatiate Question without ID.");
        }
    }
    
    /**
     * Getter function for Question properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "sectionId":
                return $this->$name;
            case "type":
            case "caption":
                $stmt = self::$db->query("SELECT $name FROM questions WHERE questionId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "options":
                $stmt = self::$db->query("SELECT $name FROM questions WHERE questionId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return json_decode($row->$name);
            default:
                throw new \Exception("Use of undefined Question property $name");
        }
    }
    
    /**
     * Setter function for Question properties
     * @param string $name Property name
     * @param int|string $value Property value.
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "type":
            case "caption":
            case "options":
                $stmt = self::$db->prepare("UPDATE questions SET $name=? WHERE questionId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Question property $name");
        }
        return false;
    }
    
    /**
     * Delete the question
     * @return bool Success
     */
    public function delete() {
        return self::$db->exec("DELETE FROM questions WHERE questionId={$this->id}");
    }
}