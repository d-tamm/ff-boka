<?php
/**
 * This file is synchronized to GitHub. Therefore, do not change this file
 * for adjustments to you local installation. Instead, put your local configuration
 * into the file config.local.php.
 */

use FFBoka\FFBoka;

$cfg = array(
	// Database connection settings
	"dbhost" => "127.0.0.1",
	"dbname" => "ff-boka",
	"dbuser" => "ff-boka",
    "dbpass" => "1zpR0B4g9hYHZIM4",

    // Connection details to Friluftsfrämjandet's API
    // Managed on https://ffapimanagement.developer.azure-api.net/
    "ff-api" => array(
        'authUrl' => "https://ffapimanagement.azure-api.net/VerifyMemberBooking/VerifyMemberBooking", // API URL for authentication and home section
        'authKey' => "4e69a664bf7f4920b28e726fc3f71359",
        'feedUrl' => "https://panoramacwp.azurewebsites.net/",
        'feedAss'     => "api/feed/Pan_ExtBokning_GetAllAssignmenttypes", // Feed to get all existing assignments
        'feedUserAss' => "api/feed/Pan_ExtBokning_GetAssignmentByMemberNoOrSocSecNo?MNoSocnr=", // Feed to get a user's assignments. Append member number or personnummer.
        'feedSec'     => "api/feed/PAN_ExtBokning_GetSections", // Feed to get all existing sections
    ),
	
	// Logging
	"logMaxSize" => 1*1024*1024, // in bytes

	// Sender address, readable name and reply-to address for auto-generated emails
	"mailFrom" => "resursbokning@friluftsframjandet.se",
    "mailFromName" => "Friluftsfrämjandets resursbokning",
    "mailReplyTo" => "resursbokning@friluftsframjandet.se",
    
	// SMTP settings for sending emails
	"SMTP" => array(
	    "host" => 'smtp.office365.com',
	    "port" => '587',
	    "user" => 'resursbokning@friluftsframjandet.se',
	    "pass" => 'l59vwJp8bX5kP8v2',
    ),
    
	// The URL of this installation, with trailing slash
    "url" => "http://localhost/",

	// Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
	"maxImgSize" => 1024,
	// Max file size for images in byte
	"uploadMaxFileSize" => 10 * 1024 * 1024,

	// Locale to use
	"locale" => "sv_SE.UTF-8",
    "timezone" => "Europe/Stockholm",
    
	// Section bound assignments always giving admin access to section
	"sectionAdmins" => array('Ordförande', 'Vice ordförande'),
    
    // UserIDs of users with superAdmin access (will display a superAdmin section on Admin page)
    "superAdmins" => array(864015),
    
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
	"DoSCount" => 3,		// How many login attempts are allowed within DoSDelay seconds
	"DoSDelay" => 300,   // delay in seconds after DoSCount unsuccessful logins
	
	// How long shall persistent login ("remember me") be valid? (seconds)
	"TtlPersistentLogin" => 60*60*24*365,
    
    // Current db version
    "db-version" => 0,
);