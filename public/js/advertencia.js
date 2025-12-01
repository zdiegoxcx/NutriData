/**
 * advertencia.js
 * Maneja Modales (Rojo/Verde), Menús y Redirecciones.
 */

// Variables globales
let actionType = ''; // 'delete' o 'reactivate'
let targetId = 0;
let targetName = '';
let targetSubType = ''; // 'usuario', 'estudiante', etc.

if (typeof globalVista === 'undefined') {
    var globalVista = 'estudiantes'; 
}

let currentUrlParams = { est: null, cur: null };

// Elementos del DOM
const modal = document.getElementById('deleteModal');
const modalContent = modal.querySelector('.modal-danger'); // El div interno
const modalTitle = modal.querySelector('h2');
const inputName = document.getElementById('confirmInput');
const inputReason = document.getElementById('reasonInput');
const btnConfirm = document.getElementById('btnConfirmDelete'); // Antes btnDelete
const warningBox = document.getElementById('modalWarning');
const nameDisplay = document.getElementById('targetNameDisplay');
const divNameInput = document.getElementById('nameInputContainer');
const divReasonInput = document.getElementById('reasonInputContainer');

/* ========================================================
   1. LÓGICA DE MENÚS DESPLEGABLES
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
   2. LÓGICA DE MODALES (ELIMINAR Y REACTIVAR)
   ======================================================== */

// Función para abrir modal de REACTIVACIÓN (Verde)
function confirmReactivate(id, nombre) {
    actionType = 'reactivate';
    targetId = id;
    targetName = nombre;
    
    // Configurar Estilo VERDE
    modalContent.classList.remove('modal-danger');
    modalContent.classList.add('modal-success');
    
    // Configurar Textos
    modalTitle.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirmar Reactivación';
    warningBox.innerHTML = `¿Desea reactivar al usuario <strong>${nombre}</strong>?<br>Se restablecerá su acceso al sistema inmediatamente.`;
    btnConfirm.innerText = "Reactivar Usuario";
    
    // Ocultar Inputs (No necesarios para reactivar)
    divNameInput.style.display = 'none';
    divReasonInput.style.display = 'none';
    
    // Botón activo por defecto
    btnConfirm.classList.add('active');
    
    modal.style.display = 'flex';
}

// Función para abrir modal de ELIMINACIÓN (Rojo)
function openDeleteModal(tipo, id, nombre, id_establecimiento = null, id_curso = null) {
    actionType = 'delete';
    targetSubType = tipo;
    targetId = id;
    targetName = nombre;
    currentUrlParams.est = id_establecimiento;
    currentUrlParams.cur = id_curso;

    // Configurar Estilo ROJO
    modalContent.classList.remove('modal-success');
    modalContent.classList.add('modal-danger');
    
    modalTitle.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ¿Estás absolutamente seguro?';
    btnConfirm.innerText = "Eliminar definitivamente";

    // Resetear Inputs
    if(inputName) inputName.value = '';
    if(inputReason) inputReason.value = '';
    btnConfirm.classList.remove('active');
    
    // Configuración según tipo
    if (tipo === 'estudiante' || tipo === 'usuario') {
        let label = (tipo === 'usuario') ? `Va a desactivar al usuario <strong>${nombre}</strong>.` : `Va a eliminar al estudiante <strong>${nombre}</strong>.`;
        warningBox.innerHTML = `${label}<br>Esta acción requiere un motivo justificativo.`;
        
        divNameInput.style.display = 'none';
        divReasonInput.style.display = 'block';
        if(inputReason) inputReason.focus();
    } else {
        if(nameDisplay) nameDisplay.textContent = nombre;
        divNameInput.style.display = 'block';
        divReasonInput.style.display = 'none';
        if(inputName) inputName.focus();

        if (tipo === 'establecimiento') {
            warningBox.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>Se borrarán cursos y estudiantes asociados.`;
        } else if (tipo === 'curso') {
            warningBox.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>Se borrarán los estudiantes del curso.`;
        }
    }
    
    modal.style.display = 'flex';
}

function closeDeleteModal() {
    modal.style.display = 'none';
}

function validateDeleteInput() {
    // Solo validamos si estamos eliminando
    if (actionType === 'reactivate') return;

    if (targetSubType === 'estudiante' || targetSubType === 'usuario') {
        if (inputReason.value.trim().length > 0) btnConfirm.classList.add('active');
        else btnConfirm.classList.remove('active');
    } else {
        if (inputName.value === targetName) btnConfirm.classList.add('active');
        else btnConfirm.classList.remove('active');
    }
}

function executeDelete() {
    let url = '';

    if (actionType === 'reactivate') {
        // Lógica de Reactivación
        url = `dashboard_admin_bd.php?action=reactivar&id=${targetId}&vista=${globalVista}`;
    } else {
        // Lógica de Eliminación
        url = `dashboard_admin_bd.php?action=eliminar&tipo=${targetSubType}&id=${targetId}&vista=${globalVista}`;
        
        if (targetSubType === 'estudiante' || targetSubType === 'usuario') {
            url += `&motivo=${encodeURIComponent(inputReason.value.trim())}`;
        } else {
            if (inputName.value !== targetName) return;
        }

        if (currentUrlParams.est) url += `&id_establecimiento=${currentUrlParams.est}`;
        if (currentUrlParams.cur) url += `&id_curso=${currentUrlParams.cur}`;
    }
    
    window.location.href = url;
}