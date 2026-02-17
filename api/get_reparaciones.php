<?php
/*
 * API para buscar y paginar reparaciones
 * Incluye búsqueda por ID exacta y texto general.
 */

ini_set('display_errors', 0); 
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
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

    // 2. Construir la consulta de búsqueda
    if ($q !== '') {
        // Agregamos 'OR id = :q6' al final para buscar por número de orden exacto
        $where = "WHERE (nombre_cliente     LIKE :q1
                       OR tipo_reparacion   LIKE :q2
                       OR modelo            LIKE :q3
                       OR codigo_barras     LIKE :q4
                       OR telefono          LIKE :q5
                       OR id                = :q6)";
        
        $term = '%' . $q . '%';
        
        // Asignamos el término con % a los campos de texto
        $params[':q1'] = $term;
        $params[':q2'] = $term;
        $params[':q3'] = $term;
        $params[':q4'] = $term;
        $params[':q5'] = $term;
        
        // Para el ID usamos el término exacto (sin %)
        // Si el usuario escribe texto, MySQL convertirá a 0 o fallará, 
        // pero al ser string bind PDO lo maneja seguro.
        $params[':q6'] = $q;
    }

    // 3. Consulta Principal
    // Nota: Eliminamos cualquier paréntesis extra o saltos de línea raros en la variable $where anterior
    $sql = "SELECT * FROM reparaciones $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);

    // 4. Vincular parámetros de búsqueda
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    
    // Vincular límite y offset
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Consulta para contar total (para la paginación)
    $sqlCount = "SELECT COUNT(*) FROM reparaciones $where";
    $cstmt = $conn->prepare($sqlCount);
    
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
    echo json_encode(['success' => false, 'error' => 'Error de BBDD: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
    exit;
}
?>