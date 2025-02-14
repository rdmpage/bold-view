<?php

// Priority queue

//----------------------------------------------------------------------------------------
// compare two nodes
function cmp_score($a, $b)
{
    if ($a->score == $b->score) {
        return 0;
    }
    return ($a->score > $b->score) ? -1 : 1;
}

//----------------------------------------------------------------------------------------
// fake priority queue that maintains a sorted list of items, making it simple but slow
class PQfake 
{
	var $queue = array();
	
	//------------------------------------------------------------------------------------
	function __construct()
	{
		$this->queue = array();
	}
	
	//------------------------------------------------------------------------------------
	function en_queue($id, $name, $score)
	{
		$obj = new stdclass;
		$obj->id 	= $id;
		$obj->name 	= $name;
		$obj->score = $score;
	
		$this->queue[] = $obj;	
		
		// sort (priority queue)
		usort($this->queue, 'cmp_score');	
	}	
	
	//------------------------------------------------------------------------------------
	function de_queue()
	{
	 	return array_shift($this->queue);
	}
	
	//------------------------------------------------------------------------------------
	function delete_from_queue($id)
	{
		$found = -1;
	
		foreach ($this->queue as $k => $v)
		{
			if ($this->queue[$k]->id == $id)
			{
				$found = $k;
				break;
			}
		}
	
		if ($found != -1)
		{
			unset($this->queue[$k]);
		}
	}	
	
	//------------------------------------------------------------------------------------
	function valid()
	{
		return count($this->queue) > 0;
	}	
	
}

//----------------------------------------------------------------------------------------
// Inspired by https://github.com/ezimuel/FastPriorityQueue, we simply need to know
// the maximum value of an array of priorities. Note that score must be an integer 
// for this to work, because we use prorities as array indices.
// To make this work for real values, we multiple supplied scores by 100 before 
// rounding them.
class PQ
{
	var $values = array();
	var $priorities = array();
	var $max = 0;
	
	var $id_priorities = array();
	
	//------------------------------------------------------------------------------------
	function __construct()
	{
		$this->values = array();
		$this->priorities = array();
		$this->max = 0;
		
		$this->id_priorities = array();
	}
	
	//------------------------------------------------------------------------------------
	function en_queue($id, $name, $score)
	{
		$obj = new stdclass;
		$obj->id 	= $id;
		$obj->name 	= $name;
		$obj->score = $score;
		
		// priority must be positive integer
		$score = 100 * $obj->score;
		if ($score > PHP_INT_MAX)
		{
			$score = PHP_INT_MAX;
		}
		else
		{
			$score = round(100 * $score, 0);
		}
		
		$obj->priority = max (0, $score);
				
		$this->values[$obj->priority][] = $obj;

        if (!isset($this->priorities[$obj->priority])) 
        {
            $this->priorities[$obj->priority] = $obj->priority;
            $this->max = max($obj->priority, $this->max);
        }
        
        // so we can quickly look up a value
        $this->id_priorities[$obj->id] = $obj->priority;		
	}	
	
	//------------------------------------------------------------------------------------
	function de_queue()
	{
		$obj = null;
		
		if ($this->valid())
		{
			$obj = array_pop($this->values[$this->max]);
			
			unset($this->id_priorities[$obj->id]);
		
			if (empty($this->values[$this->max]))
			{
				unset ($this->values[$this->max]);
				unset ($this->priorities[$this->max]);
				$this->max = empty($this->priorities) ? 0 : max($this->priorities);
			}
		}
			
	 	return $obj;
	}
	
	//------------------------------------------------------------------------------------
	function delete_from_queue($id)
	{
		$priority = $this->id_priorities[$id];
		
		$found = -1;
	
		foreach ($this->values[$priority] as $k => $v)
		{
			if ($this->values[$priority][$k]->id == $id)
			{
				$found = $k;
				break;
			}
		}
	
		if ($found != -1)
		{
			unset($this->values[$priority][$found]);
			unset($this->id_priorities[$id]);
			
			if (empty($this->values[$priority]))
			{
				unset ($this->values[$priority]);
				unset ($this->priorities[$priority]);
				$this->max = empty($this->priorities) ? 0 : max($this->priorities);
			}
			
		}
	}	
	
	//------------------------------------------------------------------------------------
	function valid()
	{
		return isset($this->values[$this->max]);
	}	
	
}

?>
