<?php
// /public/search.php

declare(strict_types=1);
mb_internal_encoding('UTF-8');

$startTime = microtime(true);
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$dataPath = realpath(__DIR__ . '/../app/data/works.csv'); // CSV FUERA DE /public

// Utilidades ----------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function normalize(string $s): string {
  $s = mb_strtolower($s, 'UTF-8');
  $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if ($trans !== false) $s = $trans;
  $s = preg_replace('/[^a-z0-9\s\-]/', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

function highlight(string $text, string $needle): string {
  if ($needle === '') return e($text);
  $quoted = preg_quote($needle, '/');
  return preg_replace_callback("/($quoted)/i", function($m){
    return '<mark>'.$m[1].'</mark>';
  }, e($text));
}

// Detectar si el usuario ha escrito un BWV (bwv232, BWV 232, 232, 232a, etc.)
$requestedBWV = null;
if ($q !== '') {
  if (preg_match('/\b(?:bwv)?\s*(\d+[a-z]?)\b/i', $q, $m)) {
    $requestedBWV = strtolower($m[1]); // ej: "232" o "1007a"
  }
}

$results = [];
$errorMsg = null;
$totalRows = 0;

if (!is_readable((string)$dataPath)) {
  $errorMsg = "No se puede leer el fichero de datos en /app/data/works.csv (ajusta la ruta si es necesario).";
} elseif ($q !== '') {
  try {
    $file = new SplFileObject($dataPath);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $file->setCsvControl(',');

    // Cabecera
    $header = $file->fgetcsv();
    if ($header === false) { throw new RuntimeException("CSV vacío o ilegible."); }
    $header = array_map('trim', $header);
    $idx = array_flip($header);

    $need = ['BWV','Title','Year','Instrumentation','Key','Genre','Duration','OpenOpusID'];
    foreach ($need as $h) {
      if (!isset($idx[$h])) throw new RuntimeException("Falta la columna '$h' en el CSV.");
    }

    $normQ = normalize($q);

    foreach ($file as $row) {
      if (!is_array($row) || $row === [null] || $row === false) continue;
      // Rellena hasta el número de columnas de cabecera
      $row = array_pad($row, count($header), '');
      $row = array_map('trim', $row);
      $totalRows++;

      $BWV  = (string)($row[$idx['BWV']] ?? '');
      $Title= (string)($row[$idx['Title']] ?? '');
      $Year = (string)($row[$idx['Year']] ?? '');
      $Instr= (string)($row[$idx['Instrumentation']] ?? '');
      $Key  = (string)($row[$idx['Key']] ?? '');
      $Genre= (string)($row[$idx['Genre']] ?? '');
      $Dur  = (string)($row[$idx['Duration']] ?? '');
      $OOID = (string)($row[$idx['OpenOpusID']] ?? '');

      if ($BWV === '' && $Title === '') continue;

      $match = false;

      if ($requestedBWV !== null) {
        // Normaliza el BWV del CSV: quita "BWV", espacios y símbolos
        $normBwvCsv = strtolower(preg_replace('/[^0-9a-z]/', '', $BWV));
        // También contempla que a veces viene solo el número (sin "BWV")
        if ($normBwvCsv === $requestedBWV) {
          $match = true;
        }
      }

      // Si no es búsqueda por BWV (o no coincidió), busca por título (substring case-insensitive)
      if (!$match) {
        $normTitle = normalize($Title);
        if ($normQ !== '' && $normTitle !== '' && str_contains($normTitle, $normQ)) {
          $match = true;
        }
      }

      if ($match) {
        $results[] = [
          'BWV' => $BWV,
          'Title' => $Title,
          'Year' => $Year,
          'Instrumentation' => $Instr,
          'Key' => $Key,
          'Genre' => $Genre,
          'Duration' => $Dur,
          'OpenOpusID' => $OOID,
        ];
      }
      // Límite de seguridad para no listar demasiado
      if (count($results) >= 200) break;
    }
  } catch (Throwable $e) {
    $errorMsg = "Error leyendo el CSV: " . $e->getMessage();
  }
}

$elapsedMs = (int)round((microtime(true) - $startTime) * 1000);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Búsqueda — Bachpedia</title>
  <meta name="description" content="Busca obras por título o por índice BWV en Bachpedia.">
  <link rel="stylesheet" href="/css/bootstrap-bachpedia.min.css">
</head>
<body class="d-flex flex-column min-vh-100">
  <header class="py-3 border-bottom">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="/" class="d-inline-flex align-items-center text-decoration-none">
        <img src="/img/bachpedia-logo.png" alt="Bachpedia" height="48" class="me-3" />
        <span class="fs-4 fw-semibold">Bachpedia</span>
      </a>
      <a class="btn btn-outline-light" href="/">Inicio</a>
    </div>
  </header>

  <main class="flex-grow-1">
    <section class="py-5">
      <div class="container">
        <h1 class="mb-4">Buscar obras</h1>

        <form class="mb-4" action="/search.php" method="get" role="search">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-9">
              <label for="q" class="visually-hidden">Buscar por título o número BWV</label>
              <input
                type="search"
                class="form-control form-control-lg"
                id="q"
                name="q"
                placeholder="Ej.: “BWV 232” o “Ich habe genug”"
                value="<?= e($q) ?>"
                autofocus
              >
            </div>
            <div class="col-12 col-md-3 d-grid">
              <button class="btn btn-primary btn-lg" type="submit">Buscar</button>
            </div>
          </div>
        </form>

        <?php if ($errorMsg): ?>
          <div class="alert alert-danger" role="alert">
            <?= e($errorMsg) ?>
          </div>
        <?php elseif ($q === ''): ?>
          <div class="alert alert-info">
            Escribe un <strong>título</strong> o un <strong>índice BWV</strong> para comenzar.
          </div>
          <div class="small opacity-75">
            Ejemplos rápidos:
            <a class="link-light" href="/search.php?q=BWV+232">BWV 232</a> ·
            <a class="link-light" href="/search.php?q=BWV+1007">BWV 1007</a> ·
            <a class="link-light" href="/search.php?q=Ich+habe+genug">Ich habe genug</a>
          </div>
        <?php else: ?>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <span class="badge bg-secondary me-2">Consulta</span>
              <code><?= e($q) ?></code>
            </div>
            <div class="small opacity-75">
              <?= count($results) ?> resultado(s) en <?= $elapsedMs ?> ms
            </div>
          </div>

          <?php if (empty($results)): ?>
            <div class="alert alert-warning">
              No se han encontrado resultados. Prueba con otro título o revisa el número BWV.
            </div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($results as $r): ?>
                <?php
                  $titleHighlighted = $requestedBWV ? e($r['Title']) : highlight($r['Title'], $q);
                  $bwvDisp = $r['BWV'] !== '' ? e($r['BWV']) : '—';
                  $genre   = $r['Genre'] !== '' ? e($r['Genre']) : '—';
                  $year    = $r['Year'] !== '' ? e($r['Year']) : '—';
                  $key     = $r['Key'] !== '' ? e($r['Key']) : '—';
                  $dur     = $r['Duration'] !== '' ? e($r['Duration']) : '—';
                  // Enlace a ficha individual (futuro)
                  $bwvForUrl = urlencode(preg_replace('/\s+/','', $r['BWV']));
                ?>
                <a href="/work.php?bwv=<?= $bwvForUrl ?>" class="list-group-item list-group-item-action py-3">
                  <div class="d-flex w-100 justify-content-between">
                    <h2 class="h5 mb-1"><?= $titleHighlighted ?></h2>
                    <span class="badge bg-primary align-self-start">BWV <?= e(preg_replace('/^bwv\s*/i','', $r['BWV'])) ?></span>
                  </div>
                  <div class="mt-1 small text-muted">
                    <span class="me-3"><strong>Género:</strong> <?= $genre ?></span>
                    <span class="me-3"><strong>Tonalidad:</strong> <?= $key ?></span>
                    <span class="me-3"><strong>Año:</strong> <?= $year ?></span>
                    <span class="me-3"><strong>Duración:</strong> <?= $dur ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>

            <div class="mt-3 small opacity-75">
              Mostrando hasta 200 resultados como máximo.
            </div>
          <?php endif; ?>

          <div class="mt-4">
            <details class="small">
              <summary class="mb-2">Sugerencias de búsqueda</summary>
              <ul class="mb-0">
                <li>Para una obra concreta usa el índice, p. ej. <code>BWV 147</code>.</li>
                <li>Puedes buscar por fragmentos del título, p. ej. <code>“Magnificat”</code> o <code>“Ich habe genug”</code>.</li>
                <li>Soporta sufijos de catálogo, p. ej. <code>BWV 1007a</code>.</li>
              </ul>
            </details>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer py-4 mt-auto">
    <div class="container text-center small">
      <div>© <?= date('Y') ?> Bachpedia</div>
      <div class="mt-1">
        Contenido bajo licencia
        <a class="link-light text-decoration-underline" href="https://creativecommons.org/licenses/by-sa/4.0/deed.es" target="_blank" rel="license noopener">CC BY-SA 4.0</a>
        salvo indicación en contrario.
      </div>
    </div>
  </footer>
</body>
</html>
