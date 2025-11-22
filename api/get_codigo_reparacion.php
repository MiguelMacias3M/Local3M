<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit();
}

try {
    // 1. Buscar la reparación
    $stmt = $conn->prepare("SELECT id, codigo_barras FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Reparación no encontrada']);
        exit();
    }

    // 2. Si ya tiene código, lo devolvemos
    if (!empty($row['codigo_barras'])) {
        echo json_encode(['success' => true, 'codigo_barras' => $row['codigo_barras']]);
        exit();
    }

    // 3. SI NO TIENE CÓDIGO (Reparación vieja), GENERAMOS UNO NUEVO
    // Usamos la misma lógica que en el registro
    $fecha = date('ymd');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = 5;
    
    // Intentamos generar y guardar (con reintentos por si se repite)
    $maxIntentos = 5;
    $nuevoCodigo = '';
    
    for ($i = 0; $i < $maxIntentos; $i++) {
        $rand = '';
        for ($j = 0; $j < $len; $j++) {
            $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $nuevoCodigo = "REP{$fecha}{$rand}";

        try {
            $updateStmt = $conn->prepare("UPDATE reparaciones SET codigo_barras = :codigo WHERE id = :id");
            $updateStmt->execute([':codigo' => $nuevoCodigo, ':id' => $id]);
            
            // Si llegamos aquí, se guardó bien
            echo json_encode(['success' => true, 'codigo_barras' => $nuevoCodigo, 'message' => 'Código generado automáticamente']);
            exit();

        } catch (PDOException $e) {
            // Si el código ya existe (error 23000), el bucle intentará generar otro
            if ($e->getCode() !== '23000') {
                throw $e; // Si es otro error, lo lanzamos
            }
        }
    }

    echo json_encode(['success' => false, 'error' => 'No se pudo generar un código único']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>