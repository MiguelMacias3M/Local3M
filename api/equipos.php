<?php
session_start();
include '../config/conexion.php'; // Conectamos a la base de datos

header('Content-Type: application/json');

// 1. SI NOS PIDEN LEER DATOS (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = $_GET['accion'] ?? 'listar';

    if ($accion === 'listar') {
        try {
            // Traemos todos los equipos, los más nuevos primero
            $stmt = $conn->query("SELECT * FROM equipos ORDER BY id DESC");
            $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($equipos);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
} 
// 2. SI NOS MANDAN DATOS A GUARDAR (POST)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar') {
        $tipo = $_POST['tipo'] ?? '';
        $marca = $_POST['marca'] ?? '';
        $modelo = $_POST['modelo'] ?? '';
        $imei_serie = $_POST['imei_serie'] ?? '';
        $color = $_POST['color'] ?? '';
        $costo = $_POST['costo'] ?? 0;
        $precio_venta = $_POST['precio_venta'] ?? 0;

        try {
            $stmt = $conn->prepare("INSERT INTO equipos (tipo, marca, modelo, imei_serie, color, costo, precio_venta, estado) 
                                    VALUES (:tipo, :marca, :modelo, :imei_serie, :color, :costo, :precio_venta, 'Disponible')");
            $stmt->execute([
                ':tipo' => $tipo,
                ':marca' => $marca,
                ':modelo' => $modelo,
                ':imei_serie' => $imei_serie,
                ':color' => $color,
                ':costo' => $costo,
                ':precio_venta' => $precio_venta
            ]);

            echo json_encode(['success' => true, 'message' => 'Equipo registrado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    }
}
?>