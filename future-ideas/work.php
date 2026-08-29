<?php

// -----------------------------------------------------------------------------
// The "work" entity — a scholarly work (journal article, preprint, citeable
// dataset, ...). Prototype of the entity-per-file pattern (see the reorg plan).
//
// Everything about a work lives here: fetch, construct-from-row, serialise.
// HTML rendering does NOT live here — that goes in views/work.php so the API
// fragment and the web UI share one renderer (the split that kills the current
// duplicate display_* functions).
//
// bold-view only READS work / work_identifier. Those tables are owned and
// populated by the citation ingestion repo (it owns the schema); this file
// never writes them, and URI minting (a writer concern) lives there, not here.
//
// Identity pattern (docs/citation-model.md): the canonical URI PK is the
// DOI-as-URI when a DOI exists, else a locally-minted URI. The DOI is a unique
// attribute, not a re-keyable PK. work/person/taxon_name all share this shape;
// when the other two exist, the shared bits get lifted into a base. One entity
// isn't enough to see the real shared shape, so it stays concrete for now.
//
// Explicit require, no autoloader. In the full reorg pg.php becomes lib/db.php.
// -----------------------------------------------------------------------------

require_once __DIR__ . '/../pg.php';   // provides global $db (Postgres connection)

class Work
{
	// --- contract fields (safe to serialise) ---
	public string  $uri;                       // canonical URI = PK
	public ?string $type            = null;    // journal-article | preprint | dataset | ...
	public ?string $container_title = null;    // journal / venue
	public ?int    $issued_year     = null;
	public ?string $doi             = null;    // set only when the URI is a DOI URI
	public ?array  $csl             = null;    // CSL-JSON display projection
	public array   $identifiers     = array(); // [{scheme, value, kind}, ...]

	// --- internal, NEVER serialised in the contract (cf. embedding on records) ---
	private ?array $raw = null;                // richest upstream blob (archival)

	// ---- fetch --------------------------------------------------------------

	// Return the Work with this id, or null if unknown. Accepts a canonical URI
	// or a DOI in any common form (bare / https / "doi:") — resolved to the URI.
	public static function get(string $id): ?Work
	{
		global $db;

		$uri = self::doi_to_uri($id);   // DOI -> canonical URI; other URIs pass through

		$sql = 'SELECT uri, type, container_title, issued_year, csl, raw
		        FROM work
		        WHERE uri = $1';

		$result = pg_query_params($db, $sql, array($uri));
		$row = $result ? pg_fetch_assoc($result) : false;
		if (!$row)
		{
			return null;
		}

		$work = self::from_row($row);
		$work->identifiers = self::fetch_identifiers($uri);
		return $work;
	}

	// ---- construct ----------------------------------------------------------

	// Build a Work from a `work` table row (associative array).
	public static function from_row(array $row): Work
	{
		$work = new Work();
		$work->uri             = $row['uri'];
		$work->type            = $row['type']            ?? null;
		$work->container_title = $row['container_title'] ?? null;
		$work->issued_year     = isset($row['issued_year']) ? (int) $row['issued_year'] : null;
		$work->csl             = self::json_or_null($row['csl'] ?? null);
		$work->raw             = self::json_or_null($row['raw'] ?? null);
		$work->doi             = self::uri_to_doi($work->uri);   // non-null only if URI is a DOI
		return $work;
	}

	// ---- serialise (contract) ----------------------------------------------

	// The public JSON projection. Deliberately omits `raw`, and lifts a couple
	// of display fields out of csl. Null fields are dropped for a tidy payload.
	public function to_array(): array
	{
		$out = array(
			'uri'             => $this->uri,
			'type'            => $this->type,
			'doi'             => $this->doi,
			'title'           => $this->csl['title'] ?? null,
			'container_title' => $this->container_title,
			'issued_year'     => $this->issued_year,
			'identifiers'     => $this->identifiers,
			'csl'             => $this->csl,
		);

		return array_filter($out, function ($v) { return $v !== null; });
	}

	// ---- identity helpers (reader side of the shared pattern) ---------------

	// Normalise a DOI (bare / https / "doi:") to its canonical lowercased URI.
	// Anything that isn't a recognisable DOI is returned unchanged (already a
	// minted or otherwise opaque URI).
	public static function doi_to_uri(string $id): string
	{
		$s = trim($id);

		if (preg_match('#^https?://doi\.org/#i', $s))
		{
			$s = preg_replace('#^https?://doi\.org/#i', '', $s);
		}
		elseif (preg_match('#^doi:#i', $s))
		{
			$s = preg_replace('#^doi:#i', '', $s);
		}
		elseif (!preg_match('#^10\.\d+/#', $s))
		{
			return $s;   // not a DOI in any form -> leave as-is
		}

		return 'https://doi.org/' . strtolower($s);
	}

	// Inverse: bare lowercased DOI if the URI is a DOI URI, else null.
	public static function uri_to_doi(string $uri): ?string
	{
		if (preg_match('#^https?://doi\.org/(10\..+)$#i', $uri, $m))
		{
			return strtolower($m[1]);
		}
		return null;
	}

	// ---- private ------------------------------------------------------------

	private static function fetch_identifiers(string $uri): array
	{
		global $db;

		$sql = 'SELECT scheme, value, kind
		        FROM work_identifier
		        WHERE work_uri = $1
		        ORDER BY scheme, value';

		$result = pg_query_params($db, $sql, array($uri));

		$identifiers = array();
		if ($result)
		{
			while ($row = pg_fetch_assoc($result))
			{
				$identifiers[] = array(
					'scheme' => $row['scheme'],
					'value'  => $row['value'],
					'kind'   => $row['kind'],
				);
			}
		}
		return $identifiers;
	}

	private static function json_or_null($value): ?array
	{
		if ($value === null || $value === '')
		{
			return null;
		}
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : null;
	}
}

?>
