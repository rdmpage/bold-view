
/* navigation bar */
nav {
	height:var(--nav-height);
	border-bottom: 1px solid #ccc;
 	background-color: var(--bg);
 	color: var(--text);  

	width:100%;
	
	position: sticky; 
	top: 0;
	z-index:100;	
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
