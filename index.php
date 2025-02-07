<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/core.php');
//require_once (dirname(__FILE__) . '/taxonomy/taxonomy.php');

//----------------------------------------------------------------------------------------
function html_start()
{
	echo '<html>';
	
	echo '<head>';
	
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
	width:100vw;
	padding:1em;
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
//require_once (dirname(__FILE__) . '/taxonomy.css.inc.php');
require_once (dirname(__FILE__) . '/treetable.css.inc.php');

echo '</style>' . "\n";

	
echo '<script>' . "\n";

require_once (dirname(__FILE__) . '/panel.js.inc.php');

echo '</script>' . "\n";

	
	echo '</head>';
	
	echo '<body>';
	
	echo '<nav>
	<ul>
		<li><a href=".">Home</a></li>
		<!-- <li><a href="?taxon=713">Taxonomy</a></li> -->
		<li><a href="?map">Map</a></li>
		<li><a href="?blast">BLAST</a></li>
	</ul>
	</nav>';
	
	echo '<div class="content">';
}

//----------------------------------------------------------------------------------------
function html_end()
{
	echo '</div>'; // content
	echo '</body>';
	echo '</html>';
}

//----------------------------------------------------------------------------------------
function default_display()
{
	html_start();

	echo '<div class="main">';
	echo '</div>';

	html_end();	
}

//----------------------------------------------------------------------------------------
function display_barcode($id)
{
	$doc = get_barcode($id);
	
	if ($doc)
	{
		html_start();
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
		
		echo '<div class="main">';
		
		echo '<h1>' . $doc->processid . '</h1>';
		
		$keys = array('identification', 'insdc_acs', 'bin_uri');
		
		echo '<h3>Data</h3>';
		echo '<dl>';
		foreach ($keys as $key)
		{
			if (isset($doc->{$key}))
			{
				echo '<dt>' . $key . '</dt>';
				echo '<dd>' . identifier_link($key, $doc->{$key}) . '</dd>';
			}
		}
		echo '</dl>';
		
		if (1)
		{
			if (isset($doc->lineage))
			{
				echo '<ul>';
				foreach ($doc->lineage as $taxon)
				{
					echo '<li>' . $taxon . '</li>';
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
			echo '<h3>Map</h3>';
			
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
			echo '<h3>Images</h3>';
			echo '<div class="gallery">';
			echo '<ul>';
			foreach ($doc->images as $image)
			{
				echo '<li>';
				echo '<img onclick="show_panel_snippet(&quot;api.php?image=' . urlencode($image->url) . '&format=html&quot;)" src="' . $image->url  . '">';
				echo '</li>';
			}
			echo '<!-- need this to avoid distorting last image -->
    <li></li>';
			echo '</ul>';
		}
		
		echo '<h3>Related barcodes</h3>
	 	<div id="output" class="tree-table"></div>';     
		
		echo '<h3>Alignment</h3>
	 	<div id="alignment" class="alignment"></div>';     
		
		
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
		html_start();
		echo '<h1>Error</h1>';
		echo '<p>' . "$id not found!" . '</p>';
		html_end();	
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
		
	
		$dot .= 'node [fontsize="10,"';
		
		if (isset($node->count))
		{
			$label .= ' (' . $node->count . ')';
			
			$dot .= 'fillcolor="yellow",style="filled"';
		}
		else
		{
			$dot .= 'fillcolor="white",style="filled"';
		}
		
		
		$dot .= 'label="' . addslashes($label) . '"] ' . $node->id . ";\n";
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
function display_bin ($id)
{
	$doc = get_bin($id);
	
	if ($doc)
	{
		html_start();
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
		
		echo '<div class="main">';
		
		echo '<h1>' . $doc->bin_uri . '</h1>';
			
		echo '<div id="small_map"></div>';
		echo '<script>';
		echo 'create_map(small_map);';
		echo '</script>';	


		if (isset($doc->geo))
		{
			echo '<script>add_data(' . json_encode($doc->geo) . ');</script>';
		}
		
		if (isset($doc->aggregations))
		{
			foreach ($doc->aggregations as $key => $value_counts)
			{				
				switch ($key)
				{
					case 'lineage':		
						echo '<h3>' . $key . '</h3>';				
						$dot = make_graph($value_counts);	
						echo "<!-- \n";
						echo $dot;
						echo "-->\n";
						echo '<div id="dot" data-dot="' . urlencode($dot) . '" >';
						echo '<div id="dot-image"></div>';
						echo '</div>';
						break;						
						
					default:
						echo '<h3>' . $key . '</h3>';
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
				echo '<h3>' . $key . '</h3>';
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
			echo '<h3>Images</h3>';
			echo '<div class="gallery">';
			echo '<ul>';
			foreach ($doc->images as $image)
			{
				echo '<li>';
				echo '<img onclick="show_panel_snippet(&quot;api.php?image=' . urlencode($image->url) . '&format=html&quot;)" src="' . $image->url  . '">';
				echo '</li>';
			}
			echo '<!-- need this to avoid distorting last image -->
    <li></li>';
			echo '</ul>';
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
		html_start();
		echo '<h1>Error</h1>';
		echo '<p>' . "$id not found!" . '</p>';
		html_end();	
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
			create_large_map("map", "' . $filter . '");
		</script>';

	html_end();		
}

/*
//----------------------------------------------------------------------------------------
function display_taxonomy ($taxon = 713)
{
	html_start();

	echo '<div class="main">';
	
	echo '<div class="tree">';
	echo get_taxonomy_subtree($taxon, 40);
	echo '</div>';
	
	echo '</div>';
	
	echo '<script>
	
	// call this to reload page with a new taxon as the focus when user double clicks 
	// on a name
	function taxon_focus(id) {
		window.location.href = "?taxon=" + id;
	}

	function taxon_info(id) {
		//alert(id);
	}
	
	echo </script>';
	

	html_end();		
}	
*/

//----------------------------------------------------------------------------------------
function display_blast()
{
	html_start();
	
		
		// side panel
		echo '<div id="panel">
<a href="javascript:close_panel()">╳</a>
<div id="info"></div>
</div>';
	

	echo '<div class="main">';

	echo '<h1>"BLAST" a sequence</h1>';
	echo '
	<div>
		<p>Search for the nearest sequences in k-mer vector space and build a neighbour-joining tree.</p>
		<p>Enter a DNA sequence:</p>
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
     	<button onclick="blast()">BLAST</button>
     </div>
     
     <h2>Output</h2>
	 <div id="output" class="tree-table"></div>';     

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
				display_bin($bin);
				$handled = true;
			}			
		}
		
	}
	
	if (!$handled)
	{
		if (isset($_GET['taxon']))
		{
			$taxon = $_GET['taxon'];
							
			//display_taxonomy($taxon);
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
