
/* slide in side panel */
#panel {
  position: fixed;
  right: 0;
  width: 40%;
  top: 0;
  bottom: 0;
  
  box-shadow: 0px 0px 15px var(--panel-shadow); 

  background: var(--panel-bg); 
  
  
  padding: 10px;
  transform-origin: 100% 50%;
  transform: scaleX(0.00001);
  transition: transform 0.2s ease-in-out;
  outline: 0;
  
  height:100%;
  overflow-y:auto;
  
  padding:1em;
  z-index:10000;
}
