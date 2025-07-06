<?php
/**
 * This is a sample configuration for the resource booking system.
 * Copy it to config.php (in the same folder) and make adjustments
 * in that file. Do not put your local configuration into this
 * sample file since it may be synchronized back to Github.
 */

use FFBoka\FFBoka;

$cfg = [
    "url" => "http://localhost/", // URL of this installation, with trailing slash

    // Database connection settings
    "dbhost" => "mariadb", // The host of the mariaDb server
    "dbport" => 3306,        // The DB port
    "dbname" => "ff-boka",   // The name of the database
    "dbuser" => "ff-boka",   // Username for connecting to mariaDb
    "dbpass" => "dAtabas3_p4assw0rD", // Password for that user

    // On a test system, users may e.g. elevate their own permissions.
    "testSystem" => TRUE,
    
    // Connection details to Friluftsfrämjandet's API
    "ff-api" => [
        'authUrl' => "https://url.to.auth_api/path/to/api", // URL for authentication
        'authKey' => "secret_key", // Key for authentication
        'feedUrl' => "https://url.to.feed_api/", // Base URL to get user's assignments and sections, with trailing slash
        'feedAss' => "api/feed/Pan_ExtBokning_GetAllAssignmenttypes", // Feed to get all existing assignments
        'feedUserAss' => "api/feed/Pan_ExtBokning_GetAssignmentByMemberNoOrSocSecNo?MNoSocnr=", // Feed to get a user's assignments. Member number or personnummer will be appended
        'feedSec' => "api/feed/PAN_ExtBokning_GetSections", // Feed to get all existing sections
        'feedSocnr' => "api/feed/Pan_ExtBokning_GetUsernameByMNoSocnr?MNoSocnr=", // Feed to convert personnummer to member number. Personnummer will be appended
    ],

    // Email settings
    "mail" => [
        // Sender address, readable name and Reply-to address for auto-generated emails
        "from"     => "someone@somewhere.com",
        "fromName" => "Resursbokning testsystem",
        "replyTo"  => "someone@somewhere.com",
        "SMTPHost" => 'smtp.mymaildomain.com',
        "SMTPPort" => '587',
        "SMTPUser" => 'someone@somewhere.com',
        "SMTPPass" => 'smtp password',
    ],

    // Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
    "maxImgSize" => 1024,
    // Max file size for images and attachments in bytes
    "uploadMaxFileSize" => 10 * 1024 * 1024,
    // Allowed file types for attachments. Use the lower case file extension as key, and the filename
    // of the corresponding icon (in the resources folder) as value. document.svg is the generic icon.
    // Icons fetched from https://www.iconfinder.com (filter: iconset:hawcons)
    "allowedAttTypes" => [
        "jpg" => "image.svg",
        "jpeg" => "image.svg",
        "png" => "image.svg",
        "pdf" => "pdf.svg",
        "odt" => "text.svg",
        "doc" => "text.svg",
        "docx" => "text.svg",
        "ods" => "spreadsheet.svg",
        "xlsx" => "spreadsheet.svg",
    ],
    
    // Locale to use
    "locale" => "sv_SE.UTF-8",
    "timezone" => "Europe/Stockholm",
    
    // Section bound assignments always giving admin access to section
    "sectionAdmins" => [ 'Ordförande', 'Vice ordförande' ],
    
    // UserIDs of users with superAdmin access (will display a superAdmin section on Admin page)
    "superAdmins" => [ 0 ],
    
    // Textual representations of access levels
    "catAccessLevels" => [
        FFBoka::ACCESS_NONE     => "Ingen behörighet",
        FFBoka::ACCESS_READASK  => "Kan göra förfrågningar men inte se upptaget-information",
        FFBoka::ACCESS_PREBOOK  => "Kan se upptaget-information och preliminärboka",
        FFBoka::ACCESS_BOOK     => "Kan boka själv",
        FFBoka::ACCESS_CONFIRM  => "Bokningsansvarig: kan bekräfta och ändra bokningar",
        FFBoka::ACCESS_CATADMIN => "Kategoriadmin: Full behörighet"
    ],

    // DoS prevention, throttling
    "DoSCount" => 3,        // How many login attempts are allowed within DoSDelay seconds
    "DoSDelay" => 300,   // delay in seconds after DoSCount unsuccessful logins
    
    // How long shall persistent login ("remember me") be valid? (seconds)
    "TtlPersistentLogin" => 60 * 60 * 24 * 365,
    
    // When to do recurring jobs
    "cronDaily" => 2, // Hour of day 0...23
    "cronWeekly" => 7, // Day of week, Monday=1, Sunday=7
    "cronMonthly" => 1, // Day of month, 1...31

    // Name and location of log file. Use an absolute path. If not set or not writable, logging will be directed to the php log file.
    "logFile" => realpath( __DIR__ . "/../.." ) . "/ff-boka.log",
    // Max log file size in bytes. Defaults to 1 MB if not set.
    "logMaxSize" => 1024 * 1024,

    // Welcome messages on landing page
    "welcomeMsg" => "<p class='ui-body ui-body-c'>OBS: Detta är en testplattform till Friluftsfrämjandets resursbokningssystem! Gå till <a href='https://boka.friluftsframjandet.se'>boka.friluftsframjandet.se</a> om du faktiskt vill boka något. Om du behöver hjälp, klicka på <b>?</b> uppe till höger.</p>",
    "welcomeMsgLoggedIn" => "<p class='ui-body ui-body-c'>OBS: Detta är en testplattform till Friluftsfrämjandets resursbokningssystem! Gå till <a href='https://boka.friluftsframjandet.se'>boka.friluftsframjandet.se</a> om du faktiskt vill boka något. Återkoppla gärna till utvecklarna med synpunkter och önskemål! Bara på det viset kan vi möta lokalavdelningarnas behov på bästa sätt.</p>",
    "maintenance" => false,
];
