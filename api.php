<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/api_utilities.php');
require_once (dirname(__FILE__) . '/core.php');
require_once (dirname(__FILE__) . '/language.php');

//----------------------------------------------------------------------------------------
function default_display()
{
	echo "hi";
}

//----------------------------------------------------------------------------------------
function display_image ($id, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_image($id);
	
	if ($doc)
	{
		$doc->status = 200;
		
		switch ($format)
		{
			case 'html':
				// to do: we want to return a small snippet, not a full page
				$html = '<img src="' . $doc->url . '" width="100%">';

				$html .= '<dl>';
				
				foreach ($doc as $k => $v)
				{
					switch ($k)
					{
						case 'processid':
							$html .= '<dt>' . get_text(['image', $k]) . '</dt>';
							$html .= '<dd><a href="record/' . $v . '">' . $v .  '</a></dd>';
							break;
							
						case 'title':
						case 'view':
							$html .= '<dt>' . get_text(['image', $k]) . '</dt>';
							$html .= '<dd>' . $v  . '</dd>';
							break;
							
						case 'clean_license':
							$html .= '<dt>' . get_text(['image', 'license']) . '</dt>';
							$html .= '<dd>';
							
							if (preg_match('/by|nc|nd|sa|cc0/i', $v))
							{
								$html .= '<div class="license cc"></div>';
							}
							
							if (preg_match('/by/i', $v))
							{
								$html .= '<div class="license by"></div>';
							}
							if (preg_match('/nc/i', $v))
							{
								$html .= '<div class="license nc"></div>';
							}
							if (preg_match('/nd/i', $v))
							{
								$html .= '<div class="license nd"></div>';
							}
							if (preg_match('/sa/i', $v))
							{
								$html .= '<div class="license sa"></div>';
							}
							if (preg_match('/cc0/i', $v))
							{
								$html .= '<div class="license zero"></div>';
							}

							$html .= '</dd>';
							break;
							
						default:
							break;
					
					}
				
				}
				$html .= '</dl>';
	
				//header("Content-type: text/plain");
				echo $html;
				break;
			
			default:
				// JSON reponse
				send_doc($doc, $callback = '');
				break;
		}		
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
}

	
//----------------------------------------------------------------------------------------
// Barcode
function display_barcode ($id, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_barcode($id);
	
	if ($doc)
	{
		$doc->status = 200;
		
		switch ($format)
		{
			case 'html':
				$html = '';
				$html .= '<h2>' . $id . '</h2>';
				//$html .= '<p>Summarise this barcode here</p>';	
				//$html .= '<p><a href="?record=' . urlencode($id) . '">View ' . $id . '</a>' . '</p>';	
				$html .= '<p><a href="record/' . $id . '">' . get_text(['record', 'view']) . ' ' . $id . '</a>' . '</p>';	
				
				if (isset($doc->images))
				{
					$html .= '<img src="' . $doc->images[0]->url . '" width="100%">';
				}				
						
				send_html($html, $doc->status);
				break;
			
			default:
				// JSON reponse
				send_doc($doc, $callback = '');
				break;
		}		
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
	

}

//----------------------------------------------------------------------------------------
// Barcode alignment
function display_barcode_alignment ($id, $format = '', $callback = '')
{
	$status = 404;
	
	$reference_seq_name = 'NC_046603';

	$reference_seq = 'TCGCGACAATGATTATTTTCTACAAATCATAAAGATATTGGAACATTATATTTTATTTTTGGAGCATGAG
CCGGTATAGTAGGGACATCTTTGAGAATTTTAATTCGAGCTGAATTAGGGCATCCAGGAGCTTTAATTGG
AGATGATCAAATTTATAACGTAATTGTTACAGCTCATGCATTTATTATGATTTTTTTTATAGTAATACCT
ATTATAATTGGAGGATTTGGAAATTGATTAGTTCCTTTAATACTAGGAGCTCCTGATATAGCTTTTCCTC
GGATGAATAATATAAGATTCTGATTATTACCTCCTGCTCTTTCTCTTTTATTAGTAAGAAGAATAGTTGA
AAATGGGGCTGGTACTGGGTGAACAGTTTACCCACCACTATCATCAGGAATTGCACATGGAGGTGCATCT
GTTGATTTAGCAATTTTTTCTCTTCATTTAGCTGGAATTTCTTCTATTTTAGGAGCAGTAAATTTTATTA
CAACTGTGATTAATATACGATCTTCGGGAATTACTCTTGATCGAATACCATTATTTGTGTGATCAGTTGT
AATTACAGCTTTATTATTATTATTATCTTTACCAGTTTTAGCGGGAGCAATTACTATATTATTAACTGAT
CGAAATTTAAATACTTCTTTTTTCGACCCTGCTGGAGGTGGAGATCCAATTTTATATCAACACTTATTTT
GATTTTTTGGTCATCCAGAAGTTTATATTTTAATTTTACCTGGATTTGGAATAATTTCCCATATTATTAG
TCAAGAATCGGGAAAAAAGGAAACTTTCGGATCTTTAGGAATGATTTATGCTATATTAGCTATTGGATTA
TTAGGATTTATTGTATGAGCTCACCACATATTCACAGTAGGTATAGATGTTGATACACGAGCTTACTTTA
CATCTGCAACTATAATTATTGCTGTACCTACAGGAATTAAAATTTTTAGTTGACTAGCAACTCTTCATGG
AGCTCAACTTTCTTATTCTCCAGCTATTTTATGAGCATTAGGATTTGTATTTTTATTTACAGTTGGTGGA
TTAACAGGAGTAGTTTTAGCTAATTCATCTGTAGACATTATCTTACATGATACATATTATGTAGTAGCTC
ACTTTCACTATGTTTTATCAATAGGAGCCGTATTTGCAATTATAGCTGGATTCATTCATTGATTCCCGTT
ATTTACTGGTTTAACAATAAATAATAAATTATTAAAAAGTCAATTTGTTATTATATTTATTGGAGTAAAT
TTAACTTTCTTCCCACAACATTTTTTAGGATTAGCAGGAATACCACGACGATATTCAGATTATCCAGATG
CTTATACAACTTGAAACGTAGTATCTACAATTGGTTCTTCAATTTCACTTTTAGGAATTTTATTCTTTTT
TTATATTATTTGAGAAAGTTTAGTATCACAACGACAAGTAATTTATCCAATTCAATTATGTTCATCTATT
GAATGATATCAAAATACTCCACCCGCTGAACATAGTTATTCTGAATTACCTCTTTTAACTAATTAA';

	$reference_seq_name = 'NC_030769';

	$reference_seq = 'CGAAAATGACTTTACTCAACAAATCATAAAGATATTGGAACATTATACTTCATATTTGGTATCTGAGCAGGAATAGTAGGAACATCTTTAAGACTATTAATTCGAGCAGAATTAGGCAACCCTGGGTCATTAATTGGAGACGACCAAATTTACAATACCATTGTAACAGCTCATGCTTTTATTATAATTTTTTTTATAGTTATACCTATTATAATCGGAGGATTTGGAAACTGATTAGTACCTTTAATGCTAGGAGCCCCAGACATAGCATTCCCCCGTATAAATAATATAAGATTTTGATTATTGCCCCCATCAATTACTTTACTAATTTCAAGAAGAATTGTAGAAAACGGAGCAGGAACCGGATGAACAGTTTACCCTCCTTTATCCTCTAATATCGCCCACGGGGGAAGATCAGTTGATCTAGCAATTTTTTCCCTTCATTTAGCAGGTATTTCATCAATTCTAGGAGCTATTAATTTTATTACAACAATTATTAATATACGATTAAATAATTTATCTTTTGATCAAATACCATTATTTGTCTGAGCAGTAGGAATCACAGCATTTTTATTATTATTATCACTACCTGTATTAGCAGGAGCTATTACTATATTATTAACTGACCGAAATTTAAATACATCATTTTTTGACCCAGCTGGAGGAGGGGATCCAATTCTTTATCAACACTTATTTTGATTTTTTGGTCACCCTGAAGTATACATTTTAATTTTACCAGGATTCGGTATAATTTCACATATTATTTCACAAGAAAGTGGTAAAAAAGAAACTTTCGGATGTTTAGGTATAATCTATGCTATAATAGCAATTGGTATTTTAGGATTTATTGTATGAGCTCATCACATATTTACAGTAGGTATAGATATTGATACTCGAGCCTATTTCACTTCAGCTACAATAATTATTGCTGTACCTACTGGTATTAAAATTTTTAGTTGACTAGCAACTCTTCATGGAACTCAAATTAATTATAGTCCATCAATTTTATGAAGATTAGGATTTGTATTTTTATTCACCGTAGGAGGACTAACAGGAGTTATTTTAGCCAATTCATCTATTGATATTACACTACATGATACTTATTATGTAGTAGCACATTTTCACTATGTTTTATCTATAGGAGCTGTATTTGCCATTATAGGAGGATTTATCCACTGATATCCACTATTCACAGGATTAACTATAAACCCTTACATATTAAAAATTCAATTTATAATCATATTCATTGGAGTAAACTTAACATTTTTCCCCCAACACTTTTTAGGATTAGCTGGAATACCCCGACGATACTCTGATTACCCTGACTCATACATTTCATGAAATATTGTATCATCTTTAGGATCATATATTTCACTACTAGCCGTAATGTTAATATTAATTATTATTTGAGAATCTATAATTAACCAACGTATAATTTTATTTACTTTAAATATATCATCAAATATTGAATGAATACAAAATTTACCCCCAGCTGAACATTCATATAATGAACTACCTATTTTAAGAAATNNN';


	$doc = get_barcode_alignment($id, $reference_seq_name, $reference_seq);

	if ($doc)
	{
		$doc->status = 200;
		
		switch ($format)
		{
			case 'html':
				$html = '<pre>';
				$html .= show_alignment($doc->alignment);
						
				$html .= '</pre>';
				send_html($html, $doc->status);
				break;
			
			default:
				// JSON reponse
				send_doc($doc, $callback = '');
				break;
		}		
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
	

}

//----------------------------------------------------------------------------------------
function display_barcode_images ($id, $limit=10, $format = '', $callback = '')
{
	$status = 404;
	
	$images = get_barcode_images($id);
	
	if (count($images) > 0)
	{
		$doc = new stdclass;
		$doc->status = 200;
		$doc->images = $images;
		
		switch ($format)
		{
			case 'html':
				// to do: we want to return a small snippet, not a full page

				break;
			
			default:
				// JSON reponse
				send_doc($doc, $callback = '');
				break;
		}		
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
	

}

//----------------------------------------------------------------------------------------
// List of closest matching sequence to a barcode
function display_barcode_related ($id, $compute_tree = false, $limit=10, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_barcode_related($id, $limit);
	
	$table = null;
	
	if ($compute_tree)
	{	
		$doc->newick = get_nj_tree($doc);
				
		$table = tree_labels($doc, $doc->newick);		
	}	
	
	if ($doc)
	{
		$doc->status = 200;
		
		switch ($format)
		{
			case 'html':
				$html = '';
				if ($compute_tree)
				{
					$selection = array($id);
					$svg = newick_to_svg($doc->newick);
					$html .= output_tree_table($svg, $table, $selection);
				}
				send_html($html, $doc->status);
				break;
			
			default:
				send_doc($doc, $callback = '');
				break;
		}		
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
	
}

//----------------------------------------------------------------------------------------
// BIN
function display_bin ($id, $limit = 100, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_bin($id, $limit);
	
	if ($doc)
	{
		$doc->status = 200;
		
		switch ($format)
		{
			case 'html':
				$html = '';
				$html .= '<h2>' . $id . '</h2>';
				//$html .= '<p>Summarise this bin here</p>';			
				// $html .= '<p><a href="?bin_uri=' . urlencode($id) . '">View ' . $id . '</a>' . '</p>';	
				$html .= '<p><a href="bin/' . $id . '">' . get_text(['bin', 'view']) . ' '. $id . '</a>' . '</p>';	
				send_html($html, $doc->status);
				break;
				
			case 'fasta':
				$sequences = get_sequences_for_bin($id, $limit);
				$fasta = sequences_to_fasta($sequences);
				send_text($fasta);
				break;
			
			default:
				send_doc($doc, $callback = '');
				break;
		}			
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}

}

//----------------------------------------------------------------------------------------
// Do a BLAST-style sequence search
function display_blast ($text, $marker_code = 'COI-5P', $compute_tree = false, $limit = 100, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_similar_sequences($text, $marker_code, $limit);
	
	if ($compute_tree)
	{	
		$doc->newick = get_nj_tree($doc);
		$table = tree_labels($doc, $doc->newick);
	}
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	switch ($format)
	{
		case 'html':
			$html = '';
			if ($compute_tree)
			{
				$svg = newick_to_svg($doc->newick);
				$html .= output_tree_table($svg, $table, ['query']);
			}
			send_html($html, $doc->status);
			break;
			
		default:
			send_doc($doc, $callback = '');
			break;
	}	
}

//----------------------------------------------------------------------------------------
// Build tree for set of processids
function display_ids ($ids, $compute_tree = false, $format = '', $callback = '')
{
	$status = 404;
	
	$doc = get_records_from_id_list($ids);
	
	if ($compute_tree)
	{	
		$doc->newick = get_nj_tree($doc);
	}
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	switch ($format)
	{
		case 'html':
		default:
			send_doc($doc, $callback = '');
			break;
	}	
}

//----------------------------------------------------------------------------------------
// Display simple Newick tree
function display_tree($newick, $format = '')
{
	switch ($format)
	{
		case 'svg':
			$svg = newick_to_svg($newick);
			header("Content-type: image/svg+xml; charset=utf-8");
			echo $svg;
			break;
			
		case 'html':
			$svg = newick_to_svg($newick);
			header("Content-type: text/html; charset=utf-8");
			echo $svg;
			break;
		
		default:
			echo $newick;
			break;
	}
}

//----------------------------------------------------------------------------------------
// Return list of barcodes within the bounds of the GeoJSON encoded region
function display_geojson($geojson, $filter, $limit, $callback)
{
	$status = 404;
	
	// decode geojson string
	$error = parse_json($geojson);
	
	if ($error->code == 0)
	{
		// OK
		$geo = json_decode($geojson);		
		$geometry = $geo->geometry;
		
		$doc = get_geo($geometry, $filter, $limit);
		
		if (count($doc->hits) > 0)
		{
			$doc->status = 200; 
		}
		else
		{
			$doc->status = 404; 
		}
		send_doc($doc, $callback = '');		
	}
	else
	{
		// Bad JSON
		$doc = new stdclass;
		$doc->status = 400;
		$doc->message = $error->msg;
		send_doc($doc, $callback = '');				
	}
}

//----------------------------------------------------------------------------------------
// Get information on a taxon 
function display_taxon_from_id ($taxid, $callback = '')
{
	$status = 404;
	
	$doc = get_taxon_from_taxid($taxid);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	send_doc($doc, $callback = '');
}

//----------------------------------------------------------------------------------------
// Get information on a taxon 
function display_taxon_from_name ($name, $rank = '', $callback = '')
{
	$status = 404;
	
	$doc = get_taxon_from_name($name, $rank);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	send_doc($doc, $callback = '');
}

//----------------------------------------------------------------------------------------
// Get details for a recordset (i.e., a dataset)
function display_recordset($id, $callback = '')
{
	$status = 404;
	
	$doc = get_recordset($id);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	send_doc($doc, $callback = '');
}

//----------------------------------------------------------------------------------------
// Get paged list of barcodes
function display_paged_barcodes ($page_start = 0, $page_size = 100, $filter = '', $callback = '')
{
	$status = 404;
	
	$doc = get_paged_barcodes($page_start, $page_size, $filter);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	send_doc($doc, $callback = '');
}


//----------------------------------------------------------------------------------------
// Get pages list of images
function display_paged_images ($page_start = 0, $page_size = 100, $filter = '', $callback = '')
{
	$status = 404;
	
	$doc = get_paged_images($page_start, $page_size, $filter);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 404;
	}
	
	send_doc($doc, $callback = '');
}


//----------------------------------------------------------------------------------------
function main()
{
	global $config;

	$callback = '';
	$handled = false;
	
	$post_content = file_get_contents('php://input');
	
	// If no query parameters 
	if (count($_GET) == 0 && $post_content == '')
	{
		default_display();
		exit(0);
	}
	
	$callback = '';
	if (isset($_GET['callback']))
	{	
		$callback = $_GET['callback'];
	}
	
	$debug = false;			
	if (isset($_GET['debug']))
	{
		$debug = true;
	}	
	
	$format = '';
	if (isset($_GET['format']))
	{	
		$format = $_GET['format'];
	}
	
	$filter = '';
	if (isset($_GET['filter']))
	{	
		$filter = $_GET['filter'];
	}

	$limit = 100;
	if (isset($_GET['limit']))
	{	
		$limit = $_GET['limit'];
	}
	
	$compute_tree = false;	
	if (isset($_GET['tree']))
	{	
		$compute_tree = true;
	}
	
	// for now only use COI
	$marker_code = 'COI-5P';
	
	// get one barcode from id
	// be flexible in input, either processid, barcode, or record
	if (!$handled)
	{		
		$barcode = '';
		if (isset($_GET['record']))
		{	
			$barcode = $_GET['record']; // consistent with https://portal.boldsystems.org
		} 
		elseif (isset($_GET['barcode']))
		{
			$barcode = $_GET['barcode'];
		}	
		elseif (isset($_GET['processid'])) 
		{
			$barcode = $_GET['processid'];
		}	
		
		if ($barcode != '')	
		{			
			if (isset($_GET['related']))
			{	
				display_barcode_related($barcode, $compute_tree, $limit, $format,  $callback);				
				$handled = true;
			}
			
			if (isset($_GET['images']))
			{	
				display_barcode_images($barcode, $limit, $format,  $callback);				
				$handled = true;
			}
			
			if (isset($_GET['alignment']))
			{	
				display_barcode_alignment($barcode, $format,  $callback);				
				$handled = true;
			}
														
			if (!$handled)
			{
				display_barcode($barcode, $format, $callback);
				$handled = true;
			}			
		}

	}
	
	// be flexible in name of bin parameter
	if (!$handled)
	{
		$bin = '';		
		if (isset($_GET['bin'])) // consistent with https://portal.boldsystems.org
		{	
			$bin = $_GET['bin'];
		}
		if (isset($_GET['bin_uri']))
		{	
			$bin = $_GET['bin_uri'];
		}
		
		if ($bin != '')
		{
			if (!$handled)
			{
				display_bin($bin, $limit, $format, $callback);
				$handled = true;
			}			
		}
		
	}
	
	// one image
	if (!$handled)
	{
		if (isset($_GET['image']))
		{	
			$url = $_GET['image'];
			display_image($url, $format, $callback);
			$handled = true;
		}
	}	
	
	// display a Newick tree (simple for debugging)
	if (!$handled)
	{
		if (isset($_GET['newick']))
		{	
			$newick = $_GET['newick'];
			display_tree($newick, $format);
			$handled = true;
		}
	}
	
	// map search using GeoJSON
	if (!$handled)
	{
		if (isset($_GET['geojson']))
		{	
			$geojson = $_GET['geojson'];
			display_geojson($geojson, $filter, $limit, $callback);
			$handled = true;
		}
	}
	
	// taxonomy
	if (!$handled)
	{	
		if (isset($_GET['taxonid']))
		{	
			$taxid = $_GET['taxonid'];
			display_taxon_from_id($taxid, $callback);
			$handled = true;
		}
	}

	if (!$handled)
	{	
		if (isset($_GET['taxonname']))
		{	
			$name = $_GET['taxonname'];
			$rank = '';
			if (isset($_GET['rank']))
			{
				$rank = $_GET['rank'];
			}
			display_taxon_from_name($name, $rank, $callback);
			$handled = true;
		}
	}
	
	// dataset
	if (!$handled)
	{	
		if (isset($_GET['recordset']))
		{	
			$recordset = $_GET['recordset'];		
		
			// paged result for recordset
			$page = 0;
			if (isset($_GET['page']))
			{	
				$page = $_GET['page'] - 1;
			}	
			
			// page size
			$page_size = 10;
			if (isset($_GET['limit']))
			{	
				$page_size = $_GET['limit'];
			}	
			
			$offset = $page * $page_size;
		
			$filter = 'recordset:' . $recordset;
			
			if (!$handled)
			{
				if (isset($_GET['images']))
				{
					display_paged_images($offset, $page_size, $filter, $callback);				
					$handled = true;
				}
			}
			
			if (!$handled)
			{	
				if (isset($_GET['records']))
				{						
					// list of barcodes
					display_paged_barcodes($offset, $page_size, $filter, $callback);
					$handled = true;
				}
			}

			if (!$handled)
			{			
				// metadata for recordset
				display_recordset($recordset, $callback);
				$handled = true;
			}
			
		}
	}
	
		
	// POST
	// Larger items, such as a sequence to BLAST or a set of records to work with
	if (!$handled)
	{
		if ($post_content != '')
		{
			$error = parse_json($post_content);
			
			if ($error->code == 0)
			{
				// OK
				$input = json_decode($post_content);
				
				if (isset($input->sequence))
				{			
					display_blast($input->sequence, $marker_code, $compute_tree, $limit, $format, $callback);				
					$handled = true;
				}
				
				if (isset($input->ids))
				{			
					display_ids($input->ids, $compute_tree, $format, $callback);				
					$handled = true;
				}
				
			}
			else
			{
				// Bad JSON
				$doc = new stdclass;
				$doc->status = 400;
				$doc->message = $error->msg;
				send_doc($doc, $callback = '');				
			}
		}
	}	
	
	
	if (!$handled)
	{
		default_display();
	}

}


main();

?>
