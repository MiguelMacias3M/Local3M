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
    $id_rep = null;

    // 1. ¿ES UNA REPARACIÓN? 
    if (is_numeric($codigo)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM reparaciones WHERE id = ? LIMIT 1");
            $stmt->execute([$codigo]);
            if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $id_rep = $r['id'];
        } catch(Exception $e) {}
    }
    
    if (!$id_rep) {
        $columnas = ['codigo_barras', 'codigo', 'folio', 'id_transaccion'];
        foreach ($columnas as $col) {
            try {
                $stmt = $conn->prepare("SELECT id FROM reparaciones WHERE $col = ? LIMIT 1");
                $stmt->execute([$codigo]);
                if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $id_rep = $r['id']; break; 
                }
            } catch (Exception $e) {}
        }
    }

    if ($id_rep) {
        echo json_encode(['success' => true, 'tipo' => 'reparacion', 'url' => 'editar_reparacion.php?id=' . $id_rep]);
        exit();
    }

    // 2. ¿ES MERCANCÍA / REFACCIÓN? (Lo manda al inventario)
    try {
        $stmtMerc = $conn->prepare("SELECT codigo_barras FROM mercancia WHERE codigo_barras = ? LIMIT 1");
        $stmtMerc->execute([$codigo]);
        if ($merc = $stmtMerc->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'tipo' => 'mercancia', 'url' => 'mercancia.php?search=' . urlencode($merc['codigo_barras'])]);
            exit();
        }
    } catch(Exception $e) {}
    
    // 3. ¿ES UN PRODUCTO DE VENTA? (Lo manda al carrito)
    try {
        $stmtProd2 = $conn->prepare("SELECT codigo_barras FROM productos WHERE codigo_barras = ? LIMIT 1");
        $stmtProd2->execute([$codigo]);
        if ($prod2 = $stmtProd2->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => true, 'tipo' => 'producto', 'url' => 'venta.php?search=' . urlencode($prod2['codigo_barras'])]);
            exit();
        }
    } catch(Exception $e) {}

    // 4. ¿ES UN EQUIPO DE VITRINA?
    $stmtVit = $conn->prepare("SELECT imei_serie FROM vitrina WHERE imei_serie = ? LIMIT 1");
    $stmtVit->execute([$codigo]);
    if ($vit = $stmtVit->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'tipo' => 'vitrina', 'url' => 'vitrina.php?buscar=' . urlencode($vit['imei_serie'])]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'No encontrado']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error BD']);
}
?>