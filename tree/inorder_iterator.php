<?php

//----------------------------------------------------------------------------------------
// See https://www.geeksforgeeks.org/inorder-tree-traversal-without-recursion/
// This only works for binary trees! 
class BinaryInorderIterator extends NodeIterator
{
	//------------------------------------------------------------------------------------
	function Begin()
	{
		$this->cur = $this->root;
		while ($this->cur)
		{
			array_push($this->stack, $this->cur);			
			$this->cur = $this->cur->GetChild();
		}
		return $this->Next();			
	}
	
	//------------------------------------------------------------------------------------
	function Next()
	{
		if (count($this->stack) == 0)
		{
			$this->cur = NULL;
			return $this->cur;
		}
		else
		{
			$top = array_pop($this->stack);
						
			if ($top->GetChild())
			{
				$this->cur = $top->GetChild()->GetSibling();
				while ($this->cur)
				{				
					array_push($this->stack, $this->cur);			
					$this->cur = $this->cur->GetChild();
				}
			}
			
			return $top;
		}
	}
}	

?>
