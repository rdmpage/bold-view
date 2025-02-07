<!-- leaflet -->
<link rel="stylesheet" href="js/leaflet-0.7.3/leaflet.css" />
<script src="js/leaflet-0.7.3/leaflet.js" type="text/javascript"></script>

<link rel="stylesheet" href="js/leaflet.draw/leaflet.draw.css" /> 
<script src="js/leaflet.draw/leaflet.draw.js" type="text/javascript"></script>

<style>
.mydivicon{
	width: 12px
	height: 12px;
	border-radius: 10px;
	/* background: #408000; */
	background: #333399; 
	border: 1px solid #33CCFF;
	opacity: 0.85
	
	#FF0
}	  
</style>

<script>
var map;
var geojson = null;
var drawnItems = null;

// http://gis.stackexchange.com/a/116193
// http://jsfiddle.net/GFarkas/qzdr2w73/4/
var icon = new L.divIcon({className: 'mydivicon'});		

//--------------------------------------------------------------------------------
function onEachFeature(feature, layer) {
	if (feature.properties && feature.properties.name) {
		//console.log(feature.properties.popupContent);
		// content must be a string, see http://stackoverflow.com/a/22476287
		layer.bindPopup(String(feature.properties.name));
	}
}	
	
//--------------------------------------------------------------------------------
function create_map(id) {
	map = new L.Map(id);

	// create the tile layer with correct attribution
	var layerUrl = '';
	var layerAttrib = '';
	
	// https://stackoverflow.com/a/57795495
	if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
		layerUrl='https://{s}.tile.thunderforest.com/transport-dark/{z}/{x}/{y}.png?apikey=<?php echo getenv('THUNDERFOREST_API_KEY') ?>';
		layerAttrib = 'Map © <a href="https://www.thunderforest.com/">Thunderforest</a>, data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';	
	} else {
	 // default OpenStreetMap
	 layerUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
	 layerAttrib = 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';	
	}
	
	layer = new L.TileLayer(layerUrl, {minZoom: 1, maxZoom: 16, attribution: layerAttrib});	

	map.setView(new L.LatLng(0, 0), 4);
	map.addLayer(layer);	
}

//--------------------------------------------------------------------------------
function clear_map() {
	if (geojson) {
		map.removeLayer(geojson);
	}
}	

//--------------------------------------------------------------------------------
function add_data(data) {	
	clear_map();

	geojson = L.geoJson(data.features, { 
		pointToLayer: function (feature, latlng) {
			return L.marker(latlng, {
				icon: icon});
		},			
		style: function (feature) {
			return feature.properties && feature.properties.style;
		},
		onEachFeature: onEachFeature,
	}).addTo(map);
	
	// Open popups on hover
	geojson.on('mouseover', function (e) {
		e.layer.openPopup();
	});
	
	/*
	"type": "FeatureCollection",
"features": [
  {
	"type": "Feature",
	"geometry": {
	  "type": "Point",
	  "coordinates": [
		30.135,
		-24.052
	  ]
	},
	"properties": {
	  "name": "AMPSA365-13"
	}
  },
  */
	
	minx = 180;
	miny = 90;
	maxx = -180;
	maxy = -90;
	
	for (var i in data.features) {
	   data.features[i].geometry
	   
		minx = Math.min(minx, data.features[i].geometry.coordinates[0]);
		miny = Math.min(miny, data.features[i].geometry.coordinates[1]);
		maxx = Math.max(maxx, data.features[i].geometry.coordinates[0]);
		maxy = Math.max(maxy, data.features[i].geometry.coordinates[1]);
	}
	var min_size = 2;
	
	if (maxx - minx < min_size) {
		minx -= min_size/2;
		maxx += min_size/2;
	}
	if (maxy - miny < min_size) {
		miny -= min_size/2;
		maxy += min_size/2;
	}
	
	bounds = L.latLngBounds(L.latLng(miny,minx), L.latLng(maxy,maxx));
	map.fitBounds(bounds);
}

