<?php
// Execute ajax requests for admins

use FFBoka\Category;
use FFBoka\FFBoka;
use FFBoka\Image;
use FFBoka\Item;
use FFBoka\Question;
use FFBoka\Section;
use FFBoka\User;


session_start();
require( __DIR__ . "/../inc/common.php" );
global $cfg, $FF;

// Section and userId must be known
if ( !$_SESSION[ 'sectionId' ] || !$_SESSION[ 'authenticatedUser' ] ) {
    http_response_code( 403 ); // forbidden
    die();
}
$section = new Section( $_SESSION[ 'sectionId' ] );
$currentUser = new User( $_SESSION[ 'authenticatedUser' ] );

if ( !isset( $_REQUEST[ 'action' ] ) ) { http_response_code( 400 ); die(); } // Bad request


// Check permissions and set some basic objects
switch ($_REQUEST[ 'action' ]) {
    case "findUser":
        // no further requirements
        break;

    // Section admin changes: require section admin or admin-by-config
    case "addSectionAdmin":
    case "listSectionAdmins":
    case "removeSectionAdmin":
        if (
            $section->getAccess( $currentUser ) < FFBoka::ACCESS_SECTIONADMIN &&
            ( !isset( $_SESSION[ 'assignments' ][ $section->id ] ) || !array_intersect( $_SESSION[ 'assignments' ][ $section->id ], $cfg[ 'sectionAdmins' ] ) )
        ) {
            http_response_code( 403 ); // Forbidden
            die();
        }
        break;

    // Section level: require section admin permissions
    case "getQuestion":
    case "getQuestions":
    case "saveQuestion":
    case "deleteQuestion":
        if ( $section->getAccess( $currentUser ) < FFBoka::ACCESS_SECTIONADMIN ) {
            http_response_code( 403 ); // Forbidden
            die();
        }
        break;

    // Category level: Require cat admin, and set Category object
    case "getCatContactData":
    case "setCatProp":
    case "addAlert":
    case "deleteAlert":
    case "setCatImage":
    case "setCatAccess":
    case "getCatAccess":
    case "getCatQuestions":
    case "toggleQuestion":
    case "deleteCat":
    case "getCatFiles":
    case "addCatFile":
    case "setCatFileProp":
    case "deleteCatFile":
        // CatId must be known
        if ( !isset( $_SESSION[ 'catId' ] ) ) {
            http_response_code( 400 ); // Bad request
            die();
        }
        $cat = new Category( $_SESSION[ 'catId' ] );
        if ( $cat->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
            http_response_code( 403 ); // Forbidden
            die();
        }
        break;

    // The following Reminder requests can be done for either categories or items
    case "getReminders":
    case "getReminder":
    case "saveReminder":
    case "deleteReminder":
        if ( $_GET[ 'class' ] == "item" ) {
            if ( !isset( $_SESSION[ 'itemId' ] ) ) { http_response_code( 400 ); die(); } // Bad request
            $catOrItem = new Item( $_SESSION[ 'itemId' ] );
        }
        elseif ( $_GET[ 'class' ] == "cat" ) {
            if ( !isset( $_SESSION[ 'catId' ] ) ) { http_response_code( 400 ); die(); } // Bad request
            $catOrItem = new Category( $_SESSION[ 'catId' ] );
        }
        else { http_response_code( 405 ); die(); } // Method not allowed
        if ( $catOrItem->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
            http_response_code( 403 ); // forbidden
            die();
        }
        break;    
    
    // Item level: Require cat admin, and set Category and Item objects
    case "saveItemProp":
    case "deleteItem":
    case "getItemImages":
    case "addItemImage":
    case "deleteItemImage":
    case "saveItemImgCaption":
        // itemId must be known
        if ( !isset( $_SESSION[ 'itemId' ] ) ) {
            http_response_code( 400 ); // Bad request
            die();
        }
        $item = new Item( $_SESSION[ 'itemId' ] );
        $cat = $item->category();
        if ( $cat->getAccess( $currentUser ) < FFBoka::ACCESS_CATADMIN ) {
            http_response_code( 403 ); // Forbidden
            die();
        }
        break;

    default:
        http_response_code( 405 ); // Method not allowed
        die();
}





