<?php

// Configuración inicial
$csv_file = 'bach_works_debug.csv';
$base_url = 'https://api.openopus.org/dyn/work/list/composer/87/genre/all.json'; // Confirmado: ID 87 para Bach
$csv_headers = [
    'ID', 'Title', 'Subtitle', 'Genre', 'Popular', 'Recommended', 'Searchterms'
    // Agrega más si usas detalles: 'Key', 'Year', 'Catalogue', etc. (de /work/ID.json)
];

// Función para hacer la solicitud a la API con debug
function fetchPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script/1.0)'); // Simula navegador
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30s
    curl_setopt($ch, CURLOPT_HEADER, true); // Para obtener headers
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    if (curl_errno($ch)) {
        $error = 'Error en cURL: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    }
    
    curl_close($ch);
    
    // Debug: Muestra HTTP code y raw body (truncado)
    echo "HTTP Code: $http_code\n";
    echo "Raw Body (primeros 500 chars): " . substr($body, 0, 500) . "\n";
    if (strpos($body, '<html') !== false) {
        echo "¡Advertencia! Respuesta parece HTML (posible error/redirección).\n";
        echo "Fetching:$url \n";
    }
    
    // Parse JSON
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error = 'Error JSON: ' . json_last_error_msg() . ' (Code: ' . json_last_error() . ')';
        return ['error' => $json_error, 'raw' => $body];
    }
    
    return ['data' => $data, 'http_code' => $http_code];
}

// Función para paginación y fetch completo
function fetchAllBachWorks($base_url) {
    $all_works = [];
    $page = 1;
    $total_rows = 0;

    do {
        $page_url = $page === 1 ? $base_url : str_replace('.json', "/page/$page.json", $base_url);
        echo "\n--- Fetching página $page ---\n";
        
        $result = fetchPage($page_url);
        if (isset($result['error'])) {
            echo $result['error'] . "\n";
            if (isset($result['raw'])) {
                echo "Raw completo: " . $result['raw'] . "\n";
            }
            die("Fallo en página $page. Detén aquí.");
        }
        
        $data = $result['data'];
        $http_code = $result['http_code'];
        
        if ($http_code !== 200) {
            die("HTTP Error $http_code en página $page.\n");
        }
        
        if ($data['status']['success'] !== 'true') {
            die('Error API en página ' . $page . ': ' . ($data['status']['message'] ?? 'Success no es true'));
        }
        
        $works = $data['works'] ?? [];
        $all_works = array_merge($all_works, $works);
        $current_rows = count($works);
        echo "Página $page: $current_rows obras. Total hasta ahora: " . count($all_works) . "\n";
        
        if ($page === 1) {
            $total_rows = (int) $data['status']['rows'];
            echo "Total estimado: $total_rows obras.\n";
        }
        
        $page++;
        // La API devuelve ~50 por página; para eficiencia, podrías limitar páginas si es mucho
    } while (count($all_works) < $total_rows && $current_rows > 0);

    return ['works' => $all_works, 'total' => $total_rows];
}

// Función para escribir CSV (ajustada a campos disponibles)
function writeToCsv($data, $filename, $headers) {
    $file = fopen($filename, 'w');
    if (!$file) {
        die('Error al crear CSV.');
    }
    fputcsv($file, $headers);
    
    foreach ($data['works'] as $work) {
        $detail = get_details( $work['id'] );
        $row = [
            $work['id'] ?? '',
            $work['title'] ?? '',
            $work['subtitle'] ?? '',
            $work['genre'] ?? '',
            $work['popular'] ?? '',
            $work['recommended'] ?? '',
            $work['searchterms'] ?? '',
            $detail['year'] ?? '',
            $detail['key'] ?? '',
            $detail['catalogue'] ?? '',
            json_encode($detail['instruments'] ?? []),
            $detail['movements_count'] ?? count($detail['movements'] ?? []),
            $detail['description'] ?? '',
            $detail['recordings_count'] ?? '',
            count($detail['scores'] ?? [])
        ];
        fputcsv($file, $row);
    }
    fclose($file);
    echo "CSV generado: $filename con " . count($data['works']) . " filas.\n";
}

// Ejecución principal
echo "Iniciando fetch de obras de Bach...\n";
$response = fetchAllBachWorks($base_url);
writeToCsv($response, $csv_file, $csv_headers);

echo "¡Completado! Total: " . $response['total'] . " obras.\n";

// OPCIÓN: Para detalles extras (e.g., key, year), descomenta y usa un ID de ejemplo
function get_details( $work_id ){
    $detail_url = "https://api.openopus.org/dyn/work/$work_id.json";
    $detail_result = fetchPage($detail_url);
    if (isset($detail_result['data'])) {
        $detail = $detail_result['data']['work'];
        echo "Detalles extra para ID $work_id: Key=" . ($detail['key'] ?? 'N/A') . ", Year=" . ($detail['year'] ?? 'N/A') . "\n";
        return( $detail );
    }
    sleep(1);
}

?>
