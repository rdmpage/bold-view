<?php

require_once (dirname(__FILE__) . '/database-tree.php');
require_once (dirname(__FILE__) . '/pq.php');

//----------------------------------------------------------------------------------------
// We create a simple graph to hold the nodes and edges of the summary tree
// graph that is our summary tree
// Node key is node id, value is label for node, edges are k, v pair of node id for
// node (k) and node id for its ancestor (v). Hence the graph is directed with the
// root as "sink"

class SummaryTree
{
	var $dbtree;
	var $k;
	var $subtree_id;
	
	var $nodes = array();
	var $edges = array();
	var $other = array();

	//------------------------------------------------------------------------------------
	function __construct($dbtree)
	{
		$this->dbtree = $dbtree;
	}

	//------------------------------------------------------------------------------------
	function get_edges()
	{
		return $this->edges;
	}

	//------------------------------------------------------------------------------------
	function get_nodes()
	{
		return $this->nodes;
	}

	//------------------------------------------------------------------------------------
	function get_others()
	{
		return $this->other;
	}

	//------------------------------------------------------------------------------------
	function summarise($subtree_id, $k)
	{
		// to do: ensure k is sane!
		
		global $queue;
		
		$this->subtree_id = $subtree_id;
		
		$this->nodes = array();
		$this->edges = array();
		$this->other = array();
		
		$queue = array();
		
		$node = $this->dbtree->get_node($this->subtree_id);
	
		// Add root to the queue
		en_queue($queue, $node->id, $node->name, $this->dbtree->get_node_score($this->subtree_id));
		
		$summary_tree_size = 0;
		while ($summary_tree_size < $k && count($queue) > 0)
		{
			$current_item = de_queue($queue);
			
			if (count($this->nodes) == 0)
			{
				// just add subtree root to summary tree
				$this->nodes[$current_item->id] = $current_item->name;
			}
			else
			{
				// node in tree
				$node = $this->dbtree->get_node($current_item->id);
			
				$id = $node->id;				
				if (is_object($node->parentTaxon))
				{
					$anc_id = $node->parentTaxon->id;
				}
				else
				{
					$anc_id = $node->parentTaxon;
				}
		
				// add node, and add edge between node and its ancestor
				$this->nodes[$id] = $node->name;
				$this->edges[$id] = $anc_id;
			
				// except for root, curnode will be in the "others" list for its 
				// ancestor, so remove it
				$key = array_search($id, $this->other[$anc_id]);
				unset($this->other[$anc_id][$key]);
			
				// id of node for "others" in the graph
				$other_node_id = 'other_' . $anc_id;
				
				// how many nodes remain in ancestor's "others" list?
				$num_others = count($this->other[$anc_id]);
				
				switch ($num_others)
				{
					case 0:
						// ancestor has single descendant (e.g., monotypic taxon of lower rank)
				
						// remove node for "others" from the graph
						if (isset($this->nodes[$other_node_id]))
						{
							unset($this->edges[$other_node_id]);
							unset($this->nodes[$other_node_id]);
						}
						break;
				
					case 1:
						// either ancestor is binary, or we have added all the other children 
						// of a polytomy

						// remove node for "others" from the graph
						if (isset($this->nodes[$other_node_id]))
						{
							unset($this->edges[$other_node_id]);
							unset($this->nodes[$other_node_id]);
						}
				
						// get last remaining element of "others"
						$last_child_id = array_pop($this->other[$anc_id]);
			
						// remove last node from queue as we are adding it here
						delete_from_queue($queue, $last_child_id);
						
						$last_child = $this->dbtree->get_node($last_child_id);
		
						//echo "last_child_id=$last_child_id \n";
						//dump_queue($queue);
		
						// add last remaining element of "others" to graph
						$this->nodes[$last_child_id] = $last_child->name;
						$this->edges[$last_child_id]  = $anc_id;
			
						// enque any children of last child
						$children = $this->dbtree->get_children($last_child_id);

						$this->other[$last_child_id] = array();

						foreach ($children as $child)
						{
							$this->other[$last_child_id][] = $child->id;	
							en_queue($queue, $child->id, $child->name, $this->dbtree->get_node_score($child->id));
						}
						break;
				
						// n
					default:
						// ensure we have a node in the graph for "others" because we may need it
						if (!isset($this->nodes[$other_node_id]))
						{
							$this->nodes[$other_node_id] = "other_" . $this->nodes[$anc_id];

							// add edge between "others" and ancestor
							$this->edges[$other_node_id] = $anc_id;	
						}		
						break;			
				}
			}				
			
			// If node added has children then add these to "others" for this node,
			// and add them to the queue			
			$children = $this->dbtree->get_children($current_item->id);

			$this->other[$current_item->id] = array();

			foreach ($children as $child)
			{
				$this->other[$current_item->id][] = $child->id;	
				en_queue($queue, $child->id, $child->name, $this->dbtree->get_node_score($child->id));
			}
			
			$summary_tree_size = count($this->nodes);
			
		}
		
		/*
		print_r($nodes);
		print_r($edges);
		
		// Graphviz
		echo "digraph {\n";
		echo "rankdir=RL;\n";
		foreach ($edges as $target => $source)
		{
			echo '"' . $nodes[$target] . '" -> "' . $nodes[$source] . '";' . "\n";
		}
		echo "}\n";
		*/

	
	}

