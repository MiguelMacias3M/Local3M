<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Candado de seguridad: Solo Admin
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

try {
    // Recibimos el mes a consultar (Formato YYYY-MM, por defecto el mes actual)
    $mes = $_GET['mes'] ?? date('Y-m'); 
    
    // Calculamos el primer y último día de ese mes
    $inicio = $mes . '-01 00:00:00';
    $fin = date('Y-m-t 23:59:59', strtotime($inicio));

    // 1. RANKING: MÁS DINERO GENERADO
    // Sumamos todo el dinero de ingresos por usuario en ese mes
    $sqlDinero = "SELECT usuario, SUM(ingreso) as total_dinero 
                  FROM caja_movimientos 
                  WHERE fecha BETWEEN ? AND ? AND ingreso > 0 
                  AND (origen = 'CAJA' OR origen IS NULL) 
                  GROUP BY usuario 
                  ORDER BY total_dinero DESC";
    $stmtD = $conn->prepare($sqlDinero);
    $stmtD->execute([$inicio, $fin]);
    $rankingDinero = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    // 2. RANKING: MÁS VENTAS REALIZADAS (Volumen)
    // Contamos cuántas transacciones de ingreso hizo cada quien
    $sqlVentas = "SELECT usuario, COUNT(*) as total_ventas 
                  FROM caja_movimientos 
                  WHERE fecha BETWEEN ? AND ? AND ingreso > 0 
                  AND (origen = 'CAJA' OR origen IS NULL) 
                  GROUP BY usuario 
                  ORDER BY total_ventas DESC";
    $stmtV = $conn->prepare($sqlVentas);
    $stmtV->execute([$inicio, $fin]);
    $rankingVentas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'mes' => $mes,
        'dinero' => $rankingDinero,
        'ventas' => $rankingVentas
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>