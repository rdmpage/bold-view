<?php

error_reporting(E_ALL);

require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/vendor/autoload.php');

use Symfony\Component\Yaml\Yaml;

$filename = 'locale/' . $config['lang'] . '.yml';

$text_strings = Yaml::parseFile($filename);

function get_text($key)
{
	global $config;
	global $text_strings;
	
	$text = '';
	$found = false;
	
	$root = $text_strings[$config['lang']];
	
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
