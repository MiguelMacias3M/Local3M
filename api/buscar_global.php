<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

include '../config/conexion.php';

$q_raw = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q_raw === '') {
    echo json_encode(['success' => true, 'data' => ['garantias' => [], 'apartados' => [], 'reparaciones' => []]]);
    exit;
}

$like = '%' . $q_raw . '%';
$id_exacto = is_numeric($q_raw) ? $q_raw : 0;

$respuesta = [
    'garantias' => [],
    'apartados' => [],
    'reparaciones' => []
];

try {
    // 1. BUSCAR EN GARANTÍAS (Ventas de contado)
    $stmtG = $conn->prepare("SELECT fecha, descripcion, monto_unitario, cliente FROM caja_movimientos WHERE categoria = 'Equipos' AND tipo = 'INGRESO' AND (descripcion LIKE :q OR cliente LIKE :q) ORDER BY fecha DESC LIMIT 5");
    $stmtG->execute([':q' => $like]);
    $respuesta['garantias'] = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    // 2. BUSCAR EN APARTADOS
    // (Ajusta los nombres de las columnas si tu tabla 'apartados' las tiene diferentes)
    $stmtA = $conn->prepare("SELECT id, fecha, equipo, cliente, estado, restante FROM apartados WHERE cliente LIKE :q OR telefono LIKE :q OR equipo LIKE :q OR id = :id_exacto ORDER BY fecha DESC LIMIT 5");
    $stmtA->execute([':q' => $like, ':q' => $like, ':q' => $like, ':id_exacto' => $id_exacto]);
    $respuesta['apartados'] = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // 3. BUSCAR EN REPARACIONES
    $stmtR = $conn->prepare("SELECT id, nombre_cliente, modelo, estado, codigo_barras FROM reparaciones WHERE nombre_cliente LIKE :q OR telefono LIKE :q OR modelo LIKE :q OR codigo_barras LIKE :q ORDER BY id DESC LIMIT 5");
    $stmtR->execute([':q' => $like, ':q' => $like, ':q' => $like, ':q' => $like]);
    $respuesta['reparaciones'] = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $respuesta]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>