<?php


// Priority queue (fake)

// While we experiment with code have an array that we sort after we update it, so 
// it is always ordered. At some point this should be replaced by a priority queue.


$queue = array();


//----------------------------------------------------------------------------------------
// compare two nodes
function cmp($a, $b)
{
    if ($a->score == $b->score) {
        return 0;
    }
    return ($a->score > $b->score) ? -1 : 1;
}

//----------------------------------------------------------------------------------------
// The children of node $q are added to the queue.
// priority queue so that the node in the queue with the highest 
// score is at the front of the queue
function add_children_to_queue(&$queue, $q)
{
	$children = get_children($q);
	
	foreach ($children as $child)
	{
		if (!$child->IsLeaf())
		{
			$obj = new stdclass;
			$obj->node = $child;
			$obj->score = score_node($child);
			$queue[] = $obj;		
		}
	}
	
	// sort (priority queue)
	usort($queue, 'cmp');		
	
	//dump_queue($queue);
}

//----------------------------------------------------------------------------------------
function dump_queue($queue)
{
	echo "Queue [" . count($queue) . "]\n";
	foreach ($queue as $obj)
	{
		echo $obj->node->label . "\n";
	}

}


?>
