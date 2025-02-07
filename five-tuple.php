<?php

error_reporting(E_ALL);


// Code for 5-mer for which we have 4^5 = 1024 distinct patterns based on alphabet A,C,G,T
// This means we can have a vector of 1024 elements, each being the frequency of a 5-mer
// in a sequence

//----------------------------------------------------------------------------------------
// https://gist.github.com/jwage/11193216

// 5-mer
$attributeValues 
	= array(
		array('A','C','G','T'), 
		array('A','C','G','T'), 
		array('A','C','G','T'), 
		array('A','C','G','T'), 
		array('A','C','G','T')
	);		

class Cartesian
{
    public static function build($set)
    {
        if (!$set) {
            return array(array());
        }
        $subset = array_shift($set);
        $cartesianSubset = self::build($set);
        $result = array();
        foreach ($subset as $value) {
            foreach ($cartesianSubset as $p) {
                array_unshift($p, $value);
                $result[] = $p;
            }
        }
        return $result;        
    }
}

// Global variable
$tuples = Cartesian::build($attributeValues);

//----------------------------------------------------------------------------------------
function clean_sequence($sequence)
{
	$sequence = strtoupper($sequence);
	
	// whitespace and numbers
	$sequence = preg_replace('/[0-9\s]/', '', $sequence);
	$sequence = preg_replace('/\R/u', '', $sequence);
	
	// replace ambiguity codes with 'N';	
	$sequence = preg_replace('/[RYKMSWBHNDV]/', 'N', $sequence);

	// remove gaps
	$sequence = preg_replace('/-/', '', $sequence);
	
	// remove crap
	$sequence = preg_replace('/I/', '', $sequence);

	return $sequence;
}


//----------------------------------------------------------------------------------------
// Take DNA sequence, remove gaps, replace any ambiguity character with 'N', then generate
// tuples that lack any ambiguity characters
function sequence_to_tuples($sequence)
{
	global $tuples;
	
	$seq_tuples = array();
	
	foreach($tuples as $tuple)
	{
		$k = join('', $tuple);
		$seq_tuples[$k] = 0;	
	}
	
	$sequence = clean_sequence($sequence);
	
	$len = strlen($sequence);
	
	for ($j = 0; $j <= $len - 5; $j++)
	{
		$k = substr($sequence, $j, 5);
		
		if (!preg_match('/N/', $k))
		{		
			$seq_tuples[$k]++;				
		}
	}
	
	return $seq_tuples;
}

//----------------------------------------------------------------------------------------
// Convert sequence to an embedding. the embedding is a vector of [1024] real numbers,
// corresponding to the frequency of each 5-mer in the sequence
function sequence_to_vector($nucleotide_sequence)
{
	global $tuples;
		
	// get tuples
	$sequence_tuples = sequence_to_tuples($nucleotide_sequence);
	
	// get tuple counts 
	$sum = 0;
	foreach ($sequence_tuples as $tuple => $tuple_count)
	{
		$sum += $tuple_count;
	}
	
	if ($sum == 0)
	{
		// shouldn't happen unless sequence is missing or is just junk
		return array();
	}
	
	// initialise embedding
	$embedding = array();
	foreach($tuples as $tuple)
	{
		$k = join('', $tuple);
		$embedding[$k] = (float)0.0;	
	}
	
	// scale the embedding
	foreach ($sequence_tuples as $tuple => $tuple_count)
	{
		$embedding[$tuple] = (float)$tuple_count/$sum;
	}
	
	return array_values($embedding);
}

// test
if (0)
{
	$query = "AACATTATATTTTATCTTTGGAACATGAGCTGGAATAGTAGGAACATCATTAAGAATTTT
AATTCGAGCAGAATTAGGACATCCTGGAGCATTAATTGGTGATGATCAAATTTATAATGT
TATTGTTACTGCTCATGCTTTTGTAATAATTTTTTTTATAGTAATACCTATTATAATTGG
AGGATTTGGAAATTGATTAGTTCCCTTAATATTGGGAGCTCCTGATATAGCTTTCCCACG
AATGAATAATATAAGTTTTTGACTACTACCACCTTCTTTAACTTTATTATTAGTAAGAAG
TATAGTAGAAAATGGAGCTGGTACAGGATGAACAGTTTATCCTCCTCTTTCAGCAAGAAT
TGCTCATGGAGGAGCATCAGTTGATTTAGCAATTTTTTCTCTTCATTTAGCAGGAATATC
TTCTATTTTAGGAGCAGTAAATTTTATTACTACAGTTATTAATATACGATCAACTGGAAT
TACATATGATCGAATACCTTTATTTGTTTGATCCGTTGTAATTACAGCCTTATTACTTCT
TCTATCTCTACCTGTATTAGCTGGAGCTATTACAATACTTTTAACTGATCGAAATTTAAA
CACATCATTTTTTGATCCAGCCGGAGGAGGAGACCCTATTTTATACCAACACTTATTT";

	$query = "TTTTT";
	
	//$t = sequence_to_vector($query);
	$t = sequence_to_tuples($query);
	
	print_r($t);

}


?>
