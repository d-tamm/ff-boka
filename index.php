<?php
use FFBoka\FFBoka;
use FFBoka\Section;
use FFBoka\User;
use FFBoka\Poll;

session_start();
require __DIR__ . "/inc/common.php";
global $db, $cfg, $FF;
$message = "";

if ( isset( $_REQUEST[ 'action' ] ) ) {
    switch ( $_REQUEST[ 'action' ] ) {
        case "help":
            $allAss = $cfg[ 'sectionAdmins' ];
            $oneAss = array_pop( $allAss );
            echo "
<h3>Få hjälp</h3>
<p>Grattis! Du har redan hittat frågetecknet <img src='resources/help.svg' style='height:1em;'>
uppe i högra hörnet. Det finns på alla sidor och visar olika innehåll beroende på vilken sida du
är på.</p>

<h3>Inloggning</h3>
<p>Resursbokningen använder samma inloggningstjänst som Friluftsfrämjandets aktivitetshanterare.
Det innebär att du kan använda både medlemsnummer och personnummer, och att du använder samma
lösenord som i aktivitetshanteraren. Har du tidigare loggat in i resursbokningen och aktiverat
din epostadress kan du även använda den istället för medlemsnumret. Har du problem med 
inloggningen, vänd dig i första hand till dem som har hand om inloggningen på friluftsframjandet.se.</p>

<h3>Kom igång med din lokalavdelning</h3>
<ul>
    <li>Uppdragen <b>" . ( $allAss ? implode( "</b>, <b>", $allAss ) . "</b> och <b>" : "" ) . $oneAss . "</b> från aktivitetshanteraren har alltid administratörsbehörighet i tillhörande lokalavdelning. För att komma igång med att använda resursbokningen i din lokalavdelning måste någon av dessa logga in först.</li>
    <li>När du har loggat in med en av dessa roller hamnar du direkt i administrationsdelen för din lokalavdelning (gäller bara så länge ni inte har skapat någon administratör).</li>
    <li>På adminsidan i avsnittet Administratörer, lägg till de personer som framöver ska ta hand om resursbokningen i lokalavdelningen (LA-administratör). Detta är den högsta behörighetsnivån och används för att skapa grundstrukturen och administrera behörigheten i kategorierna. OBS: När du söker efter personer som ska bli admin och som alrig har loggat in i systemet ska du använda deras medlemsnummer. Att söka efter namn går först när de har skapat sin användarprofil.</li>
    <li>Från den här punkten kan du som är " . ( $allAss ? implode( ", ", $allAss ) . " eller " : "" ) . $oneAss . " lämna över ansvaret till dina LA-administratörer. Om du själv ska administrera bokningen så måste du göra dig själv till admin!</li>
    <li>LA-administratören kan nu fortsätta med att lägga upp kategorier. Beroende på hur omfattande verksamhet ni har kan ni välja att lägga alla kategorier direkt på huvudnivån, eller skapa underkategorier. Ni kan använda så många nivåer som ni vill.</li>
    <li>För varje kategori kan behörigheter ställas in för att styra dels vem som ska kunna boka utrustningen, och dels vem som ska ta hand om resurserna (kategoriansvarig) och inkomna bokningar (bokningsansvarig). Inställningar som görs i en överordnad kategori gäller även dess underkategorier.</li>
    <li>Kategoriansvarig eller LA-administratör kan slutligen lägga upp resurserna.</li>
</ul>
<p>Läs gärna även hjälptexten på admin-sidan!</p>

<h3>Global sökning</h3>
<p>Här på startsidan kan du efter inloggning leta efter resurser i hela Sverige. Systemet letar då efter kategorier med
matchande namn och där du har behörighet att boka. Sökningen går inte ner på resursnivå.</p>

<h3>Säkerhet och integritet</h3>
<p>Vi jobbar aktivt med säkerheten och integriteten på sajten:</p>
<ul>
    <li>Vi sparar aldrig ditt lösenord, varken i klartext eller krypterat.</li>
    <li>När vi visar epostadresser här på hemsidan gör vi det på ett sätt som gör det praktiskt omöjligt för automatiserade system att läsa ut adressen i syfte att missbruka den för att skicka spam.</li>
    <li>Det går att ansluta med en krypterad uppkoppling. Skriv \"https://\" i webbläsarens adressfält. " . ( $_SERVER[ 'HTTPS' ] ? " - Grattis! Du är ansluten med en säker, krypterad förbindelse." : "<br><strong style='color:red;'>Du använder just nu en osäker anslutning.</strong> <a href='https://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}'>Klicka här</a> för att byta till krypterad uppkoppling." ) . "</li>
</ul>

<h3>Personuppgifter och GDPR</h3>
<p>Det ligger i sakens natur att vi måste hantera vissa personuppgifter för att kunna bedriva bokningssystemet. De uppgifter som sparas om dig i systemet är:</p>
<ul>
    <li>De <a href='userdata.php'>kontaktuppgifter</a> som du själv har angett vid registreringen (namn, telefon och mejl). De behövs för att plattformen ska kunna fungera. T.ex. används din epost-adress för att kunna skicka bekräftelser och påminnelser om bokningar. Kontaktuppgifterna visas för inloggade användare i samband med dina bokningar, så att administratörer eller användare med föregående/efterföljande bokningar kan ta kontakt med dig om det uppstår frågor. Kontaktuppgifterna visas inte för gäster.</li>
    <li>Om du gör en bokning kommer all data som du lämnar med bokningen vara tillgänglig för respektive materialansvarig/bokningsadmin. Informationen visas inte för andra användare.</li>
    <li>Informationen om dina bokningar sparas i två år. Om du raderar ditt konto tas all information om dig bort omedelbart.</li>
    <li>Vi delar aldrig dina uppgifter med tredje part. De används enbart inom resursbokningssystemet.</li>
    <li>Om du är kontaktperson för en kategori kommer dina kontaktuppgifter att visas för användare som vill boka utrustningen.</li>
</ul>
<p>Du kan alltid höra av dig till oss för att ta reda på vad som är sparat om just dig.</p>

<h3>Kontakt</h3>
<p>Om du har frågor eller synpunkter vill vi väldigt gärna veta det för att hjälpa dig och göra systemet bättre! Skicka ett mejl till adressen som du hittar längst ner på startsidan, eller gå till vårt team <a href='https://teams.microsoft.com/l/team/19%3ad94d6ea5be8c4dc99827f5a8027fa713%40thread.tacv2/conversations?groupId=d2e0218f-ec87-4b7d-8e74-d2b91e530c9b&tenantId=f68d9ffd-156c-4e18-8cb6-7c55c3ec7111' target='_blank'>Resursbokning</a> i Teams som du har tillgång till som ledare med Friluftsfrämjandet-adress.</p>";
            die();
        case "exit_impersonate":
            if ( isset( $_SESSION[ 'impersonate_realUserId' ] ) ) {
                $_SESSION[ 'authenticatedUser' ] = $_SESSION[ 'impersonate_realUserId' ];
                unset( $_SESSION[ 'impersonate_realUserId' ] );
                $currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
                $currentUser->getAssignments();
                header( "Location: {$cfg[ 'url' ]}admin/superadmin.php" );
                die();
            }
            break;
        case "make me admin":
            if ( $cfg[ 'testSystem' ] === TRUE ) {
                if ( is_numeric( $_REQUEST[ 'sectionId' ] ) ) {
                    $section = new Section( $_REQUEST[ 'sectionId' ] );
                    if ( $section->addAdmin( $_SESSION[ 'authenticatedUser' ] ) ) {
                        $message = "Bra jobbat! Du har nu administratörsrollen i {$section->name}. Titta gärna runt och återkoppla till Daniel med dina erfarenheter!";
                    } else {
                        $message = "Något har gått fel.";
                    }
                }
            }
            break;
        case "accountDeleted":
            $message = "Ditt konto har nu raderats. Välkommen åter!";
            break;
        case "bookingNotFound":
            $message = "Bokningen finns inte i systemet.";
            break;
        case "sessionExpired":
            if ( empty( $_COOKIE[ 'remember' ] ) ) {
                $message = "Du har blivit utloggad på grund av inaktivitet.";
                // Remove session
                session_unset();
                session_destroy();
                session_write_close();
                setcookie( session_name(), "", 0, "/" );
            } elseif ( $_REQUEST[ 'redirect' ] && $_SESSION[ 'authenticatedUser' ] ) {
                // This happens if user has checked Remember Me
                header( "Location: {$cfg[ 'url' ]}{$_REQUEST[ 'redirect' ]}" );
                die();
            }
            break;
        case "accessDenied":
            $message = "Du har inte tillgång till {$_REQUEST[ 'to' ]}.";
            break;
        case "ajaxAnswerPoll":
            $poll = new Poll( $_REQUEST[ 'pollId' ] );
            $poll->addVote( $_REQUEST[ 'choiceId' ], $_SESSION[ 'authenticatedUser' ] );
            die( json_encode( [ "status" => "OK" ] ) );
        case "ajaxGlobalSearch":
            if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
                header( "Content-Type: application/json" );
                $user = new User( $_SESSION[ 'authenticatedUser' ] );
                $ret = $user->findResource( $_GET[ 'q' ] );
                die( json_encode( [ "sections" => $ret, "status" => "OK" ] ) );
            } else {
                header( "HTTP/1.0 401 Unauthorized" );
                die();
            }
    }
}

