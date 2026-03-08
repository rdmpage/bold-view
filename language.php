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

//----------------------------------------------------------------------------------------
// Parse the HTTP Accept-Language header and return base language codes in
// descending preference order, e.g. "zh-CN,zh;q=0.9,en-US;q=0.8" → ['zh','en'].
function parse_accept_language($header)
{
	$entries = [];
	foreach (explode(',', $header) as $part)
	{
		$part = trim($part);
		if (preg_match('/^([a-zA-Z]{2,3})(?:-[a-zA-Z0-9]+)*(?:;q=([0-9.]+))?/', $part, $m))
		{
			$code = strtolower($m[1]);
			$q    = isset($m[2]) ? (float)$m[2] : 1.0;
			if (!isset($entries[$code]) || $entries[$code] < $q)
			{
				$entries[$code] = $q;
			}
		}
	}
	arsort($entries);
	return array_keys($entries);
}

//----------------------------------------------------------------------------------------
// Return the display name for a language code (e.g. 'zh' → '中文').
// Loads from the locale YAML if not already cached.
function get_language_name($lang)
{
	global $text_strings;
	if (!isset($text_strings[$lang]))
	{
		load_language($lang);
	}
	return $text_strings[$lang]['language'] ?? $lang;
}

//----------------------------------------------------------------------------------------
// Resolve the language for this request and store in $config['lang'].
// Priority: ?lang URL param → cookie → Accept-Language header → config default.
// Must be called before any output is sent.
function init_language()
{
	global $config;

	$available = $config['languages'];

	// 1. Explicit URL parameter — honour it and persist as cookie preference
	if (isset($_GET['lang']) && in_array($_GET['lang'], $available))
	{
		set_language($_GET['lang']);
		return;
	}

	// 2. Previously stored cookie preference
	if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], $available))
	{
		$config['lang'] = $_COOKIE['language'];
		return;
	}

	// 3. Browser Accept-Language header — only when the switcher is on so users
	//    are never silently shown a language they have no way to change back from.
	if (!empty($config['show_language_switcher']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
	{
		foreach (parse_accept_language($_SERVER['HTTP_ACCEPT_LANGUAGE']) as $code)
		{
			if (in_array($code, $available))
			{
				$config['lang'] = $code;
				return;
			}
		}
	}

	// 4. Config default — already set, nothing to do
}

init_language();

?>
