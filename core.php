<?php

// Core functionality shared by website and API

error_reporting(E_ALL);

putenv('PGGSSENCMODE=disable');

require_once (dirname(__FILE__) . '/pg.php');
require_once (dirname(__FILE__) . '/filters.php');
require_once (dirname(__FILE__) . '/five-tuple.php');
require_once (dirname(__FILE__) . '/nj.php');
require_once (dirname(__FILE__) . '/swa.php');
require_once (dirname(__FILE__) . '/tree-label.php');

require_once('tree/colour.php');
require_once('tree/svg.php');
require_once('tree/tree.php');
require_once('tree/tree-drawer.php');
require_once('tree/tree-parse.php');
require_once('tree/utils.php');

//----------------------------------------------------------------------------------------
function sequence_to_embedding ($text)
{
	// remove newlines (in case this is a FASTA-style chunked sequence)
	$text = preg_replace('/[0-9\s\.]/', '', $text);
	$text = preg_replace('/\R/u', '', $text);
	
	$embedding = sequence_to_vector($text);
	
	return $embedding;
}

//----------------------------------------------------------------------------------------
function get_nj_tree($obj, $root = true)
{
	// neighbour-joining tree
	$tree = search_result_to_nj($obj, $root);
	
	$newick = $tree->WriteNewick();

	return $newick;
}

// move these somewhere

//----------------------------------------------------------------------------------------
function pq_record_to_obj($row)
{
	$hit = new stdclass;
				
	foreach ($row as $k => $v)
	{
		if ($v)
		{
			switch ($k)
			{
				// barcode
				case 'processid':
				case 'marker_code':
				case 'identification':
				case 'bin_uri':				
				case 'insdc_acs':
				case 'embedding':
				case 'nuc':
				
				// image
				case 'url':
				case 'title':
				case 'view':
				case 'mimetype':
				case 'clean_license':				
					$hit->{$k} = $v;
					break;
					
				// coordinates as GeoJSON
				case 'point':
					if (preg_match('/POINT\((.*)\s+(.*)\)/', $row['point'], $m))
					{
						// GeoJSON
						$point = new stdclass;
						$point->type = "Feature";
						
						$point->geometry = new stdclass;
						$point->geometry->type = "Point";
						$point->geometry->coordinates =  array((Float)$m[1], (Float)$m[2]);
			
						$point->properties = new stdclass;
						$point->properties->name = $hit->processid;
						
						if (isset($hit->identification))
						{
							$point->properties->identification = $hit->identification;
						}
			
						if (isset($hit->insdc_acs))
						{
							$point->properties->insdc_acs = $hit->insdc_acs;
						}
						
						$hit->feature = $point;
					}
					break;
					
				// lineage as array
				case 'lineage':
					$hit->lineage = explode(';', $row['lineage']);
					break;
					
				// taxon
				case 'id':				
				case 'anc_id':				
				case 'external_id':				
				case 'name':				
					$hit->{$k} = $v;
					break;
					
				default:
					break;
			}
		}
	
	}

	return $hit;
}

//----------------------------------------------------------------------------------------
// Return single barcode record, or null if not found
function get_barcode($processid) 
{
	global $db;
	
	$hit = null;

	$sql = "SELECT *, ST_AsText(coord) AS point FROM boldvector WHERE processid='" . $processid . "'";
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$hit = pq_record_to_obj($row);
	}
	
	if ($hit)
	{	
		// images
		$hit->images = get_barcode_images($processid);
		if (count($hit->images) == 0)
		{
			unset($hit->images);
		}
	}
	
	return $hit;	
}

