<?php
declare(strict_types=1);

/**
 * Importa desde un dump JSON de Open Opus SOLO obras de Johann Sebastian Bach
 * Uso: php scripts/import_openopus_bach.php /tmp/openopus-dump.json [--dry-run]
 *
 * Requiere: app/config.php y app/db.php (PDO) como propusimos.
 * Supone tablas: Work(id PK AI, bwvId INT NULL, bwvFull VARCHAR UNIQUE NULL,
 *                    title VARCHAR, altTitles TEXT NULL,
 *                    genreId INT NULL, keyId INT NULL,
 *                    opusOrCollection VARCHAR NULL, notes TEXT NULL)
 *                Genre(id PK AI, name VARCHAR UNIQUE)
 *                `Key`(id PK AI, name VARCHAR UNIQUE)
 *
 * Ajusta los nombres de columnas si tu esquema difiere.
 */

ini_set('memory_limit', '1024M');

if ($argc < 2) {
  fwrite(STDERR, "Uso: php {$argv[0]} <dump.json> [--dry-run]\n");
  exit(1);
}
$dumpPath = $argv[1];
$dryRun = in_array('--dry-run', $argv, true);

if (!is_readable($dumpPath)) {
  fwrite(STDERR, "No puedo leer: $dumpPath\n");
  exit(1);
}

$pdo = require __DIR__ . '/../app/db.php';
$pdo->exec("SET NAMES utf8mb4");

function eprintf(string $fmt, ...$args) { fprintf(STDERR, $fmt, ...$args); }

function ensureGenreId(PDO $pdo, ?string $name): ?int {
  if (!$name) return null;
  $sel = $pdo->prepare("SELECT id FROM Genre WHERE name=?");
  $sel->execute([$name]);
  $id = $sel->fetchColumn();
  if ($id) return (int)$id;
  $ins = $pdo->prepare("INSERT INTO Genre(name) VALUES(?)");
  try { $ins->execute([$name]); } catch (Throwable $e) {}
  $sel->execute([$name]);
  $id = $sel->fetchColumn();
  return $id ? (int)$id : null;
}

function ensureKeyId(PDO $pdo, ?string $name): ?int {
  if (!$name) return null;
  $sel = $pdo->prepare('SELECT id FROM `Key` WHERE name=?');
  $sel->execute([$name]);
  $id = $sel->fetchColumn();
  if ($id) return (int)$id;
  $ins = $pdo->prepare('INSERT INTO `Key`(name) VALUES(?)');
  try { $ins->execute([$name]); } catch (Throwable $e) {}
  $sel->execute([$name]);
  $id = $sel->fetchColumn();
  return $id ? (int)$id : null;
}

function looksLikeBachCatalog(array $w): bool {
  $candidates = [
    'catalogue','catalog','cat','work_catalog','catalogue_number',
    'catalog_number','opus','bwv','bwv_id','bwvId','bwvFull'
  ];
  foreach ($candidates as $k) {
    if (!empty($w[$k]) && preg_match('/\bBWV\b/i', (string)$w[$k])) return true;
  }
  return false;
}

function isBachWork(array $w, bool $assumeAllBach): bool {
  if ($assumeAllBach) return true;

  // 1) Nombre del compositor en el propio objeto
  $name = null;
  if (isset($w['composer'])) {
    if (is_array($w['composer'])) {
      $name = $w['composer']['complete_name'] ?? $w['composer']['name'] ?? $w['composer']['shortname'] ?? null;
    } elseif (is_string($w['composer'])) {
      $name = $w['composer'];
    }
  }
  if (!$name && isset($w['composer_name'])) $name = (string)$w['composer_name'];
  if (isBachName($name)) return true;

  // 2) Si lleva un ID numérico de compositor, NO podemos saber quién es sin mapping;
  //    en ese caso acepta si el catálogo parece BWV.
  if ((isset($w['composer']) && (is_int($w['composer']) || ctype_digit((string)$w['composer'])))
      || isset($w['composer_id']) || isset($w['composerId'])) {
    if (looksLikeBachCatalog($w)) return true;
  }

  // 3) Fallback por catálogo BWV
  if (looksLikeBachCatalog($w)) return true;

  return false;
}

