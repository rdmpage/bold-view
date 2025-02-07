<?php

require_once(dirname(__FILE__) . '/node.php');
require_once(dirname(__FILE__) . '/node_iterator.php');

define('CHILD', 0);
define ('SIB', 1);

//----------------------------------------------------------------------------------------
function write_nexus_label($label)
{
	if (preg_match('/^(\w|\d)+$/', $label))
	{
	}
	else
	{
		str_replace ("'", "\'", $label);
		$label = "'" . $label . "'";
	}
	return $label;
}

//----------------------------------------------------------------------------------------
/**
 *
 *
 */
class Tree
{
	var $root;
	var $num_nodes;
	var $id_to_node_map = array();	
	var $num_leaves;
	var $rooted = true;
	var $has_edge_lengths = false;

	//------------------------------------------------------------------------------------
	function __construct()
	{
		$this->root = NULL;;
		$this->num_nodes = 0;
		$this->num_leaves = 0;
	}	
	
	//------------------------------------------------------------------------------------
	function GetNodeFromId($id)
	{
		if (isset($this->id_to_node_map[$id]))
		{
			return $this->id_to_node_map[$id];
		}
		else
		{
			return null;
		}	
	}
	
	//------------------------------------------------------------------------------------
	function GetNumLeaves() { return $this->num_leaves; }

	//------------------------------------------------------------------------------------
	function GetNumNodes() { return $this->num_nodes; }
		
	//------------------------------------------------------------------------------------
	function GetRoot() { return $this->root; }

	//------------------------------------------------------------------------------------
	function HasBranchLengths() { return $this->has_edge_lengths; }

	//------------------------------------------------------------------------------------
	function SetHasBranchLengths($flag) { $this->has_edge_lengths = $flag; }

	//------------------------------------------------------------------------------------
	function IsRooted() { return $this->rooted; }
	
	//------------------------------------------------------------------------------------
	function SetRoot($root)
	{
		$this->root = $root;
	}
	
	//------------------------------------------------------------------------------------
	function NewNode($label = '')
	{
		$node = new Node($label);
		$node->id = $this->num_nodes++;
		$this->id_to_node_map[$node->id] = $node;

		return $node;
	}
		
	//------------------------------------------------------------------------------------
	function Dump()
	{
		echo "Num leaves = " . $this->num_leaves . "\n";
		
		$n = new NodeIterator ($this->root);
		$a = $n->Begin();
		while ($a != NULL)
		{
			//echo "Node=\n:";
			$a->Dump();
			$a = $n->Next();
		}
	}
	
	//------------------------------------------------------------------------------------
	function WriteDot()
	{
		$dot = "digraph{\n";
		
		
		// output nodes
		foreach ($this->id_to_node_map as $q)
		{
			$dot .= "node [label=\"";
			
			$label = $q->GetLabel();
			if ($label == "")
			{
				$label = $q->GetAttribute('taxon');
			}
			
			$dot .= addcslashes($label, '"') . "\"";
						
			$dot .= "] n" . $q->GetId() . ";\n";
		
		}
		
		
		// output edges
		$n = new NodeIterator ($this->root);
		$a = $n->Begin();
		while ($a != NULL)
		{
			if ($a->GetAncestor())
			{
				$dot .= "n" . $a->GetAncestor()->GetId() . " -> n" . $a->GetId() . ";\n";
			}
			$a = $n->Next();
		}
		$dot .= "}\n";
		return $dot;
	}
		
	//------------------------------------------------------------------------------------
	function WriteNewick()
	{
		$newick = '';
		
		$stack = array();
		$curnode = $this->root;
		
		while ($curnode != NULL)
		{	
			if ($curnode->GetChild())
			{
				$newick .= '(';
				$stack[] = $curnode;
				$curnode = $curnode->GetChild();
			}
			else
			{
				$newick .= write_nexus_label($curnode->GetLabel());
				
				$length = $curnode->GetAttribute('edge_length');
				if ($length != '')
				{
					$newick .= ':' . $length;
				}
											
				while (!empty($stack) && ($curnode->GetSibling() == NULL))
				{
					$newick .= ')';
					$curnode = array_pop($stack);
					
					// Write internal node
					if ($curnode->GetLabel() != '')
					{
						$newick .= write_nexus_label($curnode->GetLabel());
					}
					$length = $curnode->GetAttribute('edge_length');
					if ($length != '')
					{
						$newick .= ':' . $length;
					}					

				}
				if (empty($stack))
				{
					$curnode = NULL;
				}
				else
				{
					$newick .= ',';
					$curnode = $curnode->GetSibling();
				}
			}		
		}
		$newick .= ";";
		return $newick;
	}	
			
	
	//------------------------------------------------------------------------------------
	// Build weights
	function BuildWeights($p)
	{
		if ($p)
		{
			$p->SetAttribute('weight', 0);
			
			$this->BuildWeights($p->GetChild());
			$this->BuildWeights($p->GetSibling());
			
			if ($p->Isleaf())
			{
				$p->SetAttribute('weight', 1);
			}
			if ($p->GetAncestor())
			{
				$p->GetAncestor()->AddWeight($p->GetAttribute('weight'));
			}
		}
	}
	
