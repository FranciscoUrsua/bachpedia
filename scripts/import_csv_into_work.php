<?php
/**
 * Importa BWV desde CSV a la tabla Work (con campo instrumentation).
 * Uso:
 *   php import_bach_work.php /ruta/bach_bwvs_1_25_extended.csv
 */

declare(strict_types=1);

// ======= CONFIG DB =======
$DB_HOST = '127.0.0.1';
$DB_NAME = 'tu_base';
$DB_USER = 'tu_usuario';
$DB_PASS = 'tu_password';
$DB_CHARSET = 'utf8mb4';

// ======= CSV PATH ========
$csvPath = $argv[1] ?? '';
if (!$csvPath || !is_readable($csvPath)) {
    fwrite(STDERR, "Uso: php import_bach_work.php /ruta/archivo.csv\n");
    exit(1);
}

// ======= HELPERS ========
function nullIfEmpty(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

/**
 * Convierte duración a minutos (int).
 * Acepta:
 *  - "25" o "25 min"
 *  - "1:30:00" (h:m:s), "42:10" (m:s)
 *  - "1 h 45 m", "90m", "2h"
 */
function parseDurationToMinutes(?string $s): ?int {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '') return null;

    // hh:mm:ss o mm:ss
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m)) {
        if ($m[3] !== '') { // h:m:s
            $h = (int)$m[1];
            $mi = (int)$m[2];
            $se = (int)$m[3];
            return $h * 60 + $mi + (int)round($se / 60);
        }
        // mm:ss
        $mi = (int)$m[1];
        $se = (int)$m[2];
        return $mi + (int)round($se / 60);
    }

    // "1 h 30 m", "2h", "95m"
    if (preg_match('/(?i)^\s*(?:(\d+)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?\s*$/', $s, $m) && ($m[1] !== '' || $m[2] !== '')) {
        $h = $m[1] !== '' ? (int)$m[1] : 0;
        $mi = $m[2] !== '' ? (int)$m[2] : 0;
        return $h * 60 + $mi;
    }

    // "25", "25 min"
    if (preg_match('/^\s*(\d+)\s*(?:m|min|minutes?)?$/i', $s, $m)) {
        return (int)$m[1];
    }

    return null; // no parseable
}

/** Trunca con seguridad a longitud máxima (UTF-8) */
function truncateUtf8(?string $s, int $maxLen): ?string {
    if ($s === null) return null;
    if (mb_strlen($s, 'UTF-8') <= $maxLen) return $s;
    return mb_substr($s, 0, $maxLen, 'UTF-8');
}

// ======= CONEXIÓN ========
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$DB_CHARSET}"
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    fwrite(STDERR, "Error conectando a DB: {$e->getMessage()}\n");
    exit(1);
}

// ======= PREPARE INSERT =======
// Nota: si quieres UPSERT por bwvId, ver bloque opcional al final.
$sql = "
INSERT INTO `Work`
(`bwvId`,`title`,`altTitles`,`genre`,`subgenre`,`key`,`instrumentation`,
 `opusOrCollection`,`durationEst`,`dateComp`,`notes`,`sources`,`openOpusId`)
VALUES
(:bwvId,:title,:altTitles,:genre,:subgenre,:wkey,:instrumentation,
 :opusOrCollection,:durationEst,:dateComp,:notes,:sources,:openOpusId)
";
$stmt = $pdo->prepare($sql);

// ======= LECTURA CSV =======
$fh = fopen($csvPath, 'r');
if (!$fh) {
    fwrite(STDERR, "No se puede abrir el CSV: {$csvPath}\n");
    exit(1);
}

// lee cabecera
$header = fgetcsv($fh);
if (!$header) {
    fwrite(STDERR, "CSV vacío o sin cabecera.\n");
    exit(1);
}

$cols = array_map('trim', $header);
$required = ['BWV','Title','Year','Instrumentation','Key','Genre','Collection','Comments','Sources','Duration','OpenOpusID'];
$missing = array_diff($required, $cols);
if ($missing) {
    fwrite(STDERR, "Faltan columnas en CSV: ".implode(', ', $missing)."\n");
    exit(1);
}
$idx = array_flip($cols);

$inserted = 0;
$lineNum  = 1; // ya leímos cabecera

