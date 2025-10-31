<?php
// --- Configuración de la Base de Datos (usando PDO) ---
$host = 'localhost';
$db   = 'local';
$user = 'root';
$pass = ''; // Contraseña vacía

// IMPORTANTE: Definimos el charset para acentos y 'ñ'
$charset = 'utf8'; 

// DSN (Data Source Name) - Cadena de conexión
// Se añade el charset aquí, es la forma correcta en PDO.
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    // 1. Creamos la conexión PDO
    $conn = new PDO($dsn, $user, $pass);
    
    // 2. Configuramos el modo de error para que lance excepciones (como en tu ejemplo)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si hay un error, lo mostramos y detenemos el script
    echo 'Error de conexión: ' . $e->getMessage();
    die();
}

// Si llegamos aquí, la conexión fue exitosa
// y la variable $conn está lista para ser usada.
?>

