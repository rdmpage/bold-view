<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/core.php');
require_once (dirname(__FILE__) . '/language.php');
require_once (dirname(__FILE__) . '/taxonomy/taxonomy.php');

//----------------------------------------------------------------------------------------
function html_start($title = '')
{
	global $config;
	
	echo '<html>';
	
	echo '<head>';
	
	echo '<meta charset="utf-8" />';
		
	echo '<!-- base -->
    	<base href="' . $config['web_root'] . '" /><!--[if IE]></base><![endif]-->';
    	
    if ($title == '')
    {
    	$title = $config['site_name'];
    }
    	
    echo '<title>' . $title . '</title>';   	
		
	require_once(dirname(__FILE__) . '/map.inc.php');
	
	echo '<style>';
	
	require_once (dirname(__FILE__) . '/root.css.inc.php');
	require_once (dirname(__FILE__) . '/body.css.inc.php');


echo '
.content {
	display:flex;
}

/* main column */
.main {
	width:90vw;
	padding:1em;
	margin:auto;
}

.main_with_map {
	width:calc(100vw - var(--side-width));
	padding:0;
	margin:0;
}

#map {
	width:auto;
	height:calc(100vh - var(--nav-height)); 	
	background:gray;
}

#filtered_map {
	width:auto;
	height:40vh;	
	background:gray;
	border-radius: 0.5em;
}


/* right column */
.side {
	width: var(--side-width);
	/* border-left:1px solid #ddd; */
	height:calc(100vh - var(--nav-height)); 
	overflow-y:auto;
}	


#small_map {
	width:100%;
	height:30vh;
	border-radius:0.5em;
}
';



require_once (dirname(__FILE__) . '/column.css.inc.php');
require_once (dirname(__FILE__) . '/dot.css.inc.php');
require_once (dirname(__FILE__) . '/gallery.css.inc.php');
require_once (dirname(__FILE__) . '/license.css.inc.php');
require_once (dirname(__FILE__) . '/media.css.inc.php');
require_once (dirname(__FILE__) . '/nav.css.inc.php');
require_once (dirname(__FILE__) . '/panel.css.inc.php');
require_once (dirname(__FILE__) . '/taxonomy.css.inc.php');
require_once (dirname(__FILE__) . '/treetable.css.inc.php');

echo '</style>' . "\n";

	
echo '<script>' . "\n";

require_once (dirname(__FILE__) . '/panel.js.inc.php');
require_once (dirname(__FILE__) . '/search.js.inc.php');

echo '</script>' . "\n";

	
	echo '</head>';
	
	echo '<body>';
	
	echo '<nav>
	<ul>
		<li><a href=".">' . get_text(['nav', 'home']) . '</a></li>
		<li>
			<input class="search" id="search" type="text" placeholder="' . get_text(['nav', 'search']) . '">
		</li>
		<li><a href="taxon/id/713">' . get_text(['nav', 'taxonomy']) . '</a></li>
		<li><a href="map">' . get_text(['nav', 'map']) . '</a></li>
		<li><a href="blast">' . get_text(['nav', 'blast']) . '</a></li>
		<li><a href="https://github.com/rdmpage/bold-view/issues" target="_new">' . get_text(['nav', 'feedback']) . '</a></li>
	</ul>
	</nav>';
	
	echo '<div class="content">';
}

//----------------------------------------------------------------------------------------
function html_end()
{
	echo '</div>'; // content
	
	echo '<script>' . "\n";
	require_once (dirname(__FILE__) . '/keypress.js.inc.php');
	echo '</script>' . "\n";

	echo '</body>';
	echo '</html>';
}

