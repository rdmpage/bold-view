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
require_once (dirname(__FILE__) . '/tSNE.php');

require_once('tree/colour.php');
require_once('tree/svg.php');
require_once('tree/tree.php');
require_once('tree/tree-drawer.php');
require_once('tree/tree-parse.php');
require_once('tree/utils.php');

require_once (dirname(__FILE__) . '/vendor/autoload.php');

use Seboettg\CiteProc\CiteProc;
use Seboettg\CiteProc\StyleSheet;

//----------------------------------------------------------------------------------------
function sequence_to_embedding ($text)
{
	// remove newlines (in case this is a FASTA-style chunked sequence)
	$text = preg_replace('/[0-9\s\.]/', '', $text);
	$text = preg_replace('/\R/u', '', $text);
	$text = preg_replace('/\/+/', '', $text);
	
	$embedding = sequence_to_vector($text);
	
	return $embedding;
}

//----------------------------------------------------------------------------------------
function get_distances($obj)
{
	// pairwise distances
	$distances = search_result_to_distances($obj);

	return $distances;
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
				case 'museumid':
				
				// image
				case 'url':
				// SHA-1 of the image bytes in our content store; empty until
				// the harvest reaches this image.
				case 'sha1':
					$hit->{$k} = $v;
					break;
					
					
				case 'title':
				case 'view':
				case 'mimetype':
				case 'clean_license':				
					$hit->{$k} = $v;
					break;
					
				case 'nuc_basecount':
					$hit->{$k} = (Integer)$v;
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
					
				// data set membership
				case 'bold_recordset_code_arr':
					$v = preg_replace('/^\{/', '', $v);
					$v = preg_replace('/\}$/', '', $v);
					$sets = explode(",", $v);
					$hit->datasets = array();
					foreach ($sets as $element)
					{
						if (preg_match('/^DS-/', $element))
						{
							$hit->datasets[] = $element;
						}
					}
					if (count($hit->datasets) == 0)
					{
						unset($hit->datasets);
					}
					break;
					
				// locality
				case 'country/ocean':
				case 'country_iso':
				case 'province/state':
				case 'region':
				case 'sector':
				case 'site':
					$hit->locality[] = $v;
					break;
				
				// coordinates
				case 'coord':
				//case 'coord_accuracy':
				case 'coord_source':
					$hit->{$k} = $v;
					break;					
				
				
				// specimen notes
				case 'voucher_type':
					$hit->{$k} = $v;
					break;	
					
				case 'typestatus':
					$hit->{$k} = $v;
					break;

				// alignment w.r.t. reference sequence
				case 'alignment':
					$hit->{$k} = json_decode($v);
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

	$sql = "SELECT *, ST_AsText(boldvector.coord) AS point
		FROM boldvector
		INNER JOIN boldmeta USING(processid)
		WHERE processid = $1";

	$result = pg_query_params($db, $sql, array($processid));
	
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
// Return barcode from accession (null if not found)
function get_barcode_from_accession($accession) 
{
	global $db;
	
	$barcode = null;

	$sql = "SELECT processid FROM boldmeta
		WHERE insdc_acs='" . $accession . "'";
		
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		if (isset($row['processid']))
		{
			$barcode = $row['processid'];
		}
	}
		
	return $barcode;	
}

//----------------------------------------------------------------------------------------
// Annotations for a thing
function get_annotations($subject) 
{
	global $db;
	
	$annotations = array();

	$sql = "SELECT * FROM boldannotation
	WHERE subject='" . $subject . "'";
	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result)) 
	{
		if (!isset($annotations[$row['predicate']]))
		{
			$annotations[$row['predicate']] = [];
		}
		$annotations[$row['predicate']][] = $row['object'];
	}

	return $annotations;
}	

//----------------------------------------------------------------------------------------
// compute colours to assign to BINs
function colour_bins(&$doc)
{
	if (isset($doc->hits))
	{		
		$doc->bin_colors = array();		
	
		foreach ($doc->hits as $hit)
		{					
			if (isset($hit->bin_uri))
			{
				if (!isset($doc->bin_colors[$hit->bin_uri]))
				{
					$bin_index = count($doc->bin_colors);
					$h = $bin_index * 137.6;
					$c = 80;
					$l = 80;
					
					$rgb = fromHCL($h, $c, $l);

					$browser = 'rgb(' . round($rgb[0], 0) . ',' . round($rgb[1], 0) . ',' . round($rgb[2], 0) . ')';
					
					$doc->bin_colors[$hit->bin_uri] = $browser;
				}
			}
		}	
	}
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
	
	colour_bins($obj);
	
	$endTime = microtime(true);	
	$obj->took = round($endTime - $startTime, 2);	

	return $obj;	
}