	//------------------------------------------------------------------------------------
	// Traverse tree and update leaf count, weights
	function Update()
	{
		$this->num_leaves = 0;
		$this->num_nodes = 0;
		
		$this->updateTraverse($this->root);

	}	
	
	//------------------------------------------------------------------------------------
	function updateTraverse($p)
	{
		if ($p)
		{
			$this->num_nodes++;
			
			$p->SetAttribute('weight', 0);
			
			$this->updateTraverse($p->GetChild());
			$this->updateTraverse($p->GetSibling());
			
			if ($p->Isleaf())
			{
				$p->SetAttribute('weight', 1);
				$this->num_leaves++;
			}
			if ($p->GetAncestor())
			{
				$p->GetAncestor()->AddWeight($p->GetAttribute('weight'));
			}
		}
	}	
	
	//------------------------------------------------------------------------------------
	function markPath($p)
	{
		$q = $p;
		while ($q)
		{
			$q->SetAttribute('marked', true);
			$q = $q->GetAncestor();
		}
	}
	
	//------------------------------------------------------------------------------------
	function unMarkPath($p)
	{
		$q = $p;
		while ($q)
		{
			$q->SetAttribute('marked', false);
			$q = $q->GetAncestor();
		}
	}
	
	//------------------------------------------------------------------------------------
	// Remove immediate descendant $q of node $p
	// For example, if one of the children is the outgroup, we may want to remove that
	// child
	function exciseDescendant($p, &$q)
	{
		$r = $p->GetChild();
		if ($r === $q)
		{
			// next node is the child of current node, excise it by making its sibling
			// the child of the current node 
			$p->SetChild($r->GetSibling());
			$r->SetSibling(null);		
		}
		else
		{
			// next node is a sibling of p's child
			$previous_r = $r;
			$r = $r->GetSibling();
			$done = false;
			while($r && !$done)
			{
				if ($r === $q)
				{
					// we have found the next node, so we excise it from sibling list
					$previous_r->SetSibling($r->GetSibling());
					$r->SetSibling(null);
					$done = true;
				}
				else
				{
					// Still haven't found next node, move to next sibling
					$previous_r = $r;
					$r = $r->GetSibling();
				}
			}
		}
		$q->SetAncestor(null);
		return $r;
	}
	
	//------------------------------------------------------------------------------------
	function reRoot($outgroup)	
	{		
		if (!$outgroup || $outgroup === $this->root)
		{
			// No change in tree
			return;
		}
	
		if ($outgroup->GetAncestor() === $this->root)
		{
			// If outgroup ancestor is the current root then there is no change in topology, 
			return;
		}
	
		// OK, we need to alter tree topology
	
		// Make a list in reverse order of nodes below the outgroup's ancestor. This "tail"
		// are all the nodes that we will have to move. 
		// $tail[0] in this list is the current root of the tree.
		$tail = array();
		$q = $outgroup->GetAncestor();
		while ($q)
		{
			array_unshift($tail, $q);
			$q = $q->GetAncestor();
		}

		// Visit tail nodes and make each one a tree in a forest
		$num_nodes = count($tail);

		// Save current root label
		$root_label = $this->root->GetLabel();
		
		for ($i = 0; $i < $num_nodes - 1; $i++)
		{
			$p = $tail[$i]; 	// current node
			$q = $tail[$i + 1];	// node above current node in the tail
	
			$this->exciseDescendant($p, $q);
	
			// In the rerooted tree these attributes will need to move "up" one.
	
			// "flip" the edge length
			if ($q->GetAttribute('edge_length'))
			{
				$p->SetAttribute('edge_length', $q->GetAttribute('edge_length'));
			}
	
			// "flip" the label
			if ($q->GetLabel())
			{
				$p->SetLabel($q->GetLabel());
			}
	
		}

		// Graft each node in the forest back onto the tree in their new positions
		// To do: decide how to handle root (binary or tifurcation?)
		$there = $outgroup->GetRightMostSibling();

		for ($i = $num_nodes - 2; $i >= 0; $i--)
		{	
			$p = $tail[$i];
			$there->SetSibling($p);
			$p->SetAncestor($there->GetAncestor());	
			$there = $p->GetChild()->GetRightMostSibling();
		}

		// Update the new root
		$this->root = $outgroup->GetAncestor();
		if ($this->root->GetAttribute('edge_length'))
		{
			$this->root->SetAttribute('edge_length', 0);
		}
		$this->root->SetLabel($root_label);

	}
	
