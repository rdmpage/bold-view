<?php

error_reporting(E_ALL);

require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/vendor/autoload.php');

use Symfony\Component\Yaml\Yaml;

$text_strings = array();

//----------------------------------------------------------------------------------------
// get current language
function get_language()
{
	global $config;
	
	/*
	if (isset($_COOKIE["language"])) 
	{ 
		$lang = $_COOKIE["language"];
	    $config['lang'] = $lang;
	}
	else
	{
		$lang = $config['lang'];
		setcookie('language', $config['lang']); 
	}
	*/
	
	$lang = $config['lang'];
	return $lang;
}

//----------------------------------------------------------------------------------------
// Set language and update text strings to use that language
function set_language($lang)
{
	global $config;
	global $text_strings;

	$config['lang'] = $lang;
	setcookie('language', $config['lang']); 
	
	$text_strings = [];
	load_language($config['lang']);
}

//----------------------------------------------------------------------------------------
function load_language($lang)
{
	global $text_strings;

	$filename = 'locale/' . $lang . '.yml';
	$text_strings = Yaml::parseFile($filename);	   
}

//----------------------------------------------------------------------------------------
// Get text for a key in the current language
function get_text($key)
{
	global $config;
	global $text_strings;
	
	$lang = get_language();
	
	if (!isset($text_strings[$lang]))
	{
		load_language($lang);
	}
	
	$text = '';
	$found = false;
	
	$root = $text_strings[$lang];
	
	//print_r($root);
	
	foreach ($key as $k)
	{
		if (isset($root[$k]))
		{
			if (is_array($root[$k]))
			{
				$root = $root[$k];
			}
			else
			{
				$found = true;
				$text = $root[$k];
			}
		}
	}
	
	if (!$found)
	{
		$text = "Text [" . join(", ", $key) . "] not found!";
	}
	
	return $text;
}

?>
