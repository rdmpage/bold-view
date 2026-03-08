
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
  position:relative;
}

.gallery li:last-child {
  flex-grow: 10;
}

.gallery div {
	font-size:0.8em;
	
	position:absolute;
	left:0px;
	top:0px;
	width: 90%;
	text-align:center;
	
	height:auto;
	background-color:rgba(0, 0, 0, 0.4);
	color:white;
}

.gallery img {
  max-height: 90%;
  min-width: 90%;
  object-fit: cover;
  vertical-align: bottom;

  /* border-radius:0.2em; */

  border:1px solid #CCC;
}

.gallery li.gallery_more {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-basis: 100px;
  flex-grow: 0;
  flex-shrink: 0;
  cursor: pointer;
  border: 1px dashed currentColor;
  opacity: 0.6;
  font-size: 0.85em;
  text-align: center;
  padding: 0.5em;
  user-select: none;
  box-sizing: border-box;
}
