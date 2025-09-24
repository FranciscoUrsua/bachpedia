<?php

// Configuración inicial
$dump_file = 'openopus_dump.json';
$csv_file = 'bach_works_dump.csv';
$dump_url = 'https://api.openopus.org/work/dump.json';
$bach_id = 87;

$csv_headers = [
    'ID', 'Title', 'Subtitle', 'Genre', 'Key', 'Catalogue', 'Year',
    'Number', 'Numbering_Type', 'Description', 'Popular', 'Recommended',
    'Instruments', 'Movements_Count', 'Recordings_Count', 'Scores_Count'
];

// Función para descargar y guardar el dump
function downloadAndSaveDump($url, $file) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200 || !empty($error)) {
        die("Error al descargar el dump: HTTP $http_code. Error: $error\n");
    }
    
    if (file_put_contents($file, $response) === false) {
        die("Error al guardar el dump en $file.\n");
    }
    
    echo "Dump descargado y guardado en $file (" . filesize($file) . " bytes).\n";
    echo "Primeros 500 chars: " . substr($response, 0, 500) . "...\n";
    
    return true;
}

// Función para procesar el dump desde disco
function processDumpFile($file, $bach_id) {
    echo "Cargando el dump desde $file...\n";
    $response = file_get_contents($file);
    if ($response === false) {
        die("Error al leer $file.\n");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Error JSON al parsear el archivo: ' . json_last_error_msg());
    }
    
    // Debug: Mostrar claves de nivel raíz
    echo "Claves en el JSON raíz: " . implode(', ', array_keys($data)) . "\n";
    
    $bach_works = [];
    $bach_name = '';
    
    // Caso 1: Estructura con compositores
    if (isset($data['composers'])) {
        echo "Estructura con 'composers' detectada.\n";
        foreach ($data['composers'] as $composer) {
            if (($composer['id'] ?? 0) == $bach_id) {
                $bach_name = $composer['name'] ?? 'J.S. Bach';
                $bach_works = $composer['works'] ?? [];
                break;
            }
        }
    }
    // Caso 2: Estructura plana de obras con composer_id
    elseif (isset($data['works'])) {
        echo "Estructura plana con 'works' detectada.\n";
        foreach ($data['works'] as $work) {
            if (($work['composer_id'] ?? 0) == $bach_id) {
                $bach_works[] = $work;
            }
        }
        $bach_name = 'J.S. Bach (ID ' . $bach_id . ')';
    }
    else {
        die("Estructura desconocida. No se encontraron 'composers' ni 'works'. Revisa $file manualmente.\n");
    }
    
    if (empty($bach_works)) {
        die("No se encontraron obras de Bach (ID $bach_id). Revisa $file manualmente.\n");
    }
    
    echo "Bach encontrado: $bach_name con " . count($bach_works) . " obras.\n";
    
    // Mostrar ejemplo de la primera obra
    if (!empty($bach_works)) {
        $first = $bach_works[0];
        echo "Ejemplo primera obra: ID={$first['id']}, Title='{$first['title']}', Key='{$first['key']}', Year={$first['year']}\n";
        // Mostrar todas las claves de la primera obra
        echo "Claves disponibles en la obra: " . implode(', ', array_keys($first)) . "\n";
    }
    
    return $bach_works;
}

// Función para escribir CSV
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
echo "Paso 1: Descargando el dump completo desde la API...\n";
downloadAndSaveDump($dump_url, $dump_file);

echo "\nPaso 2: Procesando el archivo $dump_file...\n";
$bach_works = processDumpFile($dump_file, $bach_id);

echo "\nPaso 3: Generando CSV...\n";
writeToCsv($bach_works, $csv_file, $csv_headers);

echo "\n¡Completado! Revisa $dump_file para el JSON completo y $csv_file para las obras de Bach.\n";

?>
