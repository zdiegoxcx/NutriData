/**
 * advertencia.js
 * Maneja la lógica de la ventana modal de confirmación de eliminación segura.
 */

// Variables globales para almacenar qué estamos borrando
let deleteType = '';
let deleteId = 0;
let targetName = '';
let currentUrlParams = { est: null, cur: null };

// Elementos del DOM
const modal = document.getElementById('deleteModal');
const input = document.getElementById('confirmInput');
const btnDelete = document.getElementById('btnConfirmDelete');
const warningBox = document.getElementById('modalWarning');
const nameDisplay = document.getElementById('targetNameDisplay');

// Función para ABRIR el modal
function openDeleteModal(tipo, id, nombre, id_establecimiento = null, id_curso = null) {
    deleteType = tipo;
    deleteId = id;
    targetName = nombre;
    currentUrlParams.est = id_establecimiento;
    currentUrlParams.cur = id_curso;

    // Limpiar input y botón
    if(input) input.value = '';
    if(btnDelete) btnDelete.classList.remove('active');
    
    // Configurar textos
    if(nameDisplay) nameDisplay.textContent = nombre;

    if (tipo === 'establecimiento') {
        warningBox.innerHTML = `
            <i class="fa-solid fa-circle-exclamation"></i> <strong>ADVERTENCIA:</strong><br>
            Al eliminar este Establecimiento, <strong>se borrarán automáticamente todos sus CURSOS y ESTUDIANTES</strong> asociados. <br>
            Los datos dejarán de estar accesibles inmediatamente.
        `;
    } else if (tipo === 'curso') {
        warningBox.innerHTML = `
            <i class="fa-solid fa-circle-exclamation"></i> <strong>ADVERTENCIA:</strong><br>
            Al eliminar este Curso, <strong>se borrarán todos los ESTUDIANTES</strong> que pertenecen a él.
        `;
    }

    // Mostrar modal
    if(modal) {
        modal.style.display = 'flex';
        input.focus();
    }
}

// Función para CERRAR el modal
function closeDeleteModal() {
    if(modal) modal.style.display = 'none';
}

// Función para VALIDAR lo que escribe el usuario
function validateDeleteInput() {
    if (input.value === targetName) {
        btnDelete.classList.add('active'); // Habilitar botón
    } else {
        btnDelete.classList.remove('active'); // Deshabilitar botón
    }
}

// Función para EJECUTAR el borrado (Redirigir a PHP)
function executeDelete() {
    if (input.value !== targetName) return;

    let url = `dashboard_admin_bd.php?action=eliminar&tipo=${deleteType}&id=${deleteId}`;
    if (currentUrlParams.est) url += `&id_establecimiento=${currentUrlParams.est}`;
    if (currentUrlParams.cur) url += `&id_curso=${currentUrlParams.cur}`;
    
    window.location.href = url;
}

// Función simple para estudiantes (sin modal complejo, solo confirm)
function confirmSimpleDelete(tipo, id, nombre, id_establecimiento, id_curso) {
    if (confirm(`¿Eliminar estudiante "${nombre}"?`)) {
        let url = `dashboard_admin_bd.php?action=eliminar&tipo=${tipo}&id=${id}&id_establecimiento=${id_establecimiento}&id_curso=${id_curso}`;
        window.location.href = url;
    }
}

// Cerrar modal si se hace clic fuera (Event Listener)
window.onclick = function(event) {
    if (event.target == modal) {
        closeDeleteModal();
    }
}