//----------------------------------------------------------------------------------------
// Return sequences similar to a barcode
function get_barcode_related($processid, $limit) 
{
	global $db;
	
	$startTime = microtime(true);	
	
	$obj = new stdclass;
	$obj->took = 0;
	$obj->processid = $processid;
	$obj->hits = array();
	
	// barcode
	$from = get_barcode	($processid);
	
	if (!$from)
	{
		return $obj;
	}
	
	// Do we include the sequence itself?
	$include_from = true;
	
	if (!$include_from)
	{
		$limit += 1; // add one so we get an additional n number of sequences
	}
	
	$sql = "SELECT *, ST_AsText(coord) AS point, embedding <-> '" 
			. pg_escape_string($db, $from->embedding) 
			. "' AS distance FROM boldvector WHERE marker_code='" . $from->marker_code . "' ORDER BY distance LIMIT " . $limit;
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$hit = pq_record_to_obj($row);
		
		$hit->distance = (float)round($row['distance'], 5);
		
		if ($include_from)
		{
			$obj->hits[] = $hit;
		}
		else
		{
			if ($hit->processid != $processid)
			{
				$obj->hits[] = $hit;
			}
		}
	}
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);	

	return $obj;	
}

//----------------------------------------------------------------------------------------
// Align barcode to a reference sequence
function get_barcode_alignment($processid, $reference_seq_name, $reference_seq) 
{
	global $db;
	
	$doc = null;
	
	$hit = null;

	$sql = "SELECT * FROM boldmeta WHERE processid='" . $processid . "'";
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$hit = pq_record_to_obj($row);
	}
	
	if ($hit && isset($hit->nuc))
	{
		$seq1 = clean_sequence($reference_seq);
		$seq2 = clean_sequence($hit->nuc);
	
		$doc = new stdclass;
		
		$doc->alignment = swa (
			$reference_seq_name, 
			$hit->processid, 
			$seq1, 
			$seq2
		);

	}
	
	return $doc;
}

//----------------------------------------------------------------------------------------
// Return sequences similar to a sequence in text form (e.g., simple string
// or FASTA
function get_similar_sequences($text, $marker_code = 'COI-5P', $limit = 100) 
{
	global $db;
	
	$startTime = microtime(true);	

	$obj = new stdclass;
	$obj->took = 0;
		
	$obj->query = $text;
	$obj->embedding = json_encode(sequence_to_embedding($text));
	$obj->hits = array();
	
	// add query as first "hit"
	$q = new stdclass;
	$q->embedding = json_encode(sequence_to_embedding($text));
	$q->distance = 0;
	$q->processid = "query";
	
	$obj->hits[] = $q;

	$sql = "SELECT *, ST_AsText(boldvector.coord) AS point, embedding <-> '" 
			. pg_escape_string($db, $q->embedding) 
			. "' AS distance 
			FROM boldvector 
			INNER JOIN boldmeta USING(processid)
			WHERE boldvector.marker_code='" . $marker_code . "' ORDER BY distance LIMIT " . $limit;

	$sql = "SELECT *, ST_AsText(boldvector.coord) AS point, embedding <-> '" 
			. pg_escape_string($db, $q->embedding) 
			. "' AS distance 
			FROM boldvector 
			WHERE boldvector.marker_code='" . $marker_code . "' ORDER BY distance LIMIT " . $limit;

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$hit = pq_record_to_obj($row);
		
		$hit->distance = (float)round($row['distance'], 5);			
		$obj->hits[] = $hit;
	}
	
	// GeoJSON
	$obj->geo = feature_collection($obj->hits);
	
	// other things of interest
	$obj->collections = get_collection($obj->hits, ['insdc_acs']);
	
	$obj->aggregations = get_aggregations($obj->hits, ['identification']);	
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);		

	return $obj;	
}

//----------------------------------------------------------------------------------------
// Return records based on list of processids
function get_records_from_id_list($ids) 
{
	global $db;
	
	$startTime = microtime(true);	
	
	$obj = new stdclass;
	$obj->took = 0;
	$obj->hits = array();
	
	$set = array();
	foreach ($ids as $id)
	{
		$set[] = "'" . $id . "'";
	}

	$sql = "SELECT *, ST_AsText(coord) AS point
	FROM boldvector 
	WHERE processid IN (" . join(',', $set) . ")";
	
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$hit = pq_record_to_obj($row);
		$obj->hits[] = $hit;
	}
	
	// GeoJSON
	$obj->geo = feature_collection($obj->hits);
	
	// other things of interest
	$obj->collections = get_collection($obj->hits, ['insdc_acs']);
	
	$obj->aggregations = get_aggregations($obj->hits, ['identification']);	
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);		

	return $obj;	
}

