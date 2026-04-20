<?php

// Geographic alpha-shape browser. Drills down through the BOLD admin
// hierarchy: country → province/state → region → sector → site. Mirrors
// the API exposed by api.php?hull=...

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/language.php');
require_once (dirname(__FILE__) . '/core.php');

global $config;

$country  = isset($_GET['country'])  ? trim($_GET['country'])  : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$region   = isset($_GET['region'])   ? trim($_GET['region'])   : '';
$sector   = isset($_GET['sector'])   ? trim($_GET['sector'])   : '';
$site     = isset($_GET['site'])     ? trim($_GET['site'])     : '';
$marker   = isset($_GET['marker']) && $_GET['marker'] !== '' ? trim($_GET['marker']) : 'COI-5P';
$alpha    = isset($_GET['alpha'])  && $_GET['alpha']  !== '' ? (float)$_GET['alpha'] : 0.3;
$hlimit   = isset($_GET['limit'])  && $_GET['limit']  !== '' ? (int)$_GET['limit']   : 10000;

// Build the breadcrumb path. Each entry has a label, the URL parameters
// needed to navigate to it, and a flag for whether it's the current page.
$levels = [
	['key' => 'country',  'label' => 'Country',  'value' => $country],
	['key' => 'province', 'label' => 'Province', 'value' => $province],
	['key' => 'region',   'label' => 'Region',   'value' => $region],
	['key' => 'sector',   'label' => 'Sector',   'value' => $sector],
	['key' => 'site',     'label' => 'Site',     'value' => $site],
];

function geo_url($params)
{
	$qs = http_build_query(array_filter($params, function($v) { return $v !== '' && $v !== null; }));
	return 'geo.php?' . $qs;
}

function build_breadcrumb_html($levels, $marker, $alpha)
{
	$crumbs   = [];
	$crumbs[] = '<a href="geo.php">All countries</a>';

	$accum = ['marker' => $marker];
	if ($alpha != 0.3) $accum['alpha'] = $alpha;

	foreach ($levels as $i => $lvl)
	{
		if ($lvl['value'] === '') break;
		$accum[$lvl['key']] = $lvl['value'];
		// last crumb shouldn't be a link
		$is_last = true;
		for ($j = $i + 1; $j < count($levels); $j++)
		{
			if ($levels[$j]['value'] !== '') { $is_last = false; break; }
		}
		if ($is_last)
		{
			$crumbs[] = '<span>' . htmlspecialchars($lvl['value']) . '</span>';
		}
		else
		{
			$crumbs[] = '<a href="' . htmlspecialchars(geo_url($accum)) . '">' . htmlspecialchars($lvl['value']) . '</a>';
		}
	}

	return '<nav class="breadcrumb">' . implode(' &rsaquo; ', $crumbs) . '</nav>';
}

// Build the API URLs for the in-page JS fetch.
$api_params = ['hull' => $country !== '' ? $country : ''];
foreach (['province', 'region', 'sector', 'site'] as $k)
{
	if ($$k !== '') $api_params[$k] = $$k;
}
$api_params['marker'] = $marker;
$api_params['alpha']  = $alpha;
$api_params['limit']  = $hlimit;

// children listing always uses the same path; the API computes the next
// admin level down from what's specified.
$children_params = $api_params;
unset($children_params['alpha']);
$children_params['children'] = 1;
// when no country is set, drop the empty hull param so the API treats it
// as a top-level country listing
if ($country === '')
{
	unset($api_params['hull']);
	$children_params['hull'] = '';
}

$hull_api_url     = 'api.php?' . http_build_query($api_params);
$children_api_url = 'api.php?' . http_build_query($children_params);

// page title
$title_parts = [];
foreach ($levels as $lvl) if ($lvl['value'] !== '') $title_parts[] = $lvl['value'];
$title = empty($title_parts) ? 'Geographic hulls' : ('Geographic hull: ' . implode(' / ', $title_parts));

?><html>
<head>
<meta charset="utf-8" />
<base href="<?php echo $config['web_root']; ?>" />
<title><?php echo htmlspecialchars($title); ?></title>
<?php require_once(dirname(__FILE__) . '/map.inc.php'); ?>
<style>
<?php
require_once (dirname(__FILE__) . '/root.css.inc.php');
require_once (dirname(__FILE__) . '/body.css.inc.php');
require_once (dirname(__FILE__) . '/nav.css.inc.php');
?>

