<?php

// Configuración de BBDD
$host = 'localhost'; // Ajusta
$dbname = 'bachdb'; // Ajusta
$user = 'bachuser'; // Ajusta
$pass = '!InAspic65'; // Ajusta

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión a BBDD exitosa.\n";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para parsear BWV del título
function parseBWV($title) {
    // Busca BWV.XXX o BWV.XXX-YYY, captura el número o rango completo
    if (preg_match('/BWV\.?\s*(\d+(?:-\d+)?)/i', $title, $match)) {
        return $match[1]; // Retorna el número o rango como texto (e.g., "1046" o "939-943")
    }
    return NULL;
}

// Seleccionar todas las filas de la tabla Work donde bwvId es NULL
$select_sql = "SELECT id, title FROM Work WHERE bwvId IS NULL";
$stmt_select = $pdo->query($select_sql);

$count = 0;
$updated = 0;
$update_sql = "UPDATE Work SET bwvId = ? WHERE id = ?";
$stmt_update = $pdo->prepare($update_sql);

while ($row = $stmt_select->fetch(PDO::FETCH_ASSOC)) {
    $id = $row['id'];
    $title = $row['title'];
    $bwvId = parseBWV($title);
    
    // Depuración: Mostrar título y BWV parseado
    echo "Procesando id=$id, title='$title': bwvId=" . ($bwvId ?? 'NULL') . "\n";
    
    if ($bwvId !== NULL) {
        try {
            $stmt_update->execute([$bwvId, $id]);
            $updated++;
            echo "  - Actualizado id=$id con bwvId='$bwvId'\n";
        } catch (PDOException $e) {
            echo "  - Error al actualizar id=$id: " . $e->getMessage() . "\n";
        }
    }
    
    $count++;
}

echo "Procesadas $count filas, actualizadas $updated filas con bwvId.\n";

?>