while (($row = fgetcsv($fh)) !== false) {
    $lineNum++;

    // CSV → variables
    $BWV           = $row[$idx['BWV']]           ?? '';
    $Title         = $row[$idx['Title']]         ?? '';
    $Year          = $row[$idx['Year']]          ?? '';
    $Instr         = $row[$idx['Instrumentation']] ?? '';
    $KeySig        = $row[$idx['Key']]           ?? '';
    $Genre         = $row[$idx['Genre']]         ?? '';
    $Collection    = $row[$idx['Collection']]    ?? '';
    $Comments      = $row[$idx['Comments']]      ?? '';
    $Sources       = $row[$idx['Sources']]       ?? '';
    $Duration      = $row[$idx['Duration']]      ?? '';
    $OpenOpusID    = $row[$idx['OpenOpusID']]    ?? '';

    // Mapeo a columnas Work
    $bwvId             = nullIfEmpty($BWV);
    $title             = nullIfEmpty($Title);
    $altTitles         = null; // opcional de momento
    $genre             = nullIfEmpty($Genre);
    $subgenre          = null; // opcional
    $key               = nullIfEmpty($KeySig);
    $instrumentation   = nullIfEmpty($Instr);
    $opusOrCollection  = nullIfEmpty($Collection);
    $durationEst       = parseDurationToMinutes(nullIfEmpty($Duration));
    $dateComp          = nullIfEmpty($Year); // puede ser rango "1725 - 1727"
    $notes             = nullIfEmpty($Comments);
    $sources           = nullIfEmpty($Sources);
    $openOpusId        = nullIfEmpty($OpenOpusID);

    // Tipos y límites
    $bwvIdInt      = $bwvId !== null ? (int)$bwvId : null;
    $openOpusIdInt = ($openOpusId !== null && is_numeric($openOpusId)) ? (int)$openOpusId : null;

    $title            = truncateUtf8($title, 191);
    $altTitles        = truncateUtf8($altTitles, 191);
    $genre            = truncateUtf8($genre, 100);
    $subgenre         = truncateUtf8($subgenre, 100);
    $key              = truncateUtf8($key, 25);
    $instrumentation  = truncateUtf8($instrumentation, 191);
    $opusOrCollection = truncateUtf8($opusOrCollection, 191);
    $dateComp         = truncateUtf8($dateComp, 25);
    $notes            = truncateUtf8($notes, 512);
    $sources          = truncateUtf8($sources, 191);

    if ($title === null) {
        fwrite(STDERR, "[L{$lineNum}] Saltado: Title vacío.\n");
        continue;
    }

    try {
        $stmt->execute([
            ':bwvId'            => $bwvIdInt,
            ':title'            => $title,
            ':altTitles'        => $altTitles,
            ':genre'            => $genre,
            ':subgenre'         => $subgenre,
            ':wkey'             => $key,
            ':instrumentation'  => $instrumentation,
            ':opusOrCollection' => $opusOrCollection,
            ':durationEst'      => $durationEst,
            ':dateComp'         => $dateComp,
            ':notes'            => $notes,
            ':sources'          => $sources,
            ':openOpusId'       => $openOpusIdInt,
        ]);
        $inserted++;
    } catch (Throwable $e) {
        fwrite(STDERR, "[L{$lineNum}] Error insertando (BWV={$bwvId}, Title={$title}): {$e->getMessage()}\n");
    }
}

fclose($fh);

echo "Importación finalizada. Filas insertadas: {$inserted}\n";

// ===== OPCIONAL: UPSERT por bwvId =====
// Reemplaza la SQL superior por esto y añade el índice único:
//   ALTER TABLE `Work` ADD UNIQUE KEY `uniq_bwv` (`bwvId`);
/*
$sql = "
INSERT INTO `Work`
(`bwvId`,`title`,`altTitles`,`genre`,`subgenre`,`key`,`instrumentation`,
 `opusOrCollection`,`durationEst`,`dateComp`,`notes`,`sources`,`openOpusId`)
VALUES
(:bwvId,:title,:altTitles,:genre,:subgenre,:wkey,:instrumentation,
 :opusOrCollection,:durationEst,:dateComp,:notes,:sources,:openOpusId)
ON DUPLICATE KEY UPDATE
 `title`=VALUES(`title`),
 `altTitles`=VALUES(`altTitles`),
 `genre`=VALUES(`genre`),
 `subgenre`=VALUES(`subgenre`),
 `key`=VALUES(`key`),
 `instrumentation`=VALUES(`instrumentation`),
 `opusOrCollection`=VALUES(`opusOrCollection`),
 `durationEst`=VALUES(`durationEst`),
 `dateComp`=VALUES(`dateComp`),
 `notes`=VALUES(`notes`),
 `sources`=VALUES(`sources`),
 `openOpusId`=VALUES(`openOpusId`)
";
$stmt = $pdo->prepare($sql);
*/