//----------------------------------------------------------------------------------------
// Aggregate values from list of barcodes, storing counts
// Use this for things like BINs, identifiers, sequenceing centres, etc.
function get_aggregations($hits, $fields = array())
{
	$aggregations = new stdclass;
	
	foreach ($hits as $hit)
	{
		foreach ($fields as $key)
		{
			if (isset($hit->{$key}))
			{			
				if (!isset($aggregations->{$key}))
				{
					$aggregations->{$key} = array();
				}
				
				if (is_array($hit->{$key}))
				{
					$value = join(';', $hit->{$key});
				}
				else
				{
					$value = $hit->{$key};
				}
				
				
				if (!isset($aggregations->{$key}[$value]))
				{
					$aggregations->{$key}[$value] = 0;
				}
				$aggregations->{$key}[$value]++;
			}
		}		
	}
	return $aggregations;
}

//----------------------------------------------------------------------------------------
// Get lists of values that are likely unique (e.g., Genbank accessions)
// For things that are not unique and for which we want count data 
// use get_aggregations.
function get_collection($hits, $fields = array())
{	
	$collections = new stdclass;
		
	foreach ($hits as $hit)
	{
		foreach ($fields as $key)
		{
			if (isset($hit->{$key}))
			{			
				if (!isset($collections->{$key}))
				{
					$collections->{$key} = array();
				}
				$collections->{$key}[] = $hit->{$key};
			}
		}	
	}	
	
	// Sort nicely
	foreach ($collections as $k => &$v)
	{
		asort($v);
	}
	
	return $collections;
}


//----------------------------------------------------------------------------------------
// Get images for a list of hits
function decorate_hits_with_images($hits)
{	
	foreach ($hits as &$hit)
	{
		$images = get_barcode_images($hit->processid);
		if (count($images) > 0)
		{
			$hit->images = $images;
		}
	}	
	
	return $hits;
}
//----------------------------------------------------------------------------------------
// Collect GeoJSON points from individual barcodes into a feature collection
// so we can easily make a map
function feature_collection($hits)
{	
	$geo = new stdclass;
	$geo->type = "FeatureCollection";
	$geo->features = array();			
	
	foreach ($hits as $hit)
	{
		if (isset($hit->feature))
		{
			$geo->features[] = $hit->feature;
		}	
	}
	
	return $geo;
}

//----------------------------------------------------------------------------------------
// Get image using URL
function get_image($url) 
{
	global $db;
	
	$url = str_replace(' ', '+', $url);
	
	$image = null;
	
	$sql = "SELECT * FROM boldimage
	WHERE url='" . $url . "'";
	
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$image = pq_record_to_obj($row);
	}

	return $image;
}	

//----------------------------------------------------------------------------------------
// Images for a barcode
function get_barcode_images($processid) 
{
	global $db;
	
	$images = array();

	$sql = "SELECT boldimage.processid, boldimage.url, boldimage.title FROM boldvector 
	INNER JOIN boldimage USING(processid)
	WHERE processid='" . $processid . "'";
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$images[] = pq_record_to_obj($row);
	}

	return $images;
}	




//----------------------------------------------------------------------------------------
// Images for a BIN
function get_bin_images($bin_uri, $limit = 500) 
{
	global $db;
	
	$images = array();

	$sql = "SELECT boldimage.processid, boldimage.url, boldimage.title FROM boldvector 
	INNER JOIN boldimage USING(processid)
	WHERE bin_uri='" . $bin_uri . "' LIMIT " . $limit;
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$images[] = pq_record_to_obj($row);
	}

	return $images;
}	