//----------------------------------------------------------------------------------------
// Compute the convex hull of a set of [longitude, latitude] pairs using
// Andrew's monotone chain algorithm.  Returns the hull vertices in CCW order
// (without repeating the first point).  Degenerate inputs (0–2 points, or all
// collinear) return whatever vertices exist so the caller can decide what to do.
function convex_hull($points)
{
	$n = count($points);
	if ($n < 2)
	{
		return $points;
	}

	// Sort by longitude, break ties by latitude
	usort($points, function($a, $b)
	{
		if ($a[0] != $b[0]) return $a[0] < $b[0] ? -1 : 1;
		return $a[1] < $b[1] ? -1 : 1;
	});

	// Remove exact duplicates
	$points = array_values(array_unique($points, SORT_REGULAR));
	$n = count($points);
	if ($n < 2)
	{
		return $points;
	}

	$cross = function($O, $A, $B)
	{
		return ($A[0] - $O[0]) * ($B[1] - $O[1])
		     - ($A[1] - $O[1]) * ($B[0] - $O[0]);
	};

	// Lower hull
	$lower = [];
	foreach ($points as $p)
	{
		while (count($lower) >= 2
			&& $cross($lower[count($lower)-2], $lower[count($lower)-1], $p) <= 0)
		{
			array_pop($lower);
		}
		$lower[] = $p;
	}

	// Upper hull
	$upper = [];
	foreach (array_reverse($points) as $p)
	{
		while (count($upper) >= 2
			&& $cross($upper[count($upper)-2], $upper[count($upper)-1], $p) <= 0)
		{
			array_pop($upper);
		}
		$upper[] = $p;
	}

	// Each hull ends with a duplicate of the other's first point — remove them
	array_pop($lower);
	array_pop($upper);

	return array_merge($lower, $upper);
}

//----------------------------------------------------------------------------------------
// Build a GeoJSON FeatureCollection from the related barcodes of a given barcode.
// Contains:
//   - one Point feature per hit that has coordinates
//   - one Polygon feature per BIN whose hull has >= 3 vertices
// BIN colours from colour_bins() are carried into feature properties so that
// the client can fill/stroke accordingly.
function get_barcode_map($processid, $limit = 50)
{
	$related = get_barcode_related($processid, $limit);

	$fc = new stdclass;
	$fc->type = 'FeatureCollection';
	$fc->features = [];

	// Collect raw coordinates per BIN for hull computation
	$bin_points = [];

	foreach ($related->hits as $hit)
	{
		if (!isset($hit->feature))
		{
			continue;
		}

		$color = (isset($hit->bin_uri) && isset($related->bin_colors[$hit->bin_uri]))
		       ? $related->bin_colors[$hit->bin_uri]
		       : 'rgb(128,128,128)';

		// Point feature
		$f = new stdclass;
		$f->type = 'Feature';
		$f->geometry = $hit->feature->geometry;
		$f->properties = new stdclass;
		$f->properties->processid     = $hit->processid;
		$f->properties->bin_uri       = $hit->bin_uri ?? null;
		$f->properties->identification = $hit->identification ?? null;
		$f->properties->color         = $color;
		$fc->features[] = $f;

		// Accumulate for convex hull
		if (isset($hit->bin_uri))
		{
			$bin_points[$hit->bin_uri][] = $hit->feature->geometry->coordinates;
		}
	}

	// Polygon feature per BIN with >= 3 hull vertices
	foreach ($bin_points as $bin_uri => $points)
	{
		$hull = convex_hull($points);
		if (count($hull) < 3)
		{
			continue; // single point or collinear — skip polygon
		}

		// Close the ring
		$hull[] = $hull[0];

		$color = $related->bin_colors[$bin_uri];

		$f = new stdclass;
		$f->type = 'Feature';
		$f->geometry = new stdclass;
		$f->geometry->type = 'Polygon';
		$f->geometry->coordinates = [$hull];
		$f->properties = new stdclass;
		$f->properties->bin_uri = $bin_uri;
		$f->properties->color   = $color;
		$f->properties->count   = count($points);
		$fc->features[] = $f;
	}

	return $fc;
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
	
	colour_bins($obj);	
	
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
// Fetch the image from BOLD through our caching proxy. This is the fallback
// for images the harvest hasn't reached yet.
function image_proxy_url($url)
{
	return 'image_proxy.php?url=' . urlencode(str_replace('www.', 'v4.', $url));
}

//----------------------------------------------------------------------------------------
// Preferred <img src>: our own content store when we hold the bytes, otherwise
// BOLD via the proxy.
function image_src($image)
{
	global $config;

	if (isset($image->sha1) && $image->sha1 != '')
	{
		return $config['content_store'] . $image->sha1;
	}

	return image_proxy_url($image->url);
}

//----------------------------------------------------------------------------------------
// Attach the display URLs to an image object, so PHP and the JavaScript
// galleries render from the same decision instead of each re-deriving it.
//
// fallback_src is only set when src points at the content store: the database
// can be ahead of the bucket (a sha1 synced before its upload landed), and a
// single retry against BOLD keeps that gap from showing as a broken image.
function image_add_src($image)
{
	if (!isset($image->url))
	{
		return $image;
	}

	$image->src = image_src($image);

	$proxy = image_proxy_url($image->url);
	if ($image->src !== $proxy)
	{
		$image->fallback_src = $proxy;
	}

	return $image;
}

//----------------------------------------------------------------------------------------
// src="..." plus, where relevant, the onerror that retries via BOLD. Emitting
// both from one place keeps the quoting correct at each <img> site.
function image_src_attrs($image)
{
	$attrs = 'src="' . htmlspecialchars(image_src($image), ENT_QUOTES) . '"';

	if (isset($image->sha1) && $image->sha1 != '')
	{
		$js = "this.onerror=null;this.src='" . image_proxy_url($image->url) . "'";
		$attrs .= ' onerror="' . htmlspecialchars($js, ENT_QUOTES) . '"';
	}

	return $attrs;
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
		$image = image_add_src(pq_record_to_obj($row));
	}

	return $image;
}

