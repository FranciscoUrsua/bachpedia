<?php
/**
 * Descarga todas las obras de Johann Sebastian Bach desde Open Opus
 * y genera un CSV local con la estructura:
 * BWV,Title,Year,Instrumentation,Key,Genre,Collection,Comments,Sources,Duration,OpenOpusID
 *
 * Requisitos: PHP 7.4+ con cURL habilitado.
 */

const API_BASE = 'https://api.openopus.org';
const OUT_CSV  = 'bach_openopus.csv';

function getJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: BachpediaImport/1.0 (+https://bachpedia.com)'
        ],
        CURLOPT_ENCODING       => '' // acepta gzip/deflate si el server lo ofrece
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        fwrite(STDERR, "cURL error on $url: " . curl_error($ch) . PHP_EOL);
        curl_close($ch);
        return null;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        fwrite(STDERR, "HTTP $status for $url\n");
        return null;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "JSON decode error for $url: " . json_last_error_msg() . PHP_EOL);
        return null;
    }
    return $data;
}

/** Devuelve el primer valor no vacío para las claves dadas. */
function pick(array $arr, array $keys) {
    foreach ($keys as $k) {
        if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) return $arr[$k];
    }
    return null;
}

/** Convierte "HH:MM:SS" o "MM:SS" a segundos. Si ya es número, lo deja. */
function durationToSeconds($val): string {
    if ($val === null || $val === '') return '';
    if (is_numeric($val)) return (string) intval($val);
    if (!is_string($val)) return '';
    $parts = explode(':', $val);
    if (count($parts) === 2) { // MM:SS
        [$m, $s] = $parts;
        if (is_numeric($m) && is_numeric($s)) return (string) (intval($m)*60 + intval($s));
    } elseif (count($parts) === 3) { // HH:MM:SS
        [$h,$m,$s] = $parts;
        if (is_numeric($h) && is_numeric($m) && is_numeric($s)) return (string) (intval($h)*3600 + intval($m)*60 + intval($s));
    }
    return '';
}

/** Normaliza un objeto "work" de Open Opus a nuestras columnas. */
function normalizeWork(array $w): array {
    // BWV: preferimos campos tipo 'catalogue' + 'catalogue_number'
    $catalogue = strtoupper((string) (pick($w, ['catalogue']) ?? ''));
    $catnum    = pick($w, ['catalogue_number','catalog_number','catalogue_nr','catalogue_no']);
    $bwv = '';
    if ($catalogue === 'BWV' && $catnum !== null) {
        $bwv = "BWV " . trim((string)$catnum);
    } elseif ($catnum !== null && stripos((string)$catnum, 'BWV') === 0) {
        // a veces viene ya como "BWV 1007"
        $bwv = trim((string)$catnum);
    } else {
        // último recurso: intentar extraer "BWV \d+" del título o subtítulo
        $maybe = '';
        foreach (['title','subtitle','name'] as $k) {
            if (!empty($w[$k]) && preg_match('/BWV\\s*\\d+[a-z]?/i', $w[$k], $m)) { $maybe = $m[0]; break; }
        }
        $bwv = $maybe;
    }

    $titleMain = pick($w, ['title','name']) ?? '';
    // Comentarios: podemos usar 'subtitle' o 'notes' si existen
    $comments  = trim(implode(' — ', array_filter([
        pick($w, ['subtitle','sub_title','description','notes'])
    ], fn($x) => $x !== null && $x !== '')));

    // Año: varios nombres posibles, sin inventar
    $year = pick($w, ['year','composition_year','composed','date','composition_date']) ?? '';
    if (is_array($year)) $year = ''; // si viniera como objeto, preferimos dejarlo vacío

    // Instrumentación (no siempre disponible en Open Opus)
    $instr = pick($w, ['instrumentation','instruments']) ?? '';

    // Tonalidad
    $key   = pick($w, ['key','tonality']) ?? '';

    // Género (a veces string, a veces objeto con 'name')
    $genre = pick($w, ['genre']) ?? '';
    if (is_array($genre)) {
        $genre = pick($genre, ['name','title']) ?? '';
    }

    // Colección (cuando un ítem pertenece a set/collection/cycle)
    $collection = pick($w, ['collection','collection_name','set','cycle','parent_work']) ?? '';
    if (is_array($collection)) {
        $collection = pick($collection, ['name','title']) ?? '';
    }

    // Duración a segundos (si no hay, vacío)
    $duration = durationToSeconds(pick($w, ['duration','duration_sec','duration_seconds','avg_duration','time']));

    // Open Opus ID
    $ooid = pick($w, ['id','work_id']) ?? '';

    return [
        'BWV'           => $bwv,
        'Title'         => $titleMain,
        'Year'          => $year,
        'Instrumentation'=> $instr,
        'Key'           => $key,
        'Genre'         => $genre,
        'Collection'    => $collection,
        'Comments'      => $comments,
        'Sources'       => 'OpenOpus',
        'Duration'      => $duration,
        'OpenOpusID'    => $ooid
    ];
}