function create_large_map(id, filter = '') {
	map = new L.Map(id);

	// create the tile layer with correct attribution
	// create the tile layer with correct attribution
	var layerUrl = '';
	var layerAttrib = '';
	// https://stackoverflow.com/a/57795495
	if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
		layerUrl='https://{s}.tile.thunderforest.com/transport-dark/{z}/{x}/{y}.png?apikey=<?php echo getenv('THUNDERFOREST_API_KEY') ?>';
		layerAttrib = 'Map © <a href="https://www.thunderforest.com/">Thunderforest</a>, data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';	
	} else {
	 // default OpenStreetMap
	 layerUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
	 layerAttrib = 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';	
	}
	
	layer = new L.TileLayer(layerUrl, {minZoom: 1, maxZoom: 16, attribution: layerAttrib});	

	map.setView(new L.LatLng(0, 0), 4);
	map.addLayer(layer);	

	/* This is where we add custom tiles, e.g. with data points */
	
	var dotsAttrib='BOLD';
	var dots = new L.TileLayer('api_tile.php?x={x}&y={y}&z={z}' 
		//+ "&t=" + Date.now(), 
		+ "&filter=" + filter,
		{minZoom: 0, maxZoom: 14, attribution: dotsAttrib});

	map.addLayer(dots);	
	
	// controls to draw polygons
	
	drawnItems = new L.FeatureGroup();
	map.addLayer(drawnItems);			
	
	var drawControl = new L.Control.Draw({
		position: 'topleft',
		draw: {
			marker: false, // turn off marker
			polygon: {
				shapeOptions: {
					color: 'purple'
				},
				allowIntersection: false,
				drawError: {
					color: 'orange',
					timeout: 1000
				},
				showArea: true,
				metric: false,
				repeatMode: true
			},
			polyline: false,
			rect: {
				shapeOptions: {
					color: 'green'
				},
			},
			circle: false
		},
		edit: {
			featureGroup: drawnItems
		}
	});
	map.addControl(drawControl);	

	
	map.on('draw:created', function (e) {
		var type = e.layerType,
			layer = e.layer;

		drawnItems.addLayer(layer);
		map_search(layer.toGeoJSON(), filter);
	
	});						
	

}

function map_search(geo, filter = '') {

	// clear stuff
	document.getElementById('maphits').innerHTML = "Searching...";
	
	// move to where search is
	for (var i in geo.geometry.coordinates) {
		  minx = 180;
		  miny = 90;
		  maxx = -180;
		  maxy = -90;
  
		  for (var j in geo.geometry.coordinates[i]) {
			minx = Math.min(minx, geo.geometry.coordinates[i][j][0]);
			miny = Math.min(miny, geo.geometry.coordinates[i][j][1]);
			maxx = Math.max(maxx, geo.geometry.coordinates[i][j][0]);
			maxy = Math.max(maxy, geo.geometry.coordinates[i][j][1]);
		  }
		}
	
	bounds = L.latLngBounds(L.latLng(miny,minx), L.latLng(maxy,maxx));
	map.fitBounds(bounds);			
		
	console.log(JSON.stringify(geo, null, 2));	
		
	var url = "api.php?geojson=" + encodeURIComponent(JSON.stringify(geo));
	
	if (filter != '')
	{
		url += '&filter=' + encodeURIComponent(filter);
	}
	
	fetch(url).then(
		function(response){
			if (response.status != 200) {
				console.log("Looks like there was a problem. Status Code: " + response.status);
				document.getElementById("maphits").innerHTML = "404";
				return;
			}
					
			response.json().then(function(data) {					
				//var html = JSON.stringify(data);
				
				var html = '';
				
				html += '<div style="padding:1em;">';
				
				html += '<ul class="media-list">';
				
				for (var i in data.hits)
				{
					html += '<li class="media-item">';
					
					if (data.hits[i].images) {
						html += '<img class="media-figure" src="' + data.hits[i].images[0].url + '">';
					} else {
						html += '<img class="media-figure" src="images/100x100.png">';					
					}
					
					html += '<div class="media-body">';
					html += '<h3 class="media-title">';
					html += '<a href="?record=' + data.hits[i].processid + '">';
					html += data.hits[i].processid;
					html += '</a>';
					html += '</h3>';
					
					html += '<p>';
					if (data.hits[i].bin_uri) {
						html += data.hits[i].bin_uri;
					}
					html += '</p>';
					
					html += '</div>';


					html += '</li>';
				}
				html += '</ul>';
				
				html += '</div>';
				
				
				document.getElementById("maphits").innerHTML = html;
			});
	});										
}		
</script>