//----------------------------------------------------------------------------------------
// Home page, or badness happened
function default_display($error_msg = '')
{
	html_start();

	echo '<div class="main">';
	
	if ($error_msg != '')
	{
		echo '<div><strong>Error!</strong> ' . $error_msg . '</div>';
	}
	else
	{
		echo '<h1>BOLD View</h1>';
		
		echo '<p>BOLD View is an tool to explore DNA barcode data.</p>';
		
		echo '<h2>Starting points</h2>';
		
		echo '<ul>';
		echo '<li>A browseable <a href="map">map</a> of barcodes.</li>';
		echo '<li>Find barcodes that <a href="blast"> match</a> a sequence using vector search.</li>';
		echo '<li>View a BIN for ant-mimicing spiders in Borneo: <a href="bin/BOLD:ACO6074">BOLD:ACO6074</a>.</li>';
		echo '<li>View a barcode for a stingless bee <i>Hypotrigona</i> from South Africa: <a href="record/KMPPA063-18">KMPPA063-18</a>.</li>';
		echo '<li>View a barcode for a gecko <i>Tropiocolotes tripolitanus</i> with multiple BINs <a href="record/REWSS381-22">REWSS381-22</a></li>';
		echo '<li>View a BIN which includes holotype (<a href="record/PNGTY1822-15">PNGTY1822-15</a>): <a href="bin/BOLD:ACA8529">BOLD:ACA8529</a></li>';
		echo '<li>View a barcode for a Fijian bee <i>Homalictus nadarivatu</i> with interesting tree <a href="record/GBMNC45937-20">GBMNC45937-20</a></li>';
		echo '<li>View taxonomy for <a href="?taxonname=g__Homalictus"><i>Homalictus</i></a> which Wikipedia says is not a genus.</li>';
		echo '<li>View a BIN with two taxonomic names, one for each sex <a href="bin/BOLD:ACG2612">BOLD:ACG2612</a> (see <a href="https://doi.org/10.54102/ajt.df83w" target="_new">doi:10.54102/ajt.df83w</a>)</li>';
		echo '<li>View taxonomy for <a href="?taxonname=g__Mabuya"><i>Mabuya</i></a> which Wikipedia says is a "wastebasket taxon".</li>';
		
		echo '<li>View recordset <a href="?recordset=DS-2019PHY">DS-2019PHY</a> which is cited by "Microbiome and environment explain the absence of correlations between consumers and their diet in Bornean microsnails" (<a href="https://doi.org/10.1002/ecy.3237">doi:10.1002/ecy.3237</a>).</li>';
		echo '<li>View recordset <a href="?recordset=DS-SATYP1">DS-SATYP1</a> which comprises type specimens of saturniid moths.</li>';
		
		
		
		echo '<li>View a barcode (<a href="record/ANGBF37031-19">ANGBF37031-19</a>) that is part of a BIN labelled with synonyms (<i>Apogon smithi</i> and <i>Jaydia smithi</i>), see "Exploring artificial neural networks for the curation of DNA barcode reference libraries..." <a href="https://doi.org/10.22541/au.172374899.92498971/v1">doi:10.22541/au.172374899.92498971/v1</a>.</li>';
		
		echo '</ul>';
	}
	
	echo '</div>';

	html_end();	
}

//----------------------------------------------------------------------------------------
// parse a "green genes-style rank__name string and convert to URL parameters"
function rank_prefix_name_to_url($string)
{
	$url = '?taxonname=' . urlencode($string);
	
	/*
	if (preg_match('/([a-z]+)__([A-Z].*)/', $string, $m))
	{
		$url = '?taxonname=' . urlencode($m[2]) . '&rank=' . urlencode($m[1]);
	}
	else
	{
		// shouldn't happen
		$url = '?taxonname=' . urlencode($string);
	}
	*/
	
	return $url;

}