//----------------------------------------------------------------------------------------
// Images for a barcode
function get_barcode_images($processid) 
{
	global $db;
	
	$images = array();

	$sql = "SELECT boldimage.processid, boldimage.url, boldimage.title, boldimage.sha1 FROM boldvector
	INNER JOIN boldimage USING(processid)
	WHERE processid='" . $processid . "'";
	$result = pg_query($db, $sql);

	while ($row = pg_fetch_assoc($result))
	{
		$images[] = image_add_src(pq_record_to_obj($row));
	}

	return $images;
}	




//----------------------------------------------------------------------------------------
// Images for a BIN
function get_bin_images($bin_uri, $limit = 500) 
{
	global $db;
	
	$images = array();

	$sql = "SELECT boldimage.processid, boldimage.url, boldimage.title, boldimage.sha1 FROM boldvector
	INNER JOIN boldimage USING(processid)
	WHERE bin_uri='" . $bin_uri . "' LIMIT " . $limit;
	$result = pg_query($db, $sql);

	while ($row = pg_fetch_assoc($result))
	{
		$images[] = image_add_src(pq_record_to_obj($row));
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
	
	colour_bins($obj);
	
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
	global $config;
	
	$table = new stdclass;
	
	// columns (database fields)
	if ($config['show_alignment'])
	{
		$table->columns = array('processid', 'alignment', 'typestatus', 'insdc_acs', 'bin_uri', 'identification', 'classification');
	}
	else
	{
		$table->columns = array('processid', 'typestatus', 'insdc_acs', 'bin_uri', 'identification', 'classification');
	}	
	
	// column names for display (by default same as database field)
	$table->column_aliases = array();
	foreach ($table->columns as $column)
	{
		$table->column_aliases[$column] = $column;
	}
	
	// for some columns we need shorter aliases, 
	// make sure we only generate aliases for columns that we actually have
	if (in_array('typestatus', $table->columns))
	{
		$table->column_aliases['typestatus'] = 'type';
	}
	if (in_array('alignment', $table->columns))
	{
		$table->column_aliases['alignment'] = 'align';
	}
	
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
			
		case 'museumid':
			//$html = '<a href="record/' . $value . '">' . $value . '</a>';		
			$html = '<a href="javascript:show_panel_museumid(&quot;' . $value . '&quot;)">' . $value . '</a>';
			break;
			
		case 'datasets':
			foreach ($value as $v)
			{
				$html .= '<a href="recordset/' . $v . '">' . $v . '</a>' . ' ';	
			}
			break;
			
		case 'locality':
			$html = join(", ", $value);	
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
function output_tree_table($svg, $table, $bin_colors, $selection = [])
{
	$bin_index = array();

	$html = '';
	
	$html .= '<table cellspacing="0" cellpadding="1">';
	$html .= '<tbody>';
	$html .= '<tr>';
	$html .= '<th>tree</th>';

	foreach ($table->column_aliases as $column)
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
					$browser = $bin_colors[$row[$column]->value];
					
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
				if ($column == 'typestatus')
				{
					$html .= '●';
				}
				elseif ($column == 'alignment')
				{
					
					$width = 48;
					
					$html .= '<div style="background-color:#EBEBEB;width:' . $width . 'px">';
					
					$len = $row[$column]->value->len;
					
					$start = $row[$column]->value->spans[0][0];
					$end = $row[$column]->value->spans[0][1];
					
					$x = round($width * ($row[$column]->value->spans[0][0])/$len, 0);
					$w = round($width * ($row[$column]->value->spans[0][1] - $row[$column]->value->spans[0][0])/$len, 0);

					$html .= '<div style="height:1em;margin-left:' . $x . 'px;background-color:#C0C0C0;width:' . $w . 'px">';
					
					// $html .= $len;
					$html .= '</div>';
					$html .= '</div>';
				
				
					//$html .= $row[$column]->value;
				}
				else
				{
					$html .=  identifier_panel_link($column, $row[$column]->value); 
				}
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
		$obj->spatialCoverage = force_polygon($row['envelope']);
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
	
	$sql = "SELECT boldimage.processid,	url, title, view, mimetype, clean_license, sha1
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
		$hit = image_add_src(pq_record_to_obj($row));
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
	
	// schema.org term
	$obj->spatialCoverage = get_recordset_spatial_extent($id);
	
	if ($obj)
	{
		$endTime = microtime(true);	
		$obj->took = round($endTime - $startTime, 2);	
	}
	
	return $obj;
}

//----------------------------------------------------------------------------------------
// Can the (read-only) app actually SELECT from this table? Tables like
// dataset_citation / work_csl are created out of band (owner-privileged) and may
// exist before a GRANT is in place — so check existence AND read privilege, not
// just existence, otherwise the query fails with "permission denied" instead of
// degrading gracefully.
function pg_table_readable($qualified_name)
{
	global $db;

	// exists? (has_table_privilege would error on a non-existent table)
	$result = pg_query_params($db, "SELECT to_regclass($1) AS t", array($qualified_name));
	$exists = $result ? pg_fetch_result($result, 0, 't') : null;
	if (!is_string($exists) || $exists === '')
	{
		return false;
	}

	// readable by the current role?
	$result = pg_query_params($db, "SELECT has_table_privilege($1, 'SELECT') AS ok", array($qualified_name));
	return $result ? (pg_fetch_result($result, 0, 'ok') === 't') : false;
}

//----------------------------------------------------------------------------------------
// Works that cite this dataset, each with its decoded CSL-JSON where we have it.
// Reads dataset_citation (mirror of bold-dataset-citations/results/citations.csv)
// LEFT JOINed to work_csl (pid -> CSL). Returns [] gracefully until the tables
// exist. Each entry: ['pid' => str, 'csl' => object|null, 'link' => array|null].
// Works without CSL fall back to the bare identifier link.
function get_recordset_citation_works($id)
{
	global $db;

	$works = array();

	if (!pg_table_readable('public.dataset_citation'))
	{
		return $works;
	}

	// work_csl may not exist yet, or exist without a SELECT grant; only join to
	// it when we can actually read it.
	$have_csl = pg_table_readable('public.work_csl');

	$sql = "SELECT dc.pid, dc.doi, dc.handle, dc.urn, dc.url, "
	     . ($have_csl ? "wc.csl" : "NULL::jsonb AS csl")
	     . " FROM dataset_citation dc "
	     . ($have_csl ? "LEFT JOIN work_csl wc ON wc.pid = dc.pid " : "")
	     . "WHERE dc.dataset = $1 "
	     . "ORDER BY dc.pid";

	$result = pg_query_params($db, $sql, array($id));
	if ($result)
	{
		while ($row = pg_fetch_assoc($result))
		{
			$csl = null;
			if (!empty($row['csl']))
			{
				$csl = json_decode($row['csl']);   // stdClass — citeproc wants objects
				if ($csl !== null && !isset($csl->id))
				{
					$csl->id = $row['pid'];         // seboettg citeproc needs an id per item
				}
			}

			$works[] = array(
				'pid'  => $row['pid'],
				'csl'  => $csl,
				'link' => citation_row_to_link($row),
			);
		}
	}

	return $works;
}

//----------------------------------------------------------------------------------------
// Render the works that have CSL as a single formatted bibliography (HTML string),
// or '' if none have CSL. $format is a CSL style name (e.g. 'apa'). Malformed CSL
// degrades to '' rather than a fatal, so the caller can still show fallback links.
function format_citation_bibliography($works, $format = 'apa')
{
	$items = array();
	foreach ($works as $work)
	{
		if ($work['csl'] !== null)
		{
			$items[] = $work['csl'];
		}
	}

	if (count($items) == 0)
	{
		return '';
	}

	// Make DOIs / URLs clickable in the rendered references. 'affixes' => true so
	// the wrap covers the fully-rendered value (incl. the style's
	// "https://doi.org/" prefix), giving one anchor over the whole displayed URL.
	$markup = array(
		'DOI' => array(
			'affixes'  => true,
			'function' => function ($item, $rendered) {
				$doi = isset($item->DOI) ? trim($item->DOI) : '';
				if ($doi === '') { return $rendered; }
				$href = (stripos($doi, 'http') === 0) ? $doi : 'https://doi.org/' . $doi;
				return '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . $rendered . '</a>';
			},
		),
		'URL' => array(
			'affixes'  => true,
			'function' => function ($item, $rendered) {
				$url = isset($item->URL) ? trim($item->URL) : '';
				if ($url === '') { return $rendered; }
				return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . $rendered . '</a>';
			},
		),
	);

	try
	{
		$citeproc = new CiteProc(StyleSheet::loadStyleSheet($format), 'en-US', $markup);
		return $citeproc->render($items, "bibliography");   // one call for the whole list
	}
	catch (\Exception $e)
	{
		return '';
	}
}

//----------------------------------------------------------------------------------------
// Turn a dataset_citation row into a resolvable link. One citing work may carry
// several identifiers; prefer the most canonical/resolvable, matching the pid
// hierarchy in the DB: doi > handle > urn > url. (Display uses resolver URLs for
// the href — e.g. urns via nbn-resolving.org — whereas the pid column stores the
// bare urn; they agree on which identifier wins, just differ in href form.)
// Returns ['href', 'label', 'kind'] or null if the row has no usable identifier.
function citation_row_to_link($row)
{
	if (!empty($row['doi']))
	{
		return array('href' => 'https://doi.org/' . $row['doi'], 'label' => $row['doi'], 'kind' => 'doi');
	}
	if (!empty($row['handle']))
	{
		return array('href' => 'https://hdl.handle.net/' . $row['handle'], 'label' => $row['handle'], 'kind' => 'handle');
	}
	if (!empty($row['urn']))
	{
		return array('href' => 'https://nbn-resolving.org/' . $row['urn'], 'label' => $row['urn'], 'kind' => 'urn');
	}
	if (!empty($row['url']))
	{
		return array('href' => $row['url'], 'label' => $row['url'], 'kind' => 'url');
	}
	return null;
}

//----------------------------------------------------------------------------------------
function get_recordset_spatial_extent($id)
{
	global $db;
	
	$extent = null;

	// geographic extent
	$sql = "SELECT ST_AsGeoJSON(ST_Envelope(ST_ConcaveHull(ST_Collect(boldvector.coord), 1))) AS envelope
	FROM boldvector 
	INNER JOIN boldmeta USING(processid)
	WHERE bold_recordset_code_arr @> ARRAY['$id']";

	$result = pg_query($db, $sql);
	
	while ($row = pg_fetch_assoc($result))
	{
		$extent = force_polygon($row['envelope']);
	}
	
	return $extent;
}

//----------------------------------------------------------------------------------------
// Return the bounding box of all records matching an arbitrary filter string.
// Reuses filters_to_sql() so it works for any filter type (recordset, taxon,
// country_iso, etc.).  Returns a GeoJSON polygon string, or null if no records
// match or the filter is empty.
function get_filter_spatial_extent($filter_string)
{
	global $db;

	if (trim($filter_string) == '')
	{
		return null;
	}

	$filters = parse_filter_url_parameter($filter_string);
	if (empty($filters))
	{
		return null;
	}

	$filter_sql = filters_to_sql($filters);
	if (trim($filter_sql) == '')
	{
		return null;
	}

	$sql = "SELECT ST_AsGeoJSON(ST_Envelope(ST_Collect(boldvector.coord))) AS envelope
	FROM boldvector
	INNER JOIN boldmeta USING(processid)
	WHERE $filter_sql";

	$result = pg_query($db, $sql);

	$extent = null;
	while ($row = pg_fetch_assoc($result))
	{
		if ($row['envelope'])
		{
			$extent = force_polygon($row['envelope']);
		}
	}

	return $extent;
}

//----------------------------------------------------------------------------------------
// If GeoJSON spatial coverage is a Point convert to an enclosing polygon,
// useful for maps where we have only one point to display but want to provide
// sensible bounds for viewing the map.
function force_polygon($spatialCoverage)
{
	$spatial = json_decode($spatialCoverage);
	if ($spatial && $spatial->type == 'Point')
	{
		$padding = 2; // 2° buffer around point locality
	
		$minx = max(-180, $spatial->coordinates[0] - $padding);
		$maxx = min(180, $spatial->coordinates[0] + $padding);

		$miny = max(-90, $spatial->coordinates[1] - $padding);
		$maxy = min(90, $spatial->coordinates[1] + $padding);
		
		$coordinates = array();
		
		$coordinates[] = [$minx, $maxy];
		$coordinates[] = [$maxx, $maxy];
		$coordinates[] = [$maxx, $miny];
		$coordinates[] = [$minx, $miny];
		$coordinates[] = [$minx, $maxy];
		
		$feature = new stdclass;
		$feature->type = 'Polygon';
		$feature->coordinates = array($coordinates);
		
		$spatialCoverage = json_encode($feature);
	}
	

	return $spatialCoverage;
}

//----------------------------------------------------------------------------------------
function get_sequences_from_sql($sql)
{
	global $db;
	$result = pg_query($db, $sql);
	
	$sequences = array();
	
	while ($row = pg_fetch_assoc($result)) 
	{
		$label = ">" . join("|", array($row['processid'], $row['bin_uri'], $row['identification']));
		
		$sequence = chunk_split($row['nuc'], 60);

		$sequences[$label] = $sequence;
	}
	
	return $sequences;
}

//----------------------------------------------------------------------------------------
function get_raw_sequences_for_bin($bin_uri, $limit = 100)
{
	$sql = "SELECT processid, bin_uri, identification, nuc FROM boldmeta WHERE nuc IS NOT NULL AND bin_uri='" . $bin_uri . "' LIMIT " . $limit;

	$sequences = get_sequences_from_sql($sql);
	
	return $sequences;
}

//----------------------------------------------------------------------------------------
function sequences_to_fasta($sequences)
{
	$fasta = '';
	
	foreach ($sequences as $label => $nuc)
	{
		$fasta .= $label . "\n";
		$fasta .= $nuc . "\n";
	}
	
	return $fasta;
}

//----------------------------------------------------------------------------------------
// Given a set of search results we get the embeddings for each sequence and generate a 
// t-Distributed Stochastic Neighbor Embedding (t-SNE) which is returned in a format
// suitable for VEGA-Lite. That is, an array of values.
function get_tsne_for_sequences($search_result)
{
	if (0)
	{
		
		$values = [];
		
		// array of the embedding vectors
		$samples = array();
		
		// labels we will use
		$bins = array();
		$barcodes = array();
		
		foreach ($search_result->hits as $hit)
		{
			$data = json_decode($hit->embedding);
			$samples[] = $data;
			
			if (isset( $hit->bin_uri))
			{
				$bins[] = $hit->bin_uri;
			}
			else
			{
				$bins[] = "None";
			}
			
			$barcodes[] = $hit->processid;	
		}
		
		$tsne = new tSNE(array('dim' => 2));
		$tsne->initDataRaw($samples);
	
		for ($k=0; $k < 200; $k++) 
		{
		  // every time you call this, the solution gets better.
		  $tsne->step();
		}
		
		$Y = $tsne->getSolution();
		
		foreach ($Y as $index => $pt)
		{
			$obj = new stdclass;
			$obj->bin = $bins[$index];
			$obj->processid = $barcodes[$index];
			$obj->x = $pt[0];
			$obj->y = $pt[1];
			
			$values[]  = $obj;
		}	
		
		return $values;
		
		
	}
	else
	{
		// Return full VegaLite structure for display		
		
		$doc = new stdclass;
		$doc->status = 200;
			
		$doc->{'$schema'} = "https://vega.github.io/schema/vega-lite/v5.json";
		$doc->description = "plot";
		$doc->width  = 500;
		$doc->height = 500;
		
		$doc->data = new stdclass;
		$doc->data->values = array();
		
		$doc->mark = new stdclass;
		$doc->mark->type = "circle";
		$doc->mark->size = 200;
	
		$doc->encoding = new stdclass;
		$doc->encoding->x = new stdclass;
		$doc->encoding->x->field = "x";
		$doc->encoding->x->type = "quantitative";
		$doc->encoding->x->axis = new stdclass;
		$doc->encoding->x->axis->title = "t-SNE 1";
	
		$doc->encoding->y = new stdclass;
		$doc->encoding->y->field = "y";
		$doc->encoding->y->type = "quantitative";
		$doc->encoding->y->axis = new stdclass;
		$doc->encoding->y->axis->title = "t-SNE 2";
		 
		$doc->encoding->color = new stdclass;
		$doc->encoding->color->field = "bin";
		$doc->encoding->color->type = "nominal";
		
		$doc->encoding->color->scale = new stdclass;
		$doc->encoding->color->scale->domain = array_keys($search_result->bin_colors);
		$doc->encoding->color->scale->range = array_values($search_result->bin_colors);
	
		$doc->encoding->tooltip = [];
	
		$tip = new stdclass;
		$tip->field = "processid";
		$tip->type = "nominal";
		$doc->encoding->tooltip[] = $tip;
	
		$tip = new stdclass;
		$tip->field = "processid";
		$tip->type = "nominal";
		$doc->encoding->tooltip[] = $tip;
	
		$tip = new stdclass;
		$tip->field = "bin";
		$tip->type = "nominal";
		$doc->encoding->tooltip[] = $tip;
		
		// array of the embedding vectors
		$samples = array();
		
		// labels we will use
		$bins = array();
		$barcodes = array();
		
		foreach ($search_result->hits as $hit)
		{
			$data = json_decode($hit->embedding);
			$samples[] = $data;
			
			if (isset($hit->bin_uri))
			{
				$bins[] = $hit->bin_uri;
			}
			else
			{
				$bins[] = "None";
			}
			
			$barcodes[] = $hit->processid;	
		}
		
		$tsne = new tSNE(array('dim' => 2));
		$tsne->initDataRaw($samples);
	
		for ($k=0; $k < 200; $k++) 
		{
		  // every time you call this, the solution gets better.
		  $tsne->step();
		}
		
		$Y = $tsne->getSolution();
		
		foreach ($Y as $index => $pt)
		{
			$obj = new stdclass;
			$obj->bin = $bins[$index];
			$obj->processid = $barcodes[$index];
			$obj->x = $pt[0];
			$obj->y = $pt[1];
			
			$doc->data->values[]  = $obj;
		}	
		
		return $doc;
	}
}

if (0)
{
	$sequences = get_raw_sequences_for_bin('BOLD:AEA7008');
	$fasta = sequences_to_fasta($sequences);

	echo $fasta;
}

if (0)
{
	$s = get_barcode_related('ABLCV316-09', 50);
	
	$doc = get_tsne_for_sequences($s);
	
	echo json_encode($doc);
}

//----------------------------------------------------------------------------------------
// Geographic hulls (Flickr-style alpha shape) for barcode points within an
// administrative region. Drill-down: country → province/state → region →
// sector → site. PostGIS 3.4 lacks ST_AlphaShape so we use ST_ConcaveHull,
// which is a different algorithm but visually similar.

// Map a depth (0..4) to its boldmeta column.
function geo_admin_column($depth)
{
	$cols = ['country_iso', 'province/state', 'region', 'sector', 'site'];
	return isset($cols[$depth]) ? $cols[$depth] : null;
}

// Quote a column name for SQL: needed because two of our admin columns
// contain a "/".
function geo_quote_ident($col)
{
	return '"' . str_replace('"', '""', $col) . '"';
}

// Build the WHERE fragment + parameter list common to hull and children
// queries. $levels is an indexed array of admin values from country (depth 0)
// down to whatever the deepest specified level is. Empty values terminate
// the chain. Returns [where_sql, params_array, depth_of_last_level].
function geo_build_where($levels, $marker)
{
	$clauses = ['v.coord IS NOT NULL'];
	$params  = [];
	$depth   = -1;

	foreach ($levels as $i => $value)
	{
		if ($value === null || $value === '')
		{
			break;
		}
		$col = geo_admin_column($i);
		if ($col === null)
		{
			break;
		}
		$params[]   = $value;
		$clauses[]  = 'm.' . geo_quote_ident($col) . ' = $' . count($params);
		$depth      = $i;
	}

	if ($marker !== '' && $marker !== null)
	{
		$params[]  = $marker;
		$clauses[] = 'v.marker_code = $' . count($params);
	}

	return [implode(' AND ', $clauses), $params, $depth];
}

// Return GeoJSON FeatureCollection containing two features:
//   - hull: ST_ConcaveHull of all matching points
//   - bbox: ST_Envelope of the same points
// $alpha is the target_percent passed to ST_ConcaveHull (0..1, lower = tighter).
// $limit caps the number of points fed into the hull computation. ST_ConcaveHull
// is the dominant cost; on the full ~590k US points it takes ~30s, on a 10k
// sample about 1s. The first $limit rows the planner returns are used (no
// random sample, but spatial coverage is still good enough for visualisation).
// Pass 0 to use all points.
function get_geo_hull($country, $province = '', $region = '', $sector = '', $site = '', $marker = 'COI-5P', $alpha = 0.3, $limit = 10000)
{
	global $db;

	if ($country === '' || $country === null)
	{
		return null;
	}

	// clamp
	$alpha = (float)$alpha;
	if ($alpha < 0.0) $alpha = 0.0;
	if ($alpha > 1.0) $alpha = 1.0;

	$limit = (int)$limit;
	if ($limit < 0) $limit = 0;

	$levels = [$country, $province, $region, $sector, $site];
	list($where, $params, $depth) = geo_build_where($levels, $marker);

	// alpha and limit are numeric literals — cast/clamped above so safe to
	// interpolate. ST_ConcaveHull's second arg has to be a constant
	// expression in some PostGIS builds.
	$limit_clause = $limit > 0 ? "LIMIT $limit" : '';
	$sql = "SELECT
		COUNT(*) AS n,
		ST_AsGeoJSON(ST_ConcaveHull(ST_Collect(coord), $alpha, false)) AS hull,
		ST_XMin(ST_Collect(coord)) AS xmin,
		ST_YMin(ST_Collect(coord)) AS ymin,
		ST_XMax(ST_Collect(coord)) AS xmax,
		ST_YMax(ST_Collect(coord)) AS ymax
		FROM (
			SELECT v.coord
			FROM boldvector v
			INNER JOIN boldmeta m USING(processid)
			WHERE $where
			$limit_clause
		) t";

	$result = pg_query_params($db, $sql, $params);
	if (!$result)
	{
		return null;
	}

	$row = pg_fetch_assoc($result);
	if (!$row || (int)$row['n'] === 0)
	{
		return null;
	}

	$fc = new stdclass;
	$fc->type = 'FeatureCollection';

	// RFC 7946 §5: bbox is metadata on the FeatureCollection itself, not a
	// feature — viewers like GISLook won't render it as a polygon.
	if ($row['xmin'] !== null && $row['ymin'] !== null
		&& $row['xmax'] !== null && $row['ymax'] !== null)
	{
		$fc->bbox = [
			(float)$row['xmin'], (float)$row['ymin'],
			(float)$row['xmax'], (float)$row['ymax'],
		];
	}

	$fc->features = array();

	$props = new stdclass;
	$props->country  = $country;
	if ($province !== '') $props->province = $province;
	if ($region   !== '') $props->region   = $region;
	if ($sector   !== '') $props->sector   = $sector;
	if ($site     !== '') $props->site     = $site;
	$props->marker  = $marker;
	$props->count   = (int)$row['n'];
	$props->limit   = $limit;
	$props->sampled = $limit > 0 && (int)$row['n'] >= $limit;
	$props->depth   = $depth;
	$props->alpha   = $alpha;
	$props->role    = 'hull';

	if ($row['hull'])
	{
		$f = new stdclass;
		$f->type       = 'Feature';
		$f->geometry   = json_decode($row['hull']);
		$f->properties = $props;
		$fc->features[] = $f;
	}

	return $fc;
}

// Load the static country list (iso + name) — pre-computed because scanning
// the full dataset for distinct country codes takes ~50s.
function get_geo_countries()
{
	$path = dirname(__FILE__) . '/geo_countries.json';
	$json = @file_get_contents($path);
	if ($json === false) return [];
	$data = json_decode($json);
	return is_array($data) ? $data : [];
}

// List the children one admin level below the deepest specified level.
// Pass no levels to list countries.
function get_geo_children($country = '', $province = '', $region = '', $sector = '', $site = '', $marker = 'COI-5P')
{
	global $db;

	$levels = [$country, $province, $region, $sector, $site];

	// Top-level country listing comes from the static file (see
	// get_geo_countries) — runs in milliseconds instead of ~50s.
	$is_top = ($country === '' && $province === '' && $region === '' && $sector === '' && $site === '');
	if ($is_top)
	{
		$doc = new stdclass;
		$doc->parent_depth = -1;
		$doc->child_level  = 'country_iso';
		$doc->marker       = $marker;
		$doc->children     = get_geo_countries();
		return $doc;
	}

	list($where, $params, $depth) = geo_build_where($levels, $marker);

	$child_depth = $depth + 1;
	$child_col   = geo_admin_column($child_depth);
	if ($child_col === null)
	{
		// already at the leaf level — no children
		$doc = new stdclass;
		$doc->parent_depth = $depth;
		$doc->child_level  = null;
		$doc->children     = array();
		return $doc;
	}

	$qcol = geo_quote_ident($child_col);

	// non-empty filter on the child column so we don't return the empty bucket
	$sql = "SELECT DISTINCT m.$qcol AS name
		FROM boldvector v
		INNER JOIN boldmeta m USING(processid)
		WHERE $where AND m.$qcol IS NOT NULL AND m.$qcol <> ''
		ORDER BY name";

	$result = pg_query_params($db, $sql, $params);
	if (!$result)
	{
		return null;
	}

	$children = array();
	while ($row = pg_fetch_assoc($result))
	{
		$c = new stdclass;
		$c->name = $row['name'];
		$children[] = $c;
	}

	$doc = new stdclass;
	$doc->parent_depth = $depth;
	$doc->child_level  = $child_col;
	if ($country  !== '') $doc->country  = $country;
	if ($province !== '') $doc->province = $province;
	if ($region   !== '') $doc->region   = $region;
	if ($sector   !== '') $doc->sector   = $sector;
	$doc->marker   = $marker;
	$doc->children = $children;

	return $doc;
}

?>
