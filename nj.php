<?php

// https://en.wikipedia.org/wiki/Neighbor_joining
// https://www.tenderisthebyte.com/blog/2022/08/31/neighbor-joining-trees/

// Build a tree

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/tree/tree.php');

//----------------------------------------------------------------------------------------
function compute_distances($sequences)
{
	$n = count($sequences);

	// distance matrix
	$D = array();
	for ($i = 0; $i < $n; $i++)
	{
		$D[$i] = array();
		for ($j = 0; $j < $n; $j++)
		{
			$D[$i][$j] = 0;
		}
	}
		
	$len = count($sequences[0]->embedding);
	
	for ($i = 1; $i < $n; $i++)
	{		
		for ($j = 0; $j < $i; $j++)
		{
			$d = 0;
			
			for ($k = 0; $k < $len; $k++)
			{
				// Euclidian distance
				$d += ($sequences[$i]->embedding[$k] - $sequences[$j]->embedding[$k]) * ($sequences[$i]->embedding[$k] - $sequences[$j]->embedding[$k]);
			}

			$D[$i][$j] = $d;
			$D[$j][$i] = $d;
		}
	}

	return $D;
}

//----------------------------------------------------------------------------------------
function display_matrix($D, $labels_to_index)
{
	echo "     ";
	foreach ($labels_to_index as $label_i => $i)
	{
		echo str_pad($label_i, 4, ' ', STR_PAD_LEFT) . ' ';
	}
	echo "\n";
	echo "    +";
	foreach ($labels_to_index as $label_i => $i)
	{
		echo str_pad("", 4, '-', STR_PAD_LEFT) . '-';
	}
	echo "\n";

	foreach ($labels_to_index as $label_i => $i)
	{
		echo str_pad($label_i, 4, ' ', STR_PAD_LEFT) . '|';

		foreach ($labels_to_index as $label_j => $j)
		{
			if (isset($D[$i][$j]))
			{
				echo str_pad($D[$i][$j], 4, ' ', STR_PAD_LEFT) . ' ';
			}
			else
			{
				echo "   . ";
			}
		}
	
		echo "\n";
	}
}

//----------------------------------------------------------------------------------------
// Compute the NJ tree, return ancestor function and branch lengths
function nj($D, $labels, $labels_to_index, $debug = false)
{
	$result = null;
	
	$anc_func = array();
	$brlen = array();

	$n = count($labels_to_index);
	
	$node_counter = count($labels);
		
	while ($n > 2)
	{
	
		// Divergence
		$divergence = array();
		foreach($labels_to_index as $label_i => $i)
		{
			$divergence[$i] = 0;
			foreach($labels_to_index as $label_j => $j)
			{	
				$divergence[$i] += $D[$i][$j];
			}
		}

		if ($debug)
		{
			echo "\nDivergence\n";
			print_r($divergence);
		}

		// Q matrix
		$Q = array();

		$min_ij = array();
		$minQ = PHP_INT_MAX;
	
		foreach($labels_to_index as $label_i => $i)
		{
			$Q[$i] = array();
			foreach($labels_to_index as $label_j => $j)
			{
				if ($i == $j)
				{
					$Q[$i][$j] = 0;
				}
				else
				{		
					$Q[$i][$j] = ($n - 2) * $D[$i][$j] - $divergence[$i] - $divergence[$j];
			
					if ($Q[$i][$j] < $minQ)
					{
						$min_ij = array($i, $j);
						$minQ = $Q[$i][$j];
					}
				}
			}
		}
	
		if ($debug)
		{
			echo "\nQ\n";
			display_matrix($Q, $labels_to_index);

			echo "\nNodes to merge\n";
			print_r($min_ij);
			echo $labels[$min_ij[0]] . " + " . $labels[$min_ij[1]] . "\n";
		}

		// create a new entry in the distance matrix for our new node
		$new_node = ++$node_counter;
		$new_node_label = $new_node;	
	
		// tree
		$anc_func[$labels[$min_ij[0]]] = $new_node_label;
		$anc_func[$labels[$min_ij[1]]] = $new_node_label;
	
		if ($debug)
		{
			echo "n=$n\n";
		}
	
		$other = '';
		// if we are down to three nodes then are done and need to create "root"
		if ($n == 3)
		{
			$other = '';
			foreach ($labels_to_index as $label_i => $i)
			{
				if ($labels[$min_ij[0]] != $label_i && $labels[$min_ij[1]] != $label_i)
				{
					$other = $label_i;
				}
			}		
			$anc_func[$other] = $new_node_label;
		}
	
		// branch lengths
		$brlen[$labels[$min_ij[0]]] = 0.5 * $D[$min_ij[0]][$min_ij[1]] 
			+ 1/(2 * ($n - 2)) * ($divergence[$min_ij[0]] - $divergence[$min_ij[1]]);
	
		$brlen[$labels[$min_ij[1]]] = $D[$min_ij[0]][$min_ij[1]] - $brlen[$labels[$min_ij[0]]];
	
		if ($n == 3)
		{
			$brlen[$other] = $D[$min_ij[0]][$labels_to_index[$other]] - $brlen[$labels[$min_ij[0]]];	
		}
	
		// update D
		$D[$new_node] = array();
		$D[$new_node][$new_node] = 0;

		foreach($labels_to_index as $label_i => $i)
		{
			if ($i != $min_ij[0] && $i != $min_ij[1])
			{
				$D[$new_node][$i] = ($D[$min_ij[0]][$i] + $D[$min_ij[1]][$i] - $D[$min_ij[0]][$min_ij[1]])/2;
				$D[$i][$new_node] = $D[$new_node][$i];
			}
		}

		// Add new node to list of labels 	
		$labels_to_index[$new_node_label] = $new_node;
		$labels[$new_node] = $new_node_label;
		
		// remove labels merged at this step so we no longer visit them when
		// computing distances, etc.
		unset($labels_to_index[$labels[$min_ij[0]]]);
		unset($labels_to_index[$labels[$min_ij[1]]]);
	
		if ($debug)
		{
			echo "\nlabels_to_index\n";
			print_r($labels_to_index);
		}
	
		$n = count($labels_to_index);
	
		if ($debug)
		{
			// display updated distance matrix (with clustered nodes removed)
			echo "\nUpdated distance matrix\n";
			display_matrix($D, $labels_to_index);
		}
	
		if ($debug)
		{
			echo "\nBranch lengths\n";
			foreach ($brlen as $label => $length)
			{
				echo $label . ' ' . $length . "\n";
			}
	
			echo "\nTree\n";
			foreach ($anc_func as $label => $label_anc)
			{
				echo $label . ' ' . $label_anc . "\n";
			}	
	
			if ($debug)
			{
				echo "\n*** round ***\n\n";
			}
		}
	}

	$result = new stdclass;
	$result->anc_func = $anc_func;
	$result->brlen = $brlen;
	
	return $result;	
}	