//----------------------------------------------------------------------------------------
function display_barcode($id)
{
	$doc = get_barcode($id);
	
	if ($doc)
	{	
		$title = sprintf(get_text(['record', 'title']), $doc->processid);
	
		html_start($title);
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
		
		echo '<div class="main">';
		
		echo '<h1>' . $title . '</h1>';
		echo '<p>' . get_text(['record', 'lede']) . '</p>';
		
		$keys = array('identification', 'insdc_acs', 'bin_uri');
		
		echo '<h3>' . get_text(['record', 'details']) . '</h3>';
		echo '<p>' . get_text(['record', 'details_lede']) . '</p>';

		echo '<dl>';
		foreach ($keys as $key)
		{
			if (isset($doc->{$key}))
			{
				echo '<dt>' .get_text(['record', $key]) . '</dt>';
				echo '<dd>' . identifier_link($key, $doc->{$key}) . '</dd>';
			}
		}
		echo '</dl>';
		
		if (1)
		{
			if (isset($doc->lineage))
			{
				echo '<h3>' . get_text(['record', 'lineage']) . '</h3>';
				echo '<p>' . get_text(['record', 'lineage_lede']) . '</p>';			
			
				echo '<ul>';
				foreach ($doc->lineage as $taxon)
				{
					echo '<li>';
					echo '<a href="' . rank_prefix_name_to_url($taxon) . '">';
					echo $taxon;
					echo '</a>';
					echo '</li>';
				}
				echo '</ul>';
			}
		}
		else
		{		
			if (isset($doc->lineage))
			{
				echo '<h3>Lineage</h3>';
				echo '<div class="tree">';
				foreach ($doc->lineage as $taxon)
				{
					echo '<ul>';
					echo '<li>' . $taxon;
				}
				foreach ($doc->lineage as $taxon)
				{
					echo '</li></ul>';
				}
				echo '</div>';
			}	
		}
					
		if (isset($doc->feature))
		{
			echo '<h3>' . get_text(['record', 'map']) . '</h3>';
			echo '<p>' . get_text(['record', 'map_lede']) . '</p>';
			
			echo '<div id="small_map"></div>';
			echo '<script>';
			echo 'create_map(small_map);';
			echo '</script>';	
		
			$geo = new stdclass;
			$geo->type = "FeatureCollection";
			$geo->features = array($doc->feature);
			echo '<script>add_data(' . json_encode($geo) . ');</script>';
		}
		
		
		if (isset($doc->images))
		{
			echo '<h3>' . get_text(['record', 'images']) . '</h3>';
			echo '<p>' . get_text(['record', 'images_lede']) . '</p>';

			echo '<div class="gallery">';
			echo '<ul>';
			foreach ($doc->images as $image)
			{
				echo '<li>';
				echo '<div>' . $image->title . '</div>';
				echo '<img onclick="show_panel_snippet(&quot;api.php?image=' . urlencode($image->url) . '&format=html&quot;)" src="' . $image->url  . '">';
				echo '</li>';
			}
			echo '<!-- need this to avoid distorting last image -->
    <li></li>';
			echo '</ul>';
			echo '</div>';
		}
		
		echo '<h3>' . get_text(['record', 'related']) . '</h3>';
		echo '<p>' . get_text(['record', 'related_lede']) . '</p>';
			
	 	echo '<div id="output" class="tree-table"></div>';     
		
		/*
		echo '<h3>Alignment</h3>
	 	<div id="alignment" class="alignment"></div>';  
	 	*/   
		
		
		echo '</div> <!-- close main -->';
		
echo '<script>
		function related(id) {
									
			var url = "api.php?barcode=" + id + "&related&tree&limit=50&format=html";
			
			fetch(url).then(
				function(response){
					if (response.status != 200) {
						console.log("Looks like there was a problem. Status Code: " + response.status);
						return;
					}
			
					response.text().then(function(data) {
						document.getElementById("output").innerHTML = data;
					});
				});
		}
		
		function alignment(id) {
									
			var url = "api.php?barcode=" + id + "&alignment&format=html";
			
			fetch(url).then(
				function(response){
					if (response.status != 200) {
						console.log("Looks like there was a problem. Status Code: " + response.status);
						return;
					}
			
					response.text().then(function(data) {
						document.getElementById("alignment").innerHTML = data;
					});
				});
		}
		
		
		related("' . urlencode($id) . '");
		alignment("' . urlencode($id) . '");
	</script>';	
		
		
		html_end();
	}
	else
	{
		default_display("$id not found");
	}
}

