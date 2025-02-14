<?php

// Parse filters and convert to SQL queries

//----------------------------------------------------------------------------------------
function parse_filter_url_parameter($filter_string)
{
	$filters = array();
	
	if (trim($filter_string) == '')
	{
		return $filters;
	}
	
	$parameters = explode(',', html_entity_decode($filter_string));
	foreach($parameters as $param)
	{
	  list($key, $value) = explode(':', $param);
	  
	  $key = urldecode($key);
	  $filters[$key] = trim(urldecode($value));
	}
	
	return $filters;
}

//----------------------------------------------------------------------------------------
// Convert filters to SQL, if additional is true we use AND as a prefix as filters
// are in addition to other parts of the WHERE clause
function filters_to_sql($filters, $additional = true)
{
	$sql = '';

	if (count($filters) > 0)
	{
		$sql_filters = array();	
		foreach ($filters as $k => $v)
		{
			switch ($k)
			{
				case 'recordset':
					$sql_filters[] = "bold_recordset_code_arr @> ARRAY['" . str_replace("'", "''", $v) . "']";
					break;
			
				case 'country_iso':
				default: // simple match
					$sql_filters[] = "$k='" . str_replace("'", "''", $v) . "'";
					break;
			}
		}
		
		if ($additional)
		{
			$sql .= ' AND';
		}
		
		$sql .= ' ' . join(' AND ', $sql_filters);
	}

	return $sql;
}

if (0)
{
	$string = "country_iso:BR";
	
	$filters = parse_filter_url_parameter($string);
	
	print_r($filters);
	
	$sql = filters_to_sql($filters);
	
	echo $sql . "\n";
}

?>
