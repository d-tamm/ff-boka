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
    /** Booking has been placed, but conflicts with existing booking */
    const STATUS_CONFLICT=1;
    /** Booking has been placed, but needs to be confirmed */
    const STATUS_PREBOOKED=2;
    /** Booking has been placed and is confirmed */
    const STATUS_CONFIRMED=3;
    
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
        $offset = ($size[0]-$size[1])/2/($size[0]+$size[1])*$thumbSize;
        imagecopyresampled($tmp, $src, -$offset, $offset, 0, 0, $thumbSize+2*$offset, $thumbSize-2*$offset, $size[0], $size[1]);
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
