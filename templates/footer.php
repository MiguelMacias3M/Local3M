<script>
    // Buscamos el botón y el menú por sus ID
    const toggleButton = document.getElementById('menu-toggle');
    const menu = document.getElementById('navbar-menu');

    // Si existen, agregamos el evento clic
    if (toggleButton && menu) {
        toggleButton.addEventListener('click', () => {
            // Esto quita o pone la clase 'active' que definimos en el CSS
            menu.classList.toggle('active');
        });
    }

    // Tu script anti-caché original
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

<!-- ======================================================= -->
<!-- LA LÍNEA VITAL DEL CARRITO GLOBAL (NO BORRAR)           -->
<!-- ======================================================= -->
<script src="/local3M/js/carrito_global.js?v=<?php echo time(); ?>"></script>

<!-- ============================================== -->
<!-- OMNIBAR: BUSCADOR GLOBAL SPOTLIGHT + ROBOT     -->
<!-- ============================================== -->
<style>
/* OVERLAY DEL BUSCADOR */
.glass-spotlight-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.6) !important; backdrop-filter: blur(8px) !important; -webkit-backdrop-filter: blur(8px) !important; z-index: 9999999 !important; display: none; justify-content: center !important; align-items: flex-start !important; padding-top: 15vh !important; animation: fadeInSpotlight 0.2s ease-out; }
.spotlight-search-box { background: rgba(255, 255, 255, 0.95) !important; width: 90% !important; max-width: 650px !important; border-radius: 20px !important; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3) !important; display: flex !important; align-items: center !important; padding: 15px 25px !important; border: 1px solid rgba(255,255,255,1) !important; position: relative !important; overflow: hidden !important; transition: transform 0.1s; box-sizing: border-box !important; }
.spotlight-search-box::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #007aff, #00ffff, #34c759); }
.spotlight-search-box i { font-size: 28px !important; color: #007aff !important; margin-right: 20px !important; }
.spotlight-input { flex: 1 !important; border: none !important; background: transparent !important; font-family: 'Poppins', sans-serif !important; font-size: 24px !important; font-weight: 600 !important; color: #1d1d1f !important; outline: none !important; margin: 0 !important; padding: 0 !important; }
.spotlight-input::placeholder { color: #c7c7cc !important; font-weight: 500 !important; }
.spotlight-hint { position: absolute; top: calc(100% + 15px); left: 0; width: 100%; text-align: center; color: rgba(255,255,255,0.7); font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 500; }
@keyframes fadeInSpotlight { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.shake-error { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
@keyframes shake { 10%, 90% { transform: translate3d(-2px, 0, 0); } 20%, 80% { transform: translate3d(4px, 0, 0); } 30%, 50%, 70% { transform: translate3d(-6px, 0, 0); border-color: #ff3b30 !important; } 40%, 60% { transform: translate3d(6px, 0, 0); } }
@media (max-width: 600px) { .spotlight-search-box { padding: 12px 15px !important; } .spotlight-input { font-size: 18px !important; } .spotlight-search-box i { font-size: 20px !important; margin-right: 12px !important; } }
</style>

<div id="modalSpotlight" class="glass-spotlight-overlay">
    <div class="spotlight-search-box">
        <i id="iconSpotlight" class="fas fa-search"></i>
        <input type="text" id="inputSpotlight" class="spotlight-input" placeholder="Escanea un código..." autocomplete="off">
        <div class="spotlight-hint">Presiona <b>F2</b> o <b>Ctrl + Espacio</b> para abrir</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalSpotlight = document.getElementById('modalSpotlight');
    const inputSpotlight = document.getElementById('inputSpotlight');

    if (modalSpotlight && inputSpotlight) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F2' || (e.ctrlKey && e.code === 'Space')) {
                e.preventDefault(); abrirSpotlight();
            }
            if (e.key === 'Escape' && modalSpotlight.style.display === 'flex') { cerrarSpotlight(); }
        });

        modalSpotlight.addEventListener('click', (e) => { if (e.target === modalSpotlight) cerrarSpotlight(); });
        inputSpotlight.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); procesarBusquedaGlobal(); } });

        function abrirSpotlight() { modalSpotlight.style.display = 'flex'; inputSpotlight.value = ''; setTimeout(() => inputSpotlight.focus(), 50); }
        function cerrarSpotlight() { modalSpotlight.style.display = 'none'; inputSpotlight.blur(); }

        async function procesarBusquedaGlobal() {
            const codigo = inputSpotlight.value.trim();
            if (!codigo) return;
            inputSpotlight.disabled = true;
            const icon = document.getElementById('iconSpotlight');
            icon.className = 'fas fa-spinner fa-spin'; 

            let fd = new FormData(); fd.append('codigo', codigo);

            try {
                const res = await fetch('/local3M/api/buscador_global.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    window.location.href = '/local3M/' + json.url; 
                } else {
                    const searchBox = document.querySelector('.spotlight-search-box');
                    searchBox.classList.add('shake-error');
                    setTimeout(() => searchBox.classList.remove('shake-error'), 400);
                    inputSpotlight.value = ''; inputSpotlight.placeholder = 'No encontrado...';
                    inputSpotlight.disabled = false; inputSpotlight.focus(); icon.className = 'fas fa-search';
                }
            } catch (e) { inputSpotlight.disabled = false; icon.className = 'fas fa-search'; }
        }
    }

    // ---------------------------------------------------------
    // 2. ROBOT AUTO-EJECUTOR (Añade a Venta o Busca en Mercancía)
    // ---------------------------------------------------------
    const urlParams = new URLSearchParams(window.location.search);
    const searchCodigo = urlParams.get('search');
    
    if (searchCodigo) {
        setTimeout(() => {
            // Buscamos la caja, incluyendo la estándar de búsqueda en tablas (DataTables)
            const inputTarget = document.getElementById('codigo_barras') || 
                                document.getElementById('codigo') || 
                                document.getElementById('buscar') || 
                                document.querySelector('input[type="search"]') || 
                                document.querySelector('input[placeholder*="código" i]') || 
                                document.querySelector('input[placeholder*="barras" i]');
            
            if (inputTarget) {
                inputTarget.value = searchCodigo;
                inputTarget.focus();
                
                // Bombardeo de eventos Enter para obligar al sistema a hacer caso
                ['keydown', 'keypress', 'keyup'].forEach(type => {
                    inputTarget.dispatchEvent(new KeyboardEvent(type, { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
                });
                
                // Si el sistema usa jQuery, forzamos el Enter también por ahí
                if (typeof jQuery !== 'undefined') {
                    let e = jQuery.Event("keypress"); e.which = 13; e.keyCode = 13;
                    jQuery(inputTarget).trigger(e);
                    jQuery(inputTarget).trigger('input').trigger('change');
                } else {
                    inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
                    inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Si está en el punto de venta y tiene un botón de agregar cerca, lo apretamos
                if (window.location.pathname.includes('venta.php') && inputTarget.form) {
                    const btnSubmit = inputTarget.form.querySelector('button[type="submit"], input[type="submit"]');
                    if (btnSubmit) btnSubmit.click();
                }

                // Limpiamos la URL para evitar ejecuciones fantasma si recargas la página
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }, 600);
    }
});
</script>
<!-- ============================================== -->