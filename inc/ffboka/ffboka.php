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
    
    /** URL and key to Friluftsfrämjandet's API */
    protected static $apiAuthUrl;
    protected static $apiAuthKey;
    protected static $apiAssUrl;
    
    /** GUID in API indicating sections */
    const TYPE_SECTION = 478880001;
    
    /** Database connection */
    protected static $db;

    /** string[] Assignments from API giving section admin access. */
    protected static $sectionAdmins;
    
    /** string Timezone to use for bookings */
    protected static $timezone;
    
    /**
     * Initialize framework with API address and database connection.
     * These will also be used in the inherited classes
     * @param array(string) $api Array with connection details to FF's API, with members authUrl, authKey, assUrl
     * @param PDO::Database $db
     * @param array(string) $sectionAdmins Section level assignments giving sections admin access
     * @param string $timezone Timezone for e.g. freebusy display (Europe/Stockholm)
     */
    function __construct($api, $db, $sectionAdmins, $timezone) {
        self::$apiAuthUrl = $api['authUrl'];
        self::$apiAuthKey = $api['authKey'];
        self::$apiAssUrl = $api['assUrl'];
    	self::$db = $db;
    	self::$sectionAdmins = $sectionAdmins;
    	self::$timezone = $timezone;
    }
    

    /**
     * Get a list of all sections in FF
     * @param int $showFirst If set, this section will be returned as the first element
     * @return Section[] Array of sections in alphabetical order
     */
    public function getAllSections(int $showFirst=0) {
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
     * @return array(bool authenticated, string section) 
     */
    public function authenticateUser($userId, $password) {
		// Fake test user with id=999999
		if ($userId=="999999" && $password=="kollikok") return array("authenticated"=>TRUE, "section"=>"52");
		// Check password via API and get home section
        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/json\r\n" .
                "Cache-Control: no-cache\r\n".
                "Ocp-Apim-Subscription-Key: " . self::$apiAuthKey . "\r\n",
                'method'  => 'POST',
                'content' => json_encode([ 'membernumber' => $userId, 'password' => $password ])
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents(self::$apiAuthUrl, false, $context);
        if ($result === FALSE) return FALSE;
        $result = json_decode($result);
        return array("authenticated" => $result->isMember, "section" => $result->isMemberOfLokalavdelning);
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
        $size = @getimagesize($file['tmp_name']);
        if (!$size) return array("error"=>"Kan inte läsa filformatet.");
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
     * Creates a new one-time token
     * @param string $useFor Key designating what the token shall be used for
     * @param int $forId Entity ID the token shall be valid for
     * @param string $data Additional data
     * @param number $ttl TTL for the token
     * @throws \Exception if database operation fails
     * @return string The generated token
     */
    protected function createToken(string $useFor, int $forId, string $data="", int $ttl=86400) {
        // generate 40 character random token
        for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a{$x}, $i++);
        $stmt = self::$db->prepare("REPLACE INTO tokens SET token=SHA1('$token'), ttl=$ttl, useFor=:useFor, forId=:forId, data=:data");
        if (!$stmt->execute(array(
            ":data"=>$data,
            ":useFor"=>$useFor,
            ":forId"=>$forId,
        ))) {
            throw new \Exception($stmt->errorInfo()[2]);
        }
        return($token);
    }
    
    /**
     * Delete a token from database
     * @param string $token
     * @return bool TRUE on success
     */
    public function deleteToken(string $token) {
        $stmt = self::$db->prepare("DELETE FROM tokens WHERE token=?");
        return $stmt->execute(array($token));
    }
    
    /**
     * Get stored information for a given token.
     * @param string $token The token
     * @throws \Exception if token not found or expired
     * @return object { unixtime, token, timestamp, ttl, useFor, forId, data }
     */
    public function getToken(string $token) {
        $stmt = self::$db->query("SELECT UNIX_TIMESTAMP(timestamp) AS unixtime, tokens.* FROM tokens WHERE token='" . sha1($token) . "'");
        if (!$row = $stmt->fetch(PDO::FETCH_OBJ)) throw new \Exception("Ogiltig kod.");
        elseif (time() > $row->unixtime + $row->ttl) throw new \Exception("Koden har förfallit.");
        else return $row;
    }
}
