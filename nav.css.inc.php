
/* navigation bar */
nav {
	height:var(--nav-height);
	/* border-bottom: 1px solid #ccc; */
 	background-color: var(--bg);
 	color: var(--text);  

	width:100%;
	
	position: sticky; 
	top: 0;
	 /* Leaflet map controls have z-index: 1000 so need to be larger than that to 
	 hide controls behind nav bar when scrolling */
	z-index:1001;
}

/* https://stackoverflow.com/questions/23226888/horizontal-list-items-fit-to-100-with-even-spacing */
nav ul{
    margin: 0;
    padding: 1em;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
}
nav ul li {
    display: block;
    flex: 0 1 auto; /* Default */
    list-style-type: none;
}

#search {
	font-size:1em;
}
