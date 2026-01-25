<?php

// Smith-Waterman alignemnt, based on https://en.wikipedia.org/wiki/Smith–Waterman_algorithm

error_reporting(E_ALL);

ini_set('memory_limit', '-1');

//----------------------------------------------------------------------------------------
function swa_clean_sequence($sequence)
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
function swa($label1, $label2, $seq1, $seq2, $debug = false)
{
	$alignment = new stdclass;
	$alignment->labels = array($label1, $label2);
	$alignment->text = array();
	$alignment->spans = array(
		array(),
		array()
		);
	$alignment->score = 0.0;
	
	
	// Weights
	$match 		=  3;
	$mismatch 	= -1;
	$deletion 	= -6;
	$insertion 	= -6;
	
	// Tokenise input strings, and convert to lower case
	$X = str_split($seq1);
	$Y = str_split($seq2);
	
	// Lengths of strings
	$m = count($X);
	$n = count($Y);
	
	// Create and initialise matrix for dynamic programming
	$H = array();
	
	for ($i = 0; $i <= $m; $i++)
	{
		$H[$i][0] = 0;
	}
	for ($j = 0; $j <= $n; $j++)
	{
		$H[0][$j] = 0;
	}
	
	// Store which cell is the origin of the value in cell [i,j]
	// Possble values are ↖ (diagonal), ↑ (up), and ← (left)
	$P = array();
	for ($i = 0; $i <= $m; $i++)
	{
		$P[$i] = array();
		for ($j = 0; $j <= $n; $j++)
		{
			$P[$i][$j] = ' ';
		}
	}
	
	// Do alignment
			
	$max_i = 0;
	$max_j = 0;
	$max_H = 0;
	
	for ($i = 1; $i <= $m; $i++)
	{
		for ($j = 1; $j <= $n; $j++)
		{		
			$a = $H[$i-1][$j-1];
			
			$s1 = $X[$i-1];
			$s2 = $Y[$j-1];
			
			// Compute score of four possible situations (match, mismatch, deletion, insertion)
			if (strcasecmp ($s1, $s2) === 0)
			{
				// Strings are identical
				$a += $match;
			}
			else
			{
				// Strings are different
				$a += $mismatch; // you're either the same or you're not
			}
		
			$b = $H[$i-1][$j] + $deletion;
			$c = $H[$i][$j-1] + $insertion;
			
			// Get maximum value, and store relatve direction of cell that contributes 
			// to max value
			$max = 0;
			$pred = ' ';
			
			if ($a > $max)
			{
				$max = $a;
				$pred = '↖';
			}

			if ($b > $max)
			{
				$max = $b;
				$pred = '↑';
			}
			
			if ($c > $max)
			{
				$max = $c;
				$pred = '←';
			}
						
			$H[$i][$j] = $max;			
			$P[$i][$j] = $pred;
						
			if ($H[$i][$j] > $max_H)
			{
				$max_H = $H[$i][$j];
				$max_i = $i;
				$max_j = $j;
			}
		}
	}
	$alignment->score = $max_H;	
	
	// Best possible score is perfect alignment with no mismatches or gaps
	$maximum_possible_score = min(count($X), count($Y)) * $match;
	$alignment->normalised = $max_H / $maximum_possible_score;
	
	if ($debug)
	{
		echo "\nH\n";
		for ($i = 0; $i <= $m; $i++)
		{
			echo str_pad($i, 3, ' ', STR_PAD_RIGHT) . "|";
		
			for ($j = 0; $j <= $n; $j++)
			{
				echo str_pad($H[$i][$j], 4, ' ', STR_PAD_LEFT);
			}
		
			echo "\n";
		}
	}
	
	if ($debug)
	{
		echo "\nP\n";
		for ($i = 0; $i <= $m; $i++)
		{
			echo str_pad($i, 3, ' ', STR_PAD_RIGHT) . "|";
		
			for ($j = 0; $j <= $n; $j++)
			{
				echo '   ' . $P[$i][$j];
			}		
			echo "\n";
		}
	}
		
	// Traceback to recover alignment
	$value = $H[$max_i][$max_j];
	$i = $max_i;
	$j = $max_j;
	
	$alignment->spans[0][1] = $max_i - 1;
	$alignment->spans[1][1] = $max_j - 1;
	
	$alignment->d = 0;
		
	while ($value != 0)
	{
		if ($P[$i][$j] == '↖')
		{
			$rows[0][] = $X[$i-1];
			$rows[2][] = $Y[$j-1];
			
			if (strcasecmp($X[$i-1], $Y[$j-1]) === 0) {
				$rows[1][] = "|";
			} else {
				$rows[1][] = " ";
				$alignment->d++;
			}	
			
			$i--;
			$j--;	
		}
		else
		{
			if ($P[$i][$j]  == '↑')
			{
				$rows[0][] = $X[$i-1];
				$rows[1][] = " ";
				$rows[2][] = '-';
			
				$i--;
			}
			elseif ($P[$i][$j]  == '←')
			{
				$rows[0][] = '-';
				$rows[1][] = " ";
				$rows[2][] = $Y[$j-1];
			
				$j--;
			}
		}
		$value = $H[$i][$j];
	}
	
	
	$alignment->spans[0][0] = $i;
	$alignment->spans[1][0] = $j;
	
	
	// traceback gives us the alignment in reverse order
	
	$rows[0] = array_reverse($rows[0]);
	$rows[1] = array_reverse($rows[1]);
	$rows[2] = array_reverse($rows[2]);	

	$alignment->text = array(
		join('', $rows[0]),
		join('', $rows[1]),
		join('', $rows[2])
	);
	
	$alignment->p = $alignment->d / strlen($alignment->text[0]);	
			
	return $alignment;
}