/** Extrae bwvId entero (si es BWV "puro", sin Anh.) */
function parseBwvId(?string $bwvFull): ?int {
  if (!$bwvFull) return null;
  // Acepta "BWV 1041", "BWV1041", "BWV 1041/2" (coge 1041), pero NO "BWV Anh. 10"
  if (preg_match('/\bBWV\s*(?!Anh\.)(\d+)/i', $bwvFull, $m)) {
    return (int)$m[1];
  }
  return null;
}

function buildBwvFull(array $w): ?string {
  $cat = trim((string)($w['catalogue'] ?? ''));
  $num = trim((string)($w['catalogue_number'] ?? ($w['opus'] ?? '')));
  $parts = array_filter([$cat, $num], fn($x) => $x !== '');
  return $parts ? implode(' ', $parts) : null;
}

function getOpenOpusId(array $w): ?int {
  // Open Opus suele usar 'id' para obras; aquí contemplamos variantes
  foreach (['id','work_id','workId'] as $k) {
    if (isset($w[$k]) && is_numeric($w[$k])) return (int)$w[$k];
  }
  return null;
}

$jsonRaw = file_get_contents($dumpPath);
try {
  $data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  eprintf("JSON inválido: %s\n", $e->getMessage());
  exit(1);
}

$works = [];
$topComposerName = null;
$assumeAllBach = false;

// casos habituales documentados por Open Opus: objeto con .works
if (is_array($data) && isset($data['works']) && is_array($data['works'])) {
  $works = $data['works'];
  // si hay "composer" arriba, leer su nombre
  if (isset($data['composer'])) {
    if (is_array($data['composer'])) {
      $topComposerName = $data['composer']['complete_name'] ?? $data['composer']['name'] ?? null;
    } elseif (is_string($data['composer'])) {
      $topComposerName = $data['composer'];
    }
  }
}
// variante con .data.works
elseif (is_array($data) && isset($data['data']['works']) && is_array($data['data']['works'])) {
  $works = $data['data']['works'];
  if (isset($data['data']['composer'])) {
    $c = $data['data']['composer'];
    if (is_array($c)) {
      $topComposerName = $c['complete_name'] ?? $c['name'] ?? null;
    } elseif (is_string($c)) {
      $topComposerName = $c;
    }
  }
}
// array “puro”
elseif (is_array($data) && array_is_list($data) && isset($data[0])) {
  $works = $data;
}
// NDJSON (una obra por línea)
else {
  $lines = preg_split("/\r\n|\n|\r/", trim($jsonRaw));
  if (count($lines) > 1) {
    foreach ($lines as $ln) {
      if ($ln === '') continue;
      $obj = json_decode($ln, true);
      if (is_array($obj)) $works[] = $obj;
    }
  }
  if (!$works) {
    $keys = is_array($data) ? implode(', ', array_keys($data)) : gettype($data);
    eprintf("No encuentro la lista de obras. Claves top-level: %s\n", $keys);
    exit(1);
  }
}

function ensureLocalComposerId(PDO $pdo, int $openOpusId, string $name = 'Johann Sebastian Bach'): int {
  $sel = $pdo->prepare('SELECT id FROM Person WHERE openOpusId = ?');
  $sel->execute([$openOpusId]);
  $id = $sel->fetchColumn();
  if ($id) return (int)$id;

  // crea si no existe
  $ins = $pdo->prepare('INSERT INTO Person (name, openOpusId) VALUES (:name, :openOpusId)');
  $ins->execute([':name' => $name, ':openOpusId' => $openOpusId]);

  return (int)$pdo->lastInsertId();
}


