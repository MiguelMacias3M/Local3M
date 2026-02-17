<?php
/*
 * API para buscar y paginar reparaciones
 * Responde a peticiones GET de control.js
 */

ini_set('display_errors', 0); 
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No autorizado. Vuelva a iniciar sesión.']);
    exit();
}

include '../config/conexion.php'; 

try {
    // 1. Obtener parámetros
    $limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $q      = isset($_GET['q']) ? trim($_GET['q']) : '';

    $where = '';
    $params = [];

    // 2. Construir la consulta de búsqueda CORREGIDA
    // QUITE: LOWER() - Esto causaba el choque de collations.
    // La búsqueda sigue siendo insensible a mayúsculas/minúsculas por la configuración de la BD.
    if ($q !== '') {
        $where = "WHERE (nombre_cliente     LIKE :q1
                       OR tipo_reparacion   LIKE :q2
                       OR modelo            LIKE :q3
                       OR codigo_barras     LIKE :q4
                       OR telefono          LIKE :q5)
                       OR id                = :q6)";
        
        $term = '%' . $q . '%';
        
        // Asignamos el mismo término a todos los parámetros
        $params[':q1'] = $term;
        $params[':q2'] = $term;
        $params[':q3'] = $term;
        $params[':q4'] = $term;
        $params[':q5'] = $term;
    }

    // 3. Consulta Principal
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

    // 5. Consulta para contar total (Paginación)
    $sqlCount = "SELECT COUNT(*) FROM reparaciones $where";
    $cstmt = $conn->prepare($sqlCount);
    
    // Vinculamos los mismos parámetros de búsqueda si existen
    foreach ($params as $k => $v) {
        $cstmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    
    $cstmt->execute();
    $total = (int)$cstmt->fetchColumn();

    $hasMore = ($offset + $limit) < $total;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $rows, 'hasMore' => $hasMore, 'total' => $total]);
    exit;

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    // Mostramos mensaje genérico para usuario, el específico solo para debug si es necesario
    echo json_encode(['success' => false, 'error' => 'Error de BBDD: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
    exit;
}
?>ggi