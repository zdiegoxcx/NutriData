/**
 * advertencia.js
 * Maneja Modales, Menús y Redirecciones inteligentes.
 */

// Variables globales
let deleteType = '';
let deleteId = 0;
let targetName = '';

// CORRECCIÓN: No sobrescribimos la variable si ya viene desde PHP
// Si window.globalVista no existe, usamos 'estudiantes' por defecto.
if (typeof globalVista === 'undefined') {
    var globalVista = 'estudiantes'; 
}

let currentUrlParams = { est: null, cur: null };

// Elementos del DOM
const modal = document.getElementById('deleteModal');
const inputName = document.getElementById('confirmInput');
const inputReason = document.getElementById('reasonInput');
const btnDelete = document.getElementById('btnConfirmDelete');
const warningBox = document.getElementById('modalWarning');
const nameDisplay = document.getElementById('targetNameDisplay');
const divNameInput = document.getElementById('nameInputContainer');
const divReasonInput = document.getElementById('reasonInputContainer');

/* ========================================================
   1. LÓGICA DE MENÚS DESPLEGABLES (TRES PUNTOS)
   ======================================================== */
function toggleMenu(event, id) {
    event.stopPropagation(); 
    const dropdowns = document.getElementsByClassName("dropdown-menu");
    for (let i = 0; i < dropdowns.length; i++) {
        if (dropdowns[i].id !== 'menu-' + id) {
            dropdowns[i].classList.remove('show-menu');
        }
    }
    const menu = document.getElementById('menu-' + id);
    if (menu) menu.classList.toggle('show-menu');
}

window.addEventListener('click', function(e) {
    if (!e.target.matches('.btn-dots') && !e.target.matches('.fa-ellipsis-vertical')) {
        const dropdowns = document.getElementsByClassName("dropdown-menu");
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show-menu')) {
                dropdowns[i].classList.remove('show-menu');
            }
        }
    }
});

/* ========================================================
   2. LÓGICA DE REACTIVACIÓN
   ======================================================== */
function confirmReactivate(id, nombre) {
    if (confirm(`¿Desea reactivar al usuario "${nombre}"?\n\nSe borrará el historial de eliminación y podrá ingresar al sistema nuevamente.`)) {
        // Usamos la variable globalVista que viene inyectada desde el PHP
        window.location.href = `dashboard_admin_bd.php?action=reactivar&id=${id}&vista=${globalVista}`;
    }
}

/* ========================================================
   3. LÓGICA DEL MODAL DE ELIMINACIÓN
   ======================================================== */
function openDeleteModal(tipo, id, nombre, id_establecimiento = null, id_curso = null) {
    deleteType = tipo;
    deleteId = id;
    targetName = nombre;
    currentUrlParams.est = id_establecimiento;
    currentUrlParams.cur = id_curso;

    if(inputName) inputName.value = '';
    if(inputReason) inputReason.value = '';
    if(btnDelete) btnDelete.classList.remove('active');
    
    if(modal) modal.style.display = 'flex';

    // Configuración según tipo
    if (tipo === 'estudiante' || tipo === 'usuario') {
        let label = (tipo === 'usuario') ? `Va a desactivar al usuario <strong>${nombre}</strong>.` : `Va a eliminar al estudiante <strong>${nombre}</strong>.`;
        warningBox.innerHTML = `${label}<br>Esta acción requiere un motivo justificativo.`;
        
        if(divNameInput) divNameInput.style.display = 'none';
        if(divReasonInput) divReasonInput.style.display = 'block';
        if(inputReason) inputReason.focus();
    } else {
        if(nameDisplay) nameDisplay.textContent = nombre;
        if(divNameInput) divNameInput.style.display = 'block';
        if(divReasonInput) divReasonInput.style.display = 'none';
        if(inputName) inputName.focus();

        if (tipo === 'establecimiento') {
            warningBox.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>Se borrarán cursos y estudiantes asociados.`;
        } else if (tipo === 'curso') {
            warningBox.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>Se borrarán los estudiantes del curso.`;
        }
    }
}

function closeDeleteModal() {
    if(modal) modal.style.display = 'none';
}

function validateDeleteInput() {
    if (deleteType === 'estudiante' || deleteType === 'usuario') {
        if (inputReason.value.trim().length > 0) btnDelete.classList.add('active');
        else btnDelete.classList.remove('active');
    } else {
        if (inputName.value === targetName) btnDelete.classList.add('active');
        else btnDelete.classList.remove('active');
    }
}

function executeDelete() {
    // Usamos globalVista para mantenernos en la pestaña correcta (usuarios o estudiantes)
    let url = `dashboard_admin_bd.php?action=eliminar&tipo=${deleteType}&id=${deleteId}&vista=${globalVista}`;
    
    if (deleteType === 'estudiante' || deleteType === 'usuario') {
        url += `&motivo=${encodeURIComponent(inputReason.value.trim())}`;
    } else {
        if (inputName.value !== targetName) return;
    }

    if (currentUrlParams.est) url += `&id_establecimiento=${currentUrlParams.est}`;
    if (currentUrlParams.cur) url += `&id_curso=${currentUrlParams.cur}`;
    
    window.location.href = url;
}