<?php

require_once (dirname(dirname(__FILE__)) . '/pg.php');
require_once (dirname(__FILE__) . '/summary.php');

//----------------------------------------------------------------------------------------
function preorder(&$html, $node, $k = 'children')
{
	$html .=  '<li>' . "\n";
	
	if (preg_match('/^other_/', $node->id))
	{
		$html .=  '<details>' . "\n";
		$html .=  '<summary>' . "\n";
	}
	else
	{
		$html .=  '<span id="node' . $node->id . '"'
			. ' onclick="taxon_info(\'' . $node->id . '\')"'
			. ' ondblclick="taxon_focus(\'' . $node->id . '\')"'
			. '>';
	}
	
	$html .=  $node->name;
	
	if (preg_match('/^other_/', $node->id))
	{
		$html .=  " [" . count($node->others) . "]";
	
		$html .=  '</summary>' . "\n";
		$html .=  '<ul>' . "\n";
		foreach ($node->others as $other)
		{
			$html .=  '<li>' . "\n";
			$html .=  '<span id="node' . $other->id . '"'
				. ' onclick="taxon_info(\'' . $other->id . '\')"'
				. ' ondblclick="taxon_focus(\'' . $other->id . '\')"'
				. '>'  . "\n";
			$html .=  $other->name;
			$html .=  '</span>' . "\n";
			$html .=  '</li>' . "\n";
		}
		$html .=  '</ul>' . "\n";
		$html .=  '</details>' . "\n";
	}
	else
	{
		$html .=  '</span>' . "\n";
	}	
	
	if (isset($node->{$k}))
	{
		$html .=  '<ul>' . "\n";
		foreach ($node->{$k} as $child)
		{
			preorder($html, $child, $k);
		}
		$html .=  '</ul>' . "\n";
	}

	$html .=  '</li>' . "\n";
}

//----------------------------------------------------------------------------------------
function get_taxonomy_subtree($subtree_root_id, $k = 20)
{
	global $db;
	
	$dbtree = new DbTree($db, 'boldtaxonomy');	

	// focal node
	$node = $dbtree->get_node_context($subtree_root_id);

	$sumtree = new SummaryTree($dbtree);
	$sumtree->summarise_pq($subtree_root_id, $k);

	$node->summary = $sumtree->to_native();
	
	$html = '';

	// Path back up the tree
	$num_levels_back = 3;
	$num_levels_back = min($num_levels_back, count($node->path));

	$stack = array();
	for ($i = $num_levels_back - 1; $i >= 0; $i--)
	{
		$stack[] = $node->path[$i];
	}

	// path to focal node
	foreach ($stack as $path_node)
	{
		$html .= '<ul>' . "\n";
		$html .= '<li><a href="taxon/id/' . $path_node->id . '">' . $path_node->name . '</a>'  . "\n";
	}
	
	$html .= '<ul>' . "\n";
	preorder($html, $node, 'summary');
	$html .= '</ul>' . "\n";

	// unwind stack of path back up tree
	foreach ($stack as $path_node)
	{
		$html .=  '</li>'  . "\n";
		$html .=  '</ul>'  . "\n";
	}
	
	return $html;
}

?>