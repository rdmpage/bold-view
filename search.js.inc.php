function search(q) {	
	// classify search string
	
	var m = null
	
	// barcode
	m = q.match(/^\s*([A-Z]+\d+-\d+)\s*$/);
	if (m) {
		window.location = "record/" + m[1];			
	}
	
	// bin
	m = q.match(/^\s*(BOLD:[A-Z]+\d+)\s*$/);
	if (m) {
		window.location = "bin/" + m[1];			
	}
	
	// taxon name with rank, e.g. g__Sigaloseps
	m = q.match(/^\s*([a-z]{1,2}__([A-Z].*))\s*$/);
	if (m) {
		window.location = "?taxonname=" + m[1];			
	}	

	// Assume most other things are taxa
	m = q.match(/^\s*([A-Z].*)\s*$/);
	if (m) {
		window.location = "?taxonname=" + m[1];			
	}
	
	if (!m) {
		alert("Don't understand " + q);
	
	}
	
	
}