/*
//----------------------------------------------------------------------------------------
// Strip any leading or trailing gaps
function clean_sequence($seq)
{
	$seq = preg_replace('/^\-+/', '', $seq);
	$seq = preg_replace('/\-+$/', '', $seq);
	
	return $seq;
}
*/

//----------------------------------------------------------------------------------------
// Show alignment as chunks of aligned sequence (like BLAST results)
function show_alignment($alignment)
{
	$text = '';

	$label_length = 20;
	$chunk_length = 60;
	$pos_length    = 5;
	$pos_space     = 1;
	
	// ensure labels aren't too long
	$alignment->labels[0] = substr($alignment->labels[0], 0, $label_length);
	$alignment->labels[1] = substr($alignment->labels[1], 0, $label_length);

	$num_rows = 3; // one for each sequence, one for the vertical bars
	$num_chunks = ceil(strlen($alignment->text[0]) / $chunk_length);
	
	// zero-based position of alignment between two sequences
	$seq1_pos_start = $alignment->spans[0][0];
	$seq2_pos_start = $alignment->spans[1][0];

	$seq1_pos_end = $alignment->spans[0][1];
	$seq2_pos_end = $alignment->spans[1][1];

	$text .= "differences=" . $alignment->d . " (p=" . round($alignment->p, 3) . ")\n";
	
	for ($i = 0; $i < $num_chunks; $i++)
	{
		for ($j = 0; $j < $num_rows; $j++)
		{
			switch ($j)
			{
				case 0:
					$text .= str_pad($alignment->labels[0], $label_length, ' ', STR_PAD_RIGHT);
					
					$pos = $seq1_pos_start + ($i * $chunk_length) + 1;
					$text .= str_pad($pos, $pos_length, ' ', STR_PAD_LEFT);
					$text .= str_pad(' ', $pos_space, ' ', STR_PAD_LEFT);
					
					$text .= substr($alignment->text[$j], ($i * $chunk_length), $chunk_length);
					
					// if last row add sequence position at end
					if ($i == $num_chunks - 1)
					{
						$pos = $seq1_pos_end + 1;
						$text .= str_pad($pos, $pos_length, ' ', STR_PAD_LEFT);						
					}
					
					$text .= "\n";
					break;
				
				case 1:
					$text .= str_pad(" ", $label_length, ' ', STR_PAD_RIGHT);
					$text .= str_pad(" ", $pos_length, ' ', STR_PAD_LEFT);
					$text .= str_pad(' ', $pos_space, ' ', STR_PAD_LEFT);
					$text .= substr($alignment->text[$j], ($i * $chunk_length), $chunk_length);
					$text .= "\n";
					break;
				
				case 2:
					$text .= str_pad($alignment->labels[1], $label_length, ' ', STR_PAD_RIGHT);

					$pos = $seq2_pos_start + ($i * $chunk_length) + 1;
					$text .= str_pad($pos, $pos_length, ' ', STR_PAD_LEFT);
					$text .= str_pad(' ', $pos_space, ' ', STR_PAD_LEFT);

					$text .= substr($alignment->text[$j], ($i * $chunk_length), $chunk_length);

					// if last row add sequence position at end
					if ($i == $num_chunks - 1)
					{
						$pos = $seq2_pos_end + 1;
						$text .= str_pad($pos, $pos_length, ' ', STR_PAD_LEFT);						
					}
					
					$text .= "\n";
					break;		
			}
		}
		$text .= "\n";

	}
	
	return $text;
}