function isBachName(?string $name): bool {
  if (!$name) return false;
  $n = mb_strtolower($name, 'UTF-8');
  // exige “bach” + (johann|j. s.)
  if (strpos($n, 'bach') === false) return false;
  if (strpos($n, 'johann') !== false) return true;
  if (strpos($n, 'j. s.') !== false || strpos($n, 'j.s.') !== false) return true;
  if (preg_match('/\bj\s*\.?\s*s\.?\b/i', $name)) return true;
  return false;
}

$assumeAllBach = isBachName($topComposerName);

// Permite forzar “sé que todo es Bach” desde CLI
if (in_array('--assume-bach', $argv, true)) $assumeAllBach = true;

$composerOpenOpusId = 87; // o el que venga en la obra; para Bach es 87
$composerLocalId = ensureLocalComposerId($pdo, $composerOpenOpusId);

$insert = $pdo->prepare("
  INSERT INTO Work
    (composerId, openOpusId, bwvId, bwvFull, title, altTitles, genreId, keyId, opusOrCollection, notes)
  VALUES
    (:composerId, :openOpusId, :bwvId, :bwvFull, :title, :altTitles, :genreId, :keyId, :opusOrCollection, :notes)
  ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    altTitles = VALUES(altTitles),
    genreId = VALUES(genreId),
    keyId = VALUES(keyId),
    opusOrCollection = VALUES(opusOrCollection),
    notes = VALUES(notes),
    composerId = VALUES(composerId)
");

$total = 0; $kept = 0; $inserted = 0; $updated = 0; $skipped = 0;

$pdo->beginTransaction();
try {
  foreach ($works as $w) {
    $total++;

    if (!is_array($w)) { $skipped++; continue; }
    if (!isBachWork($w, $assumeAllBach)) { $skipped++; continue; }

    $title = trim((string)($w['title'] ?? ''));
    if ($title === '') { $skipped++; continue; }

    $alt = isset($w['subtitle']) && $w['subtitle'] !== '' ? (string)$w['subtitle'] : null;

    $bwvFull = buildBwvFull($w);
    $bwvId   = parseBwvId($bwvFull);

    $genreName = $w['genre']['name'] ?? $w['genre'] ?? null;
    $genreId = ensureGenreId($pdo, $genreName);

    $keyName = $w['key'] ?? null; // p.ej. "A minor"
    $keyId = ensureKeyId($pdo, $keyName);

    $opusOrCollection = $w['catalogue'] ?? null;

    $composerId = '87';

    if ($dryRun) {
      $kept++;
      continue;
    }

    $openOpusId = getOpenOpusId($w);
    $insert->execute([
      ':composerId'       => $composerLocalId,
      ':openOpusId'       => $openOpusId,       // int|null
      ':bwvId'            => $bwvId,            // int|null
      ':bwvFull'          => $bwvFull,          // string|null (ya permites NULL)
      ':title'            => $title,            // string
      ':altTitles'        => $alt,              // string|null
      ':genreId'          => $genreId,          // int|null
      ':keyId'            => $keyId,            // int|null
      ':opusOrCollection' => $opusOrCollection, // string|null
      ':notes'            => null,              // string|null
    ]);

    if ($bwvFull === null) {
      eprintf("[warn] Sin BWV: openOpusId=%s | title=%s\n",
          $openOpusId !== null ? (string)$openOpusId : 'NULL', $title);
    }

    // Heurística para contar insert vs update:
    $rowCount = $insert->rowCount();
    if ($rowCount === 1) $inserted++;
    else $updated++; // ON DUP KEY
    $kept++;
  }
  if (!$dryRun) $pdo->commit(); else $pdo->rollBack();
} catch (Throwable $e) {
  $pdo->rollBack();
  eprintf("Error durante la importación: %s\n", $e->getMessage());
  exit(1);
}

printf("Total en dump: %d | Bach detectadas: %d | %s\n", $total, $kept, $dryRun ? 'DRY-RUN (sin cambios)' : "Insertadas: $inserted, Actualizadas: $updated, Omitidas: $skipped");
