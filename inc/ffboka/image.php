<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Image
 * Class for handling item pictures
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
                logger(__METHOD__." Failed to instatiate Image $id. " . self::$db->errorInfo()[2], E_ERROR);
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
     * @return boolean|string True on success, error message as string on failure
     */
    public function setImage($imgFile, $maxSize=0, $thumbSize=80, $maxFileSize=0) {
        if (!file_exists(__DIR__."/../../img/item")) {
            if (!mkdir(__DIR__."/../../img/item", 0777, true)) {
                logger(__METHOD__." Failed to create folder for item images " . realpath(__DIR__."/../../img/item"), E_ERROR);
                return "Kan inte skapa mapp för resursbilder på ./img/item. Set till att servern har skrivåtkomst. Kontakta systemadministratören.";
        }
        $images = $this->imgFileToString($imgFile, $maxSize, $thumbSize, $maxFileSize);
        if ($images['error']) return $images['error'];
        // Save thumbnail to database
        $stmt = self::$db->prepare("UPDATE item_images SET thumb=? WHERE imageID={$this->id}");
        if (!$stmt->execute(array($images['thumb']))) {
            logger(__METHOD__." Failed to save thumbnail. " . $stmt->errorInfo()[2], E_ERROR);
            return "Kan inte spara miniaturbilden i databasen. Kontakta systemadministratören.";
        }
        // Save full size image to file system
        if (file_put_contents(__DIR__ . "/../../img/item/{$this->id}", $images['image'])===FALSE) {
            logger(__METHOD__." Failed to save full size image to " . realpath(__DIR__."/../../img/item/{$this->id}"), E_ERROR);
            return "Kan inte spara originalbilden som fil. Kontakta systemadministratören.";
        }
        return TRUE;
    }
    
    
    /**
     * Setter function for image properties
     * @param string $name
     * @param mixed $value
     * @throws \Exception if undefined Image property is set
     * @return mixed|boolean Returns the set value on success, and FALSE on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "caption":
                $stmt = self::$db->prepare("UPDATE item_images SET $name=? WHERE imageId={$this->id}");
                if ($stmt->execute(array($value))) return $value;
                logger(__METHOD__." Failed to set Image property $name to $value. " . $stmt->errorInfo()[2], E_ERROR);
                break;
            default:
                logger(__METHOD__." Use of undefined Image property $name", E_WARNING);
                throw new \Exception("Use of undefined Image property $name");
        }
        return false;
    }
    
    /**
     * Getter function for image properties
     * @param string $name Name of the property
     * @return string Value of the property.
     * @throws \Exception if invalid property name is used
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
                logger(__METHOD__." Use of undefined Image property $name", E_WARNING);
                throw new \Exception("Use of undefined Image property $name");
        }
    }
    
    /**
     * Delete image from database
     * @throws \Exception
     * @return boolean
     */
    public function delete() {
        // Full size image will be removed from file system by cron, because images may anyway also be deleted by cascading in db
        if (self::$db->exec("DELETE FROM item_images WHERE imageId={$this->id}")) {
            return TRUE;
        }
        logger(__METHOD__." Failed to delete item image from DB. ".self::$db->errorInfo()[2], E_WARNING);
        throw new \Exception("Failed to delete item image.");
    }
    
}