//----------------------------------------------------------------------------------------
// Convert nj datastructure to an unrooted tree
function toNewick($nj_data)
{
	// create newick tree
	$t = new Tree();
	$root = NULL;

	$label_node_map = array();

	foreach ($nj_data->anc_func as $label => $label_anc)
	{
		if (!isset($label_node_map[$label]))
		{
			$label_node_map[$label] = $t->NewNode($label);				
		}
		$node = $label_node_map[$label];
		$node->SetAttribute('edge_length', number_format($nj_data->brlen[$label], 5));

		if (!isset($label_node_map[$label_anc]))
		{
			$label_node_map[$label_anc] = $t->NewNode($label_anc);
		}
		$anc = $label_node_map[$label_anc];
		
		if (!$root)
		{
			$root = $anc;
		}
		else
		{
			if ($node === $root)
			{
				$root = $anc;
			}
		}	
		$node->SetAncestor($anc);
	
		$child = $anc->GetChild();
	
		if ($child)
		{
			$add_here = $anc->GetChild()->GetRightMostSibling();
			$add_here->SetSibling($node);
		}
		else
		{
			$anc->SetChild($node);
		}
	}

	$t->SetHasBranchLengths(true);
	$t->SetRoot($root);

	return $t;
}

//----------------------------------------------------------------------------------------
// encapsulate getting a tree
function search_result_to_nj($obj, $reroot = true)
{
	$sequences = $obj->hits;
	
	// decode embedding
	foreach ($sequences as $index => $seq)
	{
		$sequences[$index]->embedding = json_decode($seq->embedding);
	}	
		
	// get the labels
	$labels = array();
	foreach ($sequences as $seq)
	{
		$labels[] = $seq->processid;
	}
	
	// make a mapping between labels and their index
	$labels_to_index = array();
	$count = 0;
	foreach ($labels as $label)
	{
		$labels_to_index[$label] = $count++;
	}

	$D = compute_distances($sequences);

	//display_matrix($D, $labels_to_index);

	// create newick tree
	$nj_data = nj($D, $labels, $labels_to_index);
	$t = toNewick($nj_data);

	//echo $t->WriteNewick() . "\n";
	
	if ($reroot)
	{
		$midpoint = $t->midpoint();

		if ($midpoint)
		{
			// echo "Outgroup=" . $midpoint->outgroup->GetLabel() . "\n";
			$t->reRoot($midpoint->outgroup);

			$t->splitRoot($midpoint->outgroup, $midpoint->outgroup_edge, $midpoint->ingroup_edge);
		}
		
	}
	return $t;
}

//----------------------------------------------------------------------------------------
// get distance matrix and describe it
function search_result_to_distances($obj)
{
	$sequences = $obj->hits;
	
	// decode embedding
	foreach ($sequences as $index => $seq)
	{
		$sequences[$index]->embedding = json_decode($seq->embedding);
	}	
		
	// get the labels
	$labels = array();
	foreach ($sequences as $seq)
	{
		$labels[] = $seq->processid;
	}
	
	// make a mapping between labels and their index
	$labels_to_index = array();
	$count = 0;
	foreach ($labels as $label)
	{
		$labels_to_index[$label] = $count++;
	}

	$D = compute_distances($sequences);

	//display_matrix($D, $labels_to_index);
	
	return $D;
}


//----------------------------------------------------------------------------------------
// initialise



if (0)
{
	$debug = true;
	$filename = 'OQ117897.json';
	//$filename = 'KX281828.json';

	$json = file_get_contents($filename);
	$obj = json_decode($json);

	$t = search_result_to_nj($obj);
	
	echo $t->WriteNewick() . "\n";
}

if (0)
{
	// example from Wikipedia
	
	$labels = array('a','b','c','d','e');

	$node_counter = count($labels);

	// make a mapping between labels and their index
	$labels_to_index = array();
	$count = 0;
	foreach ($labels as $label)
	{
		$labels_to_index[$label] = $count++;
	}

	// example distance matrix
	$D = array();
	$D[] = array(0,	5,	9,	9,	8);
	$D[] = array(5,	0,	10,	10,	9);
	$D[] = array(9, 10,	0,	8,	7);
	$D[] = array(9,	10,	8,	0,	3);
	$D[] = array(8,	9,	7,	3,	0);
}


?>
