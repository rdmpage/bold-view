<?php

error_reporting(E_ALL);

global $config;

// Date timezone
date_default_timezone_set('UTC');

$local = false;
//$local = true;

$config['site_name'] = "Bold View";

if ($local)
{
	$config['web_server']	= 'http://localhost';
	$config['web_root']		= '/bold-view/';
}
else
{
	$config['web_server']	= 'https://bold-view-bf2dfe9b0db3.herokuapp.com';
	$config['web_root']		= '/';
}

// Default language is English
$config['lang'] = 'en';

?>
