<?php

// Evaluate vector search using R-precision
// see "Information Retreval 10.1017/CBO9780511809071 
// https://nlp.stanford.edu/IR-book/html/htmledition/evaluation-of-ranked-retrieval-results-1.html
/* If there are $\vert Rel\vert$ relevant documents for a query, we examine the 
top $\vert Rel\vert$ results of a system, and find that $r$ are relevant, 
then by definition, not only is the precision (and hence R-precision) $r/\vert Rel\vert$, 
but the recall of this result set is also $r/\vert Rel\vert$.
*/

// We retrieve all the barcodes in a given BIN. The number of barcodes corresponds to the
// number of relevant documents, |Rel|. We pick one barcode and do a vector similar search, 
// returning the top 100 hits ( Note that if the number of barcodes in the BIN exceeds 
// 100 we set |Rel| = 100). The number of barcodes in the top |Rel| results that are in the BIN 
// corresponds to r. 

/*

To get random samples of BINs

CREATE TABLE distinct_bin_uri AS
SELECT DISTINCT bin_uri
FROM boldmeta
WHERE bin_uri IS NOT NULL;

SELECT bin_uri
FROM distinct_bin_uri
ORDER BY random()
LIMIT 100;

*/


//----------------------------------------------------------------------------------------
function get($url, $format_type = '')
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	
	
	//curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	
	$headers = array();
	
	if ($format_type != '')
	{
		$headers[] = "Accept: " . $format_type;
		
		if ($format_type == 'text/html')
		{
			// play nice
			$headers[] = "Accept-Language: en-gb";
			$headers[] = "User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405";
			
			// Cookies 
			curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir() . '/cookies.txt');
			curl_setopt($ch, CURLOPT_COOKIEFILE, sys_get_temp_dir() . '/cookies.txt');	
		}
	}
	
	//print_r($headers);
	
	if (count($headers) > 0)
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	$header = substr($response, 0, $info['header_size']);
	//echo $header;
	
	$content = substr($response, $info['header_size']);
	
	curl_close($ch);
	
	
	
	return $content;
}

//----------------------------------------------------------------------------------------


// Settings
$options = array(
	'limit' => 100,
	'api_url' => 'https://bold-view-bf2dfe9b0db3.herokuapp.com/api.php',
);

$options = array(
	'limit' => 100,
	'api_url' => 'http://localhost/bold-view/api.php',
);


// List of BINs to examine
$bins = array(
'BOLD:ABW0047',
'BOLD:ACA8529',
'BOLD:AAC9543', // bettle in among leps
);

