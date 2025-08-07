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
    function __construct( $api, PDO $db, $sectionAdmins, $timezone ) {
        self::$apiAuthUrl = $api[ 'authUrl' ];
        self::$apiAuthKey = $api[ 'authKey' ];
        self::$apiFeedUserAss = $api[ 'feedUrl' ] . $api[ 'feedUserAss' ];
        self::$apiFeedSec = $api[ 'feedUrl' ] . $api[ 'feedSec' ];
        self::$apiFeedSocnr = $api[ 'feedUrl' ] . $api[ 'feedSocnr' ];
        self::$apiFeedAllAss = $api[ 'feedUrl' ] . $api[ 'feedAss' ];
        self::$db = $db;
        self::$sectionAdmins = $sectionAdmins;
        self::$timezone = $timezone;
    }
    

    /**
     * Get an updated list of all sections from API.
     */
    public function updateSectionList() {
        logger( __METHOD__ . " Getting updated section list from API..." );
        $data = json_decode( file_get_contents( self::$apiFeedSec ) );
        logger( __METHOD__ . " API returned " . count( $data->results ) . " sections." );
        // We may not remove and re-add entries because that would cause linked records in other
        // tables to be removed. So we need to use ON DUPLICATE KEY clause and keep track of last change date.
        $stmt = self::$db->prepare( "INSERT INTO sections SET sectionID=:sectionID, name=:name1, timestamp=NULL ON DUPLICATE KEY UPDATE name=:name2, timestamp=NULL" );
        foreach ( $data->results as $section ) {
            if ( $section->cint_nummer && $section->cint_name ) {
                if ( !$stmt->execute( array(
                    ":sectionID" => $section->cint_nummer,
                    ":name1" => $section->cint_name,
                    ":name2" => $section->cint_name,
                ) ) ) logger( __METHOD__ . " Failed to update section {$section->cint_nummer} {$section->cint_name}. " . self::$db->errorInfo()[ 2 ], E_ERROR );                ;
            }
        }
        // Check for outdated records
        $stmt = self::$db->query( "SELECT `sectionID`, `name` FROM sections WHERE TIMESTAMPDIFF(SECOND, `timestamp`, NOW())>100" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            logger( __METHOD__ . " Section {$row->name} ({$row->sectionID}) has not been updated from API and may need to be removed manually.", E_WARNING );
        }
    }
    
    /**
     * Get an updated list of all assignments from API.
     */
    public function updateAssignmentList() {
        logger( __METHOD__ . " Updating assignments from API..." );
        $data = json_decode( file_get_contents( self::$apiFeedAllAss ) );
        logger( __METHOD__ . " API returned " . count( $data->results ) . " assignments." );
        // We may not remove and re-add entries because that would break linked records in other
        // tables. So we need to use ON DUPLICATE KEY clause and keep track of last change date.
        $stmt = self::$db->prepare( "INSERT INTO assignments SET assName=:assName, sort=:sort, timestamp=NULL ON DUPLICATE KEY UPDATE timestamp=NULL" );
        foreach ( $data->results as $ass ) {
            if ( $ass->cint_name && $ass->cint_assignment_party_type->value && $ass->cint_assignment_party_type->value == FFBoka::TYPE_SECTION ) {
                if ( !$stmt->execute( [
                    ":assName" => $ass->cint_name,
                    ":sort" => 2
            ] ) ) logger( __METHOD__ . " Failed to add assignment: " . $stmt->errorInfo()[ 2 ], E_ERROR );
                if ( strpos( $ass->cint_name, ":" ) !== FALSE ) {
                    // This is a sub-assignment to a principal assignment (e.g. Ledare: Mulle).
                    // Save the principal assignment with the string left of the colon.
                    if ( !$stmt->execute( [
                        ":assName"   => substr( $ass->cint_name, 0, strpos( $ass->cint_name, ":" ) ),
                        ":sort" => 1
                    ])) logger( __METHOD__ . " Failed to add/update assignment: " . $stmt->errorInfo()[ 2 ], E_ERROR );
                }
            }
        }
        // Check for outdated records
        $stmt = self::$db->query( "SELECT assName FROM assignments WHERE sort>0 AND TIMESTAMPDIFF(SECOND, timestamp, NOW())>100" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            logger( __METHOD__ . " Assignment {$row->assName} has not been updated from API and may need to be removed manually.", E_WARNING );
        }
    }
    
    /**
     * Returns a list of all assignments available
     * @return string[]
     */
    public function getAllAssignments() {
        $stmt = self::$db->query( "SELECT assName FROM assignments ORDER BY sort, assName" );
        return $stmt->fetchAll( PDO::FETCH_COLUMN );
    }
    
    /**
     * Get a list of all sections in FF
     * @param int $showFirst If set, this section will be returned as the first element
     * @param string $sort Can be "name" (sorted alphabetically) or "n2s" (sorted north to south)
     * @return Section[] Array of sections in alphabetical order
     */
    public function getAllSections( int $showFirst = 0, string $sort = "name" ) {
        switch ( $sort ) {
            case "n2s":
                $stmt = self::$db->prepare( "SELECT sectionId FROM sections ORDER BY sectionId!=?, `lat` DESC" );
                break;
            default:
                $stmt = self::$db->prepare( "SELECT sectionId FROM sections ORDER BY sectionId!=?, `name`" );
        }
        $stmt->execute( [ $showFirst ] );
        $sections = [];
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $sections[] = new Section( $row->sectionId );
        }
        return $sections;
    }
    
    
    /**
     * Get the section ID for a clear text section name
     * @param string $sectionName
     * @return int|boolean The sectionId of the section, or FALSE if no match is found.
     */
    public function getSectionIdByName( string $sectionName ) {
        $stmt = self::$db->prepare( "SELECT sectionId FROM sections WHERE name=?" );
        $stmt->execute( [ $sectionName ] );
        if ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) return $row->sectionId;
        else return FALSE;
    }

    
    /**
     * Authenticate the given user data by querying the API
     * @param string $userId Member number or personnummer
     * @param string $password
     * @return bool|array(bool authenticated, int userId, string section) Returns FALSE if API does not respond.
     * Otherwise, authenticated is TRUE if the credentials were accepted. If accepted, userId
     * will contain the member ID and section will contain the section name.  
     */
    public function authenticateUser( $userId, $password ) {
        $matches = [];
        $userId = trim( $userId );
        if ( preg_match( "/^(19|20)?(\d{6})-?(\d{4})$/", $userId, $matches ) ) {
            // $userId is given as personnummer.
            // Convert to 12 digits yyyymmddnnnn
            $userId = ( $matches[1] ?: "19" ) . $matches[ 2 ] . $matches[ 3 ];
            // Convert to member number via API
            $data = json_decode( @file_get_contents( self::$apiFeedSocnr . $userId ) );
            if ( $data === FALSE ) {
                logger( __METHOD__ . " Cannot authenticate user. No contact to API.", E_ERROR );
                return FALSE;
            } elseif ( $data->results ) {
                $userId = $data->results[ 0 ]->cint_username;
            } else {
                return [ "authenticated" => FALSE, "section" => NULL ];
            }
        } elseif ( filter_var( $userId, FILTER_VALIDATE_EMAIL ) ) {
            // $userId given as email address. Look it up in the users table
            $stmt = self::$db->prepare( "SELECT userId FROM users WHERE mail=? LIMIT 1" );
            $stmt->execute( [ $userId ] );
            if ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) $userId = $row->userId;
            else {
                return [ "authenticated" => false, "section" => NULL ];
            }
        }
        // Check password via API and get home section
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n" .
                             "Cache-Control: no-cache\r\n".
                "Ocp-Apim-Subscription-Key: " . self::$apiAuthKey . "\r\n",
                'method'  => 'POST',
                'content' => json_encode( [ 'membernumber' => $userId, 'password' => $password ] )
            ]
        ];
        $context  = stream_context_create( $options );
        $result = @file_get_contents( self::$apiAuthUrl, false, $context );
        if ( $result === FALSE ) {
            logger( __METHOD__ . " Can't verify password via API. No answer from API.", E_ERROR );
            return FALSE;
        }
        $result = json_decode( $result );
        if ( isset( $result->error ) ) {
            logger( __METHOD__ . " Error from login API: " . $result->error, E_ERROR );
            return FALSE;
        }
        return [
            "authenticated" => $result->isMember,
            "userId" => $userId,
            "section" => $result->isMemberOfLokalavdelning
        ];
    }
    

    /**
     * Get a list of all users without creating User objects.
     * This avoids sending many queries to the API
     * @return [[int id, string name], ...]
     */
    public function getAllUsers() {
        $stmt = self::$db->query( "SELECT userId, name FROM users ORDER BY name" );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }
    
    
    /**
     * Get all users complying to a search term. Name and member ID will be searched.
     * @param string|int $q Search term
     * @return [[int id, string name], ...] Returns an array with IDs and names rather than User objects, avoiding many API requests
     */
    public function findUser($q) {
        $stmt = self::$db->prepare( "SELECT userId, name FROM users WHERE userId LIKE ? OR name LIKE ?" );
        $stmt->execute( [ "%$q%", "%$q%" ] );
        $ret = [];
        while ( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
            $ret[] = [ "userId" => $row[ 'userId' ], "name" => htmlspecialchars( $row[ 'name' ] ) ];
        }
        return $ret;
    }
    
    
    /**
     * Takes an uploaded image file, resizes it, makes a thumbnail, and returns both versions as strings.
     * @param $_FILES[x] $file Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of the thumbnail.
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @return ['image'=>string, 'thumb'=>string, 'error'=>string] String representations
     *  of a full-size and a thumbnail version of the image.
     */
    protected function imgFileToString( $file, $maxSize = 0, $thumbSize = 80, $maxFileSize = 0 ) {
        if ( !is_uploaded_file( $file[ 'tmp_name' ] ) ) {
            logger( __METHOD__ . " Trying to set image to a file which is not an uploaded file.", E_WARNING );
            return [ "error" => "Ogiltig begäran." ];
        }
        // reject files that are too big
        if ( $maxFileSize ) {
            if ( filesize( $file[ 'tmp_name' ] ) > $maxFileSize ) return array( "error" => "Filen är för stor. Maximal accepterad storlek är " . round( $maxFileSize / 1024 / 1024, 0 ) . " kB." );
        }
        // Get the picture and its size
        $src = @imagecreatefromstring( file_get_contents( $file[ 'tmp_name' ] ) );
        if ( !$src ) return [ "error" => "Filformatet stöds inte. Försök med en jpg- eller png-bild." ];
        $size = @getimagesize( $file[ 'tmp_name' ] );
        if ( !$size ) {
            logger( "Failed to load image for resizing.", E_WARNING );
            return [ "error" => "Kan inte läsa filformatet." ];
        }
        $ratio = $size[ 0 ] / $size[ 1 ];
        if ( $maxSize && ( $size[ 0 ] > $maxSize || $size[ 1 ] > $maxSize ) ) { // Resize
            if ( $ratio > 1 ) { // portrait
                $tmp = imagecreatetruecolor( $maxSize, $maxSize / $ratio );
                imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $maxSize, $maxSize / $ratio, $size[ 0 ], $size[ 1 ] );
            } else { // landscape
                $tmp = imagecreatetruecolor( $maxSize * $ratio, $maxSize );
                imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $maxSize * $ratio, $maxSize, $size[ 0 ], $size[ 1 ] );
            }
        } else {
            $tmp = $src;
        }
        // Get rescaled jpeg picture as string
        ob_start();
        imagejpeg( $tmp );
        $image = ob_get_clean();
        // Make a square thumbnail
        $tmp = imagecreatetruecolor( $thumbSize, $thumbSize );
        $bg = imagecolorallocatealpha( $tmp, 255, 255, 255, 127 );
        imagefill( $tmp, 0, 0, $bg );
        $offset = ( $size[ 0 ] - $size[ 1 ] ) / 2 / ( $size[ 0 ] + $size[ 1 ] ) * $thumbSize;
        imagecopyresampled( $tmp, $src, -$offset, $offset, 0, 0, $thumbSize + 2 * $offset, $thumbSize - 2 * $offset, $size[ 0 ], $size[ 1 ]);
        // Get thumbnail as string
        ob_start();
        imagepng( $tmp );
        $thumb = ob_get_clean();
        return [ "image" => $image, "thumb" => $thumb ];
    }
    
    
    /**
     * Creates a new one-time token
     * @param string $useFor Key designating what the token shall be used for
     * @param int $forId Entity ID the token shall be valid for
     * @param string $data Additional data
     * @param int $ttl TTL for the token in seconds
     * @throws \Exception if database operation fails
     * @return string The generated token
     */
    protected function createToken( string $useFor, int $forId, string $data = "", int $ttl = 86400 ) {
        // generate 40 character random token
        for ( $token = '', $i = 0, $z = strlen( $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ) - 1; $i < 40; $x = rand( 0, $z ), $token .= $a[ $x ], $i++ );
        $stmt = self::$db->prepare( "REPLACE INTO tokens SET token=SHA1('$token'), ttl=$ttl, useFor=:useFor, forId=:forId, data=:data" );
        if ( !$stmt->execute( [
            ":data" => $data,
            ":useFor" => $useFor,
            ":forId" => $forId,
        ] ) ) {
            logger( __METHOD__ . " Failed to create token. " . $stmt->errorInfo()[ 2 ], E_ERROR );
            throw new \Exception( ( string ) $stmt->errorInfo()[ 2 ] );
        }
        return $token;
    }
    
    /**
     * Delete a token from database
     * @param string $token
     * @return bool TRUE on success
     */
    public function deleteToken( string $token ) {
        $stmt = self::$db->prepare( "DELETE FROM tokens WHERE token=?" );
        return $stmt->execute( array( $token ) );
    }
    
    /**
     * Get stored information for a given token.
     * @param string $token The token
     * @throws \Exception if token not found or expired
     * @return object Object { unixtime, token, timestamp, ttl, useFor, forId, data }
     */
    public function getToken( string $token ) {
        $stmt = self::$db->query( "SELECT UNIX_TIMESTAMP(timestamp) AS unixtime, tokens.* FROM tokens WHERE token='" . sha1( $token ) . "'" );
        if ( !$row = $stmt->fetch( PDO::FETCH_OBJ ) ) throw new \Exception( "Ogiltig kod." );
        elseif ( time() > $row->unixtime + $row->ttl ) throw new \Exception( "Koden har förfallit." );
        else return $row;
    }
    
    /**
     * Formats the given amount of bytes in a human-readable way
     * @param int $bytes
     * @param int $precision Number of digits after point
     * @return string
     */
    static function formatBytes( int $bytes, int $precision = 1 ) {
        $units = [ "B", "kB", "MB", "GB", "TB" ];
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        $bytes /= ( 1 << ( 10 * $pow ) );
        return round( $bytes, $precision ) . " " . $units[ $pow ];
    }
    
    /**
     * Formats a date span as a read friendly string.
     * mån 2020-08-24 kl 07:00-21:00
     * mån 2020-08-24 kl 07:00 till tis 2020-08-25 kl 21:00
     * @param int $start Unix timestamp of start time
     * @param int $end Unix timestamp of end time
     * @param bool $includeWeekday Whether to include the weekday
     * @return string
     */
    static function formatDateSpan( int $start, int $end, bool $includeWeekday = false ) {
        $fmt_start = new \IntlDateFormatter( 'sv-SE', \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, self::$timezone, null );
        $fmt_end = new \IntlDateFormatter( 'sv-SE', \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, self::$timezone, null );
        $wd = $includeWeekday ? "E " : "";
        if ( $fmt_start->format( $start ) == $fmt_end->format( $end ) ) {
            // Start and end on same day
            $fmt_start->setPattern( "{$wd}y-MM-dd 'kl' HH:mm-" );
            $fmt_end->setPattern( "HH:mm" );
        } else {
            $fmt_start->setPattern( "{$wd}y-MM-dd 'kl' HH:mm 'till' " );
            $fmt_end->setPattern( "{$wd}y-MM-dd 'kl' HH:mm" );
        }
        return $fmt_start->format( $start ) . $fmt_end->format( $end );
    }

    /**
     * Return a human readable representation of the offset of a reminder, split up in months, weeks, days, hours and minutes.
     *
     * @param integer $offset Number of seconds before/after a booking
     * @return string
     */
    static function formatReminderOffset( int $offset ) : string {
        $beforeAfter = $offset < 0 ? "före" : "efter";
        $offset = abs( $offset );
        $parts = [];
        $months = intdiv( $offset, 2592000 ); // 2592000 seconds per month
        if ( $months ) $parts[] = $months . ( $months == 1 ? " månad" : " månader" );
        $weeks = intdiv( $offset - $months * 2592000, 604800 ); // 604800 seconds per week
        if ( $weeks ) $parts[] = $weeks . ( $weeks == 1 ? " vecka" : " veckor" );
        $days = intdiv( $offset - $months * 2592000 - $weeks * 604800, 86400 ); // 86400 seconds per day
        if ( $days ) $parts[] = $days . ( $days == 1 ? " dag" : " dagar" );
        $hours = intdiv( $offset - $months * 2592000 - $weeks * 604800 - $days * 86400, 3600 ); // 3600 seconds per hour
        if ( $hours ) $parts[] = $hours . ( $hours == 1 ? " timme" : " timmar" );
        $minutes = intdiv( $offset - $months * 2592000 - $weeks * 604800 - $days * 86400 - $hours * 3600, 60 );
        if ( $minutes ) $parts[] = $minutes . ( $minutes == 1 ? " minut" : " minuter" );
        if ( count( $parts ) ) return implode( ", ", $parts ) . " " . $beforeAfter;
        return "vid";
    }
    
    /**
     * Add a new poll
     * @return \FFBoka\Poll
     */
    public function addPoll() {
        self::$db->exec( "INSERT INTO polls SET question='Ny enkät', choices='" . json_encode( [ "Option 1", "Option 2" ] ) . "', votes='[0,0]'" );
        return new Poll( self::$db->lastInsertId() );
    }
    
    /**
     * Get all polls
     * @param string $only Set to "active" to only get non-expired polls, or "expired" to only get expired polls
     * @return \FFBoka\Poll[]
     */
    public function polls( ?string $only = NULL ) {
        switch ( $only ) {
        case "active":
            $stmt = self::$db->query( "SELECT pollId FROM polls WHERE expires IS NULL OR expires > NOW()" );
            break;
        case "expired":
            $stmt = self::$db->query( "SELECT pollId FROM polls WHERE expires <= NOW() ORDER BY expires DESC" );
            break;
        default:
            $stmt = self::$db->query( "SELECT pollId FROM polls ORDER BY expires IS NULL, expires DESC" );
        }
        $polls = [];
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $polls[] = new Poll( $row->pollId );
        }
        return $polls;
    }

    /**
     * Get the next available ID for booking series
     * @return int
     */
    public function getNextRepeatId() {
        $stmt = self::$db->query( "SELECT 0+MAX(repeatId) lastId FROM bookings" );
        $row = $stmt->fetch( PDO::FETCH_OBJ );
        if ( is_null( $row->lastId ) ) return 1;
        else return $row->lastId + 1;
    }


    /**
     * Send all emails from the mail queue
     * @param array $mailOptions Array with email config, containing the keys: from, fromName, replyTo, SMTPHost, SMTPPort, SMTPUser, SMTPPass
     */
    public function sendQueuedMails( array $mailOptions ) {
        $stmt = self::$db->query( "SELECT * FROM mailq" );
        $rows = $stmt->fetchAll( PDO::FETCH_OBJ );
        if  (count( $rows ) ) logger( __METHOD__ . " Sending mails from mail queue..." );
        foreach ( $rows as $row ) {
            if ( $row->fromName ) $mailOptions[ 'fromName' ] = $row->fromName;
            if ( $row->replyTo ) $mailOptions[ 'replyTo' ] = $row->replyTo;
            $this->sendMail(
                $row->to, // To
                $row->subject, // Subject
                $row->body, // Body
                [], // replace
                json_decode( $row->attachments ), // attachments
                $mailOptions,
                false // dont queue, send now
            ) && self::$db->exec( "DELETE FROM mailq WHERE mailqId={$row->mailqId}" );
        }
    }

    /**
     * Send an email
     * @param string $to Address where to send the mail
     * @param string $subject
     * @param string $templateBody Name of template file to use, without extension. The file must be in the templates folder.
     * If there is a file named $template.html, it will be used. Otherwise, $template will be used as the message body.
     * @param array $replace Array of strings [search=>replace] to be replaced in the body|template
     * @param array $attachments optional Array of attachments with the members 'path' (relative to boka root) and 'filename'
     * @param array $mailOptions Optional array with email config, containing the keys: from, fromName, replyTo, SMTPHost, SMTPPort, SMTPUser, SMTPPass. Optional if $queue=true.
     * @param bool $queue Add to queue (true, default) or send immediately (false)
     * @return bool True or queue ID on success, false if mail could not be sent/queued.
     */
    public function sendMail( string $to, string $subject, string $templateBody, array $replace = [], array $attachments = [], array $mailOptions = [], bool $queue = true ) {
        // Get template
        if ( is_readable( __DIR__ . "/../../templates/$templateBody.html" ) ) {
            $body = file_get_contents( __DIR__ . "/../../templates/$templateBody.html" );
        } else {
            $body = $templateBody;
        }
        // Replace placeholders
        $body = str_replace( array_keys( $replace ), array_values( $replace ), $body );

        if ( $queue ) {
            // Place mail into mail queue
            $stmt = self::$db->prepare( "INSERT INTO mailq SET fromName=:fromName, `to`=:to, replyTo=:replyTo, subject=:subject, body=:body, attachments=:attachments" );
            if ( !$stmt->execute( array(
                ":fromName" => $mailOptions[ 'fromName' ],
                ":to"       => $to,
                ":replyTo"  => $mailOptions[ 'replyTo' ],
                ":subject"  => $subject,
                ":body"     => $body,
                ":attachments" => json_encode( $attachments )
            ) ) ) {
                logger( __METHOD__ . " Failed to queue mail. " . $stmt->errorInfo()[ 2 ], E_ERROR );
                return false;
            }
            return self::$db->lastInsertId();
        } else {
            // Send message now
            try {
                $mail = new PHPMailer( true );
                $mail->XMailer = ' '; // Don't advertise that we are using PHPMailer
                // Add attachments
                foreach ( $attachments as $att ) {
                    $mail->addAttachment( __DIR__ . "/../../" . $att[ 'path' ], $att[ 'filename' ] );
                }
                //Server settings
                $mail->SMTPDebug = 0;
                $mail->Debugoutput = function( $str, $level ) { logger( "PHPMailer level $level, message: $str" ); };
                $mail->isSMTP();
                $mail->Host = $mailOptions[ 'SMTPHost' ];
                $mail->Port = $mailOptions[ 'SMTPPort' ];
                $mail->SMTPAuth = true;
                $mail->Username = $mailOptions[ 'SMTPUser' ];
                $mail->Password = $mailOptions[ 'SMTPPass' ];
                $mail->SMTPSecure = 'tls';   // Enable TLS encryption, `ssl` also accepted
                // Message content
                $mail->CharSet ="UTF-8";
                $mail->setFrom( $mailOptions[ 'from' ], $mailOptions[ 'fromName' ] );
                $mail->Sender = $mailOptions[ 'SMTPUser' ];
                $mail->addAddress( $to );
                $mail->addReplyTo( $mailOptions[ 'replyTo' ] );
                $mail->isHTML( true );
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags( str_replace( array( "</p>", "<br>" ), array( "</p>\r\n\r\n", "\r\n" ), $body ) );
                if ( !$mail->send() ) {
                    logger( __METHOD__ . " Failed to send mail '$subject' to $to. " . $mail->ErrorInfo, E_WARNING );
                    return false;
                }
            } catch ( Exception $e ) {
                logger( __METHOD__ . " Failed to send mail '$subject' to $to. " . $mail->ErrorInfo, E_WARNING );
                return false;
            }
            logger( __METHOD__ . " Mail '$subject' has been sent to $to." );
            return true;
        }
    }

    /**
     * Compare 2 strings by how exactly $needle is contained in $haystack.
     *
     * @param string $haystack 
     * @param string $needle
     * @return float Number between 0 (no match) and 100 (perfect match) If $haystack starts with $needle,
     *  returns 100, if it contains $needle otherwise, 90.
     */
    public function compareStrings( string $haystack, string $needle ) : int {
        $pos = stripos( $haystack, $needle );
        if ( $pos === false ) { // No direct match. Calculate string similarity.
            similar_text( strtolower( $haystack ), strtolower( $needle ), $percent );
            return $percent;
        } elseif ( $pos === 0 ) { // Best match: caption starts with $search
            return 100;
        } else { // Medium match: caption contains $search
            return 90;
        }
    }
}
