<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

include '../config/conexion.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    // Buscamos en 'transacciones' utilizando el campo 'descripcion'
    $stmt = $conn->prepare("
        SELECT id, fecha, descripcion, monto_unitario, cliente, usuario, metodo_pago 
        FROM caja_movimientos 
        WHERE categoria = 'Equipos' 
        AND tipo = 'INGRESO' 
        AND (descripcion LIKE :q OR cliente LIKE :q) 
        ORDER BY fecha DESC 
        LIMIT 20
    ");
    
    $like = '%' . $q . '%';
    $stmt->execute([':q' => $like]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $resultados]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>