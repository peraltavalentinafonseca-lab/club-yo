document.addEventListener('DOMContentLoaded', () => {
    const logueado = localStorage.getItem('logueado');
    if (logueado !== 'true') {
        window.location.href = 'login.html';
        return;
    }
    const nombre = localStorage.getItem('nombre') || 'Usuario';
    const rol = (localStorage.getItem('rol') || '').toLowerCase();

    const nombreEl = document.getElementById('nombreUsuario');
    if (nombreEl) {
        nombreEl.textContent = nombre;
    }

    const textoRolEl = document.getElementById('textoRol');
    const subtituloEl = document.getElementById('subtituloUsuario');
    const adminCards = document.getElementById('tarjetasAdministrador');
    const asociadoCards = document.getElementById('tarjetasAsociado');

    if (rol === 'admin' || rol === 'administrador') {
        if (textoRolEl) textoRolEl.textContent = 'Administrador';
        if (subtituloEl) subtituloEl.textContent = 'Gestiona préstamos, asociados y actividades del club.';
        if (adminCards) adminCards.classList.remove('d-none');
        if (asociadoCards) asociadoCards.classList.add('d-none');
    } else {
        if (textoRolEl) textoRolEl.textContent = 'Asociado';
        if (subtituloEl) subtituloEl.textContent = 'Selecciona un módulo para consultar tu información.';
        if (adminCards) adminCards.classList.add('d-none');
        if (asociadoCards) asociadoCards.classList.remove('d-none');
    }
});

async function cerrarSecion() {
    try { 
        const respuesta = await fetch("../Modelos/cerrarSesion.php", {method: "POST"})
        
        if (!respuesta.ok) {
            throw new Error("Error al cerrar sesión");
        }
        localStorage.clear();
        window.location.href = 'login.html';
    } catch (error) {
        console.error("Error al cerrar sesión:", error);
    }
}