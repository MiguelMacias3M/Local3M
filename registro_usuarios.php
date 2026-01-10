<?php include 'templates/header.php'; ?>

<!-- Estilos específicos -->
<link rel="stylesheet" href="/css/usuarios.css?v=1.0">

<div class="page-title">
    <h1>Gestión de Usuarios</h1>
    <p>Registra nuevos accesos para tu personal. Requiere autorización.</p>
</div>

<div class="usuarios-container">
    
    <!-- TARJETA DE REGISTRO -->
    <div class="card-registro content-box">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </div>
        
        <form id="formRegistro" onsubmit="return false;">
            <div class="form-group">
                <label>Nombre de Usuario</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="nuevo_usuario" class="form-input" placeholder="Ej: JuanPerez" required>
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="nueva_password" class="form-input" placeholder="******" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" class="form-input" placeholder="******" required>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="form-group admin-section">
                <label class="text-danger"><i class="fas fa-shield-alt"></i> Clave Maestra (Admin)</label>
                <input type="password" id="admin_password" class="form-input input-admin" placeholder="Ingresa tu clave para autorizar" required>
                <small>Solo el administrador puede crear cuentas.</small>
            </div>

            <button class="form-button btn-primary btn-block" onclick="registrarUsuario()">
                Registrar Usuario
            </button>
        </form>
    </div>

    <!-- LISTA DE USUARIOS EXISTENTES -->
    <div class="card-lista content-box">
        <h3>Usuarios Activos</h3>
        <div class="table-wrap">
            <table class="repair-table usuarios-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaUsuariosBody">
                    <!-- JS -->
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/js/usuarios.js?v=1.0"></script>

<?php include 'templates/footer.php'; ?>