/** Intenta localizar el ID del compositor por búsqueda textual. */
function findComposerId(string $query): ?int {
    $url = API_BASE . '/composer/list/search/' . rawurlencode($query) . '.json';
    $json = getJson($url);
    if (!$json) return null;
    // tolerar diferentes nombres de clave
    $list = null;
    foreach (['composers','composer','results','data'] as $k) {
        if (isset($json[$k]) && is_array($json[$k])) { $list = $json[$k]; break; }
    }
    if (!$list) return null;
    foreach ($list as $c) {
        $name = pick($c, ['complete_name','name','full_name']) ?? '';
        if (stripos($name, 'Johann Sebastian Bach') !== false) {
            $id = pick($c, ['id','composer_id']);
            return $id !== null ? intval($id) : null;
        }
    }
    // si no matchea exactamente, devolver el primero
    $first = $list[0] ?? null;
    if ($first) {
        $id = pick($first, ['id','composer_id']);
        return $id !== null ? intval($id) : null;
    }
    return null;
}

/** Descarga todas las obras del compositor por ID y devuelve array de works. */
function fetchWorksByComposerId(int $composerId): array {
    // Endpoint típico: /work/list/composer/{id}/genre/all.json
    $url = API_BASE . '/work/list/composer/' . $composerId . '/genre/all.json';
    $json = getJson($url);
    if (!$json) return [];
    // los datos pueden venir como 'works' o 'work'
    $works = null;
    foreach (['works','work','data','result','items'] as $k) {
        if (isset($json[$k]) && is_array($json[$k])) { $works = $json[$k]; break; }
    }
    return $works ?? [];
}

function writeCsv(array $rows, string $path): void {
    $fh = fopen($path, 'w');
    if ($fh === false) {
        throw new RuntimeException("No puedo escribir $path");
    }
    $header = ['BWV','Title','Year','Instrumentation','Key','Genre','Collection','Comments','Sources','Duration','OpenOpusID'];
    fputcsv($fh, $header);
    foreach ($rows as $row) {
        $out = [];
        foreach ($header as $col) $out[] = $row[$col] ?? '';
        fputcsv($fh, $out);
    }
    fclose($fh);
}

///// MAIN /////

echo "Buscando ID de compositor para 'Johann Sebastian Bach'...\n";
$composerId = findComposerId('Johann Sebastian Bach');
if (!$composerId) {
    fwrite(STDERR, "No se pudo obtener el ID del compositor. Revisa tu conexión o el endpoint.\n");
    exit(1);
}
echo "Composer ID = $composerId\n";

echo "Descargando obras de Bach desde Open Opus...\n";
$works = fetchWorksByComposerId($composerId);
if (!$works) {
    fwrite(STDERR, "No se han recibido obras. El endpoint puede haber cambiado o devuelto vacío.\n");
    exit(1);
}

echo "Normalizando y generando CSV (" . OUT_CSV . ")...\n";
$rows = [];
foreach ($works as $w) {
    if (!is_array($w)) continue;
    $rows[] = normalizeWork($w);
}

writeCsv($rows, OUT_CSV);
echo "Listo. Filas exportadas: " . count($rows) . "\n";
echo "Archivo: " . OUT_CSV . "\n";
