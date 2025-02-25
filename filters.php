<?php

// Parse filters and convert to SQL queries

// Filters are in the form ?filters=key:value,key:value 

require_once (dirname(__FILE__) . '/pg.php');

//----------------------------------------------------------------------------------------
function parse_filter_url_parameter($filter_string)
{
	$filters = array();
	
	if (trim($filter_string) == '')
	{
		return $filters;
	}
	
	// protect "BOLD" as it has a colon in the name (stupid thing to do)
	
	$filter_string = str_replace('BOLD:', urlencode('BOLD:'), $filter_string);
	
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
function subtree_span($taxid)
{
	global $db;
	
	$span = array();
	
	$sql = "SELECT * FROM boldtaxonomy WHERE external_id='$taxid' LIMIT 1";
	
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$span[] = $row['left'];
		$span[] = $row['right'];
	}
	
	return $span;
}

//----------------------------------------------------------------------------------------
// Convert filters to SQL
function filters_to_sql($filters)
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
					
				case 'taxon':
					if (preg_match('/^BOLD/', $v))
					{
						$sql_filters[] = "boldvector.bin_uri='" . str_replace("'", "''", $v) . "'";					
					}
					else
					{
						$sql_filters[] = "lineage_arr @> ARRAY['" . str_replace("'", "''", $v) . "']";
					}
					break;
			
				case 'country_iso':
				default: // simple match
					$sql_filters[] = "$k='" . str_replace("'", "''", $v) . "'";
					break;
			}
		}
		
		$sql .= ' ' . join(' AND ', $sql_filters);
	}
	
	//echo $sql;

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