if ( isset( $_POST[ 'login' ] ) ) {
    // User trying to log in.
    // Reject DoS attacks by throttling
    $stmt = $db->query( "SELECT COUNT(*) FROM logins WHERE ip='{$_SERVER['REMOTE_ADDR']}' AND TIMESTAMPDIFF(SECOND, timestamp, NOW()) < {$cfg['DoSDelay']} AND NOT success" );
    if ( $stmt->fetchColumn() > $cfg[ 'DoSCount' ] ) {
        // Too many attempts. We do not even bother to log this to login log.
        $message = "För många inloggningsförsök. Försök igen om " . (int)($cfg[ 'DoSDelay' ] / 60 ) . " minuter.";
    } else {
        $result = $FF->authenticateUser( $_POST[ 'id' ], $_POST[ 'password' ] );
        if ( $result === FALSE ) {
            $message = "Kan inte få kontakt med inloggningsservern. Vänligen försök igen senare. Om problemet kvarstår, kontakta systemadmin.";
        }
        elseif ( $result[ 'authenticated' ] === true ) {
            logger( __FILE__ . " User {$result[ 'userId' ]} (LA {$result['section']}) logged in with {$_POST[ 'id' ]}." );
            $_SESSION[ 'authenticatedUser' ] = $result[ 'userId' ];
            $u = new User( $_SESSION[ 'authenticatedUser' ], $result[ 'section' ] );
            $u->getAssignments();
            $u->updateLastLogin();
            $stmt = $db->prepare( "INSERT INTO logins (ip, login, userId, success, userAgent) VALUES (?, ?, {$result[ 'userId' ]}, 1, '{$_SERVER[ 'HTTP_USER_AGENT' ]}')" );
            $stmt->execute( [
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
                $_POST[ 'id' ]
            ] );
            // If requested, set persistent login cookie
            if ( isset( $_POST[ 'rememberMe' ] ) ) {
                if ( $u->createPersistentLogin( $cfg[ 'TtlPersistentLogin' ] ) === FALSE ) die( "Kan inte skapa permanent inloggning." );
            }
            // Redirect if requested by login form
            if ( $_REQUEST[ 'redirect' ] ) {
                header( "Location: {$cfg[ 'url' ]}{$_REQUEST[ 'redirect' ]}" );
                die();
            }
            // Redirect Ordförande etc on first login
            if ( count( array_intersect( $_SESSION[ 'assignments' ][ $u->section->id ], $cfg[ 'sectionAdmins' ] ) ) > 0 && count( $u->section->getAdmins() ) == 0 ) {
                logger( __FILE__ . " First login for section {$u->section->id}" );
                header( "Location: admin/index.php?sectionId=" . $u->section->id . "&expand=admins" );
                die();
            }
        } else {
            // Password wrong.
            logger( __FILE__ . " User failed to login with {$_POST['id']}." );
            $message = "Fel medlemsnummer eller lösenord.";
            $stmt = $db->prepare( "INSERT INTO logins (ip, login, success, userAgent) VALUES (?, ?, 0, '{$_SERVER[ 'HTTP_USER_AGENT' ]}')" );
            $stmt->execute( [
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
                $_POST[ 'id' ]
            ] );
        }
    }
}

