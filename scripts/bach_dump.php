<?php

// Configuración inicial
$csv_file = 'bach_works_dump_api.csv';
$dump_url = 'https://api.openopus.org/work/dump.json'; // Endpoint oficial del dump
$bach_id = 87; // ID de J.S. Bach

$csv_headers = [
    'ID', 'Title', 'Subtitle', 'Genre', 'Key', 'Catalogue', 'Year',
    'Number', 'Numbering_Type', 'Description', 'Popular', 'Recommended',
    'Instruments', 'Movements_Count', 'Recordings_Count', 'Scores_Count'
];

// Función para descargar y parsear el dump JSON
function downloadDump($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Para archivos grandes
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        die("Error HTTP $http_code al descargar el dump de $url.\n");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Error JSON: ' . json_last_error_msg());
    }
    
    if ($data['status']['success'] !== 'true') {
        die('Error en el dump: ' . ($data['status']['message'] ?? 'Success no es true'));
    }
    
    return $data['composers'] ?? []; // Array de compositores
}

// Descargar el dump
echo "Descargando el dump completo desde la API...\n";
$all_composers = downloadDump($dump_url);

// Encontrar Bach y extraer sus obras
$bach_works = [];
$bach_name = '';
foreach ($all_composers as $composer) {
    if (($composer['id'] ?? 0) == $bach_id) {
        $bach_name = $composer['name'] ?? 'J.S. Bach';
        $bach_works = $composer['works'] ?? [];
        break;
    }
}

if (empty($bach_works)) {
    die("No se encontraron obras de Bach (ID $bach_id). Verifica el ID.\n");
}

echo "Bach encontrado: $bach_name (ID $bach_id) con " . count($bach_works) . " obras.\n";

// Ejemplo de la primera obra (para verificar campos)
if (!empty($bach_works)) {
    $first = $bach_works[0];
    echo "Ejemplo primera obra: ID={$first['id']}, Title='{$first['title']}', Key='{$first['key']}', Year={$first['year']}\n";
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

// Generar CSV
writeToCsv($bach_works, $csv_file, $csv_headers);

echo "¡Completado! El dump incluye todos los detalles. Revisa $csv_file.\n";

?>
