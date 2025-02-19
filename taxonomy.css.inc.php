.tree {
  font-size: var(--tree-font-size);  	
  color: var(--tree-color);
  width: var(--taxonomy-width);
  height:calc(100vh - var(--nav-height)); 
  overflow-y:auto;
}

.tree, .tree ul, .tree li {
	position: relative;
}  

.tree ul {
	padding-left: var(--tree-font-size);
	list-style:none; 
}

.tree li {
	padding-left: calc(0.3 * var(--tree-font-size));
}

.tree li::before, .tree li::after {
	content: "";
	position: absolute;
	left: calc(-1 * var(--tree-font-size));
}

.tree li::before {
	border-top: 1px solid var(--tree-line-color);
	top: calc(0.7 * var(--tree-font-size));
	width: var(--tree-font-size);
	height: 0;
}
.tree li::after {
	border-left: 1px solid var(--tree-line-color);
	height: 100%;
	width: 0px;
	top: 0px;
}   

.tree ul > li:last-child::after {
	height: calc(0.7 * var(--tree-font-size));
}

.tree span {
   	white-space: nowrap;
   	
   	/* ensures hovering and selection goes to right margin of tree */
   	/* https://stackoverflow.com/a/13977725 */
   	display: inline-block;
   	width:100%;   	
}
   
.tree span:hover {
   	color: var(--hover-colour);   
   	background: var(--hover-background);   
} 

.tree .active {
   	color: var(--active-colour);   
   	background: var(--active-background);   
}

/* put marker at end */
/* https://stackoverflow.com/a/56758842 */
.tree details>summary {
  list-style: none;
}

.tree summary::-webkit-details-marker {
  display: none
}

.tree summary::after {
  content: " ▽";
}

.tree details[open] summary:after {
  content: " △";
}

.taxonomy {
  width:calc(100vw - var(--taxonomy-width));
  overflow-y:auto;
  box-sizing:border-box; /* https://developer.mozilla.org/en-US/docs/Web/CSS/box-sizing */
  padding:1em;
}