//----------------------------------------------------------------------------------------
function make_graph($value_counts)
{
	$graph = array();
	
	$node_lookup = array();

	foreach ($value_counts as $value => $count)
	{
		$items = explode(';', $value);
		
		foreach ($items as $item)
		{
			if (!isset($node_lookup[$item]))
			{
				$node = new stdclass;
				$node->id = count($graph);
				$node->label = $item;
				$node->url = rank_prefix_name_to_url($item);
				$node->edges = array();
				$graph[$node->id] = $node;				
				$node_lookup[$item] = $node->id;
			}
		}
		
		// add edges
		$n = count($items);
		for ($i = 1; $i < $n; $i++)
		{
			$source = $node_lookup[$items[$i-1]];
			$target = $node_lookup[$items[$i]];
			
			if (!isset($graph[$source]->edges[$source]))
			{
				$graph[$source]->edges[$source] = array();
			}
			if (!in_array($target, $graph[$source]->edges[$source]))
			{
				$graph[$source]->edges[$source][] = $target;
			}
		}
		
		// set properties of leaf for this path
		$leaf_index = $node_lookup[$items[$n - 1]];
		$graph[$leaf_index]->count = $count;
	}

	/*
	echo '<pre>';
	print_r($graph);
	echo '</pre>';
	*/
	
	// output as DOT
	
	$dot = "graph {\n";
	$dot .= "rankdir=LR\n";
	
	foreach ($graph as $node)
	{
		$label = $node->label;
			
		$dot .= 'node [fontsize="10",';
		
		if (isset($node->count))
		{
			$label .= ' (' . $node->count . ')';
			
			$dot .= 'fillcolor="yellow", style="filled", ';
		}
		else
		{
			$dot .= 'fillcolor="white",style="filled", ';
		}
		
		
		$dot .= 'label="' . addslashes($label) . '"';
		
		if (isset($node->url))
		{
			$dot .= ', URL="' . $node->url . '"';
		}
		
		$dot .= '] ' . $node->id . ";\n";
	}
		
	foreach ($graph as $node)
	{
		foreach ($node->edges as $source => $targets)
		{
			foreach ($targets as $target)
			{
				$dot .= $source . ' -- ' . $target . ";\n";
			}
		}
	}
	
	$dot .= "}\n";
	
	return $dot;
}

//----------------------------------------------------------------------------------------
function display_bin ($id, $limit = 100)
{
	$doc = get_bin($id, $limit);
	
	if ($doc)
	{
		$title = sprintf(get_text(['bin', 'title']), $doc->bin_uri);
	
		html_start($title);
				
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
		
		echo '<div class="main">';
		
		echo '<h1>' . $title . '</h1>';
		echo '<p>' . get_text(['bin', 'lede']) . '</p>';
		
		if (isset($doc->geo))
		{
			echo '<h2>' . get_text(['bin','map']) . '</h2>';
			echo '<p>' . get_text(['bin','map_lede']) . '</p>';
				
			echo '<div id="small_map"></div>';
			echo '<script>';
			echo 'create_map(small_map);';
			echo '</script>';	

			echo '<script>add_data(' . json_encode($doc->geo) . ');</script>';
		}
		
		if (isset($doc->aggregations))
		{
			foreach ($doc->aggregations as $key => $value_counts)
			{				
				switch ($key)
				{
					case 'lineage':		
						echo '<h3>' . get_text(['bin', $key]) . '</h3>';
						echo '<p>' . get_text(['bin', $key . '_lede']) . '</p>';

						$dot = make_graph($value_counts);	
						echo "<!-- \n";
						echo $dot;
						echo "-->\n";
						echo '<div id="dot" data-dot="' . urlencode($dot) . '" >';
						echo '<div id="dot-image"></div>';
						echo '</div>';
						break;						
						
					default:
						echo '<h3>' . get_text(['bin', $key]) . '</h3>';
						echo '<p>' . get_text(['bin', $key . '_lede']) . '</p>';

						echo '<dl>';
						foreach ($value_counts as $value => $count)
						{
							echo '<dt>' . $value  . '</dt>';
							echo '<dd>' . $count . '</dd>';
						}
						echo '</dl>';
						break;
				}
			}
		}
		
		if (isset($doc->collections))
		{
			foreach ($doc->collections as $key => $values)
			{
				echo '<h3>' . get_text(['bin', $key]) . '</h3>';
				echo '<p>' . get_text(['bin', $key . '_lede']) . '</p>';

				echo '<ul class="column_list">';
				foreach ($values as $value)
				{
					echo '<li>';
					echo identifier_panel_link($key, $value);
					echo '</li>';
				}
				echo '</ul>';
			}
		}
		
		if (isset($doc->images))
		{
			echo '<h3>' . get_text(['bin', 'images']) . '</h3>';
			echo '<p>' . get_text(['bin', 'images_lede']) . '</p>';

			echo '<div class="gallery">';
			echo '<ul>';
			foreach ($doc->images as $image)
			{
				echo '<li>';
				echo '<div>' . $image->title . '</div>';
				echo '<img onclick="show_panel_snippet(&quot;api.php?image=' . urlencode($image->url) . '&format=html&quot;)" src="' . $image->url  . '">';
				echo '</li>';
			}
			echo '<!-- need this to avoid distorting last image -->
    <li></li>';
			echo '</ul>';
			echo '</div>';
		}
		
		
		echo '</div>';
		echo '</div>';	
		
		echo '<script type="text/javascript" src="js/viz.js"></script>';
		
		echo '<script>
			var dot_element = document.getElementById("dot");
			if (dot_element) {
				var dot = decodeURIComponent(dot_element.dataset.dot).replace(/\+/g, " ");
				var graph = Viz(dot, "svg", "dot");
				document.getElementById("dot-image").innerHTML = graph;
			}
		</script>';

		
		html_end();			
	}
	else
	{
		default_display("$id not found");
	}
	

}

