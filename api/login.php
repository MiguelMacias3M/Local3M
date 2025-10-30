<?php
// Iniciar la sesión al principio de todo
session_start();

// Incluir la conexión desde su nueva ubicación en 'config/'
include '../config/conexion.php'; 

// Preparamos la respuesta que enviaremos en JSON
$response = ['success' => false, 'message' => 'Error desconocido.'];

// Solo proceder si se enviaron datos por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Obtener los datos (idealmente, los recibiremos como JSON)
    // Para este formulario, usaremos $_POST
    $nombre = $_POST['nombre'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($nombre) || empty($password)) {
        $response['message'] = 'Por favor, ingrese usuario y contraseña.';
    } else {
        try {
            // Usamos la variable $conn de tu archivo conexion.php
            $query = $conn->prepare('SELECT * FROM usuarios WHERE nombre = :nombre');
            $query->execute(['nombre' => $nombre]);
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!empty($row)) {
                // Verificar la contraseña con password_verify
                if (password_verify($password, $row['password'])) {
                    // Contraseña correcta: Iniciar sesión
                    $_SESSION['nombre'] = $row['nombre'];
                    
                    $response['success'] = true;
                    $response['message'] = '¡Acceso correcto! Redirigiendo...';
                    $response['redirect'] = 'reparacion.php'; // Página a la que debe ir
                } else {
                    // Contraseña incorrecta
                    $response['message'] = 'Usuario o contraseña incorrectos.';
                }
            } else {
                // Usuario no encontrado
                $response['message'] = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $response['message'] = "Error en la base de datos: " . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Método no permitido.';
}

// Enviar la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);
exit(); // Terminar el script
?>