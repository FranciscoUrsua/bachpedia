<?php

// Configuración inicial
$csv_file = 'bach_works_full_scrape_final.csv';
$base_url = 'https://api.openopus.org/dyn/work/list/composer/87/genre/all.json'; // ID 87 para Bach
$max_pages = 20; // Total ~20 páginas
$max_works = 981; // Limita a 10 obras para pruebas (ajusta a 981 para todo)
$csv_headers = [
    'ID', 'Title', 'Subtitle', 'Genre', 'Popular', 'Recommended', 'Searchterms',
    'Key', 'Year', 'Catalogue', 'Instruments', 'Movements', 'Description'
];

// Función para fetch una página de la lista
function fetchPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200 || !empty($error)) {
        echo "  - Error HTTP $http_code o cURL: $error en $url\n";
        return false;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status']['success']) || $data['status']['success'] !== 'true') {
        echo "  - Error JSON o API: " . json_last_error_msg() . "\n";
        return false;
    }
    
    return $data;
}

// Función para obtener todas las obras con paginación
function fetchAllBachWorks($base_url, $max_pages, $max_works) {
    $all_works = [];
    $page = 1;

    while ($page <= $max_pages && count($all_works) < $max_works) {
        $page_url = $page === 1 ? $base_url : str_replace('.json', "/page/$page.json", $base_url);
        echo "Fetching página $page ($page_url)...\n";
        
        $data = fetchPage($page_url);
        if ($data === false || empty($data['works'])) {
            echo "  - No hay más obras o error en página $page.\n";
            break;
        }
        
        $works = $data['works'];
        $all_works = array_merge($all_works, $works);
        $current = count($works);
        echo "  - $current obras obtenidas. Total: " . count($all_works) . "\n";
        
        $page++;
        if ($current == 0) {
            echo "  - No más obras en página $page. Terminando paginación.\n";
            break;
        }
    }

    return array_slice($all_works, 0, $max_works);
}

// Función para parsear BWV del title/subtitle
function parseBWV($title, $subtitle) {
    $full = trim($title . ' ' . $subtitle);
    if (preg_match('/(BWV)\.?\s*(\d+(?:\.\d+)?)/i', $full, $match)) {
        return $match[2]; // Retorna el número (e.g., 968)
    }
    return '';
}

// Función para limpiar texto (elimina CSS, HTML, espacios excesivos)
function cleanText($text) {
    $text = strip_tags($text); // Elimina HTML
    $text = preg_replace('/\s+/', ' ', $text); // Normaliza espacios
    $text = trim($text);
    // Elimina posibles restos de CSS o atributos
    $text = preg_replace('/(style|class|id)=[\'"].*?[\'"]/i', '', $text);
    // Elimina cualquier texto que parezca CSS (e.g., "color: red;")
    $text = preg_replace('/[a-z-]+:\s*[^;]+;/i', '', $text);
    return $text;
}

// Función para scrape detalles de Wikipedia usando BWV
function scrapeWikiDetails($bwv) {
    $url = "https://en.wikipedia.org/wiki/BWV_$bwv";
    echo "  - Scraping $url (BWV $bwv)...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $details = ['key' => '', 'year' => '', 'catalogue' => $bwv, 'instruments' => '', 'movements' => '', 'description' => ''];
    
    if ($http_code !== 200 || !empty($error)) {
        echo "  - Error HTTP $http_code o cURL: $error al scrape $url\n";
        return $details;
    }
    
    // Extraer infobox
    if (preg_match('/<table class="infobox vcard"(.*?)<table/is', $response, $infobox_match)) {
        $infobox = $infobox_match[0];
        // Key
        if (preg_match('/<th[^>]*>Key<\/th>\s*<td[^>]*>(.*?)<\/td>/i', $infobox, $match)) {
            $details['key'] = cleanText($match[1]);
        }
        // Year (simplificado para evitar bucle infinito)
        if (preg_match('/<th[^>]*>(?:Year|Composed|Published|First performance|Composed in|Date)\b[^<]*<\/th>\s*<td[^>]*>(.*?)<\/td>/i', $infobox, $match)) {
            $details['year'] = cleanText($match[1]);
        }
        // Instrumentation
        if (preg_match('/<th[^>]*>(?:Instrumentation|Instruments|Scoring|Scored for)\b[^<]*<\/th>\s*<td[^>]*>(.*?)<\/td>/i', $infobox, $match)) {
            $details['instruments'] = cleanText($match[1]);
        }
        // Movements
        if (preg_match('/<th[^>]*>Movements<\/th>\s*<td[^>]*>(.*?)<\/td>/i', $infobox, $match)) {
            $details['movements'] = cleanText($match[1]);
        }
    }
    
    // Extraer descripción (primer párrafo relevante tras infobox)
    if (preg_match('/<div id="mw-content-text"[^>]*>.*?<(?:h2|p)>(.*?)<(?:\/p>|h2>)/is', $response, $match)) {
        $desc = $match[1];
        if (strlen($desc) > 20) { // Filtra párrafos cortos o irrelevantes
            $details['description'] = cleanText($desc);
        }
    }
    
    return $details;
}

// Función para escribir CSV
function writeToCsv($works, $filename, $headers) {
    $file = fopen($filename, 'w');
    if (!$file) die('Error al crear CSV.');
    fputcsv($file, $headers);
    
    foreach ($works as $index => $work) {
        $bwv = parseBWV($work['title'] ?? '', $work['subtitle'] ?? '');
        echo "Procesando obra " . ($index + 1) . ": {$work['title']} (BWV: $bwv)...\n";
        if (empty($bwv)) {
            echo "  - No se encontró BWV para '{$work['title']}'. Usando valores vacíos.\n";
            $details = ['key' => '', 'year' => '', 'catalogue' => '', 'instruments' => '', 'movements' => '', 'description' => ''];
        } else {
            $details = scrapeWikiDetails($bwv);
        }
        $row = [
            $work['id'] ?? '',
            $work['title'] ?? '',
            $work['subtitle'] ?? '',
            $work['genre'] ?? '',
            $work['popular'] ?? '',
            $work['recommended'] ?? '',
            $work['searchterms'] ?? '',
            $details['key'],
            $details['year'],
            $details['catalogue'],
            $details['instruments'],
            $details['movements'],
            $details['description']
        ];
        fputcsv($file, $row);
        sleep(1); // Pausa para no saturar Wikipedia
    }
    fclose($file);
    echo "CSV generado: $filename con " . count($works) . " filas.\n";
}

// Ejecución principal
echo "Obteniendo obras de Bach de la API...\n";
$all_works = fetchAllBachWorks($base_url, $max_pages, $max_works);

// Debug: Primera obra
if (!empty($all_works)) {
    $first = $all_works[0];
    $bwv = parseBWV($first['title'] ?? '', $first['subtitle'] ?? '');
    $details = scrapeWikiDetails($bwv);
    echo "Ejemplo: Title='{$first['title']}', BWV='$bwv', Key='{$details['key']}', Year='{$details['year']}', Description='{$details['description']}'\n";
}

writeToCsv($all_works, $csv_file, $csv_headers);

echo "¡Completado! Revisa $csv_file (con detalles scrapeados de Wikipedia).\n";

?>
