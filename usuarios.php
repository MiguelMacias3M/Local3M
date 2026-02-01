<?php include 'templates/header.php'; ?>
<link rel="stylesheet" href="css/usuarios.css?v=<?php echo time(); ?>">
<style>
    .pass-wrapper { position: relative; }
    .toggle-pass { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
</style>

<div class="page-title">
    <h1>Gestión de Usuarios</h1>
    <button class="form-button btn-add" onclick="abrirModal()">
        <i class="fas fa-user-plus"></i> Nuevo Usuario
    </button>
</div>

<div class="content-box">
    <table class="repair-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaUsuarios"></tbody>
    </table>
</div>

<div id="modalUsuario" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 id="modalTitulo">Nuevo Usuario</h2>
        <form id="formUsuario" style="margin-top: 20px;">
            <input type="hidden" id="userId">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" id="nombre" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Rol</label>
                <select id="rol" class="form-input">
                    <option value="Admin">Administrador</option>
                    <option value="Tecnico">Técnico</option>
                    <option value="Vendedor">Vendedor</option>
                </select>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <div class="pass-wrapper">
                    <input type="password" id="password" class="form-input" placeholder="Dejar vacía para mantener actual">
                    <i class="fas fa-eye toggle-pass" onclick="togglePassword()"></i>
                </div>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="form-button btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="form-button btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/usuarios.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>