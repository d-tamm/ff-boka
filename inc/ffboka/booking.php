<?php
/**
 * Classes for handling the structure of the resource booking system
 * @author Daniel Tamm
 * @license GNU-GPL
 */
namespace FFBoka;
use PDO;

/**
 * Class Booking
 * Class containing complete booking.
 */
class Booking extends FFBoka {
    
    private $id;
    private $userId;
    private $sectionId;
    
    /**
     * Booking instantiation. 
     * @param int $id ID of the booking
     * @throws \Exception if no or an invalid $id is passed.
     */
    public function __construct($id) {
        if ($id) { // Try to return an existing booking from database
            $stmt = self::$db->prepare("SELECT bookingId, sectionId, userId FROM bookings WHERE bookingId=?");
            $stmt->execute(array($id));
            if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                $this->id = $row->bookingId;
                $this->sectionId = $row->sectionId;
                $this->userId = $row->userId;
            } else {
                throw new \Exception("Can't instatiate Booking with ID $id.");
            }
        } else {
            throw new \Exception("Can't instatiate Booking without ID.");
        }
    }
    
    /**
     * Getter function for Booking properties
     * @param string $name Name of the property to retrieve
     * @throws \Exception
     * @return number|string|NULL
     */
    public function __get($name) {
        switch ($name) {
            case "id":
            case "userId":
            case "sectionId":
                return $this->$name;
            case "repeatId":
            case "timestamp":
            case "ref":
            case "commentCust":
            case "commentIntern":
            case "paid":
            case "extName":
            case "extPhone":
            case "extMail":
            case "token":
            case "confirmationSent":
                $stmt = self::$db->query("SELECT $name FROM bookings WHERE bookingId={$this->id}");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->$name;
            case "price":
                $stmt = self::$db->query("SELECT SUM(price) price FROM booked_items WHERE bookingId={$this->id} AND NOT price IS NULL");
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return $row->price;
            default:
                throw new \Exception("Use of undefined Booking property $name");
        }
    }
    
    /**
     * Setter function for Booking properties
     * @param string $name Property name
     * @param string $value Property value
     * @return string Set value on success, false on failure.
     */
    public function __set($name, $value) {
        switch ($name) {
            case "repeatId":
            case "ref":
            case "commentCust":
            case "commentIntern":
            case "status":
            case "paid":
            case "extName":
            case "extPhone":
            case "extMail":
            case "confirmationSent":
                $stmt = self::$db->prepare("UPDATE bookings SET $name=:name WHERE bookingId={$this->id}");
                if ($name=="repeatId") $stmt->bindValue(":name", $value, \PDO::PARAM_INT);
                else $stmt->bindValue(":name", $value);
                if ($stmt->execute()) return $value;
                break;
            default:
                throw new \Exception("Use of undefined Booking property $name");
        }
        return false;
    }
    
    /**
     * Get the user of the booking
     * @return \FFBoka\User empty User for external bookings
     */
    public function user() {
        return new User(is_null($this->userId) ? 0 : $this->userId);
    }
    
    /**
     * Get the section the booking belongs to
     * @return \FFBoka\Section
     */
    public function section() {
            return new Section($this->sectionId);
    }

    /**
     * Remove the whole booking
     * @return bool Success
     */
    public function delete() {
        return self::$db->exec("DELETE FROM bookings WHERE bookingId={$this->id}");
    }
    
    /**
     * Get the status of the booking. 
     * @return int The least status of all items in the booking
     */
    public function status() {
        $leastStatus = FFBoka::STATUS_CONFIRMED;
        foreach ($this->items() as $item) {
            $leastStatus = $leastStatus & $item->status;
        }
        return $leastStatus;
    }
    
    /**
     * Add an item to the booking.
     * @param int $itemId ID of the item to add
     * @return Item|bool Added item on success, false on failure
     */
    public function addItem(int $itemId) {
        $stmt = self::$db->prepare("INSERT INTO booked_items SET bookingId={$this->id}, itemId=?");
        if ($stmt->execute(array( $itemId ))) return new Item(self::$db->lastInsertId(), TRUE);
        else return FALSE;
    }

    /**
     * Remove an item from the booking
     * @param int $bookedItemId Booking ID of the item to be removed
     * @return bool True on success
     */
    public function removeItem(int $bookedItemId) {
        $stmt = self::$db->prepare("DELETE FROM booked_items WHERE bookedItemId=?");
        return $stmt->execute(array( $bookedItemId ));
    }
    
    /**
     * Get all items contained in this booking
     * @return Item[]
     */
    public function items() {
        $stmt = self::$db->query("SELECT bookedItemId, status FROM booked_items WHERE bookingId={$this->id}");
        $items = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $items[] = new Item($row->bookedItemId, TRUE);
        }
        return $items;
    }

    /**
     * Add the answer to a booking question to the booking
     * @param string $question The asked question
     * @param string $answer The answer given by the booker
     * @return int|bool ID of inserted answer, FALSE on failure
     */
    public function addAnswer(string $question, string $answer) {
        $stmt = self::$db->prepare("INSERT INTO booking_answers SET bookingId={$this->id}, question=:question, answer=:answer");
        if (!$stmt->execute(array(
            ":question"=>$question,
            ":answer"=>$answer,
        ))) return FALSE;
        return self::$db->lastInsertId();
    }
    
    /**
     * Deletes all answers to booking questions
     * @return bool TRUE on success
     */
    public function clearAnswers() {
        return self::$db->exec("DELETE FROM booking_answers WHERE bookingId={$this->id}");
    }
    
    /**
     * Get all booking questions and answers
     * @return array( id => { string question, string answer }, ... )
     */
    public function answers() {
        $ans = array();
        $stmt = self::$db->query("SELECT answerId, question, answer FROM booking_answers WHERE bookingId={$this->id}");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ans[$row->answerId] = $row; 
        }
        return $ans;
    }
    
    /**
     * Get the datetime when the first item's booking starts
     * @return NULL|int Unix timestamp. Returns NULL if booking contains no items.
     */
    public function start() {
        $start = NULL;
        foreach ($this->items() as $item) {
            if (is_null($start)) $start = $item->start;
            else $start = min($start, $item->start);
        }
        return $start;
    }

    /**
     * Make a copy of the booking. Non-available items will be skipped. Items will also
     * be skipped if the user's privileges are so low that the booking would need to be confirmed.
     * @param \DateInterval $offset The date interval by which the new booking shall be moved. 
     * @return \FFBoka\Booking The new booking
     */
    public function copy(\DateInterval $offset) {
        $b = $this->user()->addBooking($this->sectionId);
        $b->repeatId = $this->repeatId;
        $b->ref = $this->ref;
        $b->commentCust = $this->commentCust;
        $b->commentIntern = $this->commentIntern;
        $b->paid = $this->paid;
        $b->extName = $this->extName;
        $b->extPhone = $this->extPhone;
        $b->extMail = $this->extMail;
        foreach ($this->answers() as $ans) {
            $b->addAnswer($ans->question, $ans->answer);
        }
        foreach ($this->items() as $item) {
            $start = new \DateTime("@".$item->start);
            $start = $start->add($offset)->getTimestamp();
            $end = new \DateTime("@".$item->end);
            $end = $end->add($offset)->getTimestamp();
            // Only add the item if user has privileges to book without confirmation and item is available
            if ($item->category()->getAccess($this->user())>=FFBoka::ACCESS_BOOK && $item->isAvailable($start, $end)) {
                $i = $b->addItem($item->id);
                $i->start = $start;
                $i->end = $end;
                $i->status = $item->status;
                $i->price = $item->price;
            }
        }
        return $b;
    }

    /**
     * Send a confirmation email to the booking user.
     * @param string $url The base URL of the installation, with trailing slash (https://domain.tld/installpath/).
     * @return bool|string Returns TRUE on success, or an error message string on failure.
     */
    public function sendConfirmation(string $url) {
        $itemList = "<tr><th>Resurs</th><th>Status</th><th>Datum</th><th></th></tr>"; // Header for table with booked items
        $leastStatus = self::STATUS_CONFIRMED;
        $rejectedItems = FALSE; // Whether there are rejected items in the booking or not
        $messages = array(); // Postbook messages
        $arContactData = array(); // array where the keys are contact data, and the values are the captions of the corresponding categories.
        $attachments = array(); // array where the keys are md5 hashes of the file, and the values arrays with 'path' and 'filename' elements.
        foreach ($this->items() as $item) {
            $cat = $item->category();
            // remember contact data for item
            $itemContact = $cat->contactData();
            if ($itemContact) $arContactData[$itemContact][] = $cat->caption;
            if ($item->status == self::STATUS_REJECTED) {
                // For rejected items, we just list them, but do not notify admins nor add postbook messages
                $rejectedItems = TRUE;
            } else {
                $leastStatus = $leastStatus & $item->status;
                // Collect postbook messages and reference numbers (e.g. (1), (2))
                $msgRef = array();
                foreach ($cat->postbookMsgs() as $msg) {
                    if (!in_array($msg, $messages)) {
                        // First time we see this message. Add it to message list
                        $messages[] = $msg;
                    }
                    $msgRef[] = "(".(array_search($msg, $messages)+1).")";
                }
            }
            // Add item to table with booked items
            $itemList .= "<tr><td>" . htmlspecialchars($item->caption) . "</td>";
            switch ($item->status) {
                case self::STATUS_CONFIRMED: $itemList .= "<td>bekräftat</td>"; break;
                case self::STATUS_REJECTED: $itemList .= "<td><b style='color:red;'>avböjt</b></td>"; break;
                default: $itemList .= "<td>väntar på bekräftelse</td>";
            }

            $itemList .= "<td>" . self::formatDateSpan($item->start, $item->end, true) . "</td>";
            $itemList .= "<td>" . implode(" ", $msgRef) . "</td>";
            $itemList .= "</tr>";
            // Add attachments to attachment list
            foreach ($cat->files() as $file) {
                if ($file->attachFile) $attachments[$file->md5] = array("path"=>"uploads/{$file->fileId}", "filename"=>$file->filename);
            }
        }
        // Contact data
        $contactData = "";
        foreach ($arContactData as $cd=>$captions) {
            $contactData .= "<p>Kontakt vid frågor angående " . htmlspecialchars(implode(" och ", array_unique($captions))) . ":<br>$cd</p>";
        }
        // Booking questions and answers
        $arAnswers = $this->answers();
        if (count($arAnswers)) {
            $answers = "<p>Bokningsfrågor och dina svar:</p>"; 
            foreach ($arAnswers as $answer) $answers .= "<p>Fråga: " . htmlspecialchars($answer->question) . "<br>Ditt svar: " . htmlspecialchars($answer->answer) . "</p>";
        }
        // Comment on booking status
        if ($leastStatus==self::STATUS_CONFIRMED && $rejectedItems) {
            $statusText = "Din bokning har nu hanterats av bokningsansvarig, men det finns poster i bokningen som inte har kunnat bekräftas. Nedan ser du vilka av posterna som är bekräftade och vilka som har avvisats. Kolla i kommentarsfältet längre ner om handläggaren har lämnat mer information om detta.";
        } elseif ($leastStatus==self::STATUS_CONFIRMED) {
            $statusText = "Alla poster i din bokning är bekräftade.";
        } else {
            $statusText = "Några poster i bokningen är preliminära och behöver bekräftas av ansvarig handläggare.";
            if ($rejectedItems) $statusText .= " OBS, det finns poster som har avvisats. Kolla i kommentarsfältet längre ner om handläggaren har lämnat mer information om detta.";
        }
        try {
            sendmail(
                is_null($this->userId) ? $this->extMail : $this->user()->mail, // to
                ($this->confirmationSent ? "Uppdaterad bokningsbekräftelse" : "Bokningsbekräftelse") . " #{$this->id} " . htmlspecialchars($this->ref), // subject
                "confirm_booking", // template name
                array( // replace array
                    "{{name}}"    => htmlspecialchars(is_null($this->userId) ? $this->extName : $this->user()->name),
                    "{{items}}"   => $itemList,
                    "{{messages}}"=> count($messages) ? "<li>".implode("</li><li>", $messages)."</li>" : "",
                    "{{status}}"  => $statusText,
                    "{{contactData}}" => $contactData,
                    "{{answers}}" => $answers,
                    "{{ref}}"     => htmlspecialchars($this->ref),
                    "{{commentCust}}" => $this->commentCust ? str_replace("\n", "<br>", htmlspecialchars($this->commentCust)) : "(ingen kommentar har lämnats)",
                    "{{bookingLink}}" => "{$url}book-sum.php?bookingId={$this->id}&token={$this->token}",
                ),
                $attachments
            );
            $this->confirmationSent = true;
        } catch(\Exception $e) {
            return "Kunde inte skicka bokningsbekräftelsen:" . $e;
        }
        return true;
    }


    /**
     * Send alerts about the booking to concerned admins
     * @param string $url The base URL of the installation, with trailing slash (https://domain.tld/installpath/).
     */
    public function sendNotifications(string $url) {
        $adminsToNotify = array(); // array where the keys are email addresses or user ids, and the values are arrays of booked item ids
        // Some information about the booking user
        if (is_null($this->userId)) {
            $contactData = "Detta är en gästbokning.<br>Namn: " . htmlspecialchars($_REQUEST['extName']) . "<br>Telefon: " . htmlspecialchars($_REQUEST['extPhone']) . "<br>Mejl: " . htmlspecialchars($_REQUEST['extMail']);
        } else {
            $contactData = "Namn: " . htmlspecialchars($this->user()->name) . "<br>Telefon: " . htmlspecialchars($this->user()->phone) . "<br>Mejl: " . htmlspecialchars($this->user()->mail) . "<br>Medlemsnummer: {$this->userId}<br>Lokalavdelning: " . htmlspecialchars($this->user()->section->name);
        }
        // Collect all addresses to send notifications to
        foreach ($this->items() as $item) {
            $cat = $item->category();
            if ($item->status !== self::STATUS_REJECTED) {
                // Functional email addresses to notify in this category
                if ($alerts = $cat->sendAlertTo) {
                    foreach (explode(",", $alerts) as $alert) {
                        $adminsToNotify[trim($alert)][] = $item->bookedItemId;
                    }
                }
                // Admins to notify
                foreach ($cat->admins(self::ACCESS_CONFIRM, TRUE) as $adm) {
                    $admin = new User($adm['userId']);
                    switch ($admin->getNotifyAdminOnNewBooking($cat)) {
                        case "confirmOnly":
                            if ($item->status > self::STATUS_PREBOOKED) break;
                        case "yes":
                            $adminsToNotify[$adm['userId']][] = $item->bookedItemId;
                    }
                }
            }
        }
        foreach ($adminsToNotify as $id=>$itemIds) {
            if (is_numeric($id)) { // Admin user
                if ($id == $_SESSION['authenticatedUser']) continue; // Don't send notification to current user
                $adm = new User($id);
                $mail = $adm->mail;
                if ($mail=="") continue; // user has not set his/her email address
                $name = $adm->name;
            } else {
                $mail = $id;
                $name = "";
            }
            $itemList = "<tr><th>Resurs</th><th>Datum</th><th>Status</th></tr>";
            foreach ($itemIds as $itemId) {
                $item = new Item($itemId, TRUE);
                $itemList .= "<tr><td>" . htmlspecialchars($item->caption) . "</td>";
                $itemList .= "<td>" . self::formatDateSpan($item->start, $item->end, true) . "</td>";
                switch ($item->status) {
                    case FFBoka::STATUS_CONFIRMED: $itemList .= "<td>bekräftat</td>"; break;
                    case FFBoka::STATUS_PREBOOKED: $itemList .= "<td><b>obekräftat</b></td>"; break;
                    case FFBoka::STATUS_CONFLICT: $itemList .= "<td><b style='color:red'>krockar med befintlig bokning</b></td>"; break;
                }
                $itemList .= "</tr>";
            }
            sendmail(
                $mail, // to
                $this->confirmationSent ? "FF Uppdaterad bokning #{$this->id}" : "FF Ny bokning #{$this->id}", // subject
                "booking_alert", // template
                array( // substitute array
                    "{{name}}" => htmlspecialchars($name),
                    "{{contactData}}" => $contactData,
                    "{{items}}" => $itemList,
                    "{{ref}}"   => htmlspecialchars($this->ref),
                    "{{commentCust}}" => $this->commentCust ? str_replace("\n", "<br>", htmlspecialchars($this->commentCust)) : "(ingen kommentar har lämnats)",
                    "{{bookingLink}}" => "{$url}book-sum.php?bookingId={$this->id}",
                )
            );
        }
    }

    /**
     * Get all bookings belonging to the same booking series.
     * @param bool $includeThis Whether to include this booking in the answer.
     * @return bool|\FFBoka\Booking[] Returns FALSE if this is not a series, otherwise returns all
     * bookings in this series.
     */
    public function getBookingSeries(bool $includeThis=FALSE) {
        if (is_null($this->repeatId)) return FALSE;
        $stmt = self::$db->query("SELECT bookingId FROM bookings WHERE repeatId={$this->repeatId}" . ($includeThis ? "" : " AND bookingId != {$this->id}"));
        $ret = array();
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $ret[] = new Booking($row->bookingId);
        }
        return $ret;
    }
    
    /**
     * Get all items in booking which are included multiple times and where the times overlap.
     * @return string[] Array where the keys are item IDs and the values are the HTML escaped item captions 
     */
    public function getOverlappingItems() {
        $itemTimes = array();
        $overlap = array();
        foreach ($this->items() as $item) {
            $start = $item->start;
            $end = $item->end;
            foreach ($itemTimes as $id=>$itemTime) {
                if (
                    $id == $item->id && (
                        ($start >= $itemTime["start"] && $start <= $itemTime["end"]) ||
                        ($end >= $itemTime["start"] && $end <= $itemTime["end"]) ||
                        ($start < $itemTime["start"] && $end > $itemTime["end"])
                    )
                ) { // item included multiple overlapping times
                    $overlap[$item->id] = htmlspecialchars($item->caption);
                    break;
                }
            }
            $itemTimes[$item->id] = [ "start"=>$start, "end"=>$end ];
        }
        return $overlap;
    }
}