//----------------------------------------------------------------------------------------
function display_map ($filter = "")
{
	html_start();

	echo '<div class="main_with_map">';
	echo '<div id="map"></div>';
	echo '</div>';

	echo '<div class="side">
				<div id="maphits"></div>
			</div>';		
 		
	echo '<script>
			create_large_map("map", true, "' . $filter . '");
		</script>';

	html_end();		
}


//----------------------------------------------------------------------------------------
function display_taxonomy ($taxid = 713, $k = 40)
{
	// we need id and name
	$taxon_obj = get_taxon_from_taxid($taxid);

	html_start();
	
	echo '<script>
	var curnode_id = \'' . $taxon_obj->id . '\';	
	</script>';

	echo '<div class="content">';
	
	echo '<div class="tree">';
	echo get_taxonomy_subtree($taxon_obj->id, $k);
	echo '</div>';
	
	echo '<div class="taxonomy">';
	
	echo '<h2 id="taxon_name"></h2>';
	echo '<div id="taxon_link"></div>';
	echo '<div id="taxon_info"></div>';
	
	echo '<h3>' . get_text(['taxonomy', 'map']) . '</h3>';
	echo '<p>'. get_text(['taxonomy', 'map_lede']) . '</p>';
	echo '<div id="filtered_map"></div>';
		
	echo '</div>'; // taxonomy
	
	echo '</div>'; // content
	
	echo '<script>
	
	// call this to reload page with a new taxon as the focus when user double clicks 
	// on a name
	function taxon_focus(id) {
		window.location.href = "taxon/id/" + id;
	}

	function taxon_info(id) {
		// toggle selection of node in tree
		document.getElementById("node" + curnode_id).classList.remove("active");	
		curnode_id = id;
		document.getElementById("node" + curnode_id).classList.add("active");	

		// display info on taxon
		document.getElementById("taxon_name").innerHTML = "";
		document.getElementById("taxon_link").innerHTML = "";
		document.getElementById("taxon_info").innerHTML = "";
		
		// remove data from map
		map_remove_data_layer(dataLayer);
		
		var url = "api.php?taxonid=" + id;
		
		fetch(url).then(
			function(response){
				if (response.status != 200) {
					console.log("Looks like there was a problem. Status Code: " + response.status);
					document.getElementById("info").innerHTML = "404";
					return;
				}
				
				response.json().then(function(data) {					
					//var html = JSON.stringify(data);
					
					var name = data.name; // consider how to format this
					document.getElementById("taxon_name").innerHTML = name;
					
					if (name.match(/BOLD/)) {
						var html = "<a href=\"bin/" + name + "\">" + name + "</a>";
						document.getElementById("taxon_link").innerHTML = html;
					}
					
					// map
					map_add_data_layer( "taxon:" + encodeURIComponent(data.name));
					
					if (data.spatialCoverage) {
						console.log(JSON.stringify(data.spatialCoverage));
						map_fit_bounds(data.spatialCoverage);
					} else {
						map_fit_bounds({"type":"Polygon","coordinates":[[[-180,90],[180,90],[180,-90],[-180,-90],[-180,90]]]});
					}
					
				});
				
		});
		
		
		// wiki-based information
		
		// BOLD taxonomy to Wikipedia via Wikidata
		var sparql = `SELECT *
WHERE
{
  ?item wdt:P3606 "` + id + `" .
  ?wikipedia_en schema:about ?item .
  ?wikipedia_en schema:isPartOf <https://en.wikipedia.org/> .
  BIND( REPLACE( STR(?wikipedia_en),"https://en.wikipedia.org/wiki/","" ) AS ?enwiki) .
}`;
		
		url = "https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=" + encodeURIComponent(sparql);
		
		fetch(url, {
			method: "GET",
    		headers: {
        	"Accept": "application/json",
    		}		
		}).then(
			function(response){
				if (response.status != 200) {
					console.log("Looks like there was a problem. Status Code: " + response.status);
					document.getElementById("taxon_info").innerHTML = "404";
					return;
				}
				
				response.json().then(function(data) {
					
					if (data.results.bindings) {
						var enwiki = data.results.bindings[0].enwiki.value;
						
						// get summary from dbpedia
						dbpedia_summary(enwiki, "taxon_info");
					}
					
				});
				
		});
		
	}
	
	function dbpedia_summary(wikipedia, element_id) {
		var url = "dbpedia_proxy.php?query=" + encodeURIComponent("DESCRIBE <http://dbpedia.org/resource/" + wikipedia + ">");
		
		fetch(url).then(
			function(response){
				if (response.status != 200) {
					console.log("Looks like there was a problem. Status Code: " + response.status);
					document.getElementById(element_id).innerHTML = "404";
					return;
				}
				
				response.json().then(function(data) {
					
					var html = "";
					for (var i in data) {
						if (data[i]["http://www.w3.org/2000/01/rdf-schema#comment"]) {	
							for (var j in data[i]["http://www.w3.org/2000/01/rdf-schema#comment"])	{	  			
								if (data[i]["http://www.w3.org/2000/01/rdf-schema#comment"][j].lang == "en") {
									html = 
									data[i]["http://www.w3.org/2000/01/rdf-schema#comment"][j].value 
									+ " " 
									+ "(from <a href=\"https://en.wikipedia.org/wiki/" + wikipedia + "\" target=\"_new\">Wikipedia</a>)"
									;
								}
							}
						}
					}
					if (html != "") {
					 document.getElementById(element_id).innerHTML = html;
					}
					
				});
				
		});
	}	
	
	create_large_map("filtered_map", false);
	
	// information 
	taxon_info(curnode_id);
	
	</script>';
	

	html_end();		
}	


