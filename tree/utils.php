<?php

// Utility functions
require_once(dirname(__FILE__) . '/node_iterator.php');
require_once(dirname(__FILE__) . '/inorder_iterator.php');

//----------------------------------------------------------------------------------------
// Get children of node q as an array of nodes
function get_children($q)
{
	$children = array();
	
	$p = $q->GetChild();
	while ($p)
	{
		$children[] = $p;
		$p = $p->GetSibling();
	}
	
	return $children;
}


//----------------------------------------------------------------------------------------
// For internal nodes that lack a nice name, create one
function generate_internal_labels($t)
{
	$n = new PreorderIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
	
		if (!$q->IsLeaf())
		{
			$subtree = get_subtree_circuit($q);
			
			$label = $q->GetLabel();
			
			if ($label == '' || preg_match('/^_/', $label))
			{
				$subtree = get_subtree_circuit($q);
				
				$parts_left = preg_split("/[\s|_]/", $subtree->leftmost->GetLabel());
				$parts_right = preg_split("/[\s|_]/", $subtree->rightmost->GetLabel());
				
				if ($parts_left[0] == $parts_right[0])
				{
					$label = $parts_left[0] . '_' . $q->GetAttribute('weight');
				}
				else
				{
					$label = $parts_left[0] . '_' . $parts_right[0]. '_' . $q->GetAttribute('weight');
				}
				
				//$label = $subtree->leftmost->GetLabel() . ' ... ' . $subtree->rightmost->GetLabel();
				
				// echo $label . "\n";
				
				$q->SetLabel($label);
			}
		}

		$q = $n->Next();
	}

}

//----------------------------------------------------------------------------------------
// Get left and right span of a subtree rooted at $p
function get_subtree_circuit ($p)
{
	$subtree = new stdclass;
	$subtree->root = $p;
	
	// span of subtree
	$subtree->leftmost 	= null;
	$subtree->rightmost = null;
	

	// go up subtree to left-most descendant 
	$q = $p->GetChild();
	while ($q)
	{
		$subtree->leftmost = $q;		
		$q = $q->GetChild();
	}
	
	// now go to right most sibling	
	$q = $p->GetChild()->GetRightMostSibling();
	while ($q)
	{		
		$subtree->rightmost = $q;
		$q = $q->GetChild();
		if ($q)
		{
			$q = $q->GetRightMostSibling();
		}
	}
	
	return $subtree;
}

//----------------------------------------------------------------------------------------
// Score of node is its "weight" (number of leaves in subtree rooted on this node)
function weight_score($q)
{
	return $q->GetAttribute('weight');
}

//----------------------------------------------------------------------------------------
// Score is info score (add reference for this)
function info_score ($q)
{
	$children = array();
	
	$p = $q->GetChild();
	while ($p)
	{
		$children[] = $p;
		$p = $p->GetSibling();
	}
	
	$sum_lengths = 0;
	$sum_weights = 0;
	$product_weights = 1;
	
	foreach ($children as $p)
	{
		$sum_lengths  += $p->GetAttribute('edge_length') * $p->GetAttribute('weight');
		
		$sum_weights += $p->GetAttribute('weight');
		
		$product_weights *= $p->GetAttribute('weight');
	}
	
	$infosave = $sum_lengths / $sum_weights;
	
	$mk = $product_weights * $infosave;

	return $mk;
}

//----------------------------------------------------------------------------------------
// Compite score for node
function score_node($q)
{
	if (1)
	{
		$score = info_score($q);
	}
	else
	{
		$score = weight_score($q);
	}
	
	return $score;
}

//----------------------------------------------------------------------------------------
// Store ordering of nodes for an INORDER traversal of tree
//function get_inorder($t, &$order_to_label, &$order_to_node)
function get_inorder($t, &$order_to_node)
{
	$count = 0;
	$ni = new BinaryInorderIterator ($t->GetRoot());
	$q = $ni->Begin();
	while ($q != NULL)
	{		
		$label = $q->GetLabel();
		//$order_to_label[$count] = $label;
		$order_to_node[$count] = $q;
		
		$q->SetAttribute('order', $count);
	
		$count++;
		$q = $ni->Next();
	}
}	

//----------------------------------------------------------------------------------------
// Clear an "marked" flags
function clear_marks(&$t)
{
	$n = new PreorderIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
		$q->SetAttribute('marked', false);
		$q = $n->Next();
	}

}

