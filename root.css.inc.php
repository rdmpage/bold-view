/* https://dev.to/arnu515/auto-dark-light-theme-using-css-only-2e0m */
:root {
  	overscroll-behavior: none; /* https://css-irl.info/preventing-overscroll-bounce-with-css/ */
  	--nav-height: 3em;
  	--side-width: 30vw;

	--text: #333;
	--bg: #fff;
	
	--panel-bg: #fff;
	--panel-shadow: gray;
			
	--tree-font-size:0.8em;	
	--tree-color: #000;
	--hover-colour:#000;   
	--hover-background: #DDD;
	--active-background: #CDE; /* rgb(174,204,249);*/
	--tree-line-color:  #BBB;
	
	/* input */
	--input-border: #BBB;
	--input-bg: #fff;
	
	/* text area */
	--textarea-border: #BBB;
	--textarea-bg: #EEE;	
	--textarea-color: var(--text);
	
	--textarea-bg-focus: white;
	--textarea-border-focus: black;
	--textarea-focus-color: black;
	
	/* phylogeny */
	--phylogeny: black;
	--bin-a: rgb(50,4,172);
	
	/* taxonomy */
	--tree-selected-bg: orange;
	--tree-selected-color:black;
	
	--tree-classification-border: 1px solid black;
	--tree-classification-bg: none;

	--taxonomy-width: 300px;

	
}

@media (prefers-color-scheme: dark) {

  :root {
    --text: #d0d0d0;
    --bg: #121212;  /* https://m2.material.io/design/color/dark-theme.html#properties */
     
    --panel-bg: #222;
    --panel-shadow: black;
    
    /* Make titles less heavy */
    h1 { font-weight: normal; }
    h2 { font-weight: normal; }
    
    /* change links */
    a { color: #76D6FF; }
    
    /* tree */
    --tree-color: ##d0d0d0;
   	--hover-colour: #d0d0d0; 
   	--hover-background: #555;
   	--active-background: #941100;
   	--tree-line-color:  #666;
   	
   	/* input */
   	--input-border:var(--bg);
   	--input-bg: rgb(56,45,71);
   	
	/* text area */
	--textarea-border: var(--bg);
	--textarea-bg: rgb(56,45,71);	
	--textarea-color: rgb(212,180,250);
	
	--textarea-bg-focus: rgb(79,70,93);
	--textarea-border-focus: white;
	--textarea-focus-color: white;
	
	/* phylogeny */
	--phylogeny: #BB86FC;
	--bin-a: rgb(50,4,172);
	
	/* taxonomy */
	
	--tree-selected-bg: rgb(68,8,201);
	--tree-selected-color:white;
	
	--tree-classification-border: 1px solid rgb(50,4,172);
	--tree-classification-bg: rgb(50,4,172);
	
	/* 
		400 rgb(117,41,243); 
		500 rgb(89,13,228);
		600 rgb(68,8,201);
		700 rgb(50,4,172);
	*/

  }
}
