<?php
/*
 * API para buscar y paginar reparaciones
 * Responde a peticiones GET de control.js
 */

ini_set('display_errors', 0); // Ocultar errores para no romper el JSON
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    // Si no hay sesión, no autorizar
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No autorizado. Vuelva a iniciar sesión.']);
    exit();
}

// Ruta de conexión corregida (subir un nivel desde /api/)
include '../config/conexion.php'; 

try {
    // 1. Obtener parámetros (con valores por defecto)
    $limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $q      = isset($_GET['q']) ? trim($_GET['q']) : '';

    $where = '';
    $params = [];

    // 2. Construir la consulta de búsqueda si existe 'q'
    if ($q !== '') {
        $where = "WHERE (LOWER(nombre_cliente)   LIKE :q
                       OR LOWER(tipo_reparacion) LIKE :q
                       OR LOWER(modelo)           LIKE :q
                       OR LOWER(codigo_barras)   LIKE :q
                       OR LOWER(telefono)        LIKE :q)"; // Añadido teléfono a la búsqueda
        // Usamos mb_strtolower para soporte de UTF-8 (acentos, ñ)
        $params[':q'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
    }

    // 3. Consulta para obtener los datos paginados
    $sql = "SELECT * FROM reparaciones $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);

    // 4. Vincular parámetros
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Consulta para contar el total de resultados (para saber si hay más páginas)
    $sqlCount = "SELECT COUNT(*) FROM reparaciones $where";
    $cstmt = $conn->prepare($sqlCount);
    
    foreach ($params as $k => $v) {
        $cstmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $cstmt->execute();
    $total = (int)$cstmt->fetchColumn();

    // 6. Comprobar si hay más páginas disponibles
    $hasMore = ($offset + $limit) < $total;

    // 7. Enviar la respuesta JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $rows, 'hasMore' => $hasMore, 'total' => $total]);
    exit;

} catch (PDOException $e) {
    // Capturar errores de base de datos
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error de BBDD: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    // Capturar cualquier otro error
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
    exit;
}
?>