//----------------------------------------------------------------------------------------
function display_blast()
{
	$title = get_text(['blast', 'title']);

	html_start($title);
	
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';

	echo '<div class="main">';
	
	echo '<h1>' . $title . '</h1>';
	echo '<p>' . get_text(['blast', 'lede']) . '</p>';
	
	echo '
	<div>
		<p>' . get_text(['blast', 'instruction']) . '</p>
		<textarea id="sequence">
  1 accttatatc taatgttcgg tgcatgagca ggtatagtag gtaccgcact tagaatatta
 61 attcgagttg aactaggtca accaggatca cttattggtg atgaccaaat ttataatgta
121 gtagtaacag ctcatgcttt cgtaataatt ttttttatag taataccaat tataattgga
181 ggatttggaa attgattagt tcctttaata ttaggagcac ctgatatagc tttcccacgt
241 ttaaataata tgagattttg actattacct ccatcattaa ctctactttt agcaagaaga
301 ttagtagaaa gaggagcagg aactggatga acagtttacc cacctttagc aggagctatt
361 gctcacgcag gaggatcggt ggatttaaca attttttcat tacacctagc aggtgtatct
421 tctattttag gagcaatcaa ttttattact acagtaatta atataaagtc cccaggaata
481 aagttagacc aattaccact atttgtatga gcagtagtaa ttactgcagt attactatta
541 ttatccttac cagtgttagc tggtgctatt acaatattat taactgatcg aaatattaat
601 acatcatttt ttgatccagc aggaggagga gatcctattt tatatcaaca tctattt    	
</textarea>
     </div>';
     
     echo '<div class="spacer"></div>';
     
echo '<div>
     	<button onclick="blast()">' . get_text(['blast', 'search']) . '</button>
     </div>';
     
     echo '<h2>' . get_text(['blast', 'results']) . '</h2>';
     
	echo '<div id="output" class="tree-table"></div>';     

	echo '</div>';
	
	
echo '<script>
		function blast() {
			document.getElementById("output").innerHTML	= "<progress></progress>";
								
			var sequence = document.getElementById("sequence").value;
			
			var doc = {};
			doc.sequence = sequence;
									
			var url = "api.php?tree&format=html";
			
			fetch(url, {
				method: "post",
				body: JSON.stringify(doc)
			}).then(
				function(response){
					if (response.status != 200) {
						console.log("Looks like there was a problem. Status Code: " + response.status);
						return;
					}
			
					response.text().then(function(data) {
						document.getElementById("output").innerHTML = data;
					});
				});
		}
	</script>';	

	html_end();		
}	