if ( isset( $_REQUEST[ 't' ] ) ) {
    // Token handling.
    // Figure out if the given token is still valid, and what it shall be used for.
    try {
        $token = $FF->getToken( $_REQUEST[ 't' ] );
        switch ( $token->useFor ) {
            case "change mail address":
                $user = new User( $token->forId );
                $user->mail = $token->data;
                $FF->deleteToken( $token->token );
                $message = "Grattis! Din epostadress {$token->data} är nu aktiverad.";
                logger( __FILE__ . " User {$token->forId} activated new email address." );
                break;
        }
    } catch ( Exception $e ) {
        logger( __FILE__ . " User failed to use token. " . $e->getMessage() );
        $message = $e->getMessage();
    }
}

if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
    $currentUser = new User( $_SESSION[ 'authenticatedUser' ] );
    // Redirect impersonated Ordförande etc on first login
    if ( isset( $_SESSION[ 'impersonate_realUserId' ] ) && isset( $_GET[ 'login' ] ) && count( array_intersect( $_SESSION[ 'assignments' ][ $currentUser->section->id ], $cfg[ 'sectionAdmins' ] ) ) > 0 && count( $currentUser->section->getAdmins() ) == 0 ) {
        header( "Location: admin/index.php?sectionId=" . $currentUser->section->id . "&expand=admins" );
        die();
    }
    if ( isset( $_REQUEST[ 'logout' ] ) ) {
        // Remove persistent login cookie
        $currentUser->removePersistentLogin();
        $currentUser = NULL;
        // Remove session
        session_unset();
        session_destroy();
        session_write_close();
        setcookie( session_name(), "", 0, "/" );
    } elseif ( !isset( $_REQUEST[ 't' ] ) && ( !$currentUser->name || !$currentUser->mail || !$currentUser->phone ) ) {
        // We are missing contact details for this user. Redirect to page where he/she must supply them.
        // (We don't allow to use the system without contact data.)
        header( "Location: userdata.php?first_login=1" );
        die();
    }
} else $currentUser = NULL;