	//------------------------------------------------------------------------------------
	function summarise_pq($subtree_id, $k)
	{
		// to do: ensure k is sane!		
		$this->subtree_id = $subtree_id;
		
		$this->nodes = array();
		$this->edges = array();
		$this->other = array();
		
		$pq = new PQ();
		
		$node = $this->dbtree->get_node($this->subtree_id);
	
		// Add root to the queue		
		$pq->en_queue($node->id, $node->name, $this->dbtree->get_node_score($this->subtree_id));
		
		$summary_tree_size = 0;
		while ($summary_tree_size < $k && $pq->valid())
		{
			$current_item = $pq->de_queue();
			
			if (count($this->nodes) == 0)
			{
				// just add subtree root to summary tree
				$this->nodes[$current_item->id] = $current_item->name;
			}
			else
			{
				// node in tree
				$node = $this->dbtree->get_node($current_item->id);
			
				$id = $node->id;				
				if (is_object($node->parentTaxon))
				{
					$anc_id = $node->parentTaxon->id;
				}
				else
				{
					$anc_id = $node->parentTaxon;
				}
		
				// add node, and add edge between node and its ancestor
				$this->nodes[$id] = $node->name;
				$this->edges[$id] = $anc_id;
			
				// except for root, curnode will be in the "others" list for its 
				// ancestor, so remove it
				$key = array_search($id, $this->other[$anc_id]);
				unset($this->other[$anc_id][$key]);
			
				// id of node for "others" in the graph
				$other_node_id = 'other_' . $anc_id;
				
				// how many nodes remain in ancestor's "others" list?
				$num_others = count($this->other[$anc_id]);
				
				switch ($num_others)
				{
					case 0:
						// ancestor has single descendant (e.g., monotypic taxon of lower rank)
				
						// remove node for "others" from the graph
						if (isset($this->nodes[$other_node_id]))
						{
							unset($this->edges[$other_node_id]);
							unset($this->nodes[$other_node_id]);
						}
						break;
				
					case 1:
						// either ancestor is binary, or we have added all the other children 
						// of a polytomy

						// remove node for "others" from the graph
						if (isset($this->nodes[$other_node_id]))
						{
							unset($this->edges[$other_node_id]);
							unset($this->nodes[$other_node_id]);
						}
				
						// get last remaining element of "others"
						$last_child_id = array_pop($this->other[$anc_id]);
			
						// remove last node from queue as we are adding it here
						$pq->delete_from_queue($last_child_id);
						
						$last_child = $this->dbtree->get_node($last_child_id);
		
						//echo "last_child_id=$last_child_id \n";
						//dump_queue($queue);
		
						// add last remaining element of "others" to graph
						$this->nodes[$last_child_id] = $last_child->name;
						$this->edges[$last_child_id]  = $anc_id;
			
						// enque any children of last child
						$children = $this->dbtree->get_children($last_child_id);

						$this->other[$last_child_id] = array();

						foreach ($children as $child)
						{
							$this->other[$last_child_id][] = $child->id;	
							$pq->en_queue($child->id, $child->name, $this->dbtree->get_node_score($child->id));
						}
						break;
				
						// n
					default:
						// ensure we have a node in the graph for "others" because we may need it
						if (!isset($this->nodes[$other_node_id]))
						{
							$this->nodes[$other_node_id] = "other_" . $this->nodes[$anc_id];

							// add edge between "others" and ancestor
							$this->edges[$other_node_id] = $anc_id;	
						}		
						break;			
				}
			}				
			
			// If node added has children then add these to "others" for this node,
			// and add them to the queue			
			$children = $this->dbtree->get_children($current_item->id);

			$this->other[$current_item->id] = array();

			foreach ($children as $child)
			{
				$this->other[$current_item->id][] = $child->id;	
				$pq->en_queue($child->id, $child->name, $this->dbtree->get_node_score($child->id));
			}
			
			$summary_tree_size = count($this->nodes);
			
		}
		
		/*
		print_r($nodes);
		print_r($edges);
		
		// Graphviz
		echo "digraph {\n";
		echo "rankdir=RL;\n";
		foreach ($edges as $target => $source)
		{
			echo '"' . $nodes[$target] . '" -> "' . $nodes[$source] . '";' . "\n";
		}
		echo "}\n";
		*/

	
	}
	
