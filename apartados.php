<?php
session_start();
if (!isset($_SESSION['nombre'])) { header("Location: /local3M/login.php"); exit(); }
include 'templates/header.php';
?>

<link rel="stylesheet" href="/local3M/css/apartados.css">

<div class="container glass-container">
    <div class="glass-header">
        <div>
            <h2><i class="fas fa-book" style="color: #ff9500;"></i> Control de Apartados</h2>
            <p>Gestiona los pagos, deudas y liquidaciones de tus clientes.</p>
        </div>
        <div>
            <a href="/local3M/equipos.php" class="glass-btn">
                <i class="fas fa-arrow-left"></i> Volver a Vitrina
            </a>
        </div>
    </div>

    <div class="glass-card">
        <div class="glass-table-wrapper">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Cliente / Contacto</th>
                        <th>Equipo Apartado</th>
                        <th>Vencimiento</th>
                        <th>Restante</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-apartados">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalAbono" class="glass-modal-overlay">
    <div class="glass-modal-content">
        <h3 class="modal-title">Registrar Abono</h3>
        
        <form id="formAbono">
            <input type="hidden" id="abono_id_apartado">
            
            <div class="debt-display-box">
                <p class="debt-label">Deuda Actual:</p>
                <h2 id="abono_deuda_actual" class="debt-amount">$0.00</h2>
            </div>

            <div class="glass-input-group">
                <label class="glass-label">Monto a Abonar</label>
                <div class="input-icon-wrapper">
                    <span class="input-icon-currency">$</span>
                    <input type="number" id="abono_monto" class="glass-input input-with-icon" step="0.01" required placeholder="0.00">
                </div>
            </div>

            <div class="glass-input-group">
                <label class="glass-label">Método de Pago</label>
                <select id="abono_metodo" class="glass-input" required>
                    <option value="Efectivo">💵 Efectivo</option>
                    <option value="Transferencia">📱 Transferencia</option>
                    <option value="Terminal">💳 Terminal / Tarjeta</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="glass-btn btn-flex-1" onclick="cerrarModalAbono()">Cancelar</button>
                <button type="submit" class="glass-btn primary success btn-flex-1">Cobrar Abono</button>
            </div>
        </form>
    </div>
</div>

<script src="js/apartados.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>