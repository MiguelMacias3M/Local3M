<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    header("Location: /local3M/login.php");
    exit();
}
include 'templates/header.php';
?>

<style>
    /* =========================================
       ESTILO GLASSMORPHISM (iOS / Apple Style)
       ========================================= */
    .glass-container {
        padding: 20px;
        animation: fadeIn 0.5s ease-in-out;
        color: #1d1d1f;
    }

    /* Tarjetas principales de cristal */
    .glass-card {
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        padding: 30px;
        margin-bottom: 25px;
    }

    .glass-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .glass-header h2 {
        margin: 0;
        font-weight: 700;
        font-size: 24px;
        letter-spacing: -0.5px;
    }

    .glass-header p {
        color: #86868b;
        margin-top: 5px;
        font-size: 15px;
    }

    /* Botones estilo iOS */
    .glass-btn {
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 14px;
        padding: 12px 20px;
        font-weight: 600;
        font-size: 14px;
        color: #1d1d1f;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .glass-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .glass-btn:active {
        transform: translateY(1px);
    }

    .glass-btn.primary {
        background: rgba(0, 122, 255, 0.9); /* Azul Apple */
        color: white;
        border: 1px solid rgba(0, 122, 255, 0.4);
    }

    .glass-btn.warning {
        background: rgba(255, 149, 0, 0.9); /* Naranja Apple */
        color: white;
        border: 1px solid rgba(255, 149, 0, 0.4);
    }

    /* Tabla transparente */
    .glass-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.5);
        background: rgba(255, 255, 255, 0.3);
    }

    .glass-table {
        width: 100%;
        border-collapse: collapse;
    }

    .glass-table th {
        background: rgba(255, 255, 255, 0.5);
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: #86868b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .glass-table td {
        padding: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.4);
        font-size: 14px;
        font-weight: 500;
    }

    .glass-table tr:last-child td {
        border-bottom: none;
    }

    .glass-table tr:hover td {
        background: rgba(255, 255, 255, 0.4);
    }

    /* Modal de Cristal Esmerilado */
    .glass-modal-overlay {
        display: none; 
        position: fixed; 
        top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0, 0, 0, 0.3); 
        backdrop-filter: blur(15px); /* Fondo súper borroso */
        -webkit-backdrop-filter: blur(15px);
        z-index: 1000; 
        justify-content: center; 
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .glass-modal-content {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.9);
        border-radius: 28px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        padding: 35px;
        width: 90%; 
        max-width: 550px; 
        max-height: 90vh; 
        overflow-y: auto;
        transform: scale(0.95);
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Inputs estilo iOS */
    .glass-input-group {
        margin-bottom: 18px;
    }

    .glass-input-row {
        display: flex;
        gap: 15px;
        margin-bottom: 18px;
    }

    .glass-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: #1d1d1f;
    }

    .glass-input {
        width: 100%;
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 14px;
        font-size: 15px;
        color: #1d1d1f;
        outline: none;
        transition: all 0.2s;
        box-sizing: border-box;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }

    .glass-input:focus {
        background: rgba(255, 255, 255, 0.9);
        border-color: #007aff;
        box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.15);
    }

    /* Animaciones */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .show-modal { opacity: 1 !important; }
    .show-modal .glass-modal-content { transform: scale(1) !important; }

    /* Scrollbar invisible Mac Style */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.3); }
</style>

<div class="container glass-container">
    
    <div class="glass-header">
        <div>
            <h2><i class="fas fa-mobile-alt" style="color: #007aff;"></i> Inventario Elite</h2>
            <p>Control de equipos de alto valor y sistema de apartados.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="glass-btn primary" onclick="abrirModalEquipo()">
                <i class="fas fa-plus"></i> Registrar Equipo
            </button>
            <button class="glass-btn warning" onclick="verApartados()">
                <i class="fas fa-book"></i> Ver Apartados
            </button>
        </div>
    </div>

    <!-- Tabla de Equipos en Vitrina -->
    <div class="glass-card">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-weight: 600; font-size: 18px;">Equipos en Vitrina</h3>
        
        <div class="glass-table-wrapper">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Marca / Modelo</th>
                        <th>IMEI / Serie</th>
                        <th>Precio Venta</th>
                        <th>Estado</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-equipos">
                    <!-- Aquí se cargarán los equipos con JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Registrar Nuevo Equipo -->
