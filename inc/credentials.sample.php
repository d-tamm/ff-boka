<?php
// Sample for sensible configuration variables.
// This will be called from config.php
// Adjust to your local settings and save as credentials.php

// Database password
$cfg["dbpass"] = "your database password";

// API URL and key for authentication and user assignments
$cfg['ff-api']['authUrl'] = "https://url-to-the-API-where-login-credentials-are-verified";
$cfg['ff-api']['authKey'] = "authentication key for authUrl";
$cfg['ff-api']['assUrl']  = "https://url-to-the-API-to-get-user's-assignments";

// SMTP configuration
$cfg["SMTP"]['host'] = 'name of SMTP host';
$cfg["SMTP"]['port'] = 587;
$cfg["SMTP"]['user'] = 'someone@somewhere.com';
$cfg["SMTP"]['pass'] = 'your very secret SMTP password';

// You may also overwrite other settings from config.php