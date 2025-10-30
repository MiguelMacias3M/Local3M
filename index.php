<?php
// Revisamos si ya hay una sesión activa
session_start();
if (isset($_SESSION['nombre'])) {
    // Si ya está logueado, lo mandamos directo a reparacion.php
    header('Location: reparacion.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-t">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - 3M-TECHNOLOGY</title>

    <link rel="stylesheet" href="css/login.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="login-card">
        
        <img src="img/3M_TECHNOLOGY.png" alt="3M TECHNOLOGY" />

        <form id="loginForm">
            <input type="text" 
                   id="nombre" 
                   class="login-input" 
                   name="nombre" 
                   placeholder="Nombre de usuario" 
                   required="required" />

            <input type="password" 
                   id="password" 
                   class="login-input" 
                   name="password" 
                   autocomplete="off" 
                   placeholder="Contraseña" 
                   required="required" />

            <button class="login-button" type="submit">Iniciar sesión</button>
        </form>

    </div>

    <script src="js/login.js"></script>
</body>
</html>