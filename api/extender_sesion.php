<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Si el usuario tiene sesión, le renovamos el tiempo de vida
if (isset($_SESSION['nombre'])) {
    $_SESSION['ultimo_acceso'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>