.geo-content {
	display: flex;
	gap: 1em;
	padding: 1em;
	height: calc(100vh - var(--nav-height));
	box-sizing: border-box;
}

.geo-side {
	width: 320px;
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.geo-main {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-width: 0;
}

#geo_map {
	flex: 1;
	min-height: 400px;
	background: gray;
	border-radius: 0.5em;
}

.geo-meta {
	padding: 0.5em 0;
	font-size: 0.9em;
	color: #555;
}

.breadcrumb {
	font-size: 0.95em;
	margin-bottom: 0.75em;
}

.geo-children {
	list-style: none;
	padding: 0 0.5em 0 0;
	margin: 0 0 0.5em 0;
	overflow-y: auto;
	flex: 1;
	min-height: 0;
}

.geo-children li {
	padding: 0.4em 0.6em;
	border-bottom: 1px solid var(--input-border, #eee);
}

.geo-children li a {
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.geo-form {
	display: flex;
	gap: 0.5em;
	align-items: center;
	margin-bottom: 0.75em;
	font-size: 0.9em;
}

.geo-form label { color: #555; }
.geo-form input { width: 5em; }

.api-links {
	margin-top: 0.75em;
	font-size: 0.85em;
}

.api-links a { display: block; word-break: break-all; }
</style>
</head>
<body>

<nav>
<ul>
	<li><a href=".">Home</a></li>
</ul>
</nav>

<div class="geo-content">

<aside class="geo-side">
	<?php echo build_breadcrumb_html($levels, $marker, $alpha); ?>

	<form class="geo-form" method="get" action="geo.php">
<?php
		// preserve current path
		foreach (['country','province','region','sector','site'] as $k)
		{
			if ($$k !== '')
			{
				echo '<input type="hidden" name="' . $k . '" value="' . htmlspecialchars($$k) . '">';
			}
		}
?>
		<label>marker
			<input type="text" name="marker" value="<?php echo htmlspecialchars($marker); ?>">
		</label>
		<label>alpha
			<input type="number" step="0.05" min="0" max="1" name="alpha" value="<?php echo htmlspecialchars((string)$alpha); ?>">
		</label>
		<label>limit
			<input type="number" step="1000" min="0" name="limit" value="<?php echo htmlspecialchars((string)$hlimit); ?>">
		</label>
		<button type="submit">Apply</button>
	</form>

	<div class="geo-meta" id="geo_meta">Loading…</div>

	<h3 style="margin:0.5em 0 0.25em;">Areas</h3>
	<ul class="geo-children" id="geo_children">
<?php
	if ($country === '')
	{
		// Top level: render the static country list server-side. Avoids the
		// 50-second top-level scan, and means there's something to see even
		// if JS fails.
		foreach (get_geo_countries() as $c)
		{
			$href = 'geo.php?country=' . urlencode($c->iso);
			echo '<li><a href="' . htmlspecialchars($href) . '">'
				. htmlspecialchars($c->iso) . ' &mdash; ' . htmlspecialchars($c->name)
				. '</a></li>' . "\n";
		}
	}
	else
	{
		echo '<li>Loading&hellip;</li>';
	}
?>
	</ul>

	<div class="api-links">
		<strong>API</strong>
		<a href="<?php echo htmlspecialchars($hull_api_url); ?>" target="_blank">hull GeoJSON</a>
		<a href="<?php echo htmlspecialchars($children_api_url); ?>" target="_blank">children JSON</a>
	</div>
</aside>

<main class="geo-main">
	<div id="geo_map"></div>
</main>

</div>

<script>
(function() {
	// Render the map. create_map() comes from map.inc.php.
	create_map('geo_map');

	var hullLayer = null;

	function clearHullLayer() {
		if (hullLayer) {
			map.removeLayer(hullLayer);
			hullLayer = null;
		}
	}

	var bboxStyle = { color: '#0b3d7d', weight: 2, fillColor: '#3b78c2', fillOpacity: 0.20 };
	var hullStyle = { color: '#ffffff', weight: 2, fillColor: '#b07fd9', fillOpacity: 0.55 };

	function renderHull(fc) {
		clearHullLayer();

		var group = L.featureGroup();

		// bbox: read from the GeoJSON `bbox` member (RFC 7946 §5) and paint
		// it ourselves as a rectangle. Drawn first so the hull sits on top.
		var bboxBounds = null;
		if (Array.isArray(fc.bbox) && fc.bbox.length >= 4) {
			var b = fc.bbox; // [west, south, east, north]
			bboxBounds = [[b[1], b[0]], [b[3], b[2]]];
			L.rectangle(bboxBounds, bboxStyle).addTo(group);
		}

		// hull
		L.geoJson(fc, {
			style: hullStyle,
			pointToLayer: function(f, latlng) {
				return L.circleMarker(latlng, {
					radius: 5,
					color: '#ffffff',
					fillColor: '#b07fd9',
					fillOpacity: 0.85,
					weight: 1
				});
			}
		}).addTo(group);

		group.addTo(map);
		hullLayer = group;

		var bounds = bboxBounds ? L.latLngBounds(bboxBounds) : null;
		if (!bounds) {
			try { bounds = group.getBounds(); } catch (e) {}
		}
		if (bounds && bounds.isValid()) {
			map.fitBounds(bounds, { padding: [20, 20] });
		}
	}

	function fetchJson(url) {
		return fetch(url).then(function(r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		});
	}

	var hullUrl     = <?php echo json_encode($hull_api_url); ?>;
	var childrenUrl = <?php echo json_encode($children_api_url); ?>;
	var hasCountry  = <?php echo $country === '' ? 'false' : 'true'; ?>;

	// Children list with drill-down links
	var childParamMap = {
		'country_iso':     'country',
		'province/state':  'province',
		'region':          'region',
		'sector':          'sector',
		'site':            'site'
	};

	function buildChildHref(childLevel, name) {
		var params = new URLSearchParams(window.location.search);
		// drop alpha if it equals default — keep URLs short for the common case
		var key = childParamMap[childLevel];
		if (!key) return null;
		params.set(key, name);
		return 'geo.php?' + params.toString();
	}

	function renderChildren(doc) {
		var ul = document.getElementById('geo_children');
		ul.innerHTML = '';
		if (!doc.children || doc.children.length === 0) {
			ul.innerHTML = '<li><em>No further levels</em></li>';
			return;
		}
		doc.children.forEach(function(c) {
			var li = document.createElement('li');
			var href = buildChildHref(doc.child_level, c.name);
			if (href) {
				var a = document.createElement('a');
				a.href = href;
				a.textContent = c.name;
				li.appendChild(a);
			} else {
				var span = document.createElement('span');
				span.textContent = c.name;
				li.appendChild(span);
			}
			ul.appendChild(li);
		});
	}

	function renderMeta(props) {
		var el = document.getElementById('geo_meta');
		if (!props) { el.textContent = 'No matching points.'; return; }
		var label = props.count.toLocaleString() + ' points';
		if (props.sampled) label += ' (sample, limit ' + props.limit.toLocaleString() + ')';
		label += ' · marker ' + props.marker + ' · alpha ' + props.alpha;
		el.textContent = label;
	}

	if (hasCountry) {
		fetchJson(hullUrl).then(function(fc) {
			renderHull(fc);
			var hullFeature = (fc.features || []).find(function(f) { return f.properties && f.properties.role === 'hull'; });
			renderMeta(hullFeature ? hullFeature.properties : null);
		}).catch(function(e) {
			document.getElementById('geo_meta').textContent = 'No hull available (' + e.message + ').';
		});

		// Children for inner levels are dynamic — fetch them. Top-level
		// country list is rendered server-side and doesn't need a fetch.
		fetchJson(childrenUrl).then(renderChildren).catch(function(e) {
			document.getElementById('geo_children').innerHTML = '<li><em>Could not load list (' + e.message + ').</em></li>';
		});
	} else {
		document.getElementById('geo_meta').textContent = 'Pick a country to see its alpha shape.';
	}
})();
</script>

</body>
</html>