//----------------------------------------------------------------------------------------
// Given a tree where some nodes are marked, extract the subtree corresponding to those 
// marked nodes
// For now code assumes root is marked, and that marked nodes are connected to other
// marked nodes (need proper term for this).
function copy_marked_tree($t)
{
	// copy tree
	$id_to_node = array();
	$copy_of_tree = new Tree();

 	$stack = array();
 	$cur = $t->GetRoot();
 	
	while ($cur != NULL)
	{
		if ($cur->GetAttribute('marked'))
		{
			//echo "* ";
			
			$label = $cur->GetLabel();
			
			if (0)
			{
				$q = $copy_of_tree->NewNode($label);
			}
			else
			{
			
				// need to preseve node ids across all trees
				// Tree->NewNode will assign a node id by incrementing the node count,
				// but we want to have a consistent way to refer to the same node across 
				// zoom levels.
			
				$q = new Node($label);
				$q->id = $cur->GetId();
				$copy_of_tree->id_to_node_map[$q->id] = $q;
			}
			
			
			$q->original = $cur;
			$q->SetAttribute('xy', $cur->GetAttribute('xy'));
			
			$id_to_node[$q->id] = $q;
			
			// add to tree
			if ($cur->GetAncestor())
			{
				$anc = $cur->GetAncestor();
				$anc_id = $anc->GetId();
				
				$p = $id_to_node[$anc_id];
				if ($p->GetChild())
				{
					$r = $p->GetChild()->GetRightMostSibling();
					$r->SetSibling($q);
				}
				else
				{
					$p->SetChild($q);
					
				}
				$q->SetAncestor($p);
				
			}
			else
			{
				$copy_of_tree->SetRoot($q);
			}			

		}
		//echo $cur->GetLabel() . "<br/>\n";
		
		if ($cur->GetChild() && $cur->GetChild()->GetAttribute('marked'))
		{
			array_push($stack, $cur);
			$cur = $cur->GetChild();
		}
		else
		{
			while (!empty($stack)
				&& ($cur->GetSibling() == NULL))
			{
				$cur = array_pop($stack);
			}
			if (empty($stack))
			{
				$cur = NULL;
			}
			else
			{
				$cur = $cur->GetSibling();
			}
		}
	}
	
	$copy_of_tree->Update();
	
	return $copy_of_tree;

}

//----------------------------------------------------------------------------------------
// Get node "heights" and store in x coordinate. Root has x=0, leaf furthest from root
// has x = limit
function get_node_heights($t, $limit = 400)
{
	$max_path_length = 0.0;		
	$t->GetRoot()->SetAttribute('path_length', $t->GetRoot()->GetAttribute('edge_length'));

	// Get path lengths
	$n = new PreorderIterator ($t->getRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{			
		$d = $q->GetAttribute('edge_length');
		
		/*
		// Avoid negative branch lengths
		if ($d < 0.00001)
		{
			$d = 0.0;
		}
		*/
		
		if ($q != $t->GetRoot())
			$q->SetAttribute('path_length', $q->GetAncestor()->GetAttribute('path_length') + $d);

		$max_path_length = max($max_path_length, $q->GetAttribute('path_length'));
		$q = $n->Next();
	}
	
	// scale to drawing size
	$n = new NodeIterator ($t->getRoot());
	
	$q = $n->Begin();
	while ($q != NULL)
	{
		$pt = array();
		$pt['y'] = 0;
		$pt['x'] = ($q->GetAttribute('path_length') / $max_path_length) * $limit;
		
		$q->SetAttribute('xy', $pt);

		$q = $n->Next();
	}

}


//----------------------------------------------------------------------------------------
// Get max heights of subtree rooted at each node. This enables us to draw
// "closed" subtrees as shapes that cover the full range of node heights
function get_max_subtree_height($t)
{
	$n = new PreorderIterator ($t->GetRoot());
	$q = $n->Begin();
	while ($q != NULL)
	{	
		$pt = $q->GetAttribute('xy');		
		$x = $pt['x'];
		$q->SetAttribute('max_x', $x);

		$q = $n->Next();
	}

	// update each node with max_x of its descendants
	{
		$n = new NodeIterator ($t->GetRoot());
		$q = $n->Begin();
		while ($q != NULL)
		{	
			$anc = $q->GetAncestor();
			if ($anc)
			{
				$anc->SetAttribute('max_x', max($anc->GetAttribute('max_x'), $q->GetAttribute('max_x')));
			}

			$q = $n->Next();
		}
	}
}

//----------------------------------------------------------------------------------------
// Add node q to subtree of informative nodes
function add_children_to_subtree(&$subtree, $q, $is_root = false)
{
	// Add root to subtree, in any subsequent call $q will already be in the subtree
	if ($is_root)
	{
		$subtree[] = $q->id;
		$q->SetAttribute('marked', true);
	}
	
	// add children
	$children = get_children($q);
	
	foreach ($children as $child)
	{
		$subtree[] = $child->id;
		$child->SetAttribute('marked', true);
	}
	
	/*
	echo '<pre>';
	echo "Subtree\n";
	print_r($subtree);
	echo '</pre>';
	*/
	
}



?>