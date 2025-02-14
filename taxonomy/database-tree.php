<?php

ini_set('memory_limit', '-1');

// class to talk to a SQL database with a taxonomic classification, extend class to
// handle specific databases

require_once (dirname(dirname(__FILE__)) . '/pg.php');


//----------------------------------------------------------------------------------------
// Comparison function
function name_compare($a, $b)
{
	// ensure "other_" nodes appear at the end of the list
	if (preg_match('/other_/', $a->name) || preg_match('/other_/', $b->name))
	{
		if (preg_match('/other_/', $a->name))
		{
			$result = 1;
		}
		else
		{
			$result = -1;
		}
	}
	else
	{
		$result = strnatcmp($a->name, $b->name);
	
		//echo $a->name . "|" . $b->name . "|" . $result . "<br>\n";
	
		if ($result < 0)
		{
			$result = -1;
		}
		if ($result > 0)
		{
			$result = 1;
		}
	}
	return $result;
}


//----------------------------------------------------------------------------------------
class DbTree
{
	var $db = null;
	var $stack = array();
	var $current_node = null;
	
	var $node_cache = array();

	//------------------------------------------------------------------------------------
	function __construct($db, $table = 'tree')
	{
		$this->db = $db;
		$this->table = $table;
	}
	
	//------------------------------------------------------------------------------------
	function do_query($sql)	
	{	
		$result = pg_query($this->db, $sql);
	
		$data = array();
	
		while ($row = pg_fetch_assoc($result))
		{
			$item = new stdclass;
			
			$keys = array_keys($row);
		
			foreach ($keys as $k)
			{
				if ($row[$k] != '')
				{
					$item->{$k} = $row[$k];
				}
			}
		
			$data[] = $item;
		}	
		return $data;	
	
	}

	//------------------------------------------------------------------------------------
	// Find node by name
	function find_node($name)
	{
	
	}

	//------------------------------------------------------------------------------------
	// Get children of node
	function get_children($id)
	{
		$children = [];
			
		$this->child_cache[$id] = array();
	
		// generic SQL
		$sql = "SELECT * FROM " . $this->table . " WHERE anc_id='" . $id . "'";
		
		$data = $this->do_query($sql);
		
		foreach ($data as $row)
		{
			$node = new stdclass;
			$node->id = $row->id;
			
			if (isset($row->anc_id))
			{
				$node->parentTaxon = $row->anc_id;
			}
			
			if (isset($row->name))
			{
				$node->name = $row->name;
			}

			if (isset($row->score))
			{
				$node->score = $row->score;
			}
			
			// cache this node
			if (!isset($this->node_cache[$node->id]))
			{
				$this->node_cache[$node->id] = $node;
			}
			
			$children[$node->id] = $node;
		}
		
		// return array not map (do this to make it easy to treat JSON as JSON-LD...?)
		return array_values($children);
	}
	
	//------------------------------------------------------------------------------------
	// Get node
	function get_node($id)
	{
		if (isset($this->node_cache[$id]))
		{
			// echo "Cache hit<br/>";
			$node = $this->node_cache[$id];
		}
		else
		{		
			// echo "Cache miss<br/>";
			// generic SQL
			$node = new stdclass;
			$node->id = $id;
			$node->name = '';
	
			$sql = "SELECT * FROM " . $this->table . " WHERE id='" . $id . "' LIMIT 1";
			
			$data = $this->do_query($sql);
			
			foreach ($data as $row)
			{
				if (isset($row->anc_id))
				{
					$node->parentTaxon = $row->anc_id;
				}
	
				if (isset($row->name))
				{
					$node->name = $row->name;
				}

				if (isset($row->score))
				{
					$node->score = $row->score;
				}
				
				$this->node_cache[$id] = $node;
				
			}
			
		}
					
		return $node;	
	}
	
	//------------------------------------------------------------------------------------
	// Get a node and add children and path to root
	function get_node_context($id)
	{
		$node = $this->get_node($id);
		$children = $this->get_children($id);

		foreach ($children as $child)
		{
			$node->children[] = $child;
		}
		
		if (isset($node->children))
		{
			uasort($node->children, 'name_compare');
		}

		$path = $this->get_path_to_root($id);
		$node->path = $path;
		
		return $node;	
	}
	
	//------------------------------------------------------------------------------------
	function get_node_score($id)
	{
		$node = $this->get_node($id);
		
		$score = $node->score;
		
		return $score;
	}
	
	
	//------------------------------------------------------------------------------------
	function get_parent($id)
	{
	
	}

	//------------------------------------------------------------------------------------
	function get_path_to_root($id)
	{
		$path = array();
		
		$node = $this->get_node($id);
		
		$parentTaxon = null;
		
		if (isset($node->parentTaxon))
		{
			$parentTaxon = $node->parentTaxon;
		}
		
		while ($parentTaxon)
		{
			$node = $this->get_node($parentTaxon);
			$path[] = $node;
			
			if (isset($node->parentTaxon))
			{
				$parentTaxon = $node->parentTaxon;
			}
			else
			{
				$parentTaxon = null;
			}

		}
			
		return $path;
	}
	
	//------------------------------------------------------------------------------------
	function get_siblings($id)
	{
	
	}
	
	//------------------------------------------------------------------------------------
	// Get list of all taxa ids in subtree, for example if we want to do some experiments
	function get_all_descendant_ids($subtree_id)
	{
		$stack = array();
		
		$ids = array();
		
		$stack[] = $subtree_id;		
		while (count($stack) > 0)
		{
			$cur_id = array_pop($stack);
			$ids[] = $cur_id;
			
			$children = $this->get_children($cur_id);
			
			foreach ($children as $child)
			{
				$stack[] = $child->id;
			}
		}
		
		return $ids;			
	}
		
	//------------------------------------------------------------------------------------
	// Export list of node ids in TGF format, assume first id is root
	function ids_to_tgf($ids, $root_id = null)
	{
		$graph_text = '';
	
		$nodes = array();
		$edges = array();
		
		if (!$root_id)
		{
			$root_id = $ids[0];
		}
		
		foreach ($ids as $id)
		{
			$node = $this->get_node($id);
			$nodes[$id] = $node->name;
			
			if ($id != $root_id)
			{
				if (is_object($node->parentTaxon))
				{
					$parent_id = $node->parentTaxon->id;
				}
				else
				{
					$parent_id = $node->parentTaxon;
				}
			
				$edges[] = $parent_id . ' ' . $id;
			}
		}
		
		foreach ($nodes as $id => $name)
		{
			$graph_text .= $id . ' ' . $name . "\n";
		}
		$graph_text .= "#\n";
		foreach ($edges as $edge)
		{
			$graph_text .= "$edge\n";
		}
		
		return $graph_text;		
	}

} 

?>
