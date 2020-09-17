<?php
/**
 * This is a sample configuration for the resource booking system.
 * Copy it to config.php (in the same folder) and make adjustments
 * in that file. Do not write in your local configuration in this
 * sample file since it may be synchronized back to Github.
 */

use FFBoka\FFBoka;

$cfg = array(
    // Database connection settings
    "dbhost" => "127.0.0.1", // The host of the mariaDb server
    "dbport" => 3306,        // The DB port
    "dbname" => "ff-boka",   // The name of the database
    "dbuser" => "ff-boka",   // Username for connecting to mariaDb
    "dbpass" => "your secret password", // Password for that user

    // On a test system, users may e.g. elevate their own permissions.
    "testSystem" => FALSE,
    
    // Connection details to Friluftsfrämjandet's API
    "ff-api" => array(
        'authUrl' => "https://url.to.auth_api/path/to/api", // URL for authentication
        'authKey' => "secret_key", // Key for authentication
        'feedUrl' => "https://url.to.feed_api/", // Base URL to get user's assignments and sections, with trailing slash
        'feedAss' => "path/to/feed", // Feed to get all existing assignments
        'feedUserAss' => "path/to/feed?MNoSocnr=", // Feed to get a user's assignments. Member number or personnummer will be appended
        'feedSec' => "path/to/feed", // Feed to get all existing sections
        'feedSocnr' => "path/to/feed?MNoSocnr=", // Feed to convert personnummer to member number. Personnummer will be appended
    ),

    // Sender address, readable name and Reply-to address for auto-generated emails
    "mailFrom"     => "someone@somewhere.com",
    "mailFromName" => "Resursbokning",
    "mailReplyTo"  => "someone@somewhere.com",
    
    // SMTP settings for sending emails
    "SMTP" => array(
        "host" => 'smtp.mymaildomain.com',
        "port" => '587',
        "user" => 'someone@somewhere.com',
        "pass" => 'my smtp password',
    ),

    // Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
    "maxImgSize" => 1024,
    // Max file size for images and attachments in bytes
    "uploadMaxFileSize" => 10 * 1024 * 1024,
    // Allowed file types for attachments. Use the lower case file extension as key, and the filename
    // of the corresponding icon (in the resources folder) as value. document.svg is the generic icon.
    // Icons fetched from https://www.iconfinder.com (filter: iconset:hawcons)
    "allowedAttTypes" => array(
        "jpg" => "image.svg",
        "jpeg" => "image.svg",
        "png" => "image.svg",
        "pdf" => "pdf.svg",
        "odt" => "text.svg",
        "doc" => "text.svg",
        "docx" => "text.svg",
        "ods" => "spreadsheet.svg",
        "xlsx" => "spreadsheet.svg",
    ),
    
    // Locale to use
    "locale" => "sv_SE.UTF-8",
    "timezone" => "Europe/Stockholm",
    
    // Section bound assignments always giving admin access to section
    "sectionAdmins" => array('Ordförande', 'Vice ordförande'),
    
    // UserIDs of users with superAdmin access (will display a superAdmin section on Admin page)
    "superAdmins" => array(),
    // URL to zip file on Github containing the latest version
    "upgradeUrl" => "https://github.com/d-tamm/ff-boka/archive/master.zip",
    
    // Textual representations of access levels
    "catAccessLevels" => array(
        FFBoka::ACCESS_NONE     => "Ingen behörighet",
        FFBoka::ACCESS_READASK  => "Kan göra förfrågningar men inte se upptaget-information",
        FFBoka::ACCESS_PREBOOK  => "Kan se upptaget-information och preliminärboka",
        FFBoka::ACCESS_BOOK     => "Kan boka själv",
        FFBoka::ACCESS_CONFIRM  => "Bokningsansvarig: kan bekräfta och ändra bokningar",
        FFBoka::ACCESS_CATADMIN => "Kategoriadmin: Full behörighet"
    ),

    // DoS prevention, throttling
    "DoSCount" => 3,        // How many login attempts are allowed within DoSDelay seconds
    "DoSDelay" => 300,   // delay in seconds after DoSCount unsuccessful logins
    
    // How long shall persistent login ("remember me") be valid? (seconds)
    "TtlPersistentLogin" => 60*60*24*365,
    
    // When to do recurring jobs
    "cronDaily" => 2, // Hour of day 0...23
    "cronWeekly" => 7, // Day of week, Monday=1, Sunday=7
    "cronMonthly" => 1, // Day of month, 1...31

    // Welcome messages on landing page
    "welcomeMsg" => "<p class='ui-body ui-body-b'>Välkommen till Friluftsfrämjandets nya resursbokningssystem! Om du behöver hjälp, klicka på <b>?</b> uppe till höger.</p>",
    "welcomeMsgLoggedIn" => "<p class='ui-body ui-body-b'>Återkoppla gärna till utvecklarna med synpunkter och önskemål! Bara på det viset kan vi möta lokalavdelningarnas behov på bästa sätt.</p>",
);
