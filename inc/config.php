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
    "dbpass" => "", // see config.local.php

    // API connection (see config.local.php)
    "ff-api" => array(
        "authUrl"=>"", // URL for authentication
        "authKey"=>"", // Key for authentication
        "assUrl"=>"",  // URL to get user's assignments
    ),
	
	// Logging
	"logMaxSize" => 1*1024*1024, // in bytes

	// Sender address and readable name of auto-generated emails
	"mailFrom" => "resursbokning@friluftsframjandet.se",
    "mailFromName" => "Friluftsfrämjandets resursbokning",
	// SMTP settings for sending emails
	"SMTP" => array(
	    "host" => '', // see config.local.php
	    "port" => '', // see config.local.php
	    "user" => '', // see config.local.php
	    "pass" => '', // see config.local.php
    ),
	// ReplyTo address of auto-generated emails
	"mailReplyTo" => "resursbokning@friluftsframjandet.se",

	// Base URL of this platform, with trailing slash
	"url"    => "", // see config.local.php

	// Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
	"maxImgSize" => 1024,
	// Max file size for images in byte
	"uploadMaxFileSize" => 10 * 1024 * 1024,

	// Locale to use
	"locale" => "sv_SE.UTF-8",
    "timezone" => "Europe/Stockholm",
    
	// Section bound assignments always giving admin access to section
	"sectionAdmins" => array('Ordförande', 'Vice ordförande', 'Webbredaktör'),
    
    // UserIDs of users with superAdmin access (will display a superAdmin section on Admin page)
    "superAdmins" => array(),
    
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
);

// Include local/secret settings, too (those not to be synchronized to Github).
include __DIR__."/config.local.php";