if ( isset( $_REQUEST[ 'message' ] ) ) $message = ( $message ? "$message<br>" : "" ) . $_REQUEST[ 'message' ];

?><!DOCTYPE html>
<html>
<head>
    <?php htmlHeaders( "Friluftsfrämjandets resursbokning", $cfg[ 'url' ] ) ?>
</head>


<body>
<div data-role="page" id="page-start">
    <?= head( "Resursbokning", $cfg[ 'url' ], $cfg[ 'superAdmins' ] ) ?>
    <div role="main" class="ui-content">

    <div data-role="popup" data-history="false" data-overlay-theme="b" id="popup-msg-page-start" class="ui-content">
        <p id="msg-page-start"><?= $message ?></p>
        <a href='#' data-rel='back' class='ui-btn ui-btn-icon-left ui-btn-inline ui-icon-check'>OK</a>
    </div>

    <img src="<?= $cfg[ 'url' ] ?>resources/liggande-bla.png" style="width:100%; max-width:300px; display:block; margin-left:auto; margin-right:auto;">

    <?= isset( $_SESSION[ 'authenticatedUser' ] ) ? $cfg[ 'welcomeMsgLoggedIn' ] : $cfg[ 'welcomeMsg' ] ?>
    
    <?php
    // Show poll?
    $poll = NULL;
    if ( !is_null( $currentUser ) ) {
        $poll = $currentUser->getUnansweredPoll();
        if ( !is_null( $poll ) ) {
            echo "<div class='ui-body ui-body-b' id='poll-page-start'>";
            echo "<p>{$poll->question}</p>";
            foreach ( $poll->choices as $index => $choice ) {
                echo "<a href='#' onClick='answerPoll({$poll->id}, $index);' style='white-space:normal;' class='ui-btn ui-btn-a'>" . htmlspecialchars( $choice ) . "</a>";
            }
            echo "</div>";
            echo "<div data-role='popup' data-overlay-theme='b' id='popup-poll-page-start' class='ui-content'>Tack för ditt svar!</div>";
        }
    }
    
    if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
        if ( $ub = $currentUser->unfinishedBookings() ) {
            echo "<p class='ui-body ui-body-c'>Du har minst en påbörjad bokning som du bör avsluta eller ta bort.";
            echo "<a href='book-sum.php?bookingId={$ub[ 0 ]}' class='ui-btn ui-btn-a'>Gå till bokningen</a></p>";
        }
    }
    ?>

    <div data-role='collapsibleset' data-inset='false'>
        <?php if ( isset( $_SESSION[ 'authenticatedUser' ] ) ) {
            // Show link for booking in user's home section
            $homeSection = new Section( $currentUser->sectionId );
            if ( $homeSection->showFor( $currentUser ) ) echo "<a href='book-part.php?sectionId={$homeSection->id}' class='ui-btn ui-btn-icon-right ui-icon-home' style='white-space:normal;'>Boka resurser i " . htmlspecialchars( $homeSection->name ) . "</a>";
            // Show a list of all sections with categories where user may book resources
            $otherSections = "";
            foreach ( $FF->getAllSections() as $section ) {
                if ( $section->showFor( $currentUser ) && count( $section->getMainCategories() ) && $section->id != $homeSection->id ) {
                    $otherSections .= "<option value='{$section->id}'>" . htmlspecialchars( $section->name ) . "</option>";
                }
            }
            if ( $otherSections ) echo "<select onChange=\"location.href='book-part.php?sectionId='+this.value;\"><option>Boka i annan lokalavdelning</option>$otherSections</select>"; ?>

            <?php
            // Show a list of all sections where user has admin role
            foreach ( $FF->getAllSections() as $section ) {
                if ( $section->showFor( $currentUser, FFBoka::ACCESS_CATADMIN ) ||
                    @array_intersect( $_SESSION[ 'assignments' ][ $section->id ] ?? [], $cfg[ 'sectionAdmins' ] ) ) {
                        $unconfirmed = [];
                        foreach ( $section->getUnconfirmedBookings( $currentUser ) as $bookingId ) {
                            if ( !isset( $unconfirmed[ $bookingId ] ) ) $unconfirmed[ $bookingId ] = 0;
                            $unconfirmed[ $bookingId ]++;
                        }
                        echo "<a href='admin/?sectionId={$section->id}' class='ui-btn ui-btn-icon-right " . ($unconfirmed ? "ui-btn-c ui-icon-alert" : "ui-icon-gear") . "' data-transition='slideup' title='" . ( $unconfirmed ? "Det finns " . count( $unconfirmed ) . " obekräftade bokningar" : "Öppna administrationssidan") . "'>Admin " . htmlspecialchars( $section->name ) . ( $unconfirmed ? " (" . count( $unconfirmed ) . ")" : "") . "</a>";
                }
            } ?>

            <form class="ui-filterable">
                <input id="search-autocomplete-input" data-type="search" placeholder="Leta efter resurser...">
            </form>
            <ul id="search-autocomplete" data-role="listview" data-filter="true" data-input="#search-autocomplete-input" data-inset="true"></ul>

            <?php if ( $cfg[ 'testSystem' ] === TRUE ) { ?><br>
            <form class="ui-body ui-body-a">
                <p>Detta är ett testsystem. Du kan därför ge dig själv administratörs-behörighet i valfri lokalavdelning för att testa alla funktioner.</p>
                <input type="hidden" name="action" value="make me admin">
                <select name="sectionId">
                    <option>Välj lokalavdelning</option><?php
                    $stmt = $db->query( "SELECT * FROM sections ORDER BY name" );
                    while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
                        echo "<option value='{$row->sectionId}'>{$row->name}</option>";
                    } ?>
                </select>
                <input data-theme="b" type="submit" data-corners="false" value="Gör mig till admin">
            </form><?php
            }
        
        } ?>
        
    </div><!-- /collapsibleset -->

    <?php if ( !isset( $_SESSION[ 'authenticatedUser' ] ) ) { ?>
        <div data-role='collapsible' data-collapsed='true'>
            <h3>Boka som gäst</h3>
            <?php // List of sections with categories open for guests
            $numCats = 0;
            foreach ( $FF->getAllSections() as $section ) {
                if ( $section->showFor( new User( 0 ) ) && count( $section->getMainCategories() ) ) {
                    echo "<a href='book-part.php?sectionId={$section->id}&guest' class='ui-btn'>" . htmlspecialchars( $section->name ) . "</a>";
                    $numCats++;
                }
            }
            if ( $numCats == 0 ) echo "<p><i>Det finns för närvarande inte några resurser du kan boka som gäst.</i></p>";
            ?>
        </div>

        <form id="formLogin" style="padding:10px 20px;" action="index.php" method="post" data-ajax="false">
            <h3>Inloggning</h3>
            <input type="hidden" name="redirect" id="loginRedirect" value="<?= $_REQUEST['redirect'] ?? "" ?>">
            <input name="id" value="" placeholder="Medlemsnummer eller personnummer" required>
            <input name="password" value="" placeholder="Lösenord" type="password">
            <label><input data-mini='true' name='rememberMe' value='1' type='checkbox'> Kom ihåg mig (sätter en permanent kaka)</label>
            <button name="login" value="login" class="ui-btn ui-shadow ui-btn-b ui-btn-icon-right ui-icon-user">Logga in</button>
        </form>
    <?php } ?>
    
    <h3>Senaste nytt</h3>
    <ul data-role="listview" data-inset="true">
        <?php
        $stmt = $db->query( "SELECT * FROM news ORDER BY date DESC LIMIT 3" );
        while ( $row = $stmt->fetch( PDO::FETCH_OBJ ) ) {
            echo "<li>
            <h3>{$row->caption}</h3>
            <p style='white-space:normal;'>{$row->body}</p>
            <p class='ui-li-aside'><strong>{$row->date}</strong></p>
            </li>";
        }
        ?>
    </ul>
    <p align='right'><a href="news.php">Visa fler nyheter</a></p>

    <div class="ui-body ui-body-a">
        <?php
		$stmt = $db->query( "SELECT COUNT(DISTINCT sectionId) FROM sections JOIN categories USING (sectionId) JOIN items USING (catId)" );
		$noSections = $stmt->fetchColumn();
		$stmt = $db->query( "SELECT COUNT(*) FROM items INNER JOIN categories USING (catId) WHERE categories.active AND items.active" );
		$noItems = $stmt->fetchColumn(); ?> 
        <h3>Om resursbokningen</h3>
        <p>Resursbokningen på <?= $cfg[ 'url' ] ?> är Friluftsfrämjandets plattform för att hantera resurser som olika lokalföreningar har, såsom kanoter, stugor och mycket annat. Tänk om du vill boka kajaksläpet med 12 kajaker, paddlar, flytvästar, kapell mm. Det blir många resurser som ska in i bokningen, och vi har inte hittat något befintligt system där detta går att göra på ett smidigt sätt. Därför har vi skapat vårt eget system. Det ska vara enkelt att tillgängliggöra våra resurser till våra medlemmar och även till externa!</p>
        <p>Plattformen är optimerad för användning i mobilen eftersom det är där den används mest. Den fungerar lika bra i datorn, men är inte så bra på att använda stora skärmar.</p>
        <p>Systemet utvecklas av volontärer inom Friluftsfrämjandet. Du kan komma i kontakt med oss på <?= obfuscatedMaillink( $cfg[ 'mail' ][ 'replyTo' ] ) ?> samt via <a href="https://github.com/d-tamm/ff-boka/">Github</a> där källkoden ligger och där vi hanterar buggar och nya funktioner. Som ledare med FF-adress kan du också hitta oss i Teams-gruppen "Resursbokning".</p>
        <p>Resursbokningen används för närvarande av <?= $noSections ?> lokalavdelningar som tillsammans har lagt upp <?= $noItems ?> resurser.</p>
    </div>
    </div><!--/main-->

</div><!--/page-->
</body>
</html>