//----------------------------------------------------------------------------------------
// Dataset
function display_recordset($id)
{
	$doc = get_recordset($id);
	
	if ($doc)
	{	
		$title = sprintf(get_text(['recordset', 'title']), $doc->id);
		
		$filter = 'recordset:' . $id;
	
		html_start($title);
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
		
		echo '<div class="main">';
		
		echo '<h1>' . $title . '</h1>';

		echo '<p>' . get_text(['recordset', 'lede']) . '</p>';		
		
		// map
		echo '<h2>'. get_text(['recordset', 'map']) . '</h2>';
		echo '<p>'. get_text(['recordset', 'map_lede']) . '</p>';
		
		echo '<p><a href="map/filter=' . $filter . '" target="_new">' . get_text(['recordset', 'map_big']) . '</a></p>';

		echo '<div id="filtered_map"></div>';
		
		// images
		
		// barcodes
		
		
		echo '</div>';
		
		
		
	echo '<script>
			create_large_map("filtered_map", false, "' . $filter . '");';
			
	if (isset($doc->spatialCoverage))
	{
		echo 'map_fit_bounds(' . json_encode($doc->spatialCoverage) . ');';
	}
	
	echo '</script>';
		
		
		// scripts to support paginated browsing....
		
		html_end();
	}
	else
	{
		default_display("$id not found");
	}
}

//----------------------------------------------------------------------------------------
function main()
{
	global $config;

	$handled = false;
	
	// If no query parameters 
	if (count($_GET) == 0)
	{
		default_display();
		exit(0);
	}
	
	// Error message
	if (isset($_GET['error']))
	{	
		$error_msg = $_GET['error'];		
		default_display($error_msg);
		exit(0);			
	}	
	
	$limit = 100; // arbitrary limit on some queries
	
	// be flexible in input, either processid, barcode, or record
	if (!$handled)
	{		
		$barcode = '';
		if (isset($_GET['record']))
		{	
			$barcode = $_GET['record']; // consistent with https://portal.boldsystems.org
		} 
		elseif (isset($_GET['barcode']))
		{
			$barcode = $_GET['barcode'];
		}	
		elseif (isset($_GET['processid'])) 
		{
			$barcode = $_GET['processid'];
		}	
		
		if ($barcode != '')	
		{			
			if (!$handled)
			{
				display_barcode($barcode);
				$handled = true;
			}			
		}

	}	
	
	// be flexible in name of bin parameter
	if (!$handled)
	{
		$bin = '';		
		if (isset($_GET['bin'])) // consistent with https://portal.boldsystems.org
		{	
			$bin = $_GET['bin'];
		}
		if (isset($_GET['bin_uri']))
		{	
			$bin = $_GET['bin_uri'];
		}
		
		if ($bin != '')
		{
			if (!$handled)
			{
				display_bin($bin, $limit);
				$handled = true;
			}			
		}
		
	}
	
	if (!$handled)
	{
		if (isset($_GET['taxonid']))
		{
			$taxonid = $_GET['taxonid'];
							
			display_taxonomy($taxonid, 20);
			$handled = true;
		
		}
	}
	
	if (!$handled)
	{
		if (isset($_GET['taxonname']))
		{
			$name = $_GET['taxonname'];
			$rank = '';
			if (isset($_GET['rank']))
			{
				$rank = $_GET['rank'];
			}
			
			$taxon = get_taxon_from_name($name, $rank);
			
			if ($taxon)
			{							
				display_taxonomy($taxon->id, 20);
			}
			else
			{
				// Oops
				// bounce
				header('Location: ?error=Taxon "' . $name . '" not found' . "\n\n");
				exit(0);
			}
			$handled = true;
		
		}
	}
	
	if (!$handled)
	{
		if (isset($_GET['recordset']))
		{
			$recordset = $_GET['recordset'];
			display_recordset($recordset);
			$handled = true;		
		}
	}
	
	if (!$handled)
	{
		if (isset($_GET['map']))
		{
			// any other parameters?
			$filter = "";
			if (isset($_GET['filter']))
			{	
				$filter = $_GET['filter'];
			}
						
			display_map($filter);
			$handled = true;
		
		}
	}
	
	if (!$handled)
	{
		if (isset($_GET['blast']))
		{
			display_blast();
			$handled = true;
		
		}
	}
	
	
	if (!$handled)
	{
		default_display();
	}

}

main();

?>
