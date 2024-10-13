<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;

use Exception;
use PDO;

/**
 * Class Category
 * Categories in the booking system, containing items.
 */
class Category extends FFBoka {
    private $_id;
    private $_sectionId;
    
    /**
     * Initialize category with ID and get some static properties.
     * @param int $id ID of requested category. If 0|FALSE|"" returns a dummy cateogory with id=0.
     * @throws \Exception if $id is given but not valid.
     */
    public function __construct( $id ){
        if ( $id ) { // Try to return an existing category from database
            $stmt = self::$db->prepare( "SELECT catId, sectionId FROM categories WHERE catId=?" );
            $stmt->execute( [ $id ] );
            if ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                $this->_id = $row->catId;
                $this->_sectionId = $row->sectionId;
            } else {
                logger( __METHOD__ . " Tried to get non-existing category with ID $id", E_ERROR );
                throw new Exception( "Can't instatiate category with ID $id." );
            }
        } else { // Return an empty object without link to database
            $this->_id = 0;
            return;
        }
    }
    
    /**
     * Setter function for category properties.
     * 
     * @param string $name Property name
     * @param string|NULL $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set( $name, $value ) {
        switch ( $name ) {
            case "sectionId":
                // May only be set on dummy category
                if ( $this->id ) throw new Exception( "Cannot change section for existing category." );
                $this->_sectionId = $value;
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
                if ( !$this->id ) {
                    logger( __METHOD__ . " Cannot set property $name on dummy category.", E_ERROR );
                    return false;
                }
                // For contact data, only allow either member as contact person, or single data
                if ( $name == "contactName" || $name == "contactPhone" || $name == "contactMail" ) {
                    self::$db->exec ("UPDATE categories SET contactUserId=NULL WHERE catId={$this->id}" );
                } elseif ( $name == "contactUserId" ) {
                    self::$db->exec( "UPDATE categories SET contactName='', contactPhone='', contactMail='' WHERE catId={$this->id}" );
                }
                $stmt = self::$db->prepare( "UPDATE categories SET $name=:value WHERE catId={$this->id}" );
                $stmt->bindValue( ":value", $value ); // Use bindValue so contactUserId can be set to null
                if ( $stmt->execute() ) return $value;
                logger( __METHOD__ . " Failed to set Category property $name. " . $stmt->errorInfo()[ 2 ], E_ERROR );
                break;
            default:
                logger( __METHOD__ . " Use of undefined Category property $name.", E_ERROR );
                throw new \Exception( "Use of undefined Category property $name" );
        }
        return false;
    }

    /**
     * Set the category image and thumbnail from uploaded image file.
     * 
     * @param $_FILES[x] $imgFile Member of $_FILES array
     * @param int $maxSize Image will be scaled down if any dimension is bigger than this. 0=no limit
     * @param int $thumbSize Size of thumbnail
     * @param int $maxFileSize If file is bigger than this, it will be rejected. 0=no limit
     * @return boolean|string True on success, error message on failure
     */
    public function setImage( $imgFile, $maxSize = 0, $thumbSize = 80, $maxFileSize = 0 ) {
        if ( !$this->id ) {
            logger( __METHOD__ . " Trying to set image on dummy category.", E_ERROR );
            return "Cannot set image on dummy category.";
        }
        if ( !file_exists( __DIR__ . "/../../img/cat" ) ) {
            if ( !mkdir( __DIR__ . "/../../img/cat", 0777, true ) ) {
                logger( __METHOD__ . " Failed to create directory to save category image.", E_ERROR );
                return "Kan inte spara bilden. Kan inte skapa mappen för kategoribilder (./img/cat). Kontakta administratören.";
            }
        }
        $images = $this->imgFileToString( $imgFile, $maxSize, $thumbSize, $maxFileSize );
        if ( $images[ 'error' ] ) return $images[ 'error' ];
        // Save thumb to database
        $stmt = self::$db->prepare( "UPDATE categories SET thumb=? WHERE catID={$this->id}" );
        if ( !$stmt->execute( array( $images[ 'thumb' ] ) ) ) {
            logger( __METHOD__ . " Failed to save thumbnail to database. " . $stmt->errorInfo()[ 2 ], E_ERROR );
            return "Kan inte spara miniaturbilden i databasen.";
        }
        // Save full size image to file system
        if ( file_put_contents( __DIR__ . "/../../img/cat/{$this->id}", $images[ 'image' ] ) === FALSE ) return "Kan inte spara originalbilden på ./img/cat. Är mappen skrivskyddad? Kontakta administratören.";
        return TRUE;
    }

    /**
     * Getter function for category properties.
     * 
     * @param string $name Name of the property
     * @return string|array|NULL Value of the property.
     */
    public function __get( $name ) {
        switch ( $name ) {
            case "id":
                return $this->_id;
            case "sectionId":
                return $this->_sectionId;
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
                if ( !$this->id ) return "";
                $stmt = self::$db->query( "SELECT $name FROM categories WHERE catId={$this->id}" );
                $row = $stmt->fetch( PDO::FETCH_OBJ );
                return $row->$name;
            case "contactType":
                if ( !is_null( $this->contactUserId ) ) return "user";
                if ( $this->contactName != "" || $this->contactPhone != "" || $this->contactMail != "" ) return "manual";
                if ( is_null( $this->parentId ) || $this->parent()->contactType == "unset" ) return "unset";
                return "inherited";
            case "itemCount":
                if ( !$this->id ) return 0;
                $stmt = self::$db->query( "SELECT itemId FROM items WHERE catId={$this->id}" );
                return $stmt->rowCount();
            default:
                logger( __METHOD__ . " Use of undefined Category property $name.", E_ERROR );
                throw new \Exception( "Use of undefined Category property $name" );
        }
    }

    /**
     * Get all pre-booking messages of this and any parent categories.
     * 
     * @return string[] Array of strings containing any pre-booking messages of this and parent categories
     */
    public function prebookMsgs() : array {
        if ( is_null( $this->parentId ) ) {
            if ( $this->prebookMsg ) return array( $this->prebookMsg );
            else return array();
        } else {
            $ret = $this->parent()->prebookMsgs();
            if ( $this->prebookMsg ) $ret[] = $this->prebookMsg;
            return $ret;
        }
    }

    /**
     * Get all post-booking messages of this and any parent categories.
     * 
     * @return string[] Array of strings containing any post-booking messages of this and parent categories
     */
    public function postbookMsgs(): array {
        if ( is_null( $this->parentId ) ) {
            if ( $this->postbookMsg ) return array( $this->postbookMsg );
            else return array();
        } else {
            $ret = $this->parent()->postbookMsgs();
            if ( $this->postbookMsg ) $ret[] = $this->postbookMsg;
            return $ret;
        }
    }
    
    /**
     * Get the section this category belongs to.
     * 
     * @return \FFBoka\Section
     */
    public function section() : \FFBoka\Section {
        return new Section( $this->sectionId );
    }

    /**
     * Get the contact user of the category.
     * 
     * @return \FFBoka\User
     */
    public function contactUser() : \FFBoka\User {
        return new User( $this->contactUserId );
    }

    /**
     * Get HTML formatted, safe string with contact information.
     * 
     * @return string If member is set as contact user, the member's data is returned, otherwise the name,
     *      mail and phone set in category. If nothing is set, the parent's contact data is returned.
     */
    public function contactData() : string {
        if ( is_null( $this->contactUserId ) ) {
            if ( $this->contactName == "" && $this->contactPhone == "" && $this->contactMail == "" && !is_null( $this->parentId ) ) return $this->parent()->contactData();
            $ret = array();
            if ( $this->contactName ) $ret[] = htmlspecialchars( $this->contactName );
            if ( $this->contactPhone ) $ret[] = "☎ " . htmlspecialchars( $this->contactPhone );
            if ( $this->contactMail ) $ret[] = "✉ " . htmlspecialchars( $this->contactMail );
            return implode( "<br>", $ret );
        } else {
            return $this->contactUser()->contactData();
        }
    }

    /**
     * Get the parent category if exists.
     * 
     * @return \FFBoka\Category|NULL
     */
    public function parent() : ?\FFBoka\Category {
        if ( $pId = $this->parentId ) return new Category( $pId );
        else return NULL;            
    }
    
    /**
     * Remove category.
     * 
     * @throws \Exception on failure
     * @return boolean TRUE on success, throws an exception otherwise.
     */
    public function delete() : bool {
        // Full size images will be removed from file system by cron
        if ( self::$db->exec( "DELETE FROM categories WHERE catId={$this->id}" ) ) {
            return TRUE;
        } else {
            logger( __METHOD__ . " Failed to delete category {$this->id}.", E_ERROR );
            throw new \Exception( "Failed to delete category." );
        }
    }
    
    /**
     * Get all chosen booking questions, including the ones specified for parent objects.
     * 
     * @param bool $inherited Mark questions on this cat level as inherited.
     * Questions from parent objects will always be marked as inherited.
     * @return Array of {inherited, required} where the key is the ID of the question.
     * If a question is set on several levels, the lowest level setting is returned.
     */
    public function getQuestions( bool $inherited = FALSE ) : array {
        if ( $this->parentId ) $ret = $this->parent()->getQuestions( TRUE );
        else $ret = array();
        $stmt = self::$db->query( "SELECT questionId, required FROM cat_questions WHERE catId={$this->id}" );
        while ( $row = $stmt->fetch( \PDO::FETCH_OBJ ) ) {
            $ret[ $row->questionId ] = (object) [ "inherited" => $inherited, "required" => (bool)$row->required ];
        }
        return $ret;
    }
    
    /**
     * Add to or update a question in the category.
     * 
     * @param int $id ID of the question to add or update
     * @param bool $required Whether the used is required to answer the question
     * @return boolean False on failure
     */
    public function addQuestion( int $id, bool $required = FALSE ) : bool {
        $stmt = self::$db->prepare( "INSERT INTO cat_questions SET questionId=:questionId, catId={$this->id}, required=:required ON DUPLICATE KEY UPDATE required=VALUES(required)" );
        $stmt->bindValue( "questionId", $id, \PDO::PARAM_INT );
        $stmt->bindValue( ":required", $required, \PDO::PARAM_BOOL );
        if ( $stmt->execute() ) return true;
        logger( __METHOD__ . " Failed to add question $id to category {$this->id}. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    }
    
    /**
     * Remove a question from the category.
     * 
     * @param int $id
     * @return boolean
     */
    public function removeQuestion( int $id ) : bool {
        $stmt = self::$db->prepare( "DELETE FROM cat_questions WHERE questionId=? AND catId={$this->id}" );
        if ( $stmt->execute( array( $id ) ) ) return true;
        logger( __METHOD__ . " Failed to remove question $id from category {$this->id}. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    }
    
    
    /**
     * Add an attachment file to the category. The caption will be set to the file name.
     * 
     * @param $_FILES[x] $file A member of the $_FILES array
     * @param array $allowedFileTypes Associative array of $extension=>$icon_filename pairs.
     * @param int $maxSize The maximum accepted file size in bytes. Defaults to 0 (no limit)
     * @throws \Exception if trying to upload files with unallowed file types, too big files, or if this is a dummy category.
     * @return integer ID of the added file
     */
    public function addFile( $file, $allowedFileTypes, int $maxSize = 0 ) : int {
        if ( !$this->id ) {
            logger( __METHOD__ . " Cannot add file to dummy category.", E_ERROR );
            throw new \Exception( "Internt fel." );
        }
        if ( $maxSize && filesize( $file[ 'tmp_name' ] ) > $maxSize ) { 
            throw new \Exception( "Filen är för stor. Största tillåtna storleken är " . self::formatBytes( $maxSize ) . "." );
        }
        if ( !is_uploaded_file( $file[ 'tmp_name' ] ) ) {
            logger( __METHOD__ . " Trying to set non-uploaded file as attachment.", E_WARNING );
            throw new \Exception( "This is not an uploaded file." );
        }
        $ext = strtolower( pathinfo( $file[ 'name' ], PATHINFO_EXTENSION ) );
        if ( !array_key_exists( $ext, $allowedFileTypes ) ) {
            throw new \Exception( "Du kan inte ladda upp filer av typen $ext. Bara följande filtyper tillåts: " . implode( ", ", array_keys( $allowedFileTypes ) ) );
        }
        $md5 = md5_file( $file[ 'tmp_name' ] );
        // Add post to database
        $stmt = self::$db->prepare( "INSERT INTO cat_files SET catId={$this->id}, filename=:filename, caption=:caption, md5='$md5'" );
        if ( !$stmt->execute( array( ":filename" => $file[ 'name' ], ":caption" => $file[ 'name' ] ) ) ) {
            unlink( $file[ 'tmp_name' ] );
            throw new \Exception( "Filen kunde inte sparas, eftersom samma fil redan har laddats upp till denna kategori." );
        }
        $newId = self::$db->lastInsertId();
        // Move file
        if ( !is_dir( __DIR__ . "/../../uploads" ) ) {
            if ( !mkdir( __DIR__ . "/../../uploads" ) ) {
                logger( __METHOD__ . " Failed to create directory for uploaded files.", E_ERROR );
                throw new \Exception( "Kan inte spara filen. Kontakta systemadministratören." );
            }
        }
        if ( !move_uploaded_file( $file[ 'tmp_name' ], __DIR__ . "/../../uploads/$newId" ) ) {
            logger( __METHOD__ . " Failed to save uploaded file.", E_ERROR );
            throw new \Exception( "Kunde inte spara filen." );
        }
        return $newId;
    }
    
    /**
     * Set property for an attached file.
     * 
     * @param int $fileId
     * @param string $name The name of the property to set. Must be one of caption|filename|displayLink|attachFile
     * @param mixed $value The value of the property to set
     * @return bool True on success, or FALSE on failure
     */
    public function setFileProp( int $fileId, string $name, $value ) : bool {
        switch ( $name ) {
        case "caption":
        case "filename":
        case "displayLink":
        case "attachFile":
            $stmt = self::$db->prepare( "UPDATE cat_files SET $name=:$name WHERE fileId=:fileId AND catId={$this->id}" );
            if ( $name == "displayLink" || $name == "attachFile" ) $stmt->bindValue( ":$name", $value, \PDO::PARAM_BOOL );
            else $stmt->bindValue( ":$name", $value, \PDO::PARAM_STR );
            $stmt->bindValue( ":fileId", $fileId, \PDO::PARAM_INT );
            if ( $stmt->execute() ) return true;
            logger( __METHOD__ . " Failed to set File property $name on file $fileId. " . $stmt->errorInfo()[ 2 ], E_ERROR );
            return false;
            break;
        default: 
            logger( __METHOD__ . " Trying to set invalid property $name on file attachment {$this->id}", E_ERROR );
            return false;
        }
    }
    
    /**
     * Delete an uploaded category attachment file.
     * 
     * @param int $fileId ID of the file to be deleted
     * @return bool True on success, False on failure
     */
    function removeFile( int $fileId ) : bool {
        if ( self::$db->exec( "DELETE FROM cat_files WHERE catId={$this->id} AND fileId=$fileId" ) ) {
            if ( unlink( __DIR__ . "/../../uploads/$fileId" ) ) return true;
            logger( __METHOD__ . " Failed to unlink attachment file " . realpath( __DIR__ . "/../../uploads/$fileId" ), E_WARNING );
            return true;
        }
        logger( __METHOD__ . " Failed to delete attachment record $fileId from DB. " . self::$db->errorInfo()[ 2 ], E_WARNING );
        return false;
    }
    
    /**
     * Get all attachments for the category.
     * 
     * @param bool $includeParents Whether to also return attachments of superordinate categories
     * @return array of objects {fileId, catId, filename, md5, displayLink, attachFile}.
     *    The array keys are the md5 checksums, so no double files should be returned.
     */
    public function files( bool $includeParents = FALSE ) : array {
        $ret = array();
        if ( $includeParents && !is_null( $parent = $this->parent() ) ) $ret = $parent->files( TRUE );
        $stmt = self::$db->query( "SELECT * FROM cat_files WHERE catId={$this->id}" );
        while ( $row = $stmt->fetch( \PDO::FETCH_OBJ ) ) {
            $ret[ $row->md5 ] = $row;
        }
        return $ret;
    }
    
    
    /**
     * Get the granted access level for given user, taking into account inherited access.
     * 
     * @param \FFBoka\User $user
     * @param bool $tieInSectionAdmin Whether to also include admin role set on section level
     * @return integer Bitfield of granted access rights. For an empty (fake) category, returns ACCESS_CATADMIN.
     */
    public function getAccess( User $user, bool $tieInSectionAdmin = TRUE ) : int {
        // On fake category, assume full cat access and don't go further
        if ( !$this->id ) return FFBoka::ACCESS_CATADMIN;
        $access = FFBoka::ACCESS_NONE;
        // Get group permissions for this category
        $access = $access | $this->getAccessRecursive( $user, "accessExternal" );
        if ( $user->id ) {
            $access = $access | $this->getAccessRecursive( $user, "accessMember" );
            if ( $user->sectionId == $this->sectionId ) $access = $access | $this->getAccessRecursive( $user, "accessLocal" );
            // Add permissions for assignment groups
            $access = $access | $this->getAccessRecursive( $user, "assignment" );
            // Add individual permissions
            $access = $access | $this->getAccessRecursive( $user, "individual" );
        }
        if ( $tieInSectionAdmin ) {
            // Tie in access rules from section
            $access = $access | $this->section()->getAccess( $user );
        }
        return $access;
    }

    /**
     * Retrieves the effective access level of the specified $type for the specified $user,
     * taking into account inherited permissions if the current category does not define
     * an explicit permission.
     *
     * @param User $user
     * @param string $type One of [accessLocal|accessMember|accessExternal|assignment|individual]
     * @throws \Exception if $type is invalid
     * @return integer
     */
    private function getAccessRecursive( User $user, string $type ) : int {
        switch ( $type ) {
        case "accessLocal":
        case "accessMember":
            if ( !$user->id ) return FFBoka::ACCESS_NONE;
            // continue to accessExternal
        case "accessExternal":
            if ( !is_null( $this->$type ) ) return $this->$type;
            break;
        case "assignment":
            $access = FFBoka::ACCESS_NONE;
            $groupPerms = array_column( $this->groupPerms( TRUE ), "access", "assName" );
            if ( isset( $_SESSION[ 'assignments' ][ $this->sectionId ] ) ) {
                if ( array_key_exists( "Valfritt uppdrag", $groupPerms ) ) $access = $access | $groupPerms[ "Valfritt uppdrag" ];
                foreach ( $_SESSION[ 'assignments' ][ $this->sectionId ] as $assName ) {
                    if ( array_key_exists( $assName, $groupPerms ) ) $access = $access | $groupPerms[ $assName ];
                }
            }
            return $access;
        case "individual":
            $stmt = self::$db->query( "SELECT access FROM cat_admins WHERE catId={$this->id} AND userId={$user->id}" );
            if ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) return $row->access;
            break;
        default:
            throw new \Exception( "Invalid access type $type passed to " . __METHOD__ );
        }
        if ( $this->parentId ) return $this->parent()->getAccessRecursive( $user, $type );
        return FFBoka::ACCESS_NONE;
    }
    
    /**
     * Get all effective permissions for the category, including inherited ones
     * which are not overwritten by explicit ones.
     *
     * @return array of ['level', 'inherited', 'name']. The indices of the array indicate
     *  the scope of the permission and are either of 'accessExternal', 'accessMember',
     *  'accessLocal', an assignment name (string) or a userId (int). The 'level' member
     *  contains the access level. The 'inherited' member is set to true for permissions
     *  which are inherited from superordinate categories. The 'name' member is only
     *  set for permissions for individual users and contains the real name of the user.
     */
    public function getAccessAll() : array {
        if ( $this->parentId ) {
            // get inherited access rights
            $access = $this->parent()->getAccessAll();
            // add inherited flags to each member
            foreach ( $access as &$acc ) $acc[ 'inherited' ] = true;
        } else $access = array();
        // Basic access
        if ( !is_null( $this->accessExternal ) ) $access[ 'accessExternal' ] = [ "level" => $this->accessExternal ];
        if ( !is_null( $this->accessMember ) ) $access[ 'accessMember' ] = [ "level" => $this->accessMember ];
        if ( !is_null( $this->accessLocal ) ) $access[ 'accessLocal' ] = [ "level" => $this->accessLocal ];
        // Group access
        foreach ( $this->groupPerms() as $perm ) {
            $access[ $perm[ 'assName' ] ] = [ "level" => $perm[ 'access' ] ];
        }
        // Individual access
        foreach ( $this->admins( FFBoka::ACCESS_NONE ) as $adm) {
            $access[ $adm[ 'userId' ] ] = [ "level" => $adm[ 'access' ], "name" => $adm[ 'name' ] ];
        }
        return $access;
    }
    
    /**
     * Set personal and assignment based access rights to category.
     * 
     * @param integer|string $id Either a numeric user id, or the name of an assignment
     * @param integer|null $access Access constant, e.g. FFBoka::ACCESS_CATADMIN, FFBoka::ACCESS_CONFIRM, FFBoka::ACCESS_NONE.
     *  If set to NULL, access is revoked
     * @return boolean True on success.
     */ 
    public function setAccess( $id, ?int $access ) : bool {
        if ( is_numeric( $id ) ) {
            $user = new User( $id ); // This will add user to database if not already there.
            if ( !$user->id ) {
                logger( __METHOD__ . " Cannot set access. Failed to add user $id to database." );
                return false;
            }
        }
        if ( is_null( $access ) ) { //revoke permission
            if ( is_numeric( $id ) ) {
                // Revoke permission for single user
                $stmt = self::$db->prepare( "DELETE FROM cat_admins WHERE catId={$this->id} AND userId=?" );
            } else {
                // Revoke permission for user group with assignment
                $stmt = self::$db->prepare( "DELETE FROM cat_perms WHERE catId={$this->id} AND assName=?" );
            }
            if ( $stmt->execute( [ $id ] ) ) return true;
            logger( __METHOD__ . " Failed to revoke access. " . $stmt->errorInfo()[ 2 ], E_ERROR );
            return false;
        }
        if ( is_numeric( $id ) ) {
            // Set permission for single user
            $stmt = self::$db->prepare( "INSERT INTO cat_admins SET catId={$this->id}, userId=:id, access=:access ON DUPLICATE KEY UPDATE access=VALUES(access)" );
        } else {
            // Set permission for user group with assignment
            $stmt = self::$db->prepare( "INSERT INTO cat_perms SET catId={$this->id}, assName=:id, access=:access ON DUPLICATE KEY UPDATE access=VALUES(access)" );
        }
        if ( $stmt->execute( [ ":id" => $id, ":access" => $access ] ) ) return true;
        logger( __METHOD__ . " Failed to set access. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    }
    
    /**
     * Retrieve all admins for category.
     * 
     * @param int $access Return all entries with at least this access level.
     * @param bool $inherit Even return admins from superordinate categories
     * @return array [userId, name, access]
     */
    public function admins( int $access = FFBoka::ACCESS_READASK, bool $inherit = FALSE ) : array {
        if ( !$this->id ) return array();
        $stmt = self::$db->prepare( "SELECT userId, name, access FROM cat_admins INNER JOIN users USING (userId) WHERE catId={$this->id} AND access>=? ORDER BY users.name" );
        $stmt->execute( array( $access ) );
        $admins = $stmt->fetchAll( PDO::FETCH_ASSOC );
        if ( $inherit && $this->parentId ) {
            // Tie in admins from parent category
            foreach ( $this->parent()->admins( $access, TRUE ) as $inh ) {
                if ( !in_array( $inh[ 'userId' ], array_column( $admins, "userId" ) ) ) $admins[] = $inh;
            }
        }
        return $admins;
    }
    
    /**
     * Retrieve all group permissions for category. If $inherit and there are set permissions for the same assignment on 
     * different levels, returns the setting from the lowest level (i.e. child level).
     * 
     * @param bool $inherit Even return permissions inherited from parents
     * @return array with members ['assName', 'access']
     */
    public function groupPerms( bool $inherit = FALSE ) : array {
        if ( !$this->id ) return array();
        $stmt = self::$db->query( "SELECT assName, access FROM cat_perms WHERE catId={$this->id} ORDER BY assName" );
        $perms = $stmt->fetchAll( PDO::FETCH_ASSOC );
        if ( $inherit && $this->parentId ) {
            // Tie in permissions from parent category only if not yet in list
            foreach ( $this->parent()->groupPerms( TRUE ) as $inh ) {
                if ( !in_array( $inh[ 'assName' ], array_column( $perms, "assName" ) ) ) $perms[] = $inh;
            }
        }
        return $perms;
    }
    
    /**
     * Check whether category or some subordinate category shall be shown to user.
     * 
     * @param \FFBoka\User $user
     * @param int $minAccess Ignore access settings lower than this level.
     *    Set to ACCESS_CONFIRM to check for visibility for admins.
     * @return boolean Returns TRUE for fake categories, and if the user has access to this or any subordinate category.
     */
    public function showFor( User $user, int $minAccess = FFBoka::ACCESS_READASK ) : bool {
        if ( !$this->id ) return TRUE;
        if ( $this->getAccess( $user ) >= $minAccess ) return TRUE;
        foreach ( $this->children() as $child ) {
            if ( $child->showFor( $user, $minAccess ) ) return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Get all direct sub-categories ordered by caption.
     * 
     * @return \FFBoka\Category[]
     */
    public function children() : array {
        if ( !$this->id ) return array();
        $stmt = self::$db->query( "SELECT catId FROM categories WHERE parentId={$this->id} ORDER BY caption" );
        $children = array();
        while ( $child = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $children[] = new Category( $child->catId );
        }
        return $children;
    }

    /**
     * Check whether the category has a subordinate category with given ID.
     * 
     * @param int $childId
     * @return boolean
     */
    function hasChild( int $childId ) : bool {
        foreach ( $this->children() as $child ) {
            if ( $child->id == $childId ) return true;
            if ( $child->hasChild( $childId ) ) return true;
        }
        return false;
    }
    
    /**
     * Get the path from section level.
     * 
     * @return array Array with members [int id, string caption] with category IDs and strings representing superordinate elements.
     *    The first element in the array has id 0 and 'LA xxx' as caption.
     */
    function getPath() : array {
        if ( $this->parentId ) $ret = $this->parent()->getPath();
        else $ret = array( [ 'id' => 0, 'caption' => "LA " . $this->section()->name ] );
        $ret[] = [ 'id' => $this->id, 'caption' => $this->caption ];
        return $ret;
    }

   
    /**
     * Get all items in the current category.
     * 
     * @return array|\FFBoka\Item[] Items sorted by caption
     */
    public function items() : array {
        if ( !$this->id ) return array();
        $stmt = self::$db->query( "SELECT itemId FROM items WHERE catId={$this->id}" );
        $items = array();
        while ( $item = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $items[] = new Item( $item->itemId );
        }
        // Sort
        usort( $items, function( $a, $b ) { return strnatcasecmp( $a->caption, $b->caption ); } );
        return $items;
    }
    
    /**
     * Add new resource to category.
     * 
     * @throws \Exception
     * @return \FFBoka\Item
     */
    public function addItem() : \FFBoka\Item {
        if ( self::$db->exec( "INSERT INTO items SET catId={$this->id}, caption='Ny resurs'" ) ) {
            return new Item( self::$db->lastInsertId() );
        } else {
            logger( __METHOD__ . " Failed to add item to category {$this->id}. " . self::$db->errorInfo()[ 2 ], E_ERROR );
            throw new \Exception( "Failed to create item." );
        }
    }

    /**
     * Look for $search string in category, including the category and item captions. Descends
     *  into child categories. Only includes categories accessible to the given user.
     * 
     * @param string $search The search string
     * @param User $user Used to determine access rights. Only categories where the user has read access will be searched.
     * @param int $minScore The minimal score needed for results to be included
     * @param int[] $matches Will be populated with an array containing matching category captions as keys and the corresponding hit distance (0-100) as value.
     */
    public function contains( string $search, User $user, int $minScore, array &$matches ) : void {
        foreach ( $this->children() as $child ) {
            if ( $child->showFor( $user ) ) {
                $child->contains( $search, $user, $minScore, $matches );
            }
        }
        if ( $this->getAccess( $user ) >= self::ACCESS_READASK ) {
            // Check category caption
            if ( ( $score = $this->compareStrings( $this->caption, $search ) ) >= $minScore) $matches[ $this->caption ] = $score;
            // Also search in item captions
            foreach ( $this->items() as $item ) {
                if ( $item->active ) {
                    if ( ( $score = $this->compareStrings( $item->caption, $search ) ) >= $minScore ) $matches[ $item->caption ] = $score;
                }
            }
        }
    }

    /**
     * Get all reminders of this category.
     *
     * @param boolean $includeInherited Set to true to include even inherited reminders from parent categories.
     * @return array Array of objects { int id, int catId, int offset, string anchor, string message }, where id is a unique
     *  category reminder identifier, offset is the number of seconds before (positive) or after (negative values)
     *  the anchor (start|end) of a booking when the reminder shall be sent, and message is the text to be sent.
     */
    public function reminders( bool $includeInherited = false ) : array {
        $reminders = array();
        if ( $includeInherited ) {
            $parent = $this->parent();
            if ( !is_null( $parent ) ) $reminders = $parent->reminders( true );
        }
        $stmt = self::$db->query( "SELECT * FROM cat_reminders WHERE catId={$this->id}" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $reminders[] = $row;
        }
        return $reminders;
    }

    /**
     * Get the category reminder with ID $id.
     *
     * @param integer $id
     * @return array|bool Returns an array with the members [ id, catId, message, anchor, offset ] or FALSE if the reminder does not exist.
     */
    public function getReminder( int $id ) {
        $stmt = self::$db->prepare( "SELECT * from cat_reminders WHERE id=?" );
        $stmt->execute( [ $id ] );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Edit the properties of a reminder or create a new reminder.
     *
     * @param integer $id The id of the reminder to change. If 0, add a new reminder.
     * @param integer $offset Number of hours before (+) or after (-) start of booking when the reminder shall be sent
     * @param string $anchor Where to anchor the offset, either "start" or "end" of the booking
     * @param integer $message The new message to send with the reminder.
     * @return int|bool The id of the reminder, false on failure.
     */
    public function editReminder( int $id, int $offset, string $anchor, string $message ) {
        if ( $id == 0 ) {
            self::$db->exec( "INSERT INTO cat_reminders SET catId={$this->id}" );
            $id = self::$db->lastInsertId();
        }
        if ( !in_array( $anchor, [ "start", "end" ] ) ) {
            logger( __METHOD__ . " Tried to save a reminder with invalid anchor $anchor", E_ERROR );
            return false;
        }
        $stmt = self::$db->prepare( "UPDATE cat_reminders SET `offset`=:offset, `anchor`=:anchor, `message`=:message WHERE catId={$this->id} AND id=:id" );
        if ( !$stmt->execute( [
            ":offset" => $offset,
            ":anchor" => $anchor,
            ":message" => $message,
            ":id" => $id
        ] ) ) {
            logger( __METHOD__ . " Failed to change or create cat reminder. ".$stmt->errorInfo()[2], E_ERROR );
            return false;
        }
        // Adjust existing bookedItems affected by this change
        // Find all items belonging to the same section
        $stmt = self::$db->query( "SELECT itemId FROM items INNER JOIN categories USING (catId) WHERE sectionId={$this->sectionId}" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $item = new Item( $row->itemId );
            if ( $item->isBelowCategory( $this ) ) {
                // This item is affected by the reminder change. Adjust all corresponding bookedItems.
                $stmt = self::$db->query( "SELECT bookedItemId, UNIX_TIMESTAMP($anchor) anchor FROM booked_items WHERE itemId={$row->itemId}" );
                while ( $row2 = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                    $bookedItem = new Item( $row2->bookedItemId, true );
                    // Mark the reminder as not being sent if sending time has not passed yet.
                    $bookedItem->setReminderSent( $id, 'cat', ( $row2->anchor + $offset < time() ) );
                }
            }
        }
        return $id;
    }

    /**
     * Delete a cat reminder
     *
     * @param integer $id The id of the reminder to delete
     * @return bool True on success, false on failure
     */
    public function deleteReminder( int $id ) : bool {
        // Remove sent flag from any bookedItems
        $stmt = self::$db->query( "SELECT bookedItemId FROM booked_items WHERE remindersSent LIKE '%\"cat$id\"%'" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            $item = new Item( $row->bookedItemId, true );
            $item->setReminderSent( $id, "cat", false );
        }
        // Delete the reminder
        $stmt = self::$db->prepare( "DELETE FROM cat_reminders WHERE catId={$this->id} AND id=?" );
        if ( $stmt->execute( [ $id ] ) ) return true;
        logger( __METHOD__ . " Failed to delete cat reminder. " . $stmt->errorInfo()[ 2 ], E_ERROR );
        return false;
    }
}
