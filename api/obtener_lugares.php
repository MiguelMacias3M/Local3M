<?php
header('Content-Type: application/json');
include '../config/conexion.php';

try {
    // Buscamos todas las reparaciones activas (NO Entregadas, NO Canceladas)
    // Seleccionamos ID y UBICACION
    $sql = "SELECT id, ubicacion FROM reparaciones 
            WHERE estado != 'Entregado' 
            AND estado != 'Cancelado' 
            AND ubicacion IS NOT NULL 
            AND ubicacion != ''";
    
    $stmt = $conn->query($sql);
    $ocupados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Devolvemos un array simple: ['A1', 'B5', 'C10'...]
    echo json_encode(['success' => true, 'data' => $ocupados]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>