switch ($_REQUEST[ 'action' ]) {

// ===== SECTION LEVEL AJAX REQUESTS =====

case "findUser":
    if ( !isset( $_REQUEST[ 'q' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: application/json" );
    die( json_encode( $FF->findUser( $_REQUEST['q'] ) ) );
    
case "addSectionAdmin":
    if ( !is_numeric( $_REQUEST[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    if ( $section->addAdmin( $_REQUEST[ 'id' ] ) ) {
        $adm = new User( $_REQUEST[ 'id' ] );
        if ( $_REQUEST[ 'id' ] != $currentUser->id && $adm->mail) {
            // Send notification to new user
            $FF->sendMail(
                $adm->mail, // to
                "Du är nu administratör för " . $section->name, // subject
                "notify_new_admin", // template
                array( // replace
                    "{{name}}" => $adm->name,
                    "{{role}}" => "lokalavdelnings-admin",
                    "{{link}}" => $cfg[ 'url' ],
                    "{{sectionName}}" => $section->name,
                    "{{superadmin-name}}" => $currentUser->name,
                    "{{superadmin-mail}}" => $currentUser->mail,
                    "{{superadmin-phone}}" => $currentUser->phone
                ),
                [], // attachments
                $cfg[ 'mail' ]
            );
        }
        die( $currentUser->id );
    }
    http_response_code( 404 ); // Not found
    die( "Kunde inte lägga till administratören. Är den kanske redan med i listan?" );

case "listSectionAdmins":
    header( "Content-Type: text/html" );
    $ret = "";
    if ( !$admins = $section->getAdmins() ) echo "<li>Inga administratörer har lagts upp än.</li>";
    foreach ( $admins as $admId ) {
        $adm = new User( $admId );
        echo "<li><a href='#'><h2>" . ( $adm->name ? htmlspecialchars( $adm->name ) : "(ingen persondata tillgänglig)" ) . "</h2><p>{$adm->id}</p></a><a href=\"javascript:removeAdmin({$adm->id}, $currentUser->id, '" . htmlspecialchars( $adm->name ) . "');\">Ta bort</a></li>";
    }
    die();
    
case "removeSectionAdmin":
    if ( !isset( $_REQUEST[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    if ( !$section->removeAdmin( $_REQUEST[ 'id' ] ) ) { http_response_code( 500 ); die(); } // Internal server error
    die();
    
case "getQuestion":
    if ( !isset( $_REQUEST[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: text/plain" );
    $question = new Question( $_REQUEST[ 'id' ] );
    die( json_encode( [
        "id" => $question->id,
        "caption" => $question->caption,
        "type" => $question->type,
        "options" => $question->options,
    ] ) );
    
case "getQuestions":
    header( "Content-Type: text/plain" );
    foreach ( $section->questions() as $question ) {
        echo "<li><a href='#' onClick='showQuestion({$question->id})'><span style='white-space:normal;'>" . htmlspecialchars( $question->caption ) . "</span><p style='white-space:normal;'>";
        echo $question->optionsReadable();
        echo "</p></a><a href='#' onClick='deleteQuestion({$question->id});'>Ta bort frågan</a></li>";
    }
    die();
    
case "saveQuestion":
    if ( !isset( $_REQUEST[ 'id' ] ) || !isset( $_REQUEST[ 'caption' ] ) || !isset( $_REQUEST[ 'type' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: application/json" );
    if ( $_REQUEST[ 'id' ] == 0 ) $question = $section->addQuestion();
    else $question = new Question( $_REQUEST[ 'id' ] );
    $question->caption = $_REQUEST[ 'caption' ];
    $question->type = $_REQUEST[ 'type' ];
    switch ( $question->type ) {
        case "radio":
        case "checkbox":
            if ( !isset( $_REQUEST[ 'choices' ] ) ) { http_response_code( 400 ); die(); } // Bad request
            $question->options = json_encode( [ "choices" => explode("\n", $_REQUEST[ 'choices' ] ) ]); break;
        case "text":
            if ( !isset( $_REQUEST[ 'length' ] ) ) { http_response_code( 400 ); die(); } // Bad request
            $question->options = json_encode( [ "length" => $_REQUEST[ 'length' ] ] ); break;
        case "number":
            if ( !isset( $_REQUEST[ 'min' ] ) || !isset( $_REQUEST[ 'max' ] ) ) { http_response_code( 400 ); die(); } // Bad request
            $question->options = json_encode( [ "min" => $_REQUEST[ 'min' ], "max" => $_REQUEST[ 'max' ] ] ); break;
    }
    die ( json_encode( "OK" ) );
    
case "deleteQuestion":
    if ( !isset( $_REQUEST[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: application/json" );
    $question = new Question( $_REQUEST[ 'id' ] );
    die( json_encode( $question->delete() ) );



// ===== CATEGORY/ITEM LEVEL AJAX REQUESTS =====

case "getReminders":
    $reminders = array();
    header( "Content-Type: text/html" );
    // Collect reminders for item
    foreach( $catOrItem->reminders() as $r ) {
        $reminders[] = [ "id"=>$r->id, "offset"=>$r->offset, "anchor"=>$r->anchor, "message"=>$r->message ];
    }
    $parent = ( $_GET[ 'class' ] == "item" ) ? $catOrItem->category() : $catOrItem->parent();
    // Add reminders inherited from parent categories
    while ( !is_null( $parent ) ) {
        foreach ( $parent->reminders() as $r ) {
            $reminders[] = [ "offset"=>$r->offset, "anchor"=>$r->anchor, "message"=>$r->message, "parentId"=>$parent->id, "parentCaption"=>$parent->caption ];
        }
        $parent = $parent->parent();
    }
    // Sort the reminders by time
    $offsets = [];
    foreach ( $reminders as $r ) $offsets[] = $r['offset'] + ( $r['anchor']=="start" ? 0 : 3600*24*30*12 );
    array_multisort( $offsets, $reminders );
    foreach ( $reminders as $r ) {
        if ( isset( $r[ 'parentId' ] ) ) { // inherited reminders are only displayed, not editable
            echo "<li><strong>" . $FF::formatReminderOffset( $r['offset'] ) . ( $r['anchor']=="start" ? " bokningsstart" : " bokningsslut" ) . "</strong><p>\"" . htmlspecialchars( $r['message'] ) . "\"<br><i>ärvt från kategori <a href='category.php?catId={$r['parentId']}&expand=reminders'>{$r['parentCaption']}</p></a></i></li>";
        } else { // reminders of the current objects are editable
            echo "<li><a href='#' onclick=\"editReminder('{$_GET[ 'class' ]}', {$r['id']});\">" . $FF::formatReminderOffset( $r['offset'] ) . ( $r['anchor']=="start" ? " bokningsstart" : " bokningsslut" ) . "<p>\"" . htmlspecialchars( $r['message'] ) . "\"</p></a><a href='#' onclick=\"deleteReminder('{$_GET[ 'class' ]}', {$r['id']});\"></a></li>";
        }
    }
    echo "<li data-icon='plus' data-theme='a'><a href='#' onclick=\"editReminder('{$_GET[ 'class' ]}', 0);\">Skapa ny påminnelse</a></li>";
    die();

case "getReminder":
    if ( !isset( $_GET[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: application/json" );
    if ( $_GET[ 'id' ] == 0 ) die( json_encode( [ "id"=>0, "message"=>"Ny påminnelse", "offset"=>0, "anchor"=>"start" ] ));
    die( json_encode( $catOrItem->getReminder( $_GET[ 'id' ] ) ) );

case "saveReminder":
    if ( !isset( $_GET[ 'id' ] ) || !isset( $_GET[ 'offset' ] ) || !isset( $_GET[ 'message' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    header( "Content-Type: application/json" );
    die( json_encode( $catOrItem->editReminder( $_GET[ 'id' ], $_GET[ 'offset' ], $_GET[ 'anchor' ], $_GET[ 'message' ] ) ) );

case "deleteReminder":
    if ( !isset( $_GET[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    if ( !$catOrItem->deleteReminder( $_GET[ 'id' ] ) ) http_response_code( 500 ); // Internal server error
    die();



// ===== CATEGORY LEVEL AJAX REQUESTS =====

case "setCatProp":
    switch ( $_REQUEST[ 'name' ] ) {
    case "sendAlertTo":
    case "contactUserId":
    case "contactName":
    case "contactPhone":
    case "contactMail":
    case "showContactWhenBooking":
    case "caption":
    case "parentId":
    case "prebookMsg":
    case "postbookMsg":
    case "bufferAfterBooking":
        header( "Content-Type: application/json" );
        // check if email is a valid email address
        if ( $_REQUEST[ 'name' ] == "contactMail" && $_REQUEST[ 'value' ] !== "" && !filter_var( $_REQUEST[ 'value' ], FILTER_VALIDATE_EMAIL ) ) {
            die( json_encode( [ "status" => "contactMailInvalid" ] ) );
        }
        if ( $_REQUEST[ 'value' ] == "NULL" ) $cat->{$_REQUEST[ 'name' ]} = null;
        else $cat->{$_REQUEST[ 'name' ]} = $_REQUEST[ 'value' ];
        header( "Content-Type: application/json" );
        die( json_encode( [ "status" => "OK" ] ) );
    default:
        logger( __FILE__ . "Trying to set unknown category property via ajax.", "ERROR" );
        http_response_code( 400 ); die(); // Bad request
    }

case "getCatContactData":
    header( "Content-Type: application/json" );
    die( json_encode( [
        "catId" => $cat->id,
        "contactType" => $cat->contactType,
        "contactData" => $cat->contactData(),
        "contactName" => $cat->contactName,
        "contactPhone" => $cat->contactPhone,
        "contactMail" => $cat->contactMail
    ] ) );

case "addAlert":
    if ( !isset( $_GET[ 'sendAlertTo1' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    // Validate input
    if ( filter_var( $_GET[ 'sendAlertTo1' ], FILTER_VALIDATE_EMAIL ) === false) {
        http_response_code( 415 ); // Unsupported media type
        die( "{$_GET[ 'sendAlertTo1' ]} är ingen giltig epostadress." );
    }
    if ( $alerts = $cat->sendAlertTo ) $alerts = explode( ", ", $alerts );
    else $alerts = [];
    $alerts[] = $_GET[ 'sendAlertTo1' ];
    $cat->sendAlertTo = implode( ", ", $alerts );
    die();

case "deleteAlert":
    if ( !isset( $_GET[ 'sendAlertTo1' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    $alerts = explode( ", ", $cat->sendAlertTo );
    if ( ( $key = array_search( $_GET[ 'sendAlertTo1' ], $alerts ) ) !== false ) {
        unset( $alerts[ $key ] );
        $cat->sendAlertTo = implode( ", ", $alerts );
    }
    die();

case "setCatImage":
    $ret = $cat->setImage( $_FILES[ 'image' ] );
    if ( $ret === TRUE ) die( $cat->id );
    http_response_code( 415 ); // Unsupported media type
    die( $ret );

case "getCatAccess":
    $ret = "";
    foreach ( $cat->getAccessAll() as $key => $access ) {
        $acc = $cfg[ 'catAccessLevels' ][ $access[ 'level' ] ];
        if ( $key === "accessExternal" ) {
            if ( $access[ 'inherited' ] ) $ret .= "<li class='wrap'>Icke-medlemmar (ärvd behörighet)<p>{$acc}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Icke-medlemmar<p>{$acc}</p></a><a href='#' onclick=\"unsetAccess('accessExternal');\">Återkalla behörighet</a></li>";
        } elseif ( $key === "accessMember" ) {
            if ( $access[ 'inherited' ] ) $ret .= "<li class='wrap'>Medlem i valfri lokalavdelning (ärvd behörighet)<p>{$acc}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Medlem i valfri lokalavdelning<p>{$acc}</p></a><a href='#' onclick=\"unsetAccess('accessMember');\">Återkalla behörighet</a></li>";
        } elseif ( $key === "accessLocal" ) {
            if ( $access[ 'inherited' ] ) $ret .= "<li class='wrap'>Lokal medlem (ärvd behörighet)<p>{$acc}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>Lokal medlem<p>{$acc}</p></a><a href='#' onclick=\"unsetAccess('accessLocal');\">Återkalla behörighet</a></li>";
        } elseif ( is_numeric( $key ) ) {
            if ( $access[ 'inherited' ]) $ret .= "<li class='wrap'>$key " . ( $access[ 'name' ] ? htmlspecialchars( $access[ 'name' ] ) : "(ingen persondata tillgänglig)" ) . " (ärvd behörighet)<p>{$acc}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>$key " . ($access[ 'name' ] ? htmlspecialchars( $access[ 'name' ] ) : "(ingen persondata tillgänglig)") . "<p>{$acc}</p></a><a href='#' onclick=\"unsetAccess('$key');\">Återkalla behörighet</a></li>";    
        } else {
            if ( $access[ 'inherited' ] ) $ret .= "<li class='wrap'>$key (ärvd behörighet)<p>{$acc}</p></li>";
            else $ret .= "<li class='wrap'><a href='#' class='ajax-input'>$key<p>{$acc}</p></a><a href='#' onclick=\"unsetAccess('$key');\">Återkalla behörighet</a></li>";    
        }
    }
    header("Content-Type: application/json");
    if ( $ret ) die( json_encode( [ "html" => "<ul data-role='listview' data-inset='true' data-split-icon='delete' data-split-theme='c'>$ret</ul>" ] ) );
    die( json_encode( [ "html" => "<p><i>Inga behörigheter har tilldelats än. Använd alternativen ovan för att tilldela behörigheter.</i></p>" ] ) );

case "setCatAccess":
    if ( !isset( $_GET[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    $notice = "";
    switch ( $_GET[ 'id' ] ) {
    case "accessExternal":
    case "accessMember":
    case "accessLocal":
        $cat->{$_GET[ 'id' ]} = ( $_GET[ 'access' ] === "NULL" ? NULL : $_GET[ 'access' ] );
        break;
    default:
        $cat->setAccess( $_GET[ 'id' ], $_GET[ 'access' ] === "NULL" ? NULL : $_GET[ 'access' ] );
        if ( isset( $_GET[ 'access' ] ) && $_GET[ 'access' ] >= FFBoka::ACCESS_CONFIRM && is_numeric( $_GET[ 'id' ] ) ) {
            // New admin added. Send notification if not same as current user and if not an assignment group
            $adm = new User( $_GET[ 'id' ] );
            if ( $_GET[ 'id' ] != $currentUser->id && $adm->mail ) {
                $FF->sendMail(
                    $adm->mail, // to
                    "Du är nu bokningsansvarig", // subject
                    "notify_new_admin", // template
                    array( // replace
                        "{{name}}" => $adm->name,
                        "{{role}}" => "bokningsansvarig för kategorin {$cat->caption}",
                        "{{link}}" => $cfg[ 'url' ],
                        "{{sectionName}}" => $section->name,
                        "{{superadmin-name}}" => $currentUser->name,
                        "{{superadmin-mail}}" => $currentUser->mail,
                        "{{superadmin-phone}}" => $currentUser->phone
                    ),
                    [], // attachments
                    $cfg[ 'mail' ]
                );
            } elseif ( $_GET[ 'id' ] != $currentUser->id ) $notice = "OBS! Vi har inte någon epostadress till denna användare och kan inte meddela hen om den nya rollen. Därför ska du informera hen på annat sätt. Se gärna också till att hen loggar in och lägger upp sin epostadress för att kunna få meddelanden om nya bokningar.";
        }
    }
    header("Content-Type: application/json");
    die( json_encode( [ "notice"=>$notice ] ) );
    
case "getCatQuestions":
    $catQuestions = $cat->getQuestions();
    $ret = "";
    foreach ( $section->questions() as $question ) {
        $color = ""; $icon = ""; $mandatory = FALSE;
        if ( isset( $catQuestions[ $question->id ] ) ) {
            $icon = "check";
            if ( $catQuestions[ $question->id ]->inherited ) {
                $color = "style='background:var(--FF-lightblue);'";
            } else {
                $color = "style='color:white; background:var(--FF-blue);'";
            }
            if ( $catQuestions[ $question->id ]->required ) {
                $mandatory = TRUE;
            }
        } else {
            $icon = "false";
        }
        $ret .= "<li data-icon='$icon'>" .
            "<a href='#' $color onClick='toggleQuestion({$question->id});'>" .
            ( $mandatory ? "<span style='font-weight:bold; color:red;'>*</span> " : "" ) .
            "<span style='white-space:normal;'>" . htmlspecialchars( $question->caption ) . "</span>" . 
            "<p style='white-space:normal;' >{$question->optionsReadable()}</p>" .
            "</a></li>\n";
    }
    
    if ( $ret ) die( "<p><i><small>Klicka på frågorna som ska visas i bokningsflödet. Frågor med blå bakgrund är aktiverade. Klicka en gång till för att göra frågan obligatorisk (<span class='required'></span>). Klicka en tredje gång för att avaktivera frågan.</small></i></p>$ret" );
    die( "<p><i>Inga frågor har lagts upp i din lokalavdelning än. Om du vill att någon fråga ska visas vid bokning i denna kategori, be LA-administratören att lägga upp frågan. Detta ska göras på LA-nivå. När detta har gjorts kommer upplagda frågor att dyka upp här så du kan välja ut de frågor som ska visas med din kategori.</i></p>" );
    
case "toggleQuestion":
    if ( !isset( $_REQUEST[ 'id' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    // empty -> show -> show+required -> empty
    // inherited -> show+required -> inherited
    // inherited+required -> show -> inherited+required
    $questions = $cat->getQuestions();
    if ( isset( $questions[ $_REQUEST[ 'id' ] ] ) ) {
        if ( $questions[ $_REQUEST[ 'id' ] ]->inherited) {
            $cat->addQuestion( $_REQUEST[ 'id' ] );
        } elseif ( $questions[ $_REQUEST[ 'id' ] ]->required ) {
            $cat->removeQuestion( $_REQUEST[ 'id' ] );
        } else {
            $cat->addQuestion( $_REQUEST[ 'id' ], TRUE );
        }
    } else {
        $cat->addQuestion( $_REQUEST[ 'id' ] );
    }
    die();
    
case "deleteCat":
    if (!$cat->delete()) http_response_code( 500 ); // Internal server error
    die();
    
case "getCatFiles":
    die( showAttachments( $cat ) );

case "addCatFile":
    try {
        $cat->addFile( $_FILES[ 'file' ], $cfg[ 'allowedAttTypes' ], $cfg[ 'uploadMaxFileSize' ] );            
    } catch ( Exception $e ) {
        http_response_code( 400 ); // Bad request
        die( $e->getMessage() );
    }
    die();

case "setCatFileProp":
    if ( !isset( $_REQUEST[ 'fileId' ] ) ) { http_response_code( 400 ); die(); } // Bad request
    if ( $cat->setFileProp( $_REQUEST[ 'fileId' ], $_REQUEST[ 'name' ], $_REQUEST[ 'value' ]) ) die();
    http_response_code( 405 ); // Method not allowed 
    die( "Kunde inte spara." );
    
case "deleteCatFile":
    if ( !$cat->removeFile( $_REQUEST[ 'fileId' ] ) ) http_response_code( 500 ); // Internal server error
    die();



// ===== ITEM LEVEL AJAX REQUESTS =====
case "saveItemProp":
    switch ( $_REQUEST[ 'name' ] ) {
        case "caption":
        case "description":
        case "postbookMsg":
        case "active":
        case "note":
        case "imageId":
            if ( $_REQUEST[ 'value' ] == "NULL" ) $item->{$_REQUEST[ 'name' ]} = null;
            else $item->{$_REQUEST[ 'name' ]} = $_REQUEST[ 'value' ];
            break;
        default:
            http_response_code( 405 ); // Method not allowed
            logger( __METHOD__ . " Tried to set invalid item property {$_REQUEST[ 'name' ]}.", E_WARNING );
    }
    die();

case "deleteItem":
    if ( !$item->delete() ) http_response_code( 404 ); // Not found
    die();
    
case "getItemImages":
    foreach ( $item->images() as $image ) {
        $caption = htmlspecialchars( $image->caption );
        $mainImage = ( $image->id == $item->imageId ? " checked='true'" : "" );
        echo <<<EOF
        <div class='ui-body ui-body-a ui-corner-all'>
        <img class='item-img-preview' src='../image.php?type=itemImage&id={$image->id}'>
        <textarea class='item-img-caption ajax-input' placeholder='Bildtext' data-id='{$image->id}'>$caption</textarea>
        <div class='ui-grid-a'>
        <div class='ui-block-a'><label><input type='radio' name='imageId' onClick="setItemProp('imageId', {$image->id});" value='{$image->id}'$mainImage>Huvudbild</label></div>
        <div class='ui-block-b'><input type='button' data-corners='false' class='ui-btn ui-corner-all' value='Ta bort' onClick='deleteImage({$image->id});'></div>
        </div></div><br>
        EOF;
    }
    die();

case "addItemImage":
    if ( is_uploaded_file( $_FILES[ 'image' ][ 'tmp_name' ] ) ) {
        $image = $item->addImage();
        $res = $image->setImage( $_FILES[ 'image' ], $cfg[ 'maxImgSize' ], 80, $cfg[ 'uploadMaxFileSize' ] );
        if ( $res !== TRUE ) {
            $image->delete();
            http_response_code( 415 ); // Unsupported media type
            die( $res );
        }
        // Set as featured image if it is the first one for this item
        if ( !$item->imageId ) $item->imageId = $image->id;
        die();
    }
    http_response_code( 406 ); // Not acceptable
    die();
    
case "deleteItemImage":
    $image = new Image( $_GET[ 'id' ] );
    $image->delete();
    die();
    
case "saveItemImgCaption":
    $image = new Image( $_GET[ 'id' ] );
    $image->caption = $_GET[ 'caption' ];
    die( $image->caption );

default:
    http_response_code( 405 ); // Method not allowed
    die();
}
    


// ===== HELPER FUNCTIONS =====

/**
 * Creates HTML code which shows all attachments for $cat. Attachments defined at mother categories are shown as non-editable items.
 *
 * @param Category $cat The category for which to show the attachments
 * @param bool $inherited Indicates that the results shall be shown as non-editable, inherited items.
 * @return string HTML code
 */
function showAttachments( Category $cat, bool $inherited = false ) : string {
    $ret = "";
    if ( $parent = $cat->parent() ) $ret .= showAttachments( $parent, true );
    $files = $cat->files();
    if ( count( $files ) ) {
        foreach ( $files as $file ) {
            if ( $inherited ) $ret .= "<p class='ui-body ui-body-a'><b>" . htmlspecialchars( $file->caption ) . "</b><br><i>ärvt från kategori <a href='?catId={$cat->id}'>{$cat->caption}</a></i><br>" . ( $file->displayLink ? "• Visa länk vid bokning<br>" : "" ) . ( $file->attachFile ? "• Skicka filen i bekräftelsen<br>" : "" ) . "</p>";
            else $ret .= "<div class='ui-body ui-body-a'>
            <button style='position:absolute; right:0px; top:0px;' title='Radera bilagan' onClick='catFileDelete({$file->fileId})' class='ui-btn ui-btn-inline ui-btn-icon-notext ui-icon-delete' id='cat-file-delete-{$file->fileId}'>Radera bilaga</button>
            <h3><a id='cat-file-header-{$file->fileId}' href='../attment.php?fileId={$file->fileId}' data-ajax='false'>" . htmlspecialchars( $file->caption ) . "</a></h3>
            <p>Filnamn: " . htmlspecialchars( $file->filename ) . "</p>
            <div class='ui-field-contain'>
                <label for='cat-file-caption-{$file->fileId}'>Rubrik:</label>
                <input id='cat-file-caption-{$file->fileId}' onInput=\"clearTimeout(toutSetValue); toutSetValue = setTimeout(setCatFileProp, 1000, {$file->fileId}, 'caption', this.value);\" placeholder='Rubrik' value='" . htmlspecialchars( $file->caption ) . "'>
            </div>
            <fieldset data-role='controlgroup' data-mini='true'>
                <label><input onChange=\"setCatFileProp({$file->fileId}, 'displayLink', this.checked ? 1 : 0);\" type='checkbox'" . ( $file->displayLink == 1 ? " checked" : "" ) . "> Visa länk till fil i bokningsflödet</label>
                <label><input onChange=\"setCatFileProp({$file->fileId}, 'attachFile', this.checked ? 1 : 0);\" type='checkbox'" . ( $file->attachFile == 1 ? " checked" : "" ) . "> Skicka med som fil i bokningsbekräftelsen</label>
            </fieldset>\n</div>\n";
        }
    }
    if ( !$ret && !$inherited ) {
        $ret = "<p><i>Här laddar du upp filer som du vill skicka med vid bokning av resurser från den här kategorin eller underordnade kategorier.</i></p>";
    }
    return $ret;
}


