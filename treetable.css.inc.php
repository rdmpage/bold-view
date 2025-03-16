.tree-table {
	
}

.tree-table tbody {
	font-size:0.7em;
}

.tree-table td {
	white-space:nowrap;
}

.tree-table .bin_uri a {
	 color: var(--bin-a);
}

.tree-table .classification {
	/* border:1px solid white; */
	border:var(--tree-classification-border); 
	border-radius:0.5em;
	
	background: var(--tree-classification-bg);
	
	text-align:center;
	/* padding:1em;*/ /* padding breaks alignment with tree */
}

.tree-table .selected {
	background: var(--tree-selected-bg);
	color: var(--tree-selected-color);
}

/* https://stackoverflow.com/a/36677589 */
/* This CSS ensures that SVG of tree will automatically recale to allocated
   space in table (when used with viewBox attribute in SVG) */
.tree-table svg {
	height:100%;
	width:100%;
	position:absolute;
	display:inline-block;
	top:0px;
}

/* CSS for lines in tree */
.tree-table path {
	stroke: var(--phylogeny);
	stroke-width:1;
	stroke-linecap:square;
	fill: none;
}

  	