$bins = array(
'BOLD:AFE8052',
'BOLD:AAZ0392',
'BOLD:ACK4452',
'BOLD:AEK8817',
'BOLD:ADW6437',
'BOLD:AED1555',
'BOLD:ADL6515',
'BOLD:AFA1479',
'BOLD:ACO8786',
'BOLD:AEM6999',
'BOLD:ACG6280',
'BOLD:AES3479',
'BOLD:ADX1815',
'BOLD:ABU8456',
'BOLD:AAU6795',
'BOLD:AEA1985',
'BOLD:AFF2046',
'BOLD:AEN2557',
'BOLD:AAM3441',
'BOLD:ACQ8536',
'BOLD:ADW8336',
'BOLD:ADX4747',
'BOLD:AAQ3416',
'BOLD:ACQ8984',
'BOLD:ADC2735',
'BOLD:ADE4265',
'BOLD:ABW5653',
'BOLD:ADE2839',
'BOLD:ABV9678',
'BOLD:AEA4122',
'BOLD:AAI3822',
'BOLD:AGA7137',
'BOLD:ACQ7992',
'BOLD:AED9204',
'BOLD:ADJ2406',
'BOLD:ABX9079',
'BOLD:ACJ2848',
'BOLD:ADW8714',
'BOLD:ABY3297',
'BOLD:AAH7607',
'BOLD:AFE4961',
'BOLD:AET0772',
'BOLD:ACW2454',
'BOLD:AAJ3195',
'BOLD:ABW9863',
'BOLD:AAI2925',
'BOLD:ACI5780',
'BOLD:AEQ3861',
'BOLD:ACN2313',
'BOLD:AER4501',
'BOLD:AFG2562',
'BOLD:ADL3777',
'BOLD:AEY3819',
'BOLD:ACK8855',
'BOLD:AES4177',
'BOLD:AEB2012',
'BOLD:ACI1125',
'BOLD:AEN4062',
'BOLD:ADG4799',
'BOLD:ACS0966',
'BOLD:AEZ7690',
'BOLD:AEL6854',
'BOLD:ACE9574',
'BOLD:ADE5268',
'BOLD:ABY0854',
'BOLD:ACU3370',
'BOLD:AEK8591',
'BOLD:AFE2212',
'BOLD:AAN1907',
'BOLD:AEG1579',
'BOLD:AAL0325',
'BOLD:ADG9616',
'BOLD:ACP1070',
'BOLD:AEO2370',
'BOLD:ADW2410',
'BOLD:ADO7060',
'BOLD:ACX8499',
'BOLD:ACY0261',
'BOLD:ABA6514',
'BOLD:ACW9098',
'BOLD:AEJ6909',
'BOLD:ADC6775',
'BOLD:AAN3102',
'BOLD:ADQ7795',
'BOLD:ACP9766',
'BOLD:AFB0188',
'BOLD:AEX5899',
'BOLD:ADF6245',
'BOLD:ADE7977',
'BOLD:AAC0416',
'BOLD:AFZ1180',
'BOLD:ADV0893',
'BOLD:AES1989',
'BOLD:AAT9403',
'BOLD:ACM8373',
'BOLD:AEW4064',
'BOLD:ACE2697',
'BOLD:ACW1056',
'BOLD:AEU7415',
'BOLD:ACY4106',
);

/*
$bins = array(
'BOLD:AFE8052',
'BOLD:AAZ0392',
'BOLD:ACK4452',
'BOLD:AEK8817',
'BOLD:ADW6437',
'BOLD:AED1555',
'BOLD:ADL6515',
'BOLD:AFA1479',
'BOLD:ACO8786',
'BOLD:AEM6999',
'BOLD:ACG6280',
'BOLD:AES3479',
'BOLD:ADX1815',
'BOLD:ABU8456',
'BOLD:AAU6795',
'BOLD:AEA1985',
'BOLD:AFF2046',
'BOLD:AEN2557',
'BOLD:AAM3441',
'BOLD:ACQ8536',
);
*/

/*

$bin_uri = 'BOLD:ACP4442';
$bin_uri = 'BOLD:AAY4708';
//$bin_uri = 'BOLD:AED5735';
//$bin_uri = 'BOLD:ACA8529'; // PNGTY1822-15 type doesn't match most in this BIN
//$bin_uri = 'BOLD:AAA3354';

//$bin_uri = 'BOLD:ADN3384';

//$bin_uri ='BOLD:ACJ3524';

//$bin_uri ='BOLD:ACO6074';

//$bin_uri ='BOLD:AEC2393';

$bin_uri ='BOLD:ABW0047'; // some cluster members aren't in BIN

//$bin_uri = 'BOLD:AAV6484';

//$bin_uri = 'BOLD:AAX3807'; // has only two members, which don't look similar w.r.t. DNA?
*/

$keys = ['bin', 'limit', 'bin_time', 'bin_size', 'seed', 'search_time', 
	'in_bin', 'not_in_bin', 'no_bin', 'precision',
	'not_found_processid', 'not_in_bin_processid', 'no_bin_processid'];	

$distances = array();
for ($i = 0; $i < $options['limit']; $i++)
{
	$distances[$i] = [];
}

$rows = [];
$rows[] = join("\t", $keys);

