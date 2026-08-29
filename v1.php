<?php

// -----------------------------------------------------------------------------
// Bold View API v1 — front controller.
//
// This is the versioned, documented, tested contract surface (see docs/api-v1.md
// and openapi.yaml). It is a routing + representation layer over the existing
// get_* functions in core.php; it does not reimplement the data layer.
//
// Routes are dispatched from the path after /v1/ (see .htaccess, which rewrites
//   /v1/<path>  ->  v1.php?path=<path>
// so a request to /v1/records/FOO arrives here as $_GET['path'] = "records/FOO").
//
// v1 conventions applied here (and NOT in the legacy api.php):
//   - the HTTP status code is authoritative (send_doc already maps $doc->status);
//   - errors use the envelope { status, message };
//   - contract-excluded fields (e.g. the raw embedding) are never serialised.
// -----------------------------------------------------------------------------

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/api_utilities.php');
require_once(dirname(__FILE__) . '/core.php');

// Fields that exist on the internal objects but are deliberately outside the v1
// contract and must never be serialised (docs/api-v1.md "Fields excluded").
$V1_HIDDEN_FIELDS = array('embedding');

// -----------------------------------------------------------------------------
// Response helpers
// -----------------------------------------------------------------------------

// Send a success document. $doc->status is the (legacy, in-body) status; the
// same value drives the real HTTP status via send_doc().
function v1_send($doc, $status = 200)
{
	$doc->status = $status;
	send_doc($doc);
}

// Send an error using the v1 envelope { status, message } with a real HTTP code.
function v1_error($status, $message)
{
	$doc = new stdclass;
	$doc->status  = $status;
	$doc->message = $message;
	send_doc($doc);
}

// Strip contract-excluded fields from a record-like object in place.
function v1_clean_record($obj)
{
	global $V1_HIDDEN_FIELDS;

	foreach ($V1_HIDDEN_FIELDS as $field)
	{
		if (isset($obj->{$field}))
		{
			unset($obj->{$field});
		}
	}

	return $obj;
}

// -----------------------------------------------------------------------------
// Route handlers
// -----------------------------------------------------------------------------

// GET /v1/records/{processid}  -> one barcode record
function v1_get_record($processid)
{
	$doc = get_barcode($processid);

	if (!$doc)
	{
		v1_error(404, "No record with processid '" . $processid . "'");
	}

	v1_clean_record($doc);
	v1_send($doc, 200);
}

// -----------------------------------------------------------------------------
// Router
// -----------------------------------------------------------------------------

function v1_main()
{
	$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

	$path = isset($_GET['path']) ? $_GET['path'] : '';
	$path = trim($path, '/');

	$segments = ($path === '') ? array() : array_map('urldecode', explode('/', $path));
	$n = count($segments);

	// GET /v1/records/{processid}
	if ($method === 'GET' && $n === 2 && $segments[0] === 'records')
	{
		v1_get_record($segments[1]);
		return;
	}

	// Everything else is not (yet) a route. Phase-1 routes still to add:
	//   records/{id}/images, records/{id}/related, bins/{id}, bins/{id}/sequences,
	//   taxa, taxa/{id}, recordsets/{id}[/records|/images], barcodes, images,
	//   POST search/sequence  (see docs/api-v1.md).
	v1_error(404, "Unknown v1 route: /" . $path);
}

v1_main();

?>
