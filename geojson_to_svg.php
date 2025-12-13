<?php

// Convert GeoJSON to points on the zoom level 0 map tile

//----------------------------------------------------------------------------------------
/**
 * Convert lat/lon to pixel coordinates on a slippy-map Web Mercator image.
 * @param float $lat Latitude in degrees (WGS84)
 * @param float $lon Longitude in degrees (WGS84)
 * @param int $zoom Slippy map zoom level (0 => one 256x256 world tile)
 * @param int $tileSize Tile size in pixels (usually 256)
 * @return array{0: float, 1: float} [x, y] in pixels (fractional)
 */
function latLonToPixel(float $lat, float $lon, int $zoom = 0, int $tileSize = 256): array
{
    // Web Mercator latitude clamp
    $maxLat = 85.05112878;
    $lat = max(min($lat, $maxLat), -$maxLat);

    // Map size in pixels at this zoom
    $mapSize = $tileSize * (1 << $zoom); // 256 * 2^zoom

    // Normalize longitude to [0,1)
    $x = ($lon + 180.0) / 360.0;

    // Web Mercator projection for y, normalized to [0,1)
    $latRad = deg2rad($lat);
    $y = (1.0 - log(tan($latRad) + 1.0 / cos($latRad)) / M_PI) / 2.0;

    // Convert to pixels
    $pixelX = $x * $mapSize;
    $pixelY = $y * $mapSize;

    return [$pixelX, $pixelY];
}

//----------------------------------------------------------------------------------------
/**
 * If you want integer pixel positions for plotting:
 */
function latLonToPixelInt(float $lat, float $lon, int $zoom = 0, int $tileSize = 256): array
{
    [$x, $y] = latLonToPixel($lat, $lon, $zoom, $tileSize);

    // For an image, you'd typically floor or round. Choose what fits your drawing method.
    return [ (int) round($x), (int) round($y) ];
}

//----------------------------------------------------------------------------------------
// Convert coordinates in a GeoJSON FeatureCollection to SVG map on a
// map tile at zoom level 0. This gives us a small map of the world 
function geo_to_svg($geo)
{
	$xml = '<?xml version="1.0" encoding="UTF-8"?>
	<svg xmlns:xlink="http://www.w3.org/1999/xlink" 
	xmlns="http://www.w3.org/2000/svg" 
	width="256px" height="256px">
	<style type="text/css">
		circle {
			fill:#333399;
			opacity:0.85;
			stroke: #33CCFF;
			stoke-width: 1;
		}
	</style>
	<image x="0" y="0" width="256" height="256" xlink:href="images/' . '0.png"/>';
	
	foreach ($geo->features as $feature)
	{
		if ($feature->geometry->type == "Point")
		{
			$xy = latLonToPixelInt(
				$feature->geometry->coordinates[1],
				$feature->geometry->coordinates[0],
				);
				
			//$xml .= '   <use xlink:href="#dot" transform="translate(' . $xy[1] . ',' . $xy[0] . ')" />' . "\n";
			$xml .= '<circle cx="' . $xy[0] . '" cy="' . $xy[1] . '" r="4" />' . "\n";
	
		}
	}
	
	$xml .= '
		</svg>';
		
	return $xml;
}

?>