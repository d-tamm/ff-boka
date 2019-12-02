<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

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
     * Get the options of the question as a readable string
     * @return string
     */
    public function optionsReadable() {
        switch ($this->type) {
            case "radio":
                return htmlspecialchars(implode(" | ", $this->options->choices));
            case "checkbox":
                return htmlspecialchars(implode(" | ", $this->options->choices)) . " (flera val möjliga)";
            case "text":
                return "Text" . ($this->options->length ? ", max {$this->options->length} tecken" : ", valfri längd");
            case "number":
                $ret = "Siffra";
                if (is_numeric($this->options->min) && is_numeric($this->options->max)) $ret .= " mellan {$this->options->min} och {$this->options->max}";
                elseif (is_numeric($this->options->min)) $ret .= ", minst {$this->options->min}";
                elseif (is_numeric($this->options->max)) $ret .= ", max {$this->options->max}";
                else $ret .= " utan begränsning";
                return $ret;
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