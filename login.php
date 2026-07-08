<?php
session_start();
if (isset($_SESSION['nombre'])) {
    header('Location: /local3M/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - 3M-TECNOLOGY</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="/local3M/css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="login-card">
      <h1 class="logo-text">3M-TECHNOLOGY</h1>
      
        <form id="loginForm">
            <input type="text" 
                   id="nombre" 
                   class="login-input" 
                   name="nombre" 
                   placeholder="Nombre de usuario" 
                   required="required" />

            <div class="password-wrapper">
                <input type="password" 
                       id="password" 
                       class="login-input password-input" 
                       name="password" 
                       autocomplete="off" 
                       placeholder="Contraseña" 
                       required="required" />
                       
                <button type="button" id="togglePassword" class="toggle-password" tabindex="-1">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>

            <button class="login-button" type="submit" id="loginButton">
                <span class="button-text">Iniciar sesión</span>
                <span class="button-loader"></span>
            </button>
        </form>

    </div>

    <script src="/local3M/js/login.js"></script>
</body>
</html>