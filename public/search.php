<?php
// /public/search.php — Búsqueda en BBDD (tabla Work)

declare(strict_types=1);
mb_internal_encoding('UTF-8');

$startTime = microtime(true);
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function highlight(string $text, string $needle): string {
  if ($needle === '') return e($text);
  $quoted = preg_quote($needle, '/');
  return preg_replace_callback("/($quoted)/i", fn($m) => '<mark>'.$m[1].'</mark>', e($text));
}

// Detectar BWV escrito como "BWV 232", "232", "bwv232", "232a", etc.
$requestedBWV = null;
if ($q !== '') {
  if (preg_match('/\b(?:bwv)?\s*([0-9]+[a-z]?)/i', $q, $m)) {
    $requestedBWV = strtolower($m[1]); // ej: "232" o "1007a"
  }
}

// --- Conexión PDO ------------------------------------------------------------
function getPDO(): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  $cfg = __DIR__ . '/../app/config.php';
  if (is_readable($cfg)) {
    $maybe = require $cfg; // Debe retornar un PDO
    if ($maybe instanceof PDO) { $pdo = $maybe; return $pdo; }
    throw new RuntimeException('El fichero /app/config/db.php debe retornar un PDO válido.');
  }

  // Fallback por entorno o por defecto
  $dsn  = getenv('DB_DSN')  ?: 'mysql:host=127.0.0.1;dbname=bachpedia;charset=utf8mb4';
  $user = getenv('DB_USER') ?: 'root';
  $pass = getenv('DB_PASS') ?: '';
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

// --- Búsqueda ---------------------------------------------------------------
$results = [];
$errorMsg = null;

try {
  if ($q !== '') {
    $pdo = getPDO();

    // Normalizamos a minúsculas para LIKE insensible a mayúsculas
    $kw = mb_strtolower($q);
    $kwLike = '%'.$kw.'%';

    // Si se detecta BWV, además construimos patrones específicos
    $params = [
      ':kw' => $kwLike,
      ':limit' => 200,
    ];

    $where = [];
    $where[] = 'LOWER(title) LIKE :kw';
    $where[] = 'LOWER(altTitles) LIKE :kw';
    $where[] = 'LOWER(bwvId) LIKE :kw';

    if ($requestedBWV !== null) {
      // Patrones para captar variantes de BWV en la columna bwvId
      $params[':bwvCore'] = '%'.$requestedBWV.'%';           // "1007" o "1007a"
      $params[':bwvWith'] = '%bwv'.$requestedBWV.'%';        // "bwv1007"
      $params[':bwvSpc']  = '%bwv '.$requestedBWV.'%';       // "bwv 1007"
      $where[] = 'LOWER(bwvId) LIKE :bwvCore';
      $where[] = 'LOWER(bwvId) LIKE :bwvWith';
      $where[] = 'LOWER(bwvId) LIKE :bwvSpc';
    }

    $sql = "
      SELECT
        id,
        bwvId,
        title,
        altTitles,
        genre,
        subgenre,
        `key`,
        instrumentation,
        opusOrCollection,
        durationEst,
        dateComp
      FROM Work
      WHERE ".implode(' OR ', $where)."
      LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
      if ($k === ':limit') $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
      else                 $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Scoring: coincidencias exactas de BWV normalizado primero
    $norm = fn(?string $s) => $s === null ? '' : strtolower(preg_replace('/[^0-9a-z]/i', '', $s));
    $normQ = $requestedBWV ? $requestedBWV : '';

    foreach ($rows as $r) {
      $score = 0;
      if ($requestedBWV !== null) {
        $b = $norm($r['bwvId'] ?? '');
        if ($b === $normQ || $b === 'bwv'.$normQ) $score += 100;
        elseif ($b !== '' && str_contains($b, $normQ)) $score += 60;
      }
      // Bonus por título que contiene el término
      if ($q !== '' && isset($r['title']) && stripos($r['title'], $q) !== false) $score += 30;

      $r['_score'] = $score;
      $results[] = $r;
    }

    // Ordenar por score desc, luego por título
    usort($results, function($a, $b) {
      if ($a['_score'] === $b['_score']) return strnatcasecmp($a['title'] ?? '', $b['title'] ?? '');
      return $b['_score'] <=> $a['_score'];
    });

    // Limitar a 200 por seguridad (ya limitado en SQL, pero por si acaso)
    if (count($results) > 200) $results = array_slice($results, 0, 200);
  }
} catch (Throwable $e) {
  $errorMsg = "Error en la búsqueda: ".$e->getMessage();
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
            Ejemplos:
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
                  $title = $r['title'] ?? '';
                  $titleHighlighted = $requestedBWV ? e($title) : highlight($title, $q);
                  $bwvId = $r['bwvId'] ?? '';
                  $genre = $r['genre'] ?? '';
                  $key   = $r['key'] ?? '';
                  $instr = $r['instrumentation'] ?? '';
                  $date  = $r['dateComp'] ?? '';
                  $dur   = $r['durationEst'] !== null && $r['durationEst'] !== '' ? ($r['durationEst'].' min') : '—';
                  $id    = (int)($r['id'] ?? 0);
                ?>
                <a href="/work.php?id=<?= $id ?>" class="list-group-item list-group-item-action py-3">
                  <div class="d-flex w-100 justify-content-between">
                    <h2 class="h5 mb-1"><?= $titleHighlighted ?></h2>
                    <?php if ($bwvId): ?>
                      <span class="badge bg-primary align-self-start"><?= e($bwvId) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="mt-1 small text-muted">
                    <?php if ($genre): ?><span class="me-3"><strong>Género:</strong> <?= e($genre) ?></span><?php endif; ?>
                    <?php if ($key):   ?><span class="me-3"><strong>Tonalidad:</strong> <?= e($key) ?></span><?php endif; ?>
                    <?php if ($date):  ?><span class="me-3"><strong>Fecha comp.:</strong> <?= e($date) ?></span><?php endif; ?>
                    <?php if ($instr): ?><span class="me-3"><strong>Instrumentación:</strong> <?= e($instr) ?></span><?php endif; ?>
                    <span class="me-3"><strong>Duración:</strong> <?= e($dur) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>

            <div class="mt-3 small opacity-75">
              Mostrando hasta 200 resultados como máximo.
            </div>
          <?php endif; ?>
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
