//----------------------------------------------------------------------------------------
// Test whether user clicked outside the panel, and if so close the panel
function click_outsider (event) {
	var element = document.getElementById('panel');
	var withinBoundaries = event.composedPath().includes(element);
	
	if (!withinBoundaries) {
		document.removeEventListener('click', click_outsider);	
		close_panel();
	}	
}

//----------------------------------------------------------------------------------------
// Make panel visible by sliding it out, add event listener for user clicking outside
// the panel
function show_panel() {
	var element = document.getElementById('panel');
	element.style.transform = "scaleX(1)";
	if (event) {
	  event.stopPropagation();
	}
	document.addEventListener('click', click_outsider);
}

//----------------------------------------------------------------------------------------
// Show a sumamry of an accession number by calling external API wrapper and formatting
// the JSON 
function show_panel_accession(accession) {
	show_panel(event);
	
	var url = "api_external.php?accession=" + accession;
	
	fetch(url).then(
		function(response){
			if (response.status != 200) {
				console.log("Looks like there was a problem. Status Code: " + response.status);
				document.getElementById("info").innerHTML = "404";
				return;
			}
			
			response.json().then(function(data) {					
				//var html = JSON.stringify(data);
				
				var html = '<h2>' + data.id + '</h2>';
				html += '<ul>';
				for (var i in data.references) {
					html += '<li>';
					
					if (data.references[i].title) {
						html += data.references[i].title;
					}

					if (data.references[i].DOI) {
						html += '<br>doi:<a href="https://identifiers.org/doi:' + data.references[i].DOI + '" target="_new">' + data.references[i].DOI + '</a>';
					}

					if (data.references[i].PMID) {
						html += '<br>pmid:<a href="https://identifiers.org/pubmed:' + data.references[i].PMID + '" target="_new">' + data.references[i].PMID + '</a>';
					}
					
					html += '</li>';
				}
				html += '</ul>';
				
				document.getElementById("info").innerHTML = html;
			});
			
	});
	
}

//----------------------------------------------------------------------------------------
// Show a summary of an museumid and try and map it to GBIF 
function show_panel_museumid(museumid) {
	show_panel(event);
	
	// just show musuemid
	var html = '<h2>' + museumid + '</h2>';
	document.getElementById("info").innerHTML = html;
	
	
	var url = "api_external.php?museumid=" + museumid;
	
	fetch(url).then(
		function(response){
			if (response.status != 200) {
				console.log("Looks like there was a problem. Status Code: " + response.status);
				document.getElementById("info").innerHTML = "404";
				return;
			}
			
			response.json().then(function(data) {					
				//var html = JSON.stringify(data);
				
				var html = '<h2>' + data.text + '</h2>';
				html += '<p>Possible match(s) in GBIF.</p>';
				html += '<ul>';
				for (var i in data.hits) {
					html += '<li>';
					
					if (data.hits[i].key) {
						html += '<span><a href="https://www.gbif.org/occurrence/' + data.hits[i].key + '" target="_new">' + 'GBIF:' + data.hits[i].key + '</a></span>';
						
						if (data.hits[i].occurrenceID) {
							html += '<ul><li>';
							
							if (data.hits[i].occurrenceID.match(/^http/)) {
								html += '<a href="' + data.hits[i].occurrenceID + '" target="_new">' + data.hits[i].occurrenceID + '</a>';
							} else {							
								html += data.hits[i].occurrenceID;
							}
							html += '</li></ul>';						
						}
						
					}
					
					html += '</li>';
				}
				html += '</ul>';
				
				document.getElementById("info").innerHTML = html;
			});
			
	});

	
}

//----------------------------------------------------------------------------------------
// Show a panel that displays a preformatted HTML snippet which we fetch from the API
function show_panel_snippet(api_url) {
	show_panel();
	
	// display
	//document.getElementById('info').innerHTML = decodeURIComponent(api_url);
	
	fetch(api_url).then(
		function(response){
			if (response.status != 200) {
				console.log("Looks like there was a problem. Status Code: " + response.status);
				document.getElementById("info").innerHTML = "404";
				return;
			}
			
			response.text().then(function(data) {					
				document.getElementById("info").innerHTML = data;
			});
			
	});
	
}

//----------------------------------------------------------------------------------------
// Close the panel by sliding it back
function close_panel() {
	document.getElementById("info").innerHTML = "";
	var element = document.getElementById('panel');
	element.style.transform = "scaleX(0.00001)";
}

//----------------------------------------------------------------------------------------
// Load a page of images into a gallery <ul>.
// Appends a clickable "Load more images" tile when more pages may exist, or an empty
// spacer <li> on the last page so the final image does not stretch to fill the row.
function load_gallery(listId, baseUrl, page, pageSize) {
	var list = document.getElementById(listId);

	// Remove any existing "Load more" tile and spacer before fetching
	var existing = list.querySelector('li.gallery_more');
	if (existing) existing.remove();
	var spacer = list.querySelector('li.gallery_spacer');
	if (spacer) spacer.remove();

	var url = baseUrl + "&page=" + page + "&limit=" + pageSize;

	fetch(url).then(function(response) {
		if (response.status != 200) {
			console.log("Gallery load failed. Status: " + response.status);
			return;
		}
		response.json().then(function(data) {
			for (var i = 0; i < data.hits.length; i++) {
				var img = data.hits[i];
				var li = document.createElement('li');
				var im = document.createElement('img');
				im.title = img.title || '';
				im.src = img.url.replace('www', 'v4');
				im.setAttribute('onclick',
					'show_panel_snippet("api.php?image=' +
					encodeURIComponent(img.url) + '&format=html")');
				li.appendChild(im);
				list.appendChild(li);
			}

			if (data.hits.length >= pageSize) {
				// More pages may exist — append a clickable tile
				var nextPage = page + 1;
				var more = document.createElement('li');
				more.className = 'gallery_more';
				more.textContent = 'Load more images';
				more.onclick = function() {
					load_gallery(listId, baseUrl, nextPage, pageSize);
				};
				list.appendChild(more);
			}

			// Always append empty spacer as last child so it absorbs extra
			// row space via :last-child { flex-grow: 10 }
			var sp = document.createElement('li');
			sp.className = 'gallery_spacer';
			list.appendChild(sp);
		});
	});
}
