<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    const STATUS_PENDING = 0;
    /** Rejected (and hence arkived) booking */
    const STATUS_REJECTED = 1;
    /** Booking has been placed, but conflicts with existing booking */
    const STATUS_CONFLICT = 2;
    /** Booking has been placed, but needs to be confirmed */
    const STATUS_PREBOOKED = 3;
    /** Booking has been placed and is confirmed */
    const STATUS_CONFIRMED = 4;
    
    /** API URL for authentication */
    protected static $apiAuthUrl;

    /** API key for authentication */
    protected static $apiAuthKey;
    
    /** URL and file path to API feed for getting user's assignments */
    protected static $apiFeedUserAss;
    
    /** URL and file path to API feed for getting all existing assignments */
    protected static $apiFeedAllAss;
    
    /** URL and file path to API feed for getting all valid sections */
    protected static $apiFeedSec;
    
    /** URL and file path to API feed to convert personnummer to member number */
    protected static $apiFeedSocnr;
    
    /** GUID in API indicating sections */
    const TYPE_SECTION = 478880001;
    
    /** string[] Assignment names from API giving section admin access. */
    protected static $sectionAdmins;
    
    /** Database connection */
    protected static $db;
    
    /** string Timezone to use for bookings */
    protected static $timezone;
    
    /**
     * Initialize framework with API address and database connection.
     * These will also be used in the inherited classes
     * @param string[] $api Array with connection details to FF's API,
     *   with members authUrl, authKey, feedUrl, feedUserAss
     * @param PDO $db
     * @param string[] $sectionAdmins Section level assignments giving sections admin access
     * @param string $timezone Timezone for e.g. freebusy display (Europe/Stockholm)
     */
    function __construct($api, PDO $db, $sectionAdmins, $timezone) {
        self::$apiAuthUrl = $api['authUrl'];
        self::$apiAuthKey = $api['authKey'];
        self::$apiFeedUserAss = $api['feedUrl'] . $api['feedUserAss'];
        self::$apiFeedSec = $api['feedUrl'] . $api['feedSec'];
        self::$apiFeedSocnr = $api['feedUrl'] . $api['feedSocnr'];
        self::$apiFeedAllAss = $api['feedUrl'] . $api['feedAss'];
        self::$db = $db;
        self::$sectionAdmins = $sectionAdmins;
        self::$timezone = $timezone;
    }
    

    /**
     * Get an updated list of all sections from API.
     * @param bool $verbose If TRUE, outputs some diagnostic text.
     */
    public function updateSectionList(bool $verbose=FALSE) {
        if ($verbose) echo "Getting updated section list from API...\n";
        $data = json_decode(file_get_contents(self::$apiFeedSec));
        // We may not remove and re-add entries because that would cause linked records in other
        // tables to be removed. So we need to use ON DUPLICATE KEY clause and keep track of last change date.
        $stmt = self::$db->prepare("INSERT INTO sections SET sectionID=:sectionID, name=:name1, timestamp=NULL ON DUPLICATE KEY UPDATE name=:name2, timestamp=NULL");
        foreach ($data->results as $section) {
            if ($section->cint_nummer && $section->cint_name) {
                if ($verbose) echo "Updating section {$section->cint_nummer} {$section->cint_name}\n";
                $stmt->execute(array(
                    ":sectionID" => $section->cint_nummer,
                    ":name1" => $section->cint_name,
                    ":name2" => $section->cint_name,
                ));
            }
        }
        if ($verbose) echo "All sections updated from API.\n\nDeleting outdated section records...";
        $numDeleted = self::$db->exec("DELETE FROM sections WHERE TIMESTAMPDIFF(SECOND, `timestamp`, NOW())>100");
        if ($verbose) echo " $numDeleted records deleted.\n\n";
    }
    
    /**
     * Get an updated list of all assignments from API.
     * @param bool $verbose If TRUE, outputs some diagnostic text.
     */
    public function updateAssignmentList(bool $verbose=FALSE) {
        if ($verbose) echo "Update assignments from API...\n";
        $data = json_decode(file_get_contents(self::$apiFeedAllAss));
        // We may not remove and re-add entries because that would break linked records in other
        // tables. So we need to use ON DUPLICATE KEY clause and keep track of last change date.
        $stmt = self::$db->prepare("INSERT INTO assignments SET assName=:assName, sort=:sort, timestamp=NULL ON DUPLICATE KEY UPDATE timestamp=NULL");
        foreach ($data->results as $ass) {
            if ($ass->cint_name && $ass->cint_assignment_party_type->value && $ass->cint_assignment_party_type->value == FFBoka::TYPE_SECTION) {
                if ($verbose) echo "Add assignment {$ass->cint_name}\n";
                if (!$stmt->execute(array(
                    ":assName"   => $ass->cint_name,
                    ":sort" => 2
                ))) echo "ERROR: Failed to add assignment: ".$stmt->errorInfo()[2];
                if (strpos($ass->cint_name, ":") !== FALSE) {
                    // This is a sub-assignment to a principal assignment (e.g. Ledare: Mulle).
                    // Save the principal assignment with the string left of the colon.
                    if ($verbose) echo "Add principal assignment for {$ass->cint_name}\n";
                    if (!$stmt->execute(array(
                        ":assName"   => substr($ass->cint_name, 0, strpos($ass->cint_name, ":")),
                        ":sort" => 1
                    ))) echo "ERROR: Failed to add assignment: " . $stmt->errorInfo()[2];
                }
            }
        }
        if ($verbose) echo "All assignments are updated.\n\n";
        if ($verbose) echo "Deleting all (outdated) records not affected by the update...";
        $numDeleted = self::$db->exec("DELETE FROM assignments WHERE sort>0 AND TIMESTAMPDIFF(SECOND, timestamp, NOW())>100");
        if ($verbose) echo "$numDeleted records deleted.\n\n";
    }
    
    /**
     * Returns a list of all assignments available
     * @return string[]
     */
    public function getAllAssignments() {
        $stmt = self::$db->query("SELECT assName FROM assignments ORDER BY sort, assName");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Get a list of all sections in FF
     * @param int $showFirst If set, this section will be returned as the first element
     * @param string $sort Can be "name" (sorted alphabetically) or "n2s" (sorted north to south)
     * @return Section[] Array of sections in alphabetical order
     */
    public function getAllSections(int $showFirst=0, string $sort="name") {
        switch ($sort) {
            case "n2s":
                $stmt = self::$db->prepare("SELECT sectionId FROM sections ORDER BY sectionId!=?, `lat` DESC");
                break;
            default:
                $stmt = self::$db->prepare("SELECT sectionId FROM sections ORDER BY sectionId!=?, `name`");
        }
        $stmt->execute(array($showFirst));
        $sections = array();
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $sections[] = new Section($row->sectionId);
        }
        return $sections;
    }
    
    
    /**
     * Get the section ID for a clear text section name
     * @param string $sectionName
     * @return int|boolean The sectionId of the section, or FALSE if no match is found.
     */
    public function getSectionIdByName(string $sectionName) {
        $stmt = self::$db->prepare("SELECT sectionId FROM sections WHERE name=?");
        $stmt->execute(array($sectionName));
        if ($row = $stmt->fetch(\PDO::FETCH_OBJ)) return $row->sectionId;
        else return FALSE;
    }

    
    /**
     * Authenticate the given user data by querying the API
     * @param string $userId Member number or personnummer
     * @param string $password
     * @return bool|array(bool authenticated, int userId, string section) Returns FALSE if API does not respond.
     * Otherwise, authenticated contains whether the credentials were accepted. If accepted, userId
     * will contain the member ID and section will contain the section name.  
     */
    public function authenticateUser($userId, $password) {
        $matches = array();
        $userId = trim($userId);
        if (preg_match("/^(19|20)?(\d{6})-?(\d{4})$/", $userId, $matches)) {
            // $userId is given as personnummer.
            // Convert to 10 digits if given as 12 digits
            $userId = $matches[2].$matches[3];
            // Convert to member number via API
            $data = json_decode(@file_get_contents(self::$apiFeedSocnr . $userId));
            //die("Personnummer: $userId, svar:".print_r($data, true));
            if ($data === FALSE) {
                return FALSE;
            } elseif ($data->results) {
                $userId = $data->results[0]->cint_username;
            } else {
                return array("authenticated" => FALSE, "section" => NULL);
            }
        } elseif (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
            // $userId given as email address. Look it up in the users table
            $stmt = self::$db->prepare("SELECT userId FROM users WHERE mail=? LIMIT 1");
            $stmt->execute(array($userId));
            if ($row = $stmt->fetch(\PDO::FETCH_OBJ)) $userId = $row->userId;
        }
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
        $result = @file_get_contents(self::$apiAuthUrl, false, $context);
        if ($result === FALSE) return FALSE;
        $result = json_decode($result);
        return array(
            "authenticated"=>$result->isMember,
            "userId"=>$userId,
            "section"=>$result->isMemberOfLokalavdelning
        );
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
        for ($token = '', $i = 0, $z = strlen($a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')-1; $i < 40; $x = rand(0,$z), $token .= $a[$x], $i++);
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
    
    /**
     * Formats the given amount of bytes in a human-readable way
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    static function formatBytes(int $bytes, int $precision=1) {
        $units = array("B", "kB", "MB", "GB", "TB");
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10*$pow));
        return round($bytes, $precision) . " " . $units[$pow];
    }
    
    /**
     * Formats a date span as a read friendly string.
     * mån 2020-08-24 kl 07:00-21:00
     * mån 2020-08-24 kl 07:00 till tis 2020-08-25 kl 21:00
     * @param int $start Unix timestamp of start time
     * @param imt $end Unix timestamp of end time
     * @param bool $includeWeekday Whether to include the weekday
     * @return string
     */
    static function formatDateSpan(int $start, int $end, bool $includeWeekday=false) {
        $wday = $includeWeekday ? "%a " : "";
        if (strftime("%F", $start) == strftime("%F", $end)) {
            // Start and end on same day
            return strftime("$wday%F kl %H:00", $start) . "-". strftime("%H:00", $end);
        } else {
            // Start and end on different days
            return strftime("$wday%F kl %H:00", $start) . " till " . strftime("%a %F kl %H:00", $end);
        }
    }

    /**
     * Add a new poll
     * @return \FFBoka\Poll
     */
    public function addPoll() {
        self::$db->exec("INSERT INTO polls SET question='Ny enkät', choices='" . json_encode(["Option 1", "Option 2"]) . "', votes='[0,0]'");
        return new Poll(self::$db->lastInsertId());
    }
    
    /**
     * Get all polls
     * @param string $only Set to "active" to only get non-expired polls, or "expired" to only get expired polls
     * @return \FFBoka\Poll[]
     */
    public function polls(string $only=NULL) {
        switch ($only) {
        case "active":
            $stmt = self::$db->query("SELECT pollId FROM polls WHERE expires IS NULL OR expires > NOW()");
            break;
        case "expired":
            $stmt = self::$db->query("SELECT pollId FROM polls WHERE expires <= NOW() ORDER BY expires DESC");
            break;
        default:
            $stmt = self::$db->query("SELECT pollId FROM polls ORDER BY expires IS NULL, expires DESC");
        }
        $polls = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $polls[] = new Poll($row->pollId);
        }
        return $polls;
    }
    
    /**
     * Get the next available ID for booking series
     * @return int
     */
    public function getNextRepeatId() {
        $stmt = self::$db->query("SELECT 0+MAX(repeatId) lastId FROM bookings");
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if (is_null($row->lastId)) return 1;
        else return $row->lastId + 1;
    }


    /**
     * Queue an email for delayed sending. Create a mail queue entry to be processed by cron.
     * @param string $to
     * @param string $subject
     * @param string $template Name of template file to use, without extension. The file must be in the templates folder.
     * If there is a file named $template.html, it will be used. Otherwise, $template will be used as the message body.
     * @param array $replace Array of strings [search=>replace] to be replaced in the body|template
     * @param array $attachments Array of files [path=>filename] to attach. path is the absolute path to the 
     * file, and filename is the name the file shall appear with in the email 
     * @param string $fromName Clear text From name
     * @param string $replyTo
     * @return int Returns the ID of the created mail queue item.
     * @throws \Exception if creation of mail queue item fails.
     */
    function queueMail(string $to, string $subject, $template, $replace=[], $attachments=[], string $fromName='', string $replyTo='') {
        if (is_readable(__DIR__."/../../templates/$template.html")) {
            $body = file_get_contents(__DIR__."/../../templates/$template.html");
        } else {
            $body = $template;
        }
        // Replace placeholders
        $body = str_replace(array_keys($replace), array_values($replace), $body);

        // Place mail into mail queue
        $stmt = self::$db->prepare("INSERT INTO mailq SET fromName=:fromName, `to`=:to, replyTo=:replyTo, subject=:subject, body=:body, attachments=:attachments");
        if (!$stmt->execute(array(
            ":fromName" => $fromName,
            ":to"       => $to,
            ":replyTo"  => $replyTo,
            ":subject"  => $subject,
            ":body"     => $body,
            ":attachments" => json_encode($attachments)
        ))) throw new \Exception("Failed to queue mail. " . $stmt->errorInfo()[2]);
        return self::$db->lastInsertId();
    }

    /**
     * Send all emails from the mail queue
     * @param string $from From address to use
     * @param string $fromName Cleartext from name to use if no from name is set in queue entry
     * @param string $replyTo Reply-to address to use if not set in queue entry
     * @param array $SMTPOptions Array with SMTP config, containing the keys: host, port, user, pass
     * @throws Exception if sending fails
     */
    function sendQueuedMails(string $from, string $fromName, string $replyTo, array $SMTPOptions) {
        $stmt = self::$db->query("SELECT * FROM mailq");
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (count($rows)) echo "Sending mails from mail queue...\n";
        else echo "No mails in the mail queue.\n\n";
        foreach ($rows as $row) {
            try {
                $mail = new PHPMailer(true);
                $mail->XMailer = ' '; // Don't advertise that we are using PHPMailer
                // Add attachments
                foreach (json_decode($row->attachments) as $att) {
                    $mail->addAttachment(dirname(__FILE__) . "/../../" . $att->path, $att->filename);
                }
                //Server settings
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host = $SMTPOptions['host'];
                $mail->Port = $SMTPOptions['port'];
                $mail->SMTPAuth = true;
                $mail->Username = $SMTPOptions['user'];
                $mail->Password = $SMTPOptions['pass'];
                $mail->SMTPSecure = 'tls';   // Enable TLS encryption, `ssl` also accepted
                // Message content
                $mail->CharSet ="UTF-8";
                $mail->setFrom($from, $row->fromName ? $row->fromName : $fromName);
                $mail->Sender = $SMTPOptions['user'];
                $mail->addAddress($row->to);
                $mail->addReplyTo($row->replyTo ? $row->replyTo : $replyTo);
                $mail->isHTML(true);
                $mail->Subject = $row->subject;
                $mail->Body = $row->body;
                $mail->AltBody = strip_tags(str_replace(array("</p>", "<br>"), array("</p>\r\n\r\n", "\r\n"), $row->body));
                if (!$mail->send()) throw new Exception($mail->ErrorInfo);
                $stmt = self::$db->prepare("DELETE FROM mailq WHERE mailqId=?");
                $stmt->execute([ $row->mailqId ]);
                echo "Mail sent to {$row->to}\n";
            } catch (Exception $e) {
                throw new \Exception("Mailer Error: ".$mail->ErrorInfo);
            }
        }
        if (count($rows)) echo "All mails from queue sent.\n\n";
    }
}
