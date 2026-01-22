<?php
// 1. Iniciamos la sesión
session_start();

// 2. Destruimos todas las variables de sesión
// Esto borra $_SESSION['nombre'] y cualquier otra que exista
$_SESSION = array();

// 3. Destruimos la sesión
session_destroy();

// 4. Redirigimos al usuario a la página de login (login.php)
header('Location: login.php');
exit();
?>

