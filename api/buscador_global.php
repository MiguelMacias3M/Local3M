<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include '../config/conexion.php';

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$codigo = trim($_POST['codigo'] ?? '');

if (empty($codigo)) {
    echo json_encode(['success' => false, 'error' => 'Código vacío']);
    exit();
}

try {
    // 1. ¿ES UNA REPARACIÓN? (Busca por ID de nota numérico o por el código alfanumérico REP...)
    $stmt = $conn->prepare("SELECT id FROM reparaciones WHERE id = ? OR codigo_barras = ? LIMIT 1");
    $stmt->execute([$codigo, $codigo]);
    if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success' => true, 
            'tipo' => 'reparacion', 
            'url' => 'editar_reparacion.php?id=' . $r['id']
        ]);
        exit();
    }

    // 2. ¿ES UN PRODUCTO? (Busca en tabla mercancia)
    try {
        $stmtProd = $conn->prepare("SELECT codigo_barras FROM mercancia WHERE codigo_barras = ? LIMIT 1");
        $stmtProd->execute([$codigo]);
        if ($prod = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'tipo' => 'producto', 'url' => 'venta.php?search=' . urlencode($prod['codigo_barras'])]);
            exit();
        }
    } catch(Exception $e) {}
    
    // 2.1 Respaldo por si el inventario está en la tabla productos
    try {
        $stmtProd2 = $conn->prepare("SELECT codigo_barras FROM productos WHERE codigo_barras = ? LIMIT 1");
        $stmtProd2->execute([$codigo]);
        if ($prod2 = $stmtProd2->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'tipo' => 'producto', 'url' => 'venta.php?search=' . urlencode($prod2['codigo_barras'])]);
            exit();
        }
    } catch(Exception $e) {}

    // 3. ¿ES UN EQUIPO DE VITRINA?
    $stmtVit = $conn->prepare("SELECT imei_serie FROM vitrina WHERE imei_serie = ? LIMIT 1");
    $stmtVit->execute([$codigo]);
    if ($vit = $stmtVit->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'tipo' => 'vitrina', 'url' => 'vitrina.php?buscar=' . urlencode($vit['imei_serie'])]);
        exit();
    }

    // Si no encuentra nada en absoluto
    echo json_encode(['success' => false, 'error' => 'No encontrado']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno de BD']);
}
?>