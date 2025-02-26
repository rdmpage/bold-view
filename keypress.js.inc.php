// Get the search field
var input = document.getElementById("search");

// Execute a function when the user presses a key on the keyboard
input.addEventListener("keypress", function(event) {
  // If the user presses the "Enter" key on the keyboard
  if (event.key === "Enter") {
    // Cancel the default action, if needed
    event.preventDefault();
    
    var content = this.value;
    if (content != "") {
    	search(content);
    }
  }
});	
