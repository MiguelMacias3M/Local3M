<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/conexion.php';

if (!isset($_GET['folio']) || empty(trim($_GET['folio'])) || !isset($_GET['tel'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos de búsqueda.']);
    exit();
}

$folioBuscado = trim($_GET['folio']);
$telBuscado = trim($_GET['tel']); 

try {
    // 🚨 AÑADIMOS "marca_celular" A LA CONSULTA
    $sql = "SELECT marca_celular, modelo, nombre_cliente, estado, telefono 
            FROM reparaciones 
            WHERE codigo_barras = :folio 
               OR id = :folio_id 
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $folio_id = is_numeric($folioBuscado) ? (int)$folioBuscado : 0;
    
    $stmt->execute([
        ':folio' => $folioBuscado,
        ':folio_id' => $folio_id
    ]);
    
    $reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reparacion) {
        
        $telefonoRegistrado = trim($reparacion['telefono']);
        $ultimos4BD = substr($telefonoRegistrado, -4);
        
        if (empty($telefonoRegistrado) || $ultimos4BD !== $telBuscado) {
            echo json_encode(['success' => false, 'message' => 'El folio existe, pero los dígitos del teléfono no coinciden por seguridad.']);
            exit();
        }

        $nombreCompleto = trim($reparacion['nombre_cliente']);
        $partesNombre = explode(' ', $nombreCompleto);
        $nombrePublico = $partesNombre[0]; 
        
        if (isset($partesNombre[1])) {
            $nombrePublico .= ' ' . mb_substr($partesNombre[1], 0, 1) . '***'; 
        }

        // 🚨 ENVIAMOS LA MARCA Y EL MODELO JUNTOS
        echo json_encode([
            'success' => true,
            'marca'   => $reparacion['marca_celular'],
            'modelo'  => $reparacion['modelo'],
            'cliente' => $nombrePublico,
            'estado'  => $reparacion['estado']
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'No encontramos ningún equipo con ese folio.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>