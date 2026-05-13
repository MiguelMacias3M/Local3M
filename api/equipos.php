<?php
session_start();
include '../config/conexion.php'; 

header('Content-Type: application/json');

// --- PETICIONES GET (Leer Datos) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = $_GET['accion'] ?? 'listar';

    if ($accion === 'listar') {
        try {
            $stmt = $conn->query("SELECT * FROM equipos ORDER BY id DESC");
            $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($equipos);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    // NUEVO: Obtener un solo equipo para editarlo
    else if ($accion === 'obtener') {
        try {
            $stmt = $conn->prepare("SELECT * FROM equipos WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
} 
// --- PETICIONES POST (Guardar, Editar, Eliminar) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Variables recibidas del formulario
    $tipo = $_POST['tipo'] ?? '';
    $marca = $_POST['marca'] ?? '';
    $modelo = $_POST['modelo'] ?? '';
    $imei_serie = $_POST['imei_serie'] ?? '';
    $color = $_POST['color'] ?? '';
    $costo = $_POST['costo'] ?? 0;
    $precio_venta = $_POST['precio_venta'] ?? 0;

    if ($accion === 'registrar') {
        try {
            $stmt = $conn->prepare("INSERT INTO equipos (tipo, marca, modelo, imei_serie, color, costo, precio_venta, estado) 
                                    VALUES (:tipo, :marca, :modelo, :imei_serie, :color, :costo, :precio_venta, 'Disponible')");
            $stmt->execute([':tipo' => $tipo, ':marca' => $marca, ':modelo' => $modelo, ':imei_serie' => $imei_serie, ':color' => $color, ':costo' => $costo, ':precio_venta' => $precio_venta]);
            echo json_encode(['success' => true, 'message' => 'Equipo registrado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    }
    
    // NUEVO: EDITAR
    else if ($accion === 'editar') {
        // Candado de Seguridad
        if (strtolower($_SESSION['rol']) !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Solo un administrador puede editar.']); exit();
        }
        
        try {
            $id = $_POST['id'];
            $stmt = $conn->prepare("UPDATE equipos SET tipo = :tipo, marca = :marca, modelo = :modelo, imei_serie = :imei_serie, color = :color, costo = :costo, precio_venta = :precio_venta WHERE id = :id");
            $stmt->execute([':tipo' => $tipo, ':marca' => $marca, ':modelo' => $modelo, ':imei_serie' => $imei_serie, ':color' => $color, ':costo' => $costo, ':precio_venta' => $precio_venta, ':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Equipo actualizado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    }

    // NUEVO: ELIMINAR
    else if ($accion === 'eliminar') {
        // Candado de Seguridad
        if (strtolower($_SESSION['rol']) !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Solo un administrador puede eliminar equipos.']); exit();
        }

        try {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM equipos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Equipo eliminado de la base de datos.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    }
}
?>