foreach ($bins as $bin_uri)
{
	echo "$bin_uri\n";
	
	// 1. Get barcodes for bin
	$url = $options['api_url'] . '?bin=' . urlencode($bin_uri);
	
	$json = get($url);
	
	$bin = json_decode($json);

	if ($bin)
	{
		$result = new stdclass;
		$result->bin = $bin_uri;
		$result->limit = $options['limit'];
	
		if (isset($bin->took))
		{
			$result->bin_time = $bin->took;
		}
	
		// How many barcodes are in this BIN?
		$result->bin_size = count($bin->hits);
		
		// Store barcodes in BIN
		$bin_barcodes = [];
		foreach ($bin->hits as $hit)
		{
			$bin_barcodes[] = $hit->processid;
		}

		// get a random barcode from bin and find the most similar sequences
		
		echo "Getting related\n";
		
		shuffle($bin->hits);
		$result->seed = $bin->hits[0]->processid;
		
		$url = $options['api_url'] . '?record=' . urlencode($result->seed) . '&related' . '&limit=' . $options['limit'];
		$json = get($url);	
		$related = json_decode($json);
	
		if ($related)
		{
			
			if (isset($related->took))
			{
				$result->search_time = $related->took;
			}
		
			// find how many similar barcodes are in this BIN and record 
			// their position in the list 
			
			// we only look at the first r sequences, where r is the BIN size
			$r = min($result->bin_size, $options['limit']);
			
			// classify hits
			$in_bin = array();
			$not_in_bin = array();
			$no_bin = array();		
	
			for ($i = 0; $i < $r; $i++)
			{
				if (isset($related->hits[$i]->bin_uri))
				{
					if ($related->hits[$i]->bin_uri == $bin_uri)
					{
						$in_bin[] = $i;					
					}
					else
					{
						$not_in_bin[] = $i;
					}
				}
				else
				{
					$no_bin[] = $i;
				}
			}
			
			// counts
			$result->in_bin = count($in_bin);
			$result->not_in_bin = count($not_in_bin);
			$result->no_bin = count($no_bin);
			
			// calculate precision
			$result->precision = round(count($in_bin) / $r, 2);
			
			if (1)	
			{
				// Store miss- or unclassified barcodes so we can investigate

				// barcodes in the BIN that weren't in retrieval set
				$found = [];
				foreach ($in_bin as $i)
				{
					$found[]  = $related->hits[$i]->processid;
				}
				
				// what barcodes in BIN haven't been found?
				$not_found = array_diff($bin_barcodes, $found);
				
				$result->not_found_processid = '[' . join(",", $not_found ) . ']';
				
				// barcodes in retrieval set that are assigned to another BIN
				// our seed is closer to these than to some members of its own BIN
				$result->not_in_bin_processid  = [];
				foreach ($not_in_bin as $i)
				{
					$result->not_in_bin_processid[]  = $related->hits[$i]->processid;
				}
				
				// barcodes in retrieval set that aren't in any BIN 
				// (might be unassigned because they are contamination, etc., 
				// or just simply missed)
				$result->no_bin_processid = [];
				foreach ($no_bin as $i)
				{
					$result->no_bin_processid[]  = $related->hits[$i]->processid;
				}
				
			}
			
			// get distances
			// Plot of distance changing
			
			for ($i = 0; $i < $options['limit']; $i++)
			{
				$distances[$i][$result->seed] = $related->hits[$i]->distance;
			}
			
			if (0)
			{
				print_r($result);
			}
			
			$row = [];
			foreach ($keys as $k)
			{
				if (isset($result->{$k}))
				{
					if (is_array($result->{$k}))
					{
						$row[] = '[' . join(",", $result->{$k}) . ']';
					}
					else
					{
						$row[] = $result->{$k};
					}
				}
				else
				{
					$row[] = '';
				}
			}
			
			$rows[] = join("\t", $row);
		}
	}
}


//print_r($distances);

// Dump results of precision
file_put_contents('precision.tsv', join("\n", $rows) . "\n");

// Dump ranked distances from seed barcode
$rows = array();

$row = ['Order'];

foreach ($distances[0] as $seed => $d)
{
	$row[] = $seed;
}

$rows[] = join("\t", $row);

foreach ($distances as $index => $barcodes)
{
	$row = [$index];
	foreach ($barcodes as $seed => $d)
	{
		$row [] = $d;
	}	
	$rows[] = join("\t", $row);
}

file_put_contents('distances.tsv', join("\n", $rows) . "\n");

?>
