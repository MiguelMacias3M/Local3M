document.addEventListener('DOMContentLoaded', () => {
    const modalSpotlight = document.getElementById('modalSpotlight');
    const inputSpotlight = document.getElementById('inputSpotlight');

    // Escuchar atajos de teclado (F2 o Ctrl + Espacio) para abrir el buscador
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2' || (e.ctrlKey && e.code === 'Space')) {
            e.preventDefault();
            abrirSpotlight();
        }
        
        // Cerrar con Escape
        if (e.key === 'Escape' && modalSpotlight.style.display === 'flex') {
            cerrarSpotlight();
        }
    });

    // Cerrar al dar clic fuera del buscador
    modalSpotlight.addEventListener('click', (e) => {
        if (e.target === modalSpotlight) {
            cerrarSpotlight();
        }
    });

    // Escuchar el "Enter" de la pistola de código de barras
    inputSpotlight.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            procesarBusquedaGlobal();
        }
    });

    function abrirSpotlight() {
        modalSpotlight.style.display = 'flex';
        inputSpotlight.value = '';
        setTimeout(() => inputSpotlight.focus(), 50); 
    }

    function cerrarSpotlight() {
        modalSpotlight.style.display = 'none';
        inputSpotlight.blur();
    }

    async function procesarBusquedaGlobal() {
        const codigo = inputSpotlight.value.trim();
        if (!codigo) return;

        inputSpotlight.disabled = true;
        const icon = document.getElementById('iconSpotlight');
        icon.className = 'fas fa-spinner fa-spin'; 

        let fd = new FormData();
        fd.append('codigo', codigo);

        try {
            const res = await fetch('/local3M/api/buscador_global.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                // Teletransportación a la URL indicada por la API
                window.location.href = json.url;
            } else {
                // Efecto de "temblor" si no encuentra el código
                const searchBox = document.querySelector('.spotlight-search-box');
                searchBox.classList.add('shake-error');
                setTimeout(() => searchBox.classList.remove('shake-error'), 400);
                
                inputSpotlight.value = '';
                inputSpotlight.placeholder = 'Código no encontrado...';
                inputSpotlight.disabled = false;
                inputSpotlight.focus();
                icon.className = 'fas fa-search';
            }
        } catch (e) {
            inputSpotlight.disabled = false;
            icon.className = 'fas fa-search';
        }
    }
}); 