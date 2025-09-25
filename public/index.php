<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bachpedia — Catálogo abierto de J. S. Bach</title>
  <meta name="description" content="Bachpedia es un catálogo abierto de las obras de Johann Sebastian Bach: índices BWV, metadatos, fuentes, ediciones y enlaces.">
  <link rel="stylesheet" href="/css/bootstrap-bachpedia.min.css">
  <!-- Favicon opcional (si lo tienes ya exportado) -->
  <!-- <link rel="icon" type="image/png" sizes="32x32" href="/img/bachpedia-icon-32.png"> -->
</head>
<body class="d-flex flex-column min-vh-100">

  <main class="flex-grow-1">
    <section class="py-5">
      <div class="container">
        <div class="text-center">
          <img
            src="/img/bachpedia-logo.png"
            alt="Logotipo Bachpedia"
            width="240"
            height="auto"
            class="img-fluid"
            style="max-width: 320px;"
          />

          <h1 class="mt-4 display-5">Bachpedia</h1>
          <p class="lead mt-2">
            Catálogo abierto y colaborativo de las obras de Johann Sebastian Bach:
            <span class="opacity-90">índices BWV, metadatos, fuentes, ediciones,
            partituras y enlaces cruzados.</span>
          </p>

          <!-- Buscador -->
          <form class="mt-4" action="/search.php" method="get" role="search">
            <div class="row justify-content-center">
              <div class="col-12 col-md-8 col-lg-6">
                <label for="q" class="visually-hidden">Buscar por título o número BWV</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text" id="icon-search" aria-hidden="true">
                    <!-- Icono lupa (SVG inline) -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
                      <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10.001 5 5 0 0 1 0 10.001z"/>
                    </svg>
                  </span>
                  <input
                    type="search"
                    class="form-control"
                    id="q"
                    name="q"
                    placeholder="Buscar por título o BWV (p. ej., “BWV 1007” o “Ich habe genug”)"
                    aria-label="Buscar por título o número BWV"
                  >
                  <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
              </div>
            </div>
          </form>

          <!-- Enlaces rápidos opcionales -->
          <div class="mt-3 small">
            <span class="me-2">Ejemplos:</span>
            <a class="link-light me-2" href="/search.php?q=BWV+232">BWV 232</a>
            <a class="link-light me-2" href="/search.php?q=BWV+1007">BWV 1007</a>
            <a class="link-light" href="/search.php?q=Ich+habe+genug">Ich habe genug</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="site-footer py-4 mt-auto">
    <div class="container text-center small">
      <div>© <?php echo date('Y'); ?> Bachpedia</div>
      <div class="mt-1">
        Contenido bajo licencia
        <a class="link-light text-decoration-underline" href="https://creativecommons.org/licenses/by-sa/4.0/deed.es" target="_blank" rel="license noopener">CC BY-SA 4.0</a>
        salvo indicación en contrario.
      </div>
    </div>
  </footer>

</body>
</html>
