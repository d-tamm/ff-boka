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