<div id="modalEquipo" class="glass-modal-overlay">
    <div class="glass-modal-content">
        <h3 id="tituloModalEquipo" style="margin-top: 0; margin-bottom: 25px; font-weight: 700; font-size: 22px; text-align: center;">Registrar Nuevo Equipo</h3>
        
        <form id="formEquipo">
            <input type="hidden" id="equipo_id">
            
            <div class="glass-input-group">
                <label class="glass-label">Tipo de Equipo</label>
                <select id="eq_tipo" class="glass-input" required>
                    <option value="">Seleccione...</option>
                    <option value="Celular">📱 Celular</option>
                    <option value="Bicicleta Eléctrica">🚲 Bicicleta Eléctrica</option>
                    <option value="Tableta">💻 Tableta</option>
                    <option value="Consola">🎮 Consola de Videojuegos</option>
                    <option value="Otro">📦 Otro</option>
                </select>
            </div>

            <div class="glass-input-row">
                <div style="flex: 1;">
                    <label class="glass-label">Marca</label>
                    <input type="text" id="eq_marca" class="glass-input" required placeholder="Ej. Apple, Solomo">
                </div>
                <div style="flex: 1;">
                    <label class="glass-label">Modelo</label>
                    <input type="text" id="eq_modelo" class="glass-input" required placeholder="Ej. iPhone 13">
                </div>
            </div>

            <div class="glass-input-group">
                <label class="glass-label">IMEI o Número de Serie</label>
                <input type="text" id="eq_serie" class="glass-input" required placeholder="Obligatorio para garantías">
            </div>

            <div class="glass-input-group">
                <label class="glass-label">Color / Detalles Adicionales</label>
                <input type="text" id="eq_color" class="glass-input" placeholder="Ej. Gris espacial, 128GB">
            </div>

            <div class="glass-input-row">
                <div style="flex: 1;">
                    <label class="glass-label">Costo (Compra)</label>
                    <input type="number" id="eq_costo" class="glass-input" step="0.01" required placeholder="$ 0.00">
                </div>
                <div style="flex: 1;">
                    <label class="glass-label">Precio de Venta</label>
                    <input type="number" id="eq_precio" class="glass-input" step="0.01" required placeholder="$ 0.00">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px;">
                <button type="button" class="glass-btn" onclick="cerrarModalEquipo()">Cancelar</button>
                <button type="submit" class="glass-btn primary" style="background: rgba(52, 199, 89, 0.9); border-color: rgba(52, 199, 89, 0.4);">
                    <i class="fas fa-check"></i> Guardar Equipo
                </button>
            </div>
        </form>
    </div>
</div>


<script>
    // Pequeño script para añadir la animación de entrada al modal de cristal
    function abrirModalEquipo() {
        const modal = document.getElementById('modalEquipo');
        modal.style.display = 'flex';
        // Un pequeñísimo retraso para que la transición CSS haga su magia
        setTimeout(() => modal.classList.add('show-modal'), 10);
    }

    function cerrarModalEquipo() {
        const modal = document.getElementById('modalEquipo');
        modal.classList.remove('show-modal');
        // Esperamos a que termine la animación antes de ocultarlo
        setTimeout(() => modal.style.display = 'none', 300);
    }
</script>
<!-- Modal: ¿Vender o Apartar? -->
<div id="modalAccionEquipo" class="glass-modal-overlay">
    <div class="glass-modal-content" style="max-width: 400px; text-align: center;">
        <h3 style="margin-top: 0; margin-bottom: 10px; font-weight: 700; font-size: 20px;">¿Qué deseas hacer?</h3>
        <p id="texto-accion-equipo" style="color: #86868b; margin-bottom: 25px; font-size: 14px;"></p>
        
        <!-- Guardamos los datos temporalmente escondidos -->
        <input type="hidden" id="accion_equipo_id">
        <input type="hidden" id="accion_equipo_precio">
        <input type="hidden" id="accion_equipo_nombre">

        <div style="display: flex; flex-direction: column; gap: 15px;">
            <button class="glass-btn primary" onclick="mandarAlCarritoGlobal()" style="justify-content: center; font-size: 16px; padding: 15px; background: rgba(52, 199, 89, 0.9); border-color: rgba(52, 199, 89, 0.4);">
                <i class="fas fa-shopping-cart"></i> Vender al Contado
            </button>
            <button class="glass-btn warning" onclick="abrirModalApartado()" style="justify-content: center; font-size: 16px; padding: 15px;">
                <i class="fas fa-calendar-alt"></i> Iniciar Sistema de Apartado
            </button>
        </div>
        
        <div style="margin-top: 25px;">
            <button class="glass-btn" onclick="cerrarModalAccion()" style="width: 100%; justify-content: center;">Cancelar</button>
        </div>
    </div>
</div>
<script>
    const ROL_USUARIO = '<?php echo isset($_SESSION["rol"]) ? strtolower($_SESSION["rol"]) : "empleado"; ?>';
</script>

<script src="js/equipos.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>