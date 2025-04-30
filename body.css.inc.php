
body {
	padding:0;
	margin:0;
	font-family: ui-sans-serif, system-ui, sans-serif;
 	background-color: var(--bg);
 	color: var(--text);  	
}

p {
	font-size:0.8em;
}

/* based on https://bloycey.medium.com/how-to-style-dl-dt-and-dd-html-tags-ced1229deda0 */
dl {
  display: grid;
  grid-gap: 4px 16px;
  grid-template-columns: max-content;
}
dt {
  text-align:right;
  color:#444;
}
dd {
  margin: 0;
  grid-column-start: 2;
  font-weight: bold;
}  

.search {
	border:1px solid var(--input-border);
 	background-color: var(--input-bg);
 	color: var(--input-color);
}  

.search:focus { 
	background-color: var(--input-bg-focus);
	border:1px solid var(--input-border-focus);
	color: var(--input-focus-color);
}

textarea {
	font-size:1em;
	width:100%;
	height:15em;
	padding:1em;
	font-family:monospace;
	white-space:pre-wrap;
	border-radius: 0.5em;
	
	border:1px solid var(--textarea-border);
 	background-color: var(--textarea-bg);
 	color: var(--textarea-color);
}

textarea:focus { 
	background-color: var(--textarea-bg-focus);
	border:1px solid var(--textarea-border-focus);
	color: var(--textarea-focus-color);
}

button { 
	font-size:1em; 

	background:#3300CC;
	color:white;
	border:1px solid #3300CC;
	
	padding: 0.5em 1em;
	border-radius: 0.2em;
	
	-webkit-appearance: none;
	display: inline-block;
}

.spacer {
	display:block;
	height:1em;
}

.error {
	padding:1em;
	background-color:red;
	color: white;
}

.warning {
	padding:1em;
	background-color:orange;
	color: white;
}