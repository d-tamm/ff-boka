<?php
use FFBoka\FFBoka;

$cfg = array(
	// Database connection settings
	"dbhost" => "127.0.0.1",
	"dbname" => "ff-boka",
	"dbuser" => "ff-boka",
	"dbpass" => "", // see credentials.php

	// API connection
	"apiUser" => "",
	"apiPass" => "",
	"apiUrl" => "", // see credentials.php
	
	// Logging
	"logMaxSize" => 1*1024*1024, // in bytes

	// Sender address of auto-generated emails
	"mailFrom" => "daniel.tamm@friluftsframjandet.se",
	// SMTP settings for sending emails
	"SMTPHost" => 'manu20.manufrog.com',
	"SMTPPort" => 587,
	"SMTPUsername" => 'nextcloud@tamm-tamm.de',
	"SMTPPassword" => '', // see credentials.php
	
	// ReplyTo address of auto-generated emails
	"mailReplyTo" => "daniel.tamm@friluftsframjandet.se",

	// Base URL of this platform, with trailing slash
	"url"    => "https://boka.tamm-tamm.de/",

	// Max size of images in pixels (longer side). If larger images are submitted, they will be downscaled.
	"maxImgSize" => 1024,
	// Max file size for images in byte
	"uploadMaxFileSize" => 10 * 1024 * 1024,

	// Locale to use
	"locale" => "sv_SE.UTF-8",

	// Section bound assignments always giving admin access to section
	"sectionAdmins" => array('Ordförande', 'Vice ordförande'),
	"catAccessLevels" => array(
	    FFBoka::ACCESS_NONE     => "Ingen behörighet",
	    FFBoka::ACCESS_READASK  => "Kan göra förfrågningar men inte se upptaget-information",
	    FFBoka::ACCESS_PREBOOK  => "Kan se upptaget-information och preliminärboka",
	    FFBoka::ACCESS_BOOK     => "Kan boka själv",
	    FFBoka::ACCESS_CONFIRM  => "Kan bekräfta förfrågningar och ändra bokningar",
        FFBoka::ACCESS_CATADMIN => "Kategoriadmin: Full behörighet"
    ),

	// DoS prevention, throttling
	"DoSCount" => 5,		// How many login attempts are allowed within DoSDelay seconds
	"DoSDelay" => 18,   // delay in seconds after DoSCount unsuccessful logins
	
	// How long shall persistent login ("remember me") be valid? (seconds)
	"persistLogin" => 60*60*24*365,
);

// Include secret settings, too (those not to be synchronized to Github).
include __DIR__."/credentials.php";