	//------------------------------------------------------------------------------------
	// If the root is not binary then split it. We can supply the node that we want to be
	// on one side of the binary split
	function splitRoot($split_node = null, $outgroup_edge = -1, $ingroup_edge = -1)
	{
		if ($this->GetRoot()->GetDegree() != 2)
		{
			if (!$split_node)
			{
				$split_node = $this->GetRoot()->GetChild()->GetRightMostSibling();
			}
			$this->exciseDescendant($this->GetRoot(), $split_node);
		
			$extra = $this->NewNode();
			$extra->SetLabel("extra");
			$extra->SetChild($this->GetRoot()->GetChild());
			$extra->GetChild()->SetAncestor($extra);
			$extra->SetSibling($split_node);
			$split_node->SetAncestor($extra);
			
			$this->GetRoot()->SetChild($extra);
			$this->GetRoot()->GetChild()->SetAncestor($this->GetRoot());
			
			if ($outgroup_edge != -1 && $ingroup_edge != -1)
			{
				$split_node->SetAttribute('edge_length', $outgroup_edge);
				$extra->SetAttribute('edge_length', $ingroup_edge);
			}
			
		}
	}
	
	//------------------------------------------------------------------------------------
	// Find the midpoint where we can root this tree. Requires tree to have branch lengths
	function midpoint()
	{
		$result = null;
		
		if (!$this->HasBranchLengths())
		{
			return $result;
		}
		
		$result = new stdclass;
		
		$outgroup = null;
		$ingroup_edge = $outgroup_edge  = -1;
	
		$leaf_list = array();
	
		$n = new NodeIterator ($this->root);
		$q = $n->Begin();
		while ($q != NULL)
		{
			if ($q->IsLeaf())
			{
				$leaf_list[] = $q;
			}
			$q = $n->Next();
		}
	
		$max_pairwise = 0.0;
	
		$from = $to = null;
		
		$result->pair = array();
	
		// make all pairwise comparisons, store largest distance
		$n = count($leaf_list);
		for ($i = 1; $i < $n; $i++)
		{
			for ($j = 0; $j < $i; $j++)
			{
				$p = $leaf_list[$i];
				$q = $leaf_list[$j];
			
				$this->markPath($p);
			
				$sum = 0.0;
				while ($q && !$q->GetAttribute('marked'))
				{
					$sum += $q->GetAttribute('edge_length');
					$q = $q->GetAncestor();
				}
			
				while ($p != $q)
				{
					$sum += $p->GetAttribute('edge_length');
					$p = $p->GetAncestor();			
				}
			
				$this->unMarkPath($leaf_list[$i]);
			
				if ($sum > $max_pairwise)
				{
					$from = $leaf_list[$i];
					$to = $leaf_list[$j];
					$max_pairwise = $sum;
					
					// Store pair, helpful for debugging
					$result->pair = array($from->GetLabel(), $to->GetLabel());
					$result->d = $max_pairwise;
				}			
			}	
		}
	
		// ok now we need to know where this split is in the tree
		
		// our target is this distance
		$half = $max_pairwise/2.0;
		$path1 = $path2 = 0.0;
	
		// go down tree starting at $from and get path length until we hit the
		// path from "to" i.e., we reach LCA(from, to), we hit the root, or we our path 
		// > $half
		$this->markPath($to);
		while ($from && !$from->GetAttribute('marked') && ($path1 < $half))
		{
			//echo $from->GetAttribute('edge_length') . "\n";
		
			$path2 = $path1;
			$path1 += $from->GetAttribute('edge_length');
			$outgroup = $from;
			$from = $from->GetAncestor();
			
			//echo "(path1=$path1)\n";
		}
		
		//echo "\npath1=$path1\n";
	
		// if baled before reaching halfway between the two nodes then midpoint is on other path
		if ($path1 < $half)
		{
			//echo "Need to try other way\n";
		
			$path1 = $path2 = 0.0;
			
			$this->unMarkPath($to);
			$this->markPath($from);
			
			while ($to && !$to->GetAttribute('marked') && ($path1 < $half))
			{
				$path2 = $path1;
				$path1 += $to->GetAttribute('edge_length');
				$outgroup = $to;
				$to = $to->GetAncestor();
			}
			
			$this->unMarkPath($from);	
		}
	
		// work out where along the edge we need to split it
		$extra = $path1 - $half;
		$outgroup_edge = $path1 - $path2 - $extra;
		$ingroup_edge = $extra;
	
		// Details of where to reroot		
		$result->outgroup = $outgroup;
		$result->outgroup_edge = $outgroup_edge;
		$result->ingroup_edge = $ingroup_edge;
		
		return $result;

	}	

}

?>