	//------------------------------------------------------------------------------------
	// to native data structure
	function to_native()
	{
		$result = array();
	
		// store nodes as we create them
		$cache = array();
		
		// makes assumption that edges array is in preorder 
		// (by the way we construct the tree)
		foreach ($this->edges as $target => $source)
		{
			// make sure we have created the source node
			if (!isset($cache[$source]))
			{
				$cache[$source] = $this->dbtree->get_node($source);
			}

			if (!isset($cache[$target]))
			{
				if (preg_match('/^other_/', $target))
				{
					// create a pseudo node for the "others"
					$other_node = new stdclass;
					$other_node->id = $target;					
					$other_node->name = "other " . $cache[$source]->name;					
					
					$cache[$target]	= $other_node;	
				}
				else
				{
					// create target node
					$cache[$target] = $this->dbtree->get_node($target);
				}
			}
			
			if (!isset($cache[$source]->summary))
			{
				$cache[$source]->summary = array();
			}
			if (!isset($cache[$source]->summary[$target]))
			{
				$cache[$source]->summary[$target] = $cache[$target];
			}
		}
		
		
		// others
		if (1)
		{			
		
			// append members of "other" nodes that have made it into this
			// summary tree, so that we can display them if users asks
			foreach ($this->other as $node_id => $members_of_other)
			{
				$other_node_id = "other_" . $node_id;
				
				// only handle "other" nodes that are in summary tree
				if (isset($cache[$other_node_id]))
				{
					foreach ($members_of_other as $member_id)
					{			
						// create node that is an element of "others"
						$cache[$member_id] = $this->dbtree->get_node($member_id);
		
						$member_node = new stdclass;
						$member_node->id = $member_id;
						$member_node->name = $cache[$member_id]->name;	
			
						if (!isset($cache[$other_node_id]->others))
						{
							$cache[$other_node_id]->others = array();
						}
						$cache[$other_node_id]->others[$member_id] = $cache[$member_id];
					}
				}
			}	
		}	
		
		// sort nicely
		foreach ($cache as $id => &$cache_node)
		{
			if (isset($cache[$id]->summary))
			{
				uasort($cache[$id]->summary, 'name_compare');
			}
			
			if (isset($cache[$id]->others))
			{
				uasort($cache[$id]->others, 'name_compare');
			}
			
		}			
			
		
		// root is node in cache with same id as subtree root
		if (isset($cache[$this->subtree_id]->summary))
		{
			$result = $cache[$this->subtree_id]->summary;
		}
			
		
		// root of subtree
		return $result;
	}


}


?>