//----------------------------------------------------------------------------------------
// Records for a BIN
function get_bin($bin_uri, $limit = 500) 
{
	global $db;
	
	$startTime = microtime(true);
	
	$obj = new stdclass;
	$obj->took = 0;
	$obj->bin_uri = $bin_uri;
	
	// List of members of this BIN
	$obj->hits = array();
	
	$sql = "SELECT *, ST_AsText(coord) AS point FROM boldvector WHERE bin_uri='" . $bin_uri . "' LIMIT " . $limit;
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$hit = pq_record_to_obj($row);
		$obj->hits[] = $hit;
	}
	
	// GeoJSON
	$obj->geo = feature_collection($obj->hits);
	
	// other things of interest
	$obj->collections = get_collection($obj->hits, ['processid','insdc_acs']);
	
	$obj->aggregations = get_aggregations($obj->hits, ['identification', 'lineage']);
	
	// images
	$obj->images = get_bin_images($bin_uri, $limit);
	if (count($obj->images) == 0)
	{
		unset($obj->images);
	}
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);

	return $obj;	
}

//----------------------------------------------------------------------------------------
function newick_to_svg($newick)
{
	// tree as SVG
	$t = parse_newick($newick);
	
	$leaf_height = 10;
	
	$settings = array(
		'inset' => $leaf_height/2,
		'width' => 200,
		'height' => $t->GetNumLeaves() * $leaf_height,
		'font_height' => $leaf_height,
		'draw_leaf_labels' => false,
		'draw_scale_bar' => false
	);
	
	// coordinates
	$t->BuildWeights($t->GetRoot());
	
	$td = new PhylogramTreeDrawer($t, $settings);
	$td->CalcCoordinates();	
	
	// SVG diagram
	$port = new SVGPort('', $settings['width'], $settings['height'], $settings['font_height'], false);
	$port->StartGroup('tree', true);

	// Draw tree
	$td->Draw($port);

	// Extend leaf tips to edge of rect using dashed line
	$n = new NodeIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
		if ($q->IsLeaf())
		{
			// Rectangle
			$p0 = $q->GetAttribute('xy');
			$p1 = $q->GetAttribute('xy');
			$p1['x'] = $settings['width'];
			
			$port->DrawDashedLine($p0, $p1);
		}
	
		$q = $n->Next();
	}

	$port->EndGroup();
	$svg = $port->GetOutput();

	return $svg;
}

//----------------------------------------------------------------------------------------
// Given a tree in newick format, create the table of labels to display
function tree_labels($obj, $newick)
{
	$table = new stdclass;
	$table->columns = array('processid', 'insdc_acs', 'bin_uri', 'identification', 'classification');
	$table->rows = array();

	$t = parse_newick($newick);
	
	// 1. Get list of processids in left-right order on tree
	$leaf_index = 0;
	$leaf_order = array();
	
	$n = new NodeIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
		if ($q->IsLeaf())
		{
			$id = $q->GetLabel();
			$leaf_order[$q->GetLabel()] = $leaf_index;
			$leaf_index++;
		}
	
		$q = $n->Next();
	}
	
	// 2.
	// $t = label_internal_nodes($doc);
	// disjoint_internal_labels($t);
	$t = label_internal_nodes($obj, $newick);
	$sets = disjoint_internal_labels($t);
	
	
	// 3. Decorate with leaf values (these will each have their own colum)	
	foreach ($obj->hits as $hit)
	{
		$leaf_index = $leaf_order[$hit->processid];
		
		$table->rows[$leaf_index] = array();
		
		foreach ($table->columns as $column)
		{
			if (isset($hit->{$column}))
			{
				$cell = new stdclass;
				$cell->value =  $hit->{$column};
				$cell->span = 1;
			
				$table->rows[$leaf_index][$column] = $cell;
			}
		}
		
		if (isset($sets[$hit->processid]))
		{
			$cell = new stdclass;
			$cell->value =  $sets[$hit->processid]->label;
			$cell->span = $sets[$hit->processid]->span;
		
			$table->rows[$leaf_index][$column] = $cell;			
		}		
	}
	
	
	// 4. sort rows in leaf order
	ksort($table->rows);
		
	return $table;
}

//----------------------------------------------------------------------------------------
// Format a link as a simple internal/external link
function identifier_link($namespace, $value)
{
	$html = '';
	
	switch ($namespace)
	{
		case 'bin_uri':
			//$html = '<a href="?' . $namespace . '=' . urlencode($value) . '">' . $value . '</a>';
			$html = '<a href="bin/' . $value . '">' . $value . '</a>';			
			break;
		
		case 'processid':
			// $html = '<a href="?' . $namespace . '=' . urlencode($value) . '">' . $value . '</a>';
			$html = '<a href="record/' . $value . '">' . $value . '</a>';			
			break;
						
		default:
			$html = $value;
			break;
	}
	
	return $html;
}

