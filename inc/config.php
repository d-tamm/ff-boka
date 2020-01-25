<?php

use FFBoka\FFBoka;

$cfg = array(
	// Database connection settings
	"dbhost" => "127.0.0.1",
	"dbname" => "ff-boka",
	"dbuser" => "ff-boka",
	"dbpass" => "", // see credentials.php

	// API connection
	"ff-api" => array(), // see credentials.php
	
	// Logging
	"logMaxSize" => 1*1024*1024, // in bytes

	// Sender address and readable name of auto-generated emails
	"mailFrom" => "resursbokning@friluftsframjandet.se",
    "mailFromName" => "Friluftsfrämjandets resursbokning",
	// SMTP settings for sending emails
	"SMTP" => array(
	    "host" => '', // see credentials.php
	    "port" => '', // see credentials.php
	    "user" => '', // see credentials.php
    	"pass" => '', // see credentials.php
    ),
	// ReplyTo address of auto-generated emails
	"mailReplyTo" => "resursbokning@friluftsframjandet.se",

	// Base URL of this platform, with trailing slash
	"url"    => "https://boka.tamm-tamm.de/",

	// Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
	"maxImgSize" => 1024,
	// Max file size for images in byte
	"uploadMaxFileSize" => 10 * 1024 * 1024,

	// Locale to use
	"locale" => "sv_SE.UTF-8",
    "timezone" => "Europe/Stockholm",
    
	// Section bound assignments always giving admin access to section
	"sectionAdmins" => array('Ordförande', 'Vice ordförande'),
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

// Include secret settings, too (those not to be synchronized to Github).
include __DIR__."/credentials.php";