if (0)
{
	
	
	$seq1 = 'ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
	$seq2 =                 'AATATAAGATTTTGATTACTNCCCCCCTCTCTAACATTATTAATTTCAAGAAGAATTGTAGAAAATGGGGCAGGT';
	
	//$seq2 = 'ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
	//$seq1 =                 'AATATAAGATTTTGATTACTNCCCCCCTCTCTAACATTATTAATTTCAAGAAGAATTGTAGAAAATGGGGCAGGT';
	
	//$seq1 = '----------------------------------------------------------------------ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
	//$seq2 = 'AACATTATATTTTATTTTTGGAGTATGATCAGGAATAATTGGAACATCTCTAAGATTATTAATTCGAGCTGAATTAGGAAATCCAGGATCATTAATTGGAGATGATCAAATTTATAATACTATCGTTACAGCACATGCATTTATTATAATTTTTTTTATAGTAATGCCAATTATAATTGGAGGATTTGGAAATTGATTAGTACCATTAATATTAGGAGCCCCAGATATAGCTTTCCCACGTATAAATAATATAAGATTTTGATTACTTCCCCCATCACTAACATTATTAATCTCAAGAAGAATTGTAGAAAATGGAGCAGGAACTGGATGAACAGTTTACCCCCCACTTTCATCAAATATCGCTCATGGGGGAAGATCTGTAGATTTAGCAATTTTCTCCTTACATTTAGCTGGAATTTCGTCAATTTTAGGGGCAATTAATTTTATCACAACAATTATTAACATAAAAATAAATGGACTATCATTTGATCAAATACCTTTATTTGTATGAGCTGTAGGAATTACCGCATTATTATTACTCCTATCTTTACCAGTACTAGCAGGAGCAATCACTATATTACTAACTGATCGAAACCTAAACACATCATTTTTCGACCCTGCTGGAGGAGGAG--------';
	
	//                                                                                                                                                                                                                                        ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT
	// AACATTATATTTTATTTTTGGAGTATGATCAGGAATAATTGGAACATCTCTAAGATTATTAATTCGAGCTGAATTAGGAAATCCAGGATCATTAATTGGAGATGATCAAATTTATAATACTATCGTTACAGCACATGCATTTATTATAATTTTTTTTATAGTAATGCCAATTATAATTGGAGGATTTGGAAATTGATTAGTACCATTAATATTAGGAGCCCCAGATATAGCTTTCCCACGTATAAATAATATAAGATTTTGATTACTTCCCCCATCACTAACATTATTAATCTCAAGAAGAATTGTAGAAAATGGAGCAGGAACTGGATGAACAGTTTACCCCCCACTTTCATCAAATATCGCTCATGGGGGAAGATCTGTAGATTTAGCAATTTTCTCCTTACATTTAGCTGGAATTTCGTCAATTTTAGGGGCAATTAATTTTATCACAACAATTATTAACATAAAAATAAATGGACTATCATTTGATCAAATACCTTTATTTGTATGAGCTGTAGGAATTACCGCATTATTATTACTCCTATCTTTACCAGTACTAGCAGGAGCAATCACTATATTACTAACTGATCGAAACCTAAACACATCATTTTTCGACCCTGCTGGAGGAGGAG
	
	// ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT
	// |  |                              | |  |     |  |      |    |      
	// TTTCCCACGTATAAATAATATAAGATTTTGATTACTTCCCCCATCACTAACATTATTAATCTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT
	
	/*
	$seq1 = 'TGTTACGG';
	$seq2 = 'GGTTGACTA';
	
	$seq1 = 'TGTTACTA';
	$seq2 = 'GGTTGACTA';
	*/
	
	// TGTT-ACTA
	// GGTTGACTA
	
	// TGTTA-CGG
	// GGTTGACTA
	
	
	// wikipedia
	$seq2 = 'TGTTACGG';
	$seq1 = 'GGTTGACTA';
	
	// GTT-AC
	// ||| ||
	// GTTGAC
	
	
	/*
			G   G   T   T   G   A   C   T   A
	0  |0   0   0   0   0   0   0   0   0   0   
	1  |0   0   0   3   3   1   0   0   3   1   T
	2  |0   3   3   1   1   6   4   2   1   0   G
	3  |0   1   1   6   4   4   3   1   5   3   T
	4  |0   0   0   4   9   7   5   3   4   2   T
	5  |0   0   0   2   7   6   10  8   6   7   A
	6  |0   0   0   0   5   4   8   13  11  9   C
	7  |0   3   3   1   3   8   6   11  10  8   G
	8  |0   3   6   4   2   6   5   9   8   7   G
	
	*/
	
	
	
	//$seq1 = 'ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
	//$seq2 =                 'AATATAAGATTTTGATTACTNCCCCCCTCTCTAACATTATTAATTTCAAGAAGAATTGTAGAAAATGGGGCAGGT';
	
	
	//$seq1 = '----------------------------------------------------------------------ATTTCCACGTATAAATAATATAAGATTTTGATTATTACCTCCATCCCTCACATTACTAATTTCAAGAAGAATTGTAGAAAATGGAGCAGGAACT';
	//$seq2 = 'AACATTATATTTTATTTTTGGAGTATGATCAGGAATAATTGGAACATCTCTAAGATTATTAATTCGAGCTGAATTAGGAAATCCAGGATCATTAATTGGAGATGATCAAATTTATAATACTATCGTTACAGCACATGCATTTATTATAATTTTTTTTATAGTAATGCCAATTATAATTGGAGGATTTGGAAATTGATTAGTACCATTAATATTAGGAGCCCCAGATATAGCTTTCCCACGTATAAATAATATAAGATTTTGATTACTTCCCCCATCACTAACATTATTAATCTCAAGAAGAATTGTAGAAAATGGAGCAGGAACTGGATGAACAGTTTACCCCCCACTTTCATCAAATATCGCTCATGGGGGAAGATCTGTAGATTTAGCAATTTTCTCCTTACATTTAGCTGGAATTTCGTCAATTTTAGGGGCAATTAATTTTATCACAACAATTATTAACATAAAAATAAATGGACTATCATTTGATCAAATACCTTTATTTGTATGAGCTGTAGGAATTACCGCATTATTATTACTCCTATCTTTACCAGTACTAGCAGGAGCAATCACTATATTACTAACTGATCGAAACCTAAACACATCATTTTTCGACCCTGCTGGAGGAGGAG--------';
	
	/*
	>DISMA003-17_Phragmatopoma_californica_COI-5P
	---------NNNTGGTCAACAAATCATAAAGATATTGGCACACTATATTTTATATTTGGAATTTGATCAGGGCTTTTAGGCACTTCAATAAGACTCCTTATTCGAGCTGAGTTAGGCCAACCA---GGATCTTTATTAGGTAGCGACCAACTTTACAATACAATTGTAACCGCCCATGCTTTTTTAATAATTTTCTTTCTTGTTATACCAGTATTTATTGGAGGATTTGGTAATTGATTACTTCCTTTAATACTCGGGGCACCAGATATAGCATTTCCTCGTCTAAATAATATAAGCTTTTGACTCCTTCCACCAGCACTAACTCTTTTAGTAGCTTCAAGAGCTGTAGAAAAGGGAGTTGGAACTGGATGAACGGTATATCCCCCCTTATCGGGAAATTTAGCTCATGCAGGACCATCTGTAGACCTGGCAATTTTTTCTCTTCACTTAGCGGGTATTTCTTCAATTTTAGGAGCCCTAAATTTTATTACGACCGTAATTAATATACGATGATCTGCTTTACGACTTGAACGTGTACCTTTATTTGTTTGATCAGTTAAAATTACAGCTGTTCTACTTCTATTATCTCTCCCAGTCTTAGCGGGGGCTATTACCATATTACTAACAGATCGAAATCTAAACACAGCATTTTTCGATCCTGCAGGAGGGGGGGACCCAGTTTTATACCAACACCTCTTCTGATTTTTTGGTCACCCTGAANNN---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	>SDP100055-13_Scolelepis_acuta_COI-5P_KR914680
	---------------------------------------------------------------------------------------NNNAGCCTTTTAATTCGAGCTGAACTTGGCCAACCT---GGATCTCTTCTAGGTAGAGACCAACTTTATAACACTATTGTTACCGCTCATGCTTTCTTAATAATTTTCTTTCTTGTAATACCTACTTTTATTGGAGGATTCGGGAATTGACTTCTTCCTTTAATATTAGGTGCTCCTGATATGGCATTTCCACGATTAAATAATATAAGATTTTGGCTTTTACCCCCCTCACTAACTTTACTAGTTTCTTCTGCAGCTGTAGAAAAAGGAGTAGGAACAGGATGAACTGTATACCCTCCTTTATCAGGGAATTTAGCTCATGCAGGTCCATCTGTAGATTTAGCTATTTTTTCTCTTCATCTGGCAGGAGTCTCATCAATTTTAGGAGCTCTTAACTTTATCACTACAGTTATTAATATACGATCTAAAGGATTACGTCTTGAACGTATTCCTTTATTTGTTTGAGCCGTTGTAATTACAGCAGTTCTTCTTCTTTTATCCCTCCCAGTTTTAGCAGGAGCAATTACCATACTTCTGACCGACCGAAATCTTAATACATCTTTC---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	*/
	
	$seq1 = '---------NNNTGGTCAACAAATCATAAAGATATTGGCACACTATATTTTATATTTGGAATTTGATCAGGGCTTTTAGGCACTTCAATAAGACTCCTTATTCGAGCTGAGTTAGGCCAACCA---GGATCTTTATTAGGTAGCGACCAACTTTACAATACAATTGTAACCGCCCATGCTTTTTTAATAATTTTCTTTCTTGTTATACCAGTATTTATTGGAGGATTTGGTAATTGATTACTTCCTTTAATACTCGGGGCACCAGATATAGCATTTCCTCGTCTAAATAATATAAGCTTTTGACTCCTTCCACCAGCACTAACTCTTTTAGTAGCTTCAAGAGCTGTAGAAAAGGGAGTTGGAACTGGATGAACGGTATATCCCCCCTTATCGGGAAATTTAGCTCATGCAGGACCATCTGTAGACCTGGCAATTTTTTCTCTTCACTTAGCGGGTATTTCTTCAATTTTAGGAGCCCTAAATTTTATTACGACCGTAATTAATATACGATGATCTGCTTTACGACTTGAACGTGTACCTTTATTTGTTTGATCAGTTAAAATTACAGCTGTTCTACTTCTATTATCTCTCCCAGTCTTAGCGGGGGCTATTACCATATTACTAACAGATCGAAATCTAAACACAGCATTTTTCGATCCTGCAGGAGGGGGGGACCCAGTTTTATACCAACACCTCTTCTGATTTTTTGGTCACCCTGAANNN---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------';
	$seq2 = '---------------------------------------------------------------------------------------NNNAGCCTTTTAATTCGAGCTGAACTTGGCCAACCT---GGATCTCTTCTAGGTAGAGACCAACTTTATAACACTATTGTTACCGCTCATGCTTTCTTAATAATTTTCTTTCTTGTAATACCTACTTTTATTGGAGGATTCGGGAATTGACTTCTTCCTTTAATATTAGGTGCTCCTGATATGGCATTTCCACGATTAAATAATATAAGATTTTGGCTTTTACCCCCCTCACTAACTTTACTAGTTTCTTCTGCAGCTGTAGAAAAAGGAGTAGGAACAGGATGAACTGTATACCCTCCTTTATCAGGGAATTTAGCTCATGCAGGTCCATCTGTAGATTTAGCTATTTTTTCTCTTCATCTGGCAGGAGTCTCATCAATTTTAGGAGCTCTTAACTTTATCACTACAGTTATTAATATACGATCTAAAGGATTACGTCTTGAACGTATTCCTTTATTTGTTTGAGCCGTTGTAATTACAGCAGTTCTTCTTCTTTTATCCCTCCCAGTTTTAGCAGGAGCAATTACCATACTTCTGACCGACCGAAATCTTAATACATCTTTC---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------';
	
	$seq1 = swa_clean_sequence($seq1);
	$seq2 = swa_clean_sequence($seq2);
	
	$alignment = swa('1', '2', $seq1, $seq2);
	
	//print_r($alignment);
	
	echo show_alignment($alignment);
	
	
}


?>
