<?php
// /public/work.php (mockup sin lectura de CSV)

declare(strict_types=1);
mb_internal_encoding('UTF-8');

$work = [
  'BWV'            => 'BWV 1007',
  'Title'          => 'Suite para violonchelo nº 1 en Sol mayor',
  'Year'           => 'c. 1717–1723',
  'Instrumentation'=> 'Violoncello solo',
  'Key'            => 'Sol mayor (G major)',
  'Genre'          => 'Suite',
  'Collection'     => 'Seis suites para violonchelo (BWV 1007–1012)',
  'Duration'       => '≈ 17 min',
  'Comments'       => 'Obra para cello solo; preludio célebre. Parte autógrafa perdida; se conservan copias de Anna Magdalena Bach (comentario de ejemplo).',
  'Sources'        => 'Bach Digital (BDW), Nueva Edición Bach (NBA), IMSLP (ejemplo).',
  'OpenOpusID'     => '9990',
  'Movements'      => [
    'I. Prélude',
    'II. Allemande',
    'III. Courante',
    'IV. Sarabande',
    'V. Menuet I & II',
    'VI. Gigue',
  ],
];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($work['Title']) ?> — <?= e($work['BWV']) ?> — Bachpedia</title>
  <meta name="description" content="Ficha de obra de Bachpedia (mockup)">
  <link rel="stylesheet" href="/css/bootstrap-bachpedia.min.css">
</head>
<body class="d-flex flex-column min-vh-100">

  <header class="py-3 border-bottom">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="/" class="d-inline-flex align-items-center text-decoration-none">
        <img src="/img/bachpedia-logo.png" alt="Bachpedia" height="48" class="me-3" />
        <span class="fs-4 fw-semibold">Bachpedia</span>
      </a>
      <div class="d-none d-md-block">
        <a class="btn btn-outline-light" href="/search.php">Buscar</a>
      </div>
    </div>
  </header>

  <main class="flex-grow-1">
    <section class="py-4 py-lg-5">
      <div class="container">
        <!-- Migas -->
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/search.php">Búsqueda</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($work['BWV']) ?></li>
          </ol>
        </nav>

        <!-- Cabecera obra -->
        <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
          <div class="me-3">
            <h1 class="display-5 mb-1"><?= e($work['Title']) ?></h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span class="badge bg-primary"><?= e($work['BWV']) ?></span>
              <span class="badge bg-secondary"><?= e($work['Genre']) ?></span>
              <span class="badge bg-info"><?= e($work['Key']) ?></span>
            </div>
          </div>
          <div class="mt-3 mt-lg-0">
            <a href="/search.php" class="btn btn-outline-light">Volver a resultados</a>
          </div>
        </div>

        <div class="row g-4">
          <!-- Columna principal -->
          <div class="col-12 col-lg-8">
            <!-- Descripción -->
            <div class="card mb-4">
              <div class="card-body">
                <h2 class="h4 mb-3">Descripción</h2>
                <p class="mb-0"><?= e($work['Comments']) ?></p>
              </div>
            </div>

            <!-- Movimientos -->
            <div class="card mb-4">
              <div class="card-body">
                <h2 class="h4 mb-3">Movimientos</h2>
                <ol class="mb-0">
                  <?php foreach ($work['Movements'] as $m): ?>
                    <li class="mb-1"><?= e($m) ?></li>
                  <?php endforeach; ?>
                </ol>
              </div>
            </div>

            <!-- DATOS RÁPIDOS (movido aquí, antes de la tabla) -->
            <div class="card mb-4">
              <div class="card-body">
                <h2 class="h4 mb-3">Datos rápidos</h2>
                <ul class="list-unstyled mb-0">
                  <li class="mb-2"><strong>Año:</strong> <?= e($work['Year']) ?></li>
                  <li class="mb-2"><strong>Duración:</strong> <?= e($work['Duration']) ?></li>
                  <li class="mb-2"><strong>Instrumentación:</strong> <?= e($work['Instrumentation']) ?></li>
                  <li class="mb-2"><strong>Tonalidad:</strong> <?= e($work['Key']) ?></li>
                </ul>
              </div>
            </div>

            <!-- Detalles -->
            <div class="card">
              <div class="card-body">
                <h2 class="h4 mb-3">Detalles</h2>
                <div class="table-responsive">
                  <table class="table table-dark table-striped align-middle mb-0">
                    <tbody>
                      <tr><th scope="row" style="width: 220px;">BWV</th><td><?= e($work['BWV']) ?></td></tr>
                      <tr><th scope="row">Título</th><td><?= e($work['Title']) ?></td></tr>
                      <tr><th scope="row">Año</th><td><?= e($work['Year']) ?></td></tr>
                      <tr><th scope="row">Instrumentación</th><td><?= e($work['Instrumentation']) ?></td></tr>
                      <tr><th scope="row">Tonalidad</th><td><?= e($work['Key']) ?></td></tr>
                      <tr><th scope="row">Género</th><td><?= e($work['Genre']) ?></td></tr>
                      <tr><th scope="row">Colección</th><td><?= e($work['Collection']) ?></td></tr>
                      <tr><th scope="row">Duración</th><td><?= e($work['Duration']) ?></td></tr>
                      <tr><th scope="row">OpenOpus ID</th><td><?= e($work['OpenOpusID']) ?></td></tr>
                      <tr><th scope="row">Fuentes</th><td><?= e($work['Sources']) ?></td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>

          <!-- Sidebar -->
          <div class="col-12 col-lg-4">
            <div class="card">
              <div class="card-body">
                <h2 class="h5 mb-3">Enlaces y fuentes</h2>
                <div class="list-group list-group-flush">
                  <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Ficha en Bach Digital <span class="small opacity-75">↗</span>
                  </a>
                  <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Partitura (IMSLP) <span class="small opacity-75">↗</span>
                  </a>
                  <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Open Opus <span class="small opacity-75">↗</span>
                  </a>
                  <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    Wikipedia (es) <span class="small opacity-75">↗</span>
                  </a>
                </div>
                <p class="small mt-3 mb-0 text-muted">* Enlaces de ejemplo para el mockup.</p>
              </div>
            </div>
          </div>
        </div>

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
