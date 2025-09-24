<?php

// Configuración inicial
$csv_file = 'bach_works_complete.csv';
$base_url = 'https://api.openopus.org/dyn/work/list/composer/87/genre/all.json'; // ID 87 para Bach
$csv_headers = [
    'ID', 'Title', 'Subtitle', 'Genre', 'Key', 'Catalogue', 'Year',
    'Number', 'Numbering_Type', 'Description', 'Popular', 'Recommended',
    'Instruments', 'Movements_Count', 'Recordings_Count', 'Scores_Count'
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
    curl_close($ch);
    
    if ($http_code !== 200) {
        die("HTTP Error $http_code en página.\n");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || $data['status']['success'] !== 'true') {
        die('Error JSON o API: ' . json_last_error_msg());
    }
    
    return $data;
}

// Función para obtener todas las obras con paginación
function fetchAllBachWorks($base_url) {
    $all_works = [];
    $page = 1;
    $total_rows = 0;

    do {
        $page_url = $page === 1 ? $base_url : str_replace('.json', "/page/$page.json", $base_url);
        echo "Fetching página $page...\n";
        
        $data = fetchPage($page_url);
        $works = $data['works'] ?? [];
        $all_works = array_merge($all_works, $works);
        $current = count($works);
        echo "  - $current obras. Total hasta ahora: " . count($all_works) . "\n";
        
        if ($page === 1) {
            $total_rows = (int) $data['status']['rows'];
            echo "Total estimado: $total_rows obras.\n";
        }
        
        $page++;
    } while (count($all_works) < $total_rows && $current > 0);

    return $all_works;
}

// Función para escribir CSV con todos los campos disponibles
function writeToCsv($works, $filename, $headers) {
    $file = fopen($filename, 'w');
    if (!$file) die('Error al crear CSV.');
    fputcsv($file, $headers);
    
    foreach ($works as $work) {
        $row = [
            $work['id'] ?? '',
            $work['title'] ?? '',
            $work['subtitle'] ?? '',
            $work['genre'] ?? '',
            $work['key'] ?? '',
            $work['catalogue'] ?? '',
            $work['year'] ?? '',
            $work['number'] ?? '',
            $work['numbering_type'] ?? '',
            $work['description'] ?? '',
            $work['popular'] ?? '',
            $work['recommended'] ?? '',
            json_encode($work['instruments'] ?? []), // Array como JSON
            $work['movements_count'] ?? count($work['movements'] ?? []),
            $work['recordings_count'] ?? '',
            count($work['scores'] ?? []) // Conteo de partituras
        ];
        fputcsv($file, $row);
    }
    fclose($file);
    echo "CSV generado: $filename con " . count($works) . " filas.\n";
}

// Ejecución principal
echo "Obteniendo todas las obras de Bach...\n";
$all_works = fetchAllBachWorks($base_url);
writeToCsv($all_works, $csv_file, $csv_headers);

echo "¡Completado! Revisa $csv_file (todos los campos incluidos).\n";

?>
