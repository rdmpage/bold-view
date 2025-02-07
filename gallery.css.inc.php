
/* heavily based on https://css-tricks.com/adaptive-photo-layout-with-flexbox/ */
.gallery {
	/* background:rgb(192,192,192); */
}

.gallery ul {
  display: flex;
  flex-wrap: wrap;	  
  list-style:none;
  /* padding-left:2px; */
  padding:4px;
}

.gallery li {
  height: 100px;
  flex-grow: 1;  
}

.gallery li:last-child {
  flex-grow: 10;
}

.gallery img {
  max-height: 90%;
  min-width: 90%;
  object-fit: cover;
  vertical-align: bottom;
  
  border-radius:0.2em;
  
  border:1px solid #CCC; 
}	
