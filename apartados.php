<?php
session_start();
if (!isset($_SESSION['nombre'])) { header("Location: /local3M/login.php"); exit(); }
include 'templates/header.php';
?>

<style>
    .glass-container { padding: 20px; animation: fadeIn 0.5s ease-in-out; color: #1d1d1f; }
    .glass-card { background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); padding: 30px; margin-bottom: 25px; }
    .glass-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .glass-header h2 { margin: 0; font-weight: 700; font-size: 24px; letter-spacing: -0.5px; }
    .glass-header p { color: #86868b; margin-top: 5px; font-size: 15px; }
    .glass-btn { background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 14px; padding: 12px 20px; font-weight: 600; font-size: 14px; color: #1d1d1f; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
    .glass-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .glass-btn.primary { background: rgba(0, 122, 255, 0.9); color: white; border: 1px solid rgba(0, 122, 255, 0.4); }
    .glass-table-wrapper { border-radius: 16px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.3); }
    .glass-table { width: 100%; border-collapse: collapse; }
    .glass-table th { background: rgba(255, 255, 255, 0.5); padding: 16px; text-align: left; font-weight: 600; font-size: 13px; color: #86868b; text-transform: uppercase; border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
    .glass-table td { padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.4); font-size: 14px; font-weight: 500; }
    .glass-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
    .glass-modal-content { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.9); border-radius: 28px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2); padding: 35px; width: 90%; max-width: 450px; transform: scale(0.95); transition: transform 0.3s; }
    .glass-input-group { margin-bottom: 18px; }
    .glass-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #1d1d1f; }
    .glass-input { width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 14px; font-size: 15px; color: #1d1d1f; outline: none; box-sizing: border-box; }
    .glass-input:focus { background: rgba(255, 255, 255, 0.9); border-color: #007aff; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.15); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .show-modal { opacity: 1 !important; } .show-modal .glass-modal-content { transform: scale(1) !important; }
</style>

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
                        <th style="text-align: center;">Acciones</th>
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
        <h3 style="margin-top: 0; margin-bottom: 20px; font-weight: 700; text-align: center;">Registrar Abono</h3>
        
        <form id="formAbono">
            <input type="hidden" id="abono_id_apartado">
            
            <div style="background: rgba(0,122,255,0.1); padding: 15px; border-radius: 14px; margin-bottom: 20px; text-align: center;">
                <p style="margin:0; font-size: 13px; color: #555;">Deuda Actual:</p>
                <h2 id="abono_deuda_actual" style="margin:5px 0 0 0; color: #007aff;">$0.00</h2>
            </div>

            <div class="glass-input-group">
                <label class="glass-label">Monto a Abonar</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 16px; top: 14px; font-weight: bold; color: #888;">$</span>
                    <input type="number" id="abono_monto" class="glass-input" step="0.01" style="padding-left: 35px;" required placeholder="0.00">
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

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="button" class="glass-btn" style="flex: 1; justify-content: center;" onclick="cerrarModalAbono()">Cancelar</button>
                <button type="submit" class="glass-btn primary" style="flex: 1; justify-content: center; background: #34c759; border-color: #34c759;">Cobrar Abono</button>
            </div>
        </form>
    </div>
</div>

<script src="js/apartados.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>