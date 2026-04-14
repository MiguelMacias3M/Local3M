<?php
// Iniciar la sesión al principio de todo
session_start();

// Incluir la conexión desde su nueva ubicación en 'config/'
include '../config/conexion.php'; 

// Preparamos la respuesta que enviaremos en JSON
$response = ['success' => false, 'message' => 'Error desconocido.'];

// Solo proceder si se enviaron datos por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nombre = $_POST['nombre'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($nombre) || empty($password)) {
        $response['message'] = 'Por favor, ingrese usuario y contraseña.';
    } else {
        try {
            $query = $conn->prepare('SELECT * FROM usuarios WHERE nombre = :nombre');
            $query->execute(['nombre' => $nombre]);
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!empty($row)) {
                if (password_verify($password, $row['password'])) {
                    // Contraseña correcta: Iniciar sesión
                    $_SESSION['nombre'] = $row['nombre'];
                    
                    // =====================================
                    // NUEVO: GUARDAMOS EL ROL EN LA SESIÓN
                    // =====================================
                    
                    $_SESSION['rol'] = $row['rol']; 
                    
                    $_SESSION['ultimo_acceso'] = time();

                    $response['success'] = true;
                    $response['message'] = '¡Acceso correcto! Redirigiendo...';
                    $response['redirect'] = 'dashboard.php'; 
                } else {
                    $response['message'] = 'Usuario o contraseña incorrectos.';
                }
            } else {
                $response['message'] = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $response['message'] = "Error en la base de datos: " . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Método no permitido.';
}

header('Content-Type: application/json');
echo json_encode($response);
exit(); 
?>