//----------------------------------------------------------------------------------------
// Format an identifier link to display a panel with a snippet of information about that
// link
function identifier_panel_link($namespace, $value)
{
	$html = '';
	
	switch ($namespace)
	{
		case 'bin_uri':
			$html = '<a href="javascript:show_panel_snippet(&quot;api.php?' . $namespace . '=' . urlencode($value) . '&format=html&quot;)">' . $value . '</a>';
			break;

		case 'processid':
			if ($value == 'query')
			{
				$html = $value;
			}
			else			
			{
				$html = '<a href="javascript:show_panel_snippet(&quot;api.php?' . $namespace . '=' . urlencode($value) . '&format=html&quot;)">' . $value . '</a>';
			}
			break;
			
		case 'insdc_acs':
			$html = '<a href="javascript:show_panel_accession(&quot;' . str_replace('-SUPPRESSED', '', $value) . '&quot;)">' . $value . '</a>';
			break;
			
		default:
			$html = $value;
			break;
	}
	
	return $html;
}


//----------------------------------------------------------------------------------------
// output tree as a table, with SVG for the tree. We may have rows to highlight, these
// will be in $selection
function output_tree_table($svg, $table, $selection = [])
{
	$bin_index = array();

	$html = '';
	
	$html .= '<table cellspacing="0" cellpadding="1">';
	$html .= '<tbody>';
	$html .= '<tr>';
	$html .= '<th>tree</th>';

	foreach ($table->columns as $column)
	{
		$html .= '<th>' . $column . '</th>';
	}
	$html .= '</tr>';
	
	// tree
	$html .= '<tr>';
	$html .= '<td style="position:relative;width:200;" rowspan="' . (count($table->rows) + 1) . '">';
	$html .= $svg;
	
	$html .= '</td>';
	$html .= '</tr>';
	
	foreach ($table->rows as $row)
	{
		$html .=  '<tr>';
		foreach ($table->columns as $column)
		{
			$html .= '<td';
			if (isset($row[$column]))
			{
				if ($row[$column]->span > 1)
				{
					$html .= ' rowspan="' . $row[$column]->span . '"';
				}
				
				// Highlight selected leaf(s)
				if (count($selection) > 0)
				{
					if ($column == 'processid' && in_array($row[$column]->value, $selection))
					{
						$html .= ' class="selected"';
					}
				}
				
				// BIN colour
				if ($column == 'bin_uri') 
				{
					$html .= ' class="bin_uri"';
					
					if (!isset($bin_index[$row[$column]->value]))
					{
						$bin_index[$row[$column]->value] = count($bin_index);
					}
					
					// golden angle, see https://medium.com/samsung-internet-dev/human-friendly-colours-using-hsl-4944bcdb6e27
					
					$h = $bin_index[$row[$column]->value] * 137.6;
					$c = 80;
					$l = 80;
					
					$rgb = fromHCL($h, $c, $l);

					$browser = 'rgb(' . round($rgb[0], 0) . ',' . round($rgb[1], 0) . ',' . round($rgb[2], 0) . ')';
					
					$html .= ' style="background:' . $browser . ';"';
				}
				
				
				if ($column == 'classification')
				{
					$html .= ' class="classification"';
				}
				
			}
			$html .= '>';
			if (isset($row[$column]))
			{
				$html .=  identifier_panel_link($column, $row[$column]->value); 
			}
			$html .=  '</td>';
		}
		$html .=  '</tr>';
	}
	$html .= '</tbody>';
	$html .=  '</table>';
	
	return $html;
}

//----------------------------------------------------------------------------------------
// Get list of barcodes within a GeoJSON geometry
function get_geo($geometry, $filter_string ='', $limit = 100)
{
	global $db;
	
	$startTime = microtime(true);	
	
	$obj = new stdclass;
	$obj->took = 0;
		
	$obj->geometry = $geometry;
	
	$filters = array();
	if ($filter_string != '')
	{
		$filters = parse_filter_url_parameter($filter_string);
		$obj->filters = $filters;
	}	
	
	// List of records within this geometry
	$obj->hits = array();
		
	$sql = "SELECT *, ST_AsText(boldvector.coord) AS point 
	FROM boldvector 
	INNER JOIN boldmeta USING(processid)
	WHERE ST_Within(boldvector.coord, ST_GeomFromGeoJSON('" . json_encode($geometry) . "'))";
	
	if (isset($obj->filters))
	{
		$filter_sql = filters_to_sql($filters);
		$sql .= ' AND ' . $filter_sql;
	}
	
	$sql .= " LIMIT $limit";

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$hit = pq_record_to_obj($row);
		$obj->hits[] = $hit;
	}
	
	// GeoJSON
	$obj->geo = feature_collection($obj->hits);
	
	// other things of interest
	$obj->collections = get_collection($obj->hits, ['insdc_acs']);
	
	$obj->aggregations = get_aggregations($obj->hits, ['identification']);
	
	$obj->hits = decorate_hits_with_images($obj->hits);
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);
	
	return $obj;	
}

//----------------------------------------------------------------------------------------
function get_taxon_from_taxid($taxid)
{
	global $db;
	
	$startTime = microtime(true);	

	$obj = null;
	
	$sql = "SELECT * FROM boldtaxonomy WHERE id='" . $taxid . "' LIMIT 1";

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$obj = pq_record_to_obj($row);
	}
	
	// geographic extent
	if (preg_match('/^BOLD/', $obj->name))
	{
		$sql = "SELECT ST_AsGeoJSON(ST_Envelope(ST_ConcaveHull(ST_Collect(boldvector.coord), 1))) AS envelope
		FROM boldvector 
		WHERE bin_uri = '" . $obj->name . "'";		
	}
	else
	{
		$sql = "SELECT ST_AsGeoJSON(ST_Envelope(ST_ConcaveHull(ST_Collect(boldvector.coord), 1))) AS envelope
		FROM boldvector 
		WHERE lineage_arr @> ARRAY['" . $obj->name . "']";
	}

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		if (!$obj)
		{
			$obj = new stdclass;
			$obj->took = 0;		
			$obj->id = $taxid;				
		}
		
		// schema.org term
		$obj->spatialCoverage = $row['envelope'];
	}
	
	if ($obj)
	{	
		$endTime = microtime(true);	
		$obj->took = round($endTime - $startTime, 2);	
	}
	
	return $obj;
}

//----------------------------------------------------------------------------------------
// Very crude taxon search, by defalt name should come with rank__ prefix
function get_taxon_from_name($name, $rank = '')
{
	global $db;
	
	$obj = null;
	
	// name has rank embedded, so we match directly...
	if (preg_match('/([a-z]+)__([A-Z].*)/', $name, $m))
	{
		$sql = "SELECT * FROM boldtaxonomy WHERE name='" . str_replace("'", "''", $name) . "' LIMIT 1";

		$result = pg_query($db, $sql);
		
		while ($row = pg_fetch_assoc($result)) 
		{
			$obj = pq_record_to_obj($row);
		}
	}
	else
	{
		// OK because I've decided to store names and ranks, a simple name search is hard  - bugger
		// taxonomy						
		$taxon_keys = array(
			"kingdom" 		=> "k__",
			"phylum"		=> "p__",
			"class" 		=> "c__",
			"order" 		=> "o__",
			"family" 		=> "f__",
			"subfamily" 	=> "sf__",
			"tribe" 		=> "t__",
			"genus" 		=> "g__",
			"species" 		=> "s__",
			"subspecies"	=> "ss__",
		);	
		
		$rank_names = array();
		
		if (preg_match('/\s/', trim($name)))
		{
			// name has space(s)
			$rank_names[] = "'" . str_replace("'", "''", $taxon_keys['species'] . $name) . "'";
			$rank_names[] = "'" . str_replace("'", "''", $taxon_keys['subspecies'] . $name) . "'";
		}
		else
		{
			// Uninomial
			unset($taxon_keys['species']);
			unset($taxon_keys['subspecies']);
			
			foreach ($taxon_keys as $prefix)
			{
				$rank_names[] =  "'" . str_replace("'", "''", $prefix . $name) . "'";
			}
		}
			
		$sql = "SELECT * FROM boldtaxonomy WHERE name IN (" . join(",", $rank_names) . ") LIMIT 1";

		$result = pg_query($db, $sql);
		
		while ($row = pg_fetch_assoc($result)) 
		{
			$obj = pq_record_to_obj($row);
		}
				
	}	
		
	return $obj;
}

//----------------------------------------------------------------------------------------
// Get paged list of barcodes with optional filter
function get_paged_barcodes($page_start = 0, $page_size = 100, $filter_string ='')
{
	global $db;

	$startTime = microtime(true);		
	
	$obj = new stdclass;
	$obj->took = 0;
		
	$obj->offset = $page_start;
	$obj->size = $page_size;
	
	$filters = array();
	if ($filter_string != '')
	{
		$filters = parse_filter_url_parameter($filter_string);
		$obj->filters = $filters;
	}	
	
	// List of records 
	$obj->hits = array();
	
	$sql = "SELECT *
	FROM boldmeta";
	
	if (isset($obj->filters))
	{	
		$sql .= ' WHERE' . filters_to_sql($filters);
	}
	
	$sql .= " LIMIT $page_size";
	
	if ($page_start !== 0)
	{
		$sql .= " OFFSET $page_start";
	}

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$hit = pq_record_to_obj($row);
		$obj->hits[] = $hit;
	}
	
	$obj->hits = decorate_hits_with_images($obj->hits);	
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);	
	
	return $obj;	
}

//----------------------------------------------------------------------------------------
// Get paged list of barcodes with optional filter
function get_paged_images($page_start = 0, $page_size = 100, $filter_string ='')
{
	global $db;
	
	$startTime = microtime(true);	

	$obj = new stdclass;
	$obj->took = 0;
	$obj->offset = $page_start;
	$obj->size = $page_size;
	
	$filters = array();
	if ($filter_string != '')
	{
		$filters = parse_filter_url_parameter($filter_string);
		$obj->filters = $filters;
	}	
	
	// List of records 
	$obj->hits = array();
	
	$sql = "SELECT boldimage.processid,	url, title, view, mimetype, clean_license
	FROM boldmeta
	INNER JOIN boldimage USING(processid)";
	
	if (isset($obj->filters))
	{	
		$sql .= ' WHERE' . filters_to_sql($filters);
	}
	
	$sql .= " LIMIT $page_size";
	
	if ($page_start !== 0)
	{
		$sql .= " OFFSET $page_start";
	}

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$hit = pq_record_to_obj($row);
		$obj->hits[] = $hit;
	}
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);		

	return $obj;	
}

//----------------------------------------------------------------------------------------
// Return details on a recordset
function get_recordset($id) 
{
	global $db;
	
	$startTime = microtime(true);	

	$obj = null;
	
	// how many barcodes?
	$filters = parse_filter_url_parameter("recordset:" . $id);
	
	$sql = "SELECT COUNT(processid) AS c FROM boldmeta";
	$sql .= ' WHERE' . filters_to_sql($filters, false);

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		if (!$obj)
		{			
			$obj = new stdclass;
			$obj->took = 0;
			$obj->id = $id;			
		}
		
		$obj->num_barcodes = (Integer)$row['c'];
	}
	
	// geographic extent
	$sql = "SELECT ST_AsGeoJSON(ST_Envelope(ST_ConcaveHull(ST_Collect(boldvector.coord), 1))) AS envelope
	FROM boldvector 
	INNER JOIN boldmeta USING(processid)
	WHERE bold_recordset_code_arr @> ARRAY['$id']";

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		if (!$obj)
		{
			$obj = new stdclass;
			$obj->took = 0;						
			$obj->id = $id;
		}
		
		// schema.org term
		$obj->spatialCoverage = $row['envelope'];
	}
	
	if ($obj)
	{
		$endTime = microtime(true);	
		$obj->took = round($endTime - $startTime, 2);	
	}
	
	return $obj;	
}



?>
