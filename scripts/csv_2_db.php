<?php

// Configuración de BBDD
$host = 'localhost'; // Ajusta
$dbname = 'bachdb'; // Ajusta
$user = 'bachuser'; // Ajusta
$pass = '!InAspic65'; // Ajusta
$csv_path = '../csv/bach_works_full_scrape_final.csv'; // Ajusta si es necesario

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión a BBDD exitosa.\n";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Leer el CSV
if (($handle = fopen($csv_path, "r")) === FALSE) {
    die("Error al abrir el CSV: $csv_path\n");
}

// Saltar la primera línea (headers)
fgetcsv($handle);

// Escapar la columna 'key' como '`key`'
$insert_sql = "INSERT INTO Work (openOpusId, title, altTitles, genre, `key`, instrumentation, dateComp, notes, bwvId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($insert_sql);

$count = 0;
while (($data = fgetcsv($handle)) !== FALSE) {
    // Mapeo
    $openOpusId = $data[0] ? (int)$data[0] : NULL; // ID
    $title = $data[1] ? $data[1] : NULL; // Title
    $altTitles = $data[2] ? $data[2] : NULL; // Subtitle
    $genre = $data[3] ? $data[3] : NULL; // Genre
    $key = $data[7] ? $data[7] : NULL; // Key
    $instrumentation = $data[10] ? $data[10] : NULL; // Instruments
    $dateComp = $data[8] ? $data[8] : NULL; // Year
    $notes = $data[12] ? substr($data[12], 0, 512) : NULL; // Description, limitado a 512 chars
    $bwvId = NULL; // Derivado de Catalogue
    if ($data[9] && preg_match('/BWV\s*(\d+(?:\.\d+)?)/i', $data[9], $match)) {
        $bwvId = (int)$match[1];
    }
    
    // Depuración: Mostrar datos de la fila
    echo "Procesando fila $count: openOpusId=$openOpusId, title=$title, bwvId=$bwvId\n";

    try {
        $stmt->execute([
            $openOpusId,
            $title,
            $altTitles,
            $genre,
            $key,
            $instrumentation,
            $dateComp,
            $notes,
            $bwvId
        ]);
        $count++;
        if ($count % 100 == 0) {
            echo "$count filas insertadas.\n";
        }
    } catch (PDOException $e) {
        echo "Error al insertar fila $count (title=$title): " . $e->getMessage() . "\n";
        echo "Datos de la fila: " . print_r($data, true) . "\n";
        continue; // Continúa con la siguiente fila
    }
}

fclose($handle);
echo "Volcado completado: $count filas insertadas en la tabla Work.\n";

?>
