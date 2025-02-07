<?php

require_once('tree/tree.php');
require_once('tree/tree-parse.php');
require_once('tree/utils.php');


//----------------------------------------------------------------------------------------
// sort
function cmp($a, $b) {
	
    if ($a->span[1] == $b->span[1]) {
        return 0;
    }
    return ($a->span[1]< $b->span[1]) ? -1 : 1;
}

//----------------------------------------------------------------------------------------
function label_internal_nodes($doc, $newick, $debug = false)
{
	// the tree
	$t = parse_newick($newick);

	// the classication associated with each node (if known)
	$lineages = array();
	foreach ($doc->hits as $hit)
	{
		if (isset($hit->lineage))
		{
			$lineages[$hit->processid] = $hit->lineage;
		}
	}
	
	if ($debug)
	{
		print_r($lineages);
	}
	
	// Associate lineages with leaf nodes, and initialise internal nodes.
	// By default every internal node is [], as is any leaf without a classification
	$node_classification = array();
	
	$n = new NodeIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{
		$node_classification[$q->GetId()] = array();
	
		if ($q->IsLeaf())
		{
			if (isset($lineages[$q->GetLabel()]))
			{
				$node_classification[$q->GetId()] = $lineages[$q->GetLabel()];
			}
		}
		$q = $n->Next();
	}
	
	// post order traverse to assign classification sets to internal nodes
	$q = $n->Begin();
	while ($q != NULL)
	{
		$anc = $q->GetAncestor();
		if ($anc)
		{		
			if (1)
			{
				// simple intersection to get LCA
				// first time visit?
				if (count($node_classification[$anc->GetId()]) == 0)
				{
					// start with $q's classification set
					$node_classification[$anc->GetId()] = $node_classification[$q->GetId()];
				}
				else
				{
					$node_classification[$anc->GetId()] = array_intersect($node_classification[$anc->GetId()], $node_classification[$q->GetId()]);
				}
			}	
			
		}
	
		$q = $n->Next();
	}
	
	// generate labels using the most terminal of labels in the classification
	$q = $n->Begin();
	while ($q != NULL)
	{
		if (!$q->IsLeaf())
		{
			if (count($node_classification[$q->GetId()]) > 0)
			{
				$q->SetLabel(array_pop($node_classification[$q->GetId()]));
			}
		}
	
		$q = $n->Next();
	}	
	
	// remove redundant labels so that no node has the same label as its ancestor
	$q = $n->Begin();
	while ($q != NULL)
	{
		$anc = $q->GetAncestor();
		if ($anc)
		{
			if (strcmp($q->GetLabel(), $anc->GetLabel()) == 0)
			{
				$q->SetLabel($q->GetId());
				$q->SetLabel("");
			}
		}
		$q = $n->Next();
	}	
	
	if ($debug)
	{
		echo $t->WriteNewick() . "\n";
	}
	
	return $t;

}


//----------------------------------------------------------------------------------------
// Get disjoint internal labels
//
// https://en.wikipedia.org/wiki/Maximum_disjoint_set
// https://www.geeksforgeeks.org/maximal-disjoint-intervals/
// Agarwal, P. K.; Van Kreveld, M.; Suri, S. (1998). "Label placement by maximum 
// independent set in rectangles". Computational Geometry. 11 (3–4): 209. 
// doi:10.1016/s0925-7721(98)00028-5. hdl:1874/18908.
function disjoint_internal_labels($t, $debug = false)
{
	// get leaf order 0,..., n and assign to each internal node the left and right
	// span of the leaves descendant from that node
	$leaf_order = 0;
	$leaf_list = array();
	
	$n = new NodeIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
		$anc = $q->GetAncestor();
		if ($anc)
		{
			if ($q->IsLeaf())
			{
				$leaf_list[$leaf_order] = $q->GetId();
				
				$q->SetAttribute('left', $leaf_order);
				$q->SetAttribute('right', $leaf_order);
			}
						
			$l = $anc->GetAttribute('left');
			if (isset($l))
			{
				$anc->SetAttribute('left', min($l, $q->GetAttribute('left')));
			}
			else
			{
				$anc->SetAttribute('left', $q->GetAttribute('left'));
			}
			
			$r = $anc->GetAttribute('right');
			if (isset($r))
			{
				$anc->SetAttribute('right', max($r, $q->GetAttribute('right')));
			}
			else
			{
				$anc->SetAttribute('right', $q->GetAttribute('right'));
			}
			
			if ($q->IsLeaf())
			{
				$leaf_order++;
			}			
			
		}
		$q = $n->Next();
	}
	
	// For each labelled internal node get span of descendants
	$intervals = array();
	
	$q = $n->Begin();
	while ($q != NULL)
	{
		if (!$q->IsLeaf())
		{
			if ($q->GetLabel() != '')
			{
				$span = new stdclass;
				$span->id = $q->GetId();
				$span->label = $q->GetLabel();
				$span->span = array(
					(Integer)$q->GetAttribute('left'),
					(Integer)$q->GetAttribute('right'),
				);
				
				$intervals[] = $span;
			}
		}
		$q = $n->Next();
	}	
	
	if ($debug)
	{
		print_r($intervals);
	}
	
	// Sort intervals
	uasort($intervals, 'cmp');
	
	$disjoint = array();
	$disjoint[] = array_shift($intervals);
	$endpoint = $disjoint[0]->span[1];
	
	foreach ($intervals as $interval)
	{
		if ($interval->span[0] > $endpoint)
		{
			$disjoint[] = $interval;			
			$endpoint = $interval->span[1];
		}
	}

	if ($debug)
	{
		echo "Disjoint\n";
		print_r($disjoint);	
	}
	
	// data structure we can use to label the tree
	$decoration = array();
	
	foreach ($disjoint as $partition)
	{
		$start_leaf_index = $partition->span[0];
		
		$q = $t->GetNodeFromId($leaf_list[$start_leaf_index]);
		
		// set has label to display, id is first leaf in set, span is how many leaves
		// are in the set
		$part = new stdclass;
		$part->id = $q->GetLabel();
		$part->label = $partition->label;
		$part->span = $partition->span[1] - $partition->span[0] + 1;
		
		$decoration [$part->id] = $part;
	}
	
	if ($debug)
	{
		print_r($decoration );	
	}
	
	return $decoration ;
}


/*


//echo $t->WriteNewick() . "\n";

$filename = 'CRBAC20268-22.json';

$json = file_get_contents($filename);
$doc = json_decode($json);

$t = label_internal_nodes($doc);
disjoint_internal_labels($t);
*/

?>
