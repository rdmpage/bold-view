<?php


// External APIs


error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/api_utilities.php');

if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}


//----------------------------------------------------------------------------------------
function default_display()
{
	echo "hi";
}

//----------------------------------------------------------------------------------------
function display_accession($accession, $format = '', $callback = '')
{
	$api_key = getenv('NCBI_API_KEY');
		
	$doc = new stdclass;
	$doc->id = $accession;
	$doc->references = array();
	
	$csl = array();
		
	$parameters = array(
		'db' 		=> 'nucleotide',
		'retmode' 	=> 'xml',
		'id' 		=> $accession,
		'api_key'	=> $api_key
	);
	
	$url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query($parameters);

	$xml = get($url);	
	
	if ($xml == '')
	{
		$doc = new stdclass;
		$doc->status = 404;
		send_doc($doc, $callback = '');
	}
	else
	{	
		$dom = new DOMDocument;
		$dom->loadXML($xml, LIBXML_NOCDATA); // So we get text wrapped in <![CDATA[ ... ]]>
		$xpath = new DOMXPath($dom);
		
		foreach($xpath->query('//GBReference') as $node)
		{
			$reference = new stdclass;
			
			// hash the XML for this reference to use as the identifier
			$reference->id = md5($dom->saveXML($node));
			
			foreach ($xpath->query('GBReference_journal', $node) as $n)
			{
				$matched = false;
				
				if (!$matched)
				{
					if (preg_match('/(?<journal>.*)\s+(?<volume>\d+)(\s*\((?<issue>[^\)]+)\))?,\s+(?<spage>\w?\d+)(-(?<epage>[A-Z]?\d+))?\s+\((?<year>[0-9]{4})\)/', $n->firstChild->nodeValue, $m))
					{
						$matched = true;
						//print_r($m);
						
						$reference->{'container-title'} = $m['journal'];
						$reference->volume = $m['volume'];
						if ($m['issue'] != '')
						{
							$reference->issue = $m['issue'];
						}
						
						$reference->{'page-first'} = $m['spage'];
						$reference->page = $m['spage'];
						
						if ($m['epage'] != '')
						{
							$reference->page .= '-' . $m['epage'];
						}
						
						$reference->issued = new stdclass;
						$reference->issued->{'date-parts'} = array();
						$reference->issued->{'date-parts'}[0] = array((Integer)$m['year']);
					}
				}
				
				if (!$matched)
				{
					// Russ. J. Theriol. (2009) In press
					if (preg_match('/(?<journal>.*)\s+\((?<year>[0-9]{4})\)\s+In\s+press/', $n->firstChild->nodeValue, $m))
					{
						$matched = true;
						// print_r($m);
						
						$reference->{'container-title'} = $m['journal'];
						
						$reference->issued = new stdclass;
						$reference->issued->{'date-parts'} = array();
						$reference->issued->{'date-parts'}[0] = array((Integer)$m['year']);
					}
				}
				
			}
			
			foreach ($xpath->query('GBReference_title', $node) as $n)
			{
				$reference->title = $n->firstChild->nodeValue;
			}
			
			$reference->author = array();
			
			foreach ($xpath->query('GBReference_authors/GBAuthor', $node) as $n)
			{
				$author = new stdclass;
				$author->literal = $n->firstChild->nodeValue;			
				$reference->author[] = $author;
			}
			
			if (count($reference->author) == 0)
			{
				unset($reference->author);
			}
			
			// PMID
			foreach ($xpath->query('GBReference_pubmed', $node) as $n)
			{
				$reference->PMID = (Integer)$n->firstChild->nodeValue;
			}
			
			// DOI
			foreach($xpath->query('GBReference_xref/GBXref[GBXref_dbname="doi"]/GBXref_id', $node) as $node)
			{
				$reference->DOI = $node->firstChild->nodeValue;
				$reference->DOI = preg_replace('/https?:\/\/(dx\.)?doi.org\//', '', $reference->DOI);
			}				
			
			// Ignore default "Direct Submission"
			if (isset($reference->title) && $reference->title != "Direct Submission")
			{
				$doc->references[] = $reference;
			}
		}
		
		$doc->status = 200;
		send_doc($doc, $callback = '');
	}
}

//----------------------------------------------------------------------------------------
function display_material_examined($museumid, $format = '', $callback = '')
{
	$url = 'https://material-examined.herokuapp.com/service/api.php?code=' . urlencode($museumid) . '&match';
	$json = get($url);	
	
	$doc = json_decode($json);
	
	if ($doc)
	{
		$doc->status = 200;
	}
	else
	{
		$doc = new stdclass;
		$doc->status = 500;
	}

	send_doc($doc, $callback = '');
}

//----------------------------------------------------------------------------------------
function main()
{

	$callback = '';
	$handled = false;
		
	// If no query parameters 
	if (count($_GET) == 0)
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


	if (!$handled)
	{		
		$accession = '';

		if (isset($_GET['accession']))
		{	
			$accession = $_GET['accession']; 
		} 
		
		if ($accession != '')
		{
			display_accession($accession, $format, $callback);				
			$handled = true;
		}
	}

	if (!$handled)
	{		
		$museumid = '';

		if (isset($_GET['museumid']))
		{	
			$museumid = $_GET['museumid']; 
		} 

		if ($museumid != '')
		{
			display_material_examined($museumid , $format, $callback);				
			$handled = true;
		}
	}
	
	if (!$handled)
	{
		default_display();
	}

}


main();

?>
