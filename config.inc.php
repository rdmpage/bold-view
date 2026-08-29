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
	$config['web_server']	= 'https://boldview.iphylo.org';
	$config['web_root']		= '/';
}

// Content store ---------------------------------------------------------------------------
// Harvested BOLD thumbnails, addressed by the SHA-1 of the image bytes and
// served from our own infrastructure (see rdmpage/bold-image-harvest).
// boldimage.sha1 is filled in incrementally as the harvest progresses, so any
// image we don't have yet still falls back to BOLD via image_proxy.php.
$config['content_store'] = 'https://content.bionames.org/sha1/';

$config['languages'] = ['en', 'zh'];

// Default language is English
$config['lang'] = 'en';

// Show language switcher in the nav bar (enable when translation coverage is sufficient)
$config['show_language_switcher'] = false;

// Show alignment of individual sequences in tree view
$config['show_alignment'] = false;

?>
