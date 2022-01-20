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
            if (is_numeric($section)) {
                $this->sectionId = $section;
            } else {
                $stmt = self::$db->prepare("SELECT sectionId FROM sections WHERE name=:name");
                $stmt->execute(array(":name" => $section));
                $row = $stmt->fetch(\PDO::FETCH_OBJ);
                $this->sectionId = $row->sectionId;
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
            default:
                logger(__METHOD__." Use of invalid User property $name.", E_ERROR);
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
                logger(__METHOD__." Failed to set User property $name to $value. " . $stmt->errorInfo()[2], E_ERROR);
                break;
            default:
                logger(__METHOD__." Use of undefined User property $name.", E_ERROR);
                throw new \Exception("Use of undefined User property $name");
        }
        return false;
    }

    /**
     * Get user's assignments on section level from the FF API.
     * Fills $_SESSION['assignments'] with array[sectionIds][names]
     * @return bool Success or failure
     */
    public function getAssignments() : bool {
        $_SESSION['assignments'] = array();
        if (self::$apiFeedUserAss) { // API URL for assignments is set. Try to get user's assignments
            $data = @file_get_contents(self::$apiFeedUserAss . $this->id);
            if ($data === FALSE) { // no answer
                logger(__METHOD__." Failed to get assignments from API.", E_WARNING);
                $_SESSION['assignments'][0][] = "Kunde inte läsa in uppdrag från API.";
            } else { // Got an answer
                $data = json_decode($data);
                foreach ($data->results as $ass) {
                    if ($ass->uppdragstyp__cint_assignment_party_type->value == FFBoka::TYPE_SECTION) {
                        // This will sort the assignments on section ID
                        $_SESSION['assignments'][$ass->section__cint_nummer][] = $ass->cint_assignment_type_id->name;
                        // Add general assignment group if applicable
                        if (strpos($ass->cint_assignment_type_id->name, ":") !== FALSE) {
                            list($main, $sub) = explode(":", $ass->cint_assignment_type_id->name);
                            if (!in_array($main, $_SESSION['assignments'][$ass->section__cint_nummer])) $_SESSION['assignments'][$ass->section__cint_nummer][] = $main;
                        }
                    }
                }
                return TRUE;
            }
        }
        return FALSE;
    }
    
    /**
     * Deletes the user and all related data from the database
     * @return boolean TRUE on success, FALSE otherwise
     */
    public function delete() : bool {
        $name = $this->name;
        $id = $this->id;
        if (self::$db->exec("DELETE FROM users WHERE userID={$this->id}") !== FALSE) {
            logger(__METHOD__." Deleted user $name, #$id.");
            return TRUE;
        }
        logger(__METHOD__." Failed to delete user $name. " . self::$db->errorInfo()[2], E_ERROR);
        return FALSE;
    }
    
    /**
     * Set the timestamp for the user to current time
     * @return bool
     */
    public function updateLastLogin() {
        if (self::$db->exec("UPDATE users SET lastLogin=NULL WHERE userId='{$this->id}'") !== FALSE) return TRUE;
        logger(__METHOD__." Failed to update last login. " . self::$db->errorInfo()[2], E_ERROR);
        return FALSE;
    }
    
    /**
     * Restore an expired session from persistent-login cookie.
     * On success, will also replace the persistent-login cookie with a new one.
     * @param string $cookie String in the format selector:authenticator where authenticator is base64 encoded
     * @param int $ttl TTL for the new cookie
     */
    static function restorePersistentLogin(string $cookie, int $ttl) : void {
        list($selector, $authenticator) = explode(':', $cookie);
        $stmt = self::$db->prepare("SELECT * FROM persistent_logins WHERE selector=? AND expires>NOW()");
        $stmt->execute(array($selector));
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            if (hash_equals($row->authenticator, hash('sha256', base64_decode($authenticator)))) {
                // User authenticated. Set as logged in
                $_SESSION['authenticatedUser'] = $row->userId;
                $u = new User($row->userId);
                // Fetch assignments
                $u->getAssignments();
                // Regenerate login token
                $u->createPersistentLogin($ttl);
                // Log
                self::$db->exec("INSERT INTO logins (ip, login, userId, success, userAgent) VALUES (INET_ATON('{$_SERVER['REMOTE_ADDR']}'), '(token)', '{$row->userId}', 2, '{$_SERVER['HTTP_USER_AGENT']}')");
            }
        }
    }
    
    /**
     * Create a cookie and db post to remember the logged-in user even when session has expired.
     * The cookie is valid for the currently used device only.
     * @param int $ttl How long the cookie shall be valid (seconds)
     * @return boolean TRUE on success, FALSE on failure to create cookie
     */
    public function createPersistentLogin(int $ttl) : bool {
        //https://stackoverflow.com/questions/3128985/php-login-system-remember-me-persistent-cookie
        // Remove old token
        $this->removePersistentLogin();
        // Create token
        $selector = base64_encode(random_bytes(15));
        $authenticator = random_bytes(40);
        // Send token as cookie to browser
        if (!setcookie(
            'remember',
            $selector.':'.base64_encode($authenticator),
            time() + $ttl,
            "/",
            $_SERVER['SERVER_NAME'],
            true, // TLS-only
            true  // http-only
        )) return FALSE;
        
        // Save token to database
        $stmt = self::$db->prepare("INSERT INTO persistent_logins SET userId=:userId, userAgent=:userAgent, selector=:selector, authenticator=:authenticator, expires=DATE_ADD(NOW(), INTERVAL $ttl SECOND)");
        if (!$stmt->execute(array(
            "userId"=>$this->id,
            "userAgent"=>$_SERVER['HTTP_USER_AGENT'],
            ":selector"=>$selector,
            ":authenticator"=>hash('sha256', $authenticator),
        ))) {
            logger(__METHOD__." Failed to save persistent login cookie to db. " . $stmt->errorInfo()[2], E_ERROR);
            return false;
        }
        
        // Save userAgent string to database
        $stmt = self::$db->prepare("INSERT IGNORE INTO user_agents SET uaHash=:hash, userAgent=:ua");
        $stmt->execute(array(
            ":hash" => sha1($_SERVER['HTTP_USER_AGENT']),
            ":ua" => $_SERVER['HTTP_USER_AGENT']
        ));
        return TRUE;
    }
    
    /**
     * Remove cookie and database post for persistent login ("Remember me")
     * @param string $selector If set, will remove the persistent login containing this selector,
     * otherwise the login on the current connection is removed
     * @return bool|void False if $selector is empty and there is not current persistent login
     * @throws \Exception if database post cannot be deleted
     */
    public function removePersistentLogin(string $selector="") {
        $currentSelector = explode(":", $_COOKIE['remember'])[0];
        if ($selector == "") {
            if ($currentSelector == "") return false; // No information available on which login to remove
            $selector = $currentSelector;
        }
        if ($selector == $currentSelector) {
            // We are on the same device as the cookie. Remove it from the device.
            setcookie(
                'remember',
                '',
                time() - 3600,
                "/",
                $_SERVER['SERVER_NAME'],
                true, // TLS-only
                true  // http-only
            );
        }
        $stmt = self::$db->prepare("DELETE FROM persistent_logins WHERE userId=:userId AND selector=:selector");
        if (!$stmt->execute([
            ":selector"=>$selector,
            ":userId"=>$this->id
        ])) {
            logger(__METHOD__." Failed to remove persistent login from database. " . $stmt->errorInfo()[2], E_ERROR);
            throw new \Exception((string) $stmt->errorInfo()[2]);
        }
    }
    
    /**
     * Get all persistent logins for user
     * @return array of objects { string userAgent, string selector, int expires } Expires is returned as Unix timestamp
     */
    public function persistentLogins() : array {
        $stmt = self::$db->query("SELECT userAgent, selector, UNIX_TIMESTAMP(expires) expires FROM persistent_logins WHERE userId={$this->id}");
        return $stmt->fetchall(\PDO::FETCH_OBJ);
    }
    
    /**
     * Get an HTML formatted string with contact data
     * @return string
     */
    public function contactData() : string {
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
    public function addBooking(int $sectionId) : \FFBoka\Booking {
        // Create token
        for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a[$x], $i++);
        if ($this->id) $stmt = self::$db->prepare("INSERT INTO bookings SET sectionId=?, userId={$this->id}, token='$token'");
        else $stmt = self::$db->prepare("INSERT INTO bookings SET sectionId=?, token='$token'");
        $stmt->execute(array($sectionId));
        return new Booking(self::$db->lastInsertId());
    }
    
    /**
     * Get booking IDs of all the user's bookings, incl up to 1 year old ones
     * @return int[] IDs of bookings no older than 1 year
     */
    public function bookingIds() : array {
        $stmt = self::$db->query("SELECT bookingId FROM bookings WHERE userId={$this->id} AND timestamp>DATE_SUB(CURDATE(), INTERVAL 1 YEAR) ORDER BY timestamp DESC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Get booking IDs of bookings which the user has initiated but not completed
     * @return int[] booking IDs
     */
    public function unfinishedBookings() : array {
        $stmt = self::$db->query("SELECT bookingId FROM booked_items INNER JOIN bookings USING (bookingId) WHERE userId={$this->id} AND status=" . FFBoka::STATUS_PENDING);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Find sections where user has booking admin roles
     * @return Section[]
     */
    public function bookingAdminSections() : array {
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
    public function getNotifyAdminOnNewBooking(Category $cat) : string {
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
            if (self::$db->exec("DELETE FROM cat_admin_noalert WHERE userId={$this->id} AND catId=$catId")===FALSE) {
                logger(__METHOD__." Failed to remove admin notification optout for userId {$this->id} and catId $catId. " . self::$db->errorInfo()[2], E_ERROR);
                return FALSE;
            }
        } elseif ($value=="no" || $value=="confirmOnly") {
            if (self::$db->exec("INSERT INTO cat_admin_noalert SET userId={$this->id}, catId=$catId, notify='$value' ON DUPLICATE KEY UPDATE notify='$value'")===FALSE) {
                logger(__METHOD__." Failed to add admin notification optout for userId {$this->id} and catId $catId, value $value." . self::$db->errorInfo()[2], E_ERROR);
                return FALSE;
            }
        } else {
            logger(__METHOD__." Trying to set admin notification to invalid value ($value).", E_ERROR);
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
    
    /**
     * Save a new email address which needs to be confirmed before being used in the system
     * @param string $newMail the new email address
     * @return string the token used to preliminarily save the address
     */
    public function setUnverifiedMail(string $mail) : string {
        // Delete any previous tokens for same user
        self::$db->exec("DELETE FROM tokens WHERE useFor='change mail address' AND forId={$this->id}");
        return $this->createToken("change mail address", $this->id, $mail);
    }
    
    /**
     * Check whether the user has a pending, non-expired change of the email address.
     * @return false|string Email address if there is a pending change, otherwise false.
     */
    public function getUnverifiedMail() {
        $stmt = self::$db->query("SELECT data FROM tokens WHERE forId={$this->id} AND useFor='change mail address' AND DATE_ADD(timestamp, INTERVAL ttl SECOND)>NOW()");
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) return $row->data;
        return false;
    }
    
    /**
     * Get a poll not yet answered by this user
     * @return \FFBoka\Poll|NULL
     */
    public function getUnansweredPoll() {
        // Look for any admin assignments
        $access = self::ACCESS_BOOK;
        $stmt = self::$db->query("SELECT COUNT(*) count FROM section_admins WHERE userId={$this->id}");
        if ($stmt->fetch(PDO::FETCH_OBJ)->count > 0) $access = self::ACCESS_SECTIONADMIN;
        else {
            $stmt = self::$db->query("SELECT COUNT(*) count FROM cat_admins WHERE userId={$this->id}");
            if ($stmt->fetch(PDO::FETCH_OBJ)->count > 0) $access = self::ACCESS_CATADMIN;
        }
        $stmt = self::$db->query("SELECT polls.pollId FROM polls WHERE targetGroup<=$access AND (expires IS NULL OR expires > NOW()) AND pollId NOT IN (SELECT pollId FROM poll_answers WHERE userId={$this->id}) LIMIT 1");
        if ($row = $stmt->fetch(\PDO::FETCH_OBJ)) return new \FFBoka\Poll($row->pollId);
        else return NULL;
    }

    /**
     * Look for categories with similar captions as the specified search string.
     * @param string $search The string to search for
     * @return array[] Array of arrays with the following keys:
     *    name (section name),
     *    id (section ID),
     *    distance (the composed geographic and least comparison distance),
     *    matches (a string with comma-separated matches.
     * The return array is sorted by geographic distance, ascending.
     */
    public function findResource(string $search) : array {
        // Get home position as radians
        $homeSec = $this->section;
        $homeLat = pi() * $homeSec->lat / 180;
        $homeLon = pi() * $homeSec->lon / 180;
        $ret = array();
        foreach ($this->getAllSections() as $sec) {
            $matches = $sec->contains($search, $this, 70);
            if (count($matches)) {
                // Sort matches after relevance and take the 4 best matches
                asort($matches);
                $matches = array_keys(array_slice($matches, 0, 4));
                // Get section's position as radians and calculate geographic distance.
                $lat = pi() * $sec->lat / 180;
                $lon = pi() * $sec->lon / 180;
                $dLon2 = pow(cos($homeLat) * ($homeLon - $lon), 2);
                $dLat2 = pow($homeLat - $lat, 2);
                $distance = (int) (6300 * sqrt($dLon2 + $dLat2)); // distance in km
                $ret[] = [
                    "name" => $sec->name,
                    "id" => $sec->id,
                    "distance" => $distance,
                    "matches" => implode(", ", $matches)
                ];
            }
        }
        // Sort sections by calculated distance
        usort($ret, function($a, $b) {
            return $a['distance'] - $b['distance'];
        });
        return $ret;
    }
}
