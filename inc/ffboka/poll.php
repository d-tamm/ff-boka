<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Category
 * Categories in the booking system, containing items.
 */
class Poll extends FFBoka {
    private $id;
    
    /**
     * Initialize poll with ID and get some static properties.
     * @param int $id ID of requested poll
     */
    public function __construct($id){
        $stmt = self::$db->prepare("SELECT pollId FROM polls WHERE pollId=?");
        $stmt->execute(array($id));
        if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $this->id = $row->pollId;
        } else {
            logger(__METHOD__." Trying to instatiate non-existing poll with id $id", E_WARNING);
            throw new \Exception("Poll with ID $id does not exist.");
        }
    }
    
    /**
     * Setter function for poll properties
     * @param string $name Property name. May be 'question' or 'expires' or 'choices'.
     * @param string|NULL|int|array $value Property value.
     *  'expires' is set as YYYY-mm-dd. Set to NULL or en empty string if the poll shall not expire.
     *  Supply array of strings for 'choices'. If choices is set with this function, votes will be reset to 0.
     * @throws \Exception if an invalid property name is used.
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        if ($name=="expires" && $value==="") $value=NULL;
        switch ($name) {
            case "choices":
                // Choices are passed as array. Convert to string.
                $value = json_encode($value);
            case "question":
                $stmt = self::$db->prepare("UPDATE polls SET $name=? WHERE pollId={$this->id}");
                if ($stmt->execute(array($value))) {
                    // Reset vote counts if question or choices are changed.
                    self::$db->exec("DELETE FROM poll_answers WHERE pollId={$this->id}");
                    $this->setVotes(array_fill(0, count($this->choices), 0));
                    return $value;
                }
                logger(__METHOD__." Failed to set Poll property $name to $value. " . $stmt->errorInfo()[2], E_ERROR);
                break;
            case "expires":
                if (is_null($value)) {
                    if(self::$db->exec("UPDATE polls SET $name=NULL WHERE pollId={$this->id}") !== FALSE) return true;
                    logger(__METHOD__." Failed to set Poll property $name to $value. " . self::$db->errorInfo()[2], E_ERROR);
                } else {
                    $stmt = self::$db->prepare("UPDATE polls SET $name=? WHERE pollId={$this->id}");
                    if ($stmt->execute(array($value))) return $value;
                    logger(__METHOD__." Failed to set Poll property $name to $value. " . $stmt->errorInfo()[2], E_ERROR);
                }
                break;
            default:
                logger(__METHOD__." Use of undefined Poll property $name.", E_WARNING);
                throw new \Exception("Use of undefined Poll property $name");
        }
        return false;
    }
    
    /**
     * Getter function for poll properties
     * @param string $name Name of the property. Can be 'question' or 'expires' or 'choices' or 'votes'.
     * @throws \Exception if an invalid property name is used.
     * @return string|array|int Value of the property.
     *  Returns expires as YYYY-mm-dd.
     *  Returns NULL for expires if poll does not expire.
     *  Returns array for 'choices' and 'votes'.
     */
    public function __get($name) {
        switch ($name) {
            case "id":
                return $this->id;
            case "question":
            case "expires":
            case "choices":
            case "votes":
                $stmt = self::$db->query("SELECT $name FROM polls WHERE pollId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($name == 'choices' || $name == 'votes') return json_decode($row[$name]);
                else return $row[$name];
            case "voteMax":
                return max($this->votes);
            default:
                logger(__METHOD__." Use of undefined Poll property $name.", E_WARNING);
                throw new \Exception("Use of undefined poll property $name");
        }
    }

    /**
     * Add a choice to the poll.
     * @param string $choice
     * @param int $offset If set, add the new choice at this position (0-based). Otherwise it will be appeded at the end.
     * @throws \Exception if invalid offset is used.
     * @return int Number of choices after adding the new one
     */
    public function addChoice(string $choice, int $offset=NULL) {
        $choices = $this->choices;
        $votes = $this->votes;
        if (is_null($offset)) $offset = count($choices);
        if ($offset < 0 || $offset > count($choices)) {
            logger(__METHOD__." Offset $offset for new poll choice out of bounds.", E_WARNING);
            throw new \Exception("Offset $offset out of bounds.");
        }
        array_splice($choices, $offset, 0, $choice);
        array_splice($votes, $offset, 0, 0);
        $this->choices = $choices;
        $this->setVotes($votes);
        return count($choices);
    }
    
    /**
     * Remove one choice from the poll.
     * @param int $offset The choice's position to remove (0-based)
     * @throws \Exception if invalid offset is used.
     * @return int Number of choices left after removing.
     */
    public function removeChoice(int $offset) {
        $choices = $this->choices;
        $votes = $this->votes;
        if ($offset < 0 || $offset >= count($choices)) {
            logger(__METHOD__." Offset $offset to remove poll chooice out of bounds.", E_WARNING);
            throw new \Exception("Offset $offset out of bounds.");
        }
        array_splice($choices, $offset, 1);
        array_splice($votes, $offset, 1);
        $this->choices = $choices;
        $this->setVotes($votes);
        return count($choices);
    }
    
    /**
     * Save the votes for the poll
     * @param array $votes Array of integers
     */
    private function setVotes(array $votes) {
        self::$db->query("UPDATE polls SET votes='" . json_encode($votes) . "' WHERE pollId={$this->id}");
    }
    
    /**
     * Add a user's vote
     * @param int $choice Choice number to increment
     * @param int $userId Id of user who has voted
     * @throws \Exception if choice number is invalid
     */
    public function addVote(int $choice, int $userId) {
        $votes = $this->votes;
        if ($choice < 0 || $choice >= count($votes)) {
            logger(__METHOD__." Choice number $choice out of bounds.", E_WARNING);
            throw new \Exception("Choice number $choice out of bounds.");
        }
        $votes[$choice]++;
        $this->setVotes($votes);
        // Record that this user has voted
        if (!self::$db->exec("INSERT INTO poll_answers SET pollId={$this->id}, userId=$userId")) logger(__METHOD__." Failed to record that user has voted. " . self::$db->errorInfo()[2], E_ERROR);
    }
    
    /**
     * Delete the poll and all answers
     * @return bool Returns true if successful, false on error
     */
    public function delete() {
        if (self::$db->exec("DELETE FROM polls WHERE pollId={$this->id}")) return true;
        logger(__METHOD__." Failed to delete poll. " . self::$db->errorInfo()[2], E_ERROR);
        return false;
    }
    
    /**
     * Closes the poll for further votes.
     */
    public function close() {
        $this->expires = time();
    }
}
