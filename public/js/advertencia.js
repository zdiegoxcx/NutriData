/**
 * advertencia.js
 * - Maneja el Modal de Eliminación.
 * - Maneja los Menús Desplegables (Tres puntos).
 */

let deleteType = '';
let deleteId = 0;
let targetName = '';
let currentUrlParams = { est: null, cur: null };

// Elementos del Modal
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
    event.stopPropagation(); // Evita que se active el click de la fila
    
    // Cerrar todos los otros menús abiertos
    const dropdowns = document.getElementsByClassName("dropdown-menu");
    for (let i = 0; i < dropdowns.length; i++) {
        if (dropdowns[i].id !== 'menu-' + id) {
            dropdowns[i].classList.remove('show-menu');
        }
    }
    
    // Alternar el menú actual
    const menu = document.getElementById('menu-' + id);
    if (menu) {
        menu.classList.toggle('show-menu');
    }
}

// Cerrar menús al hacer click en cualquier otro lado
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
   2. LÓGICA DEL MODAL DE ELIMINACIÓN
   ======================================================== */
function openDeleteModal(tipo, id, nombre, id_establecimiento = null, id_curso = null) {
    deleteType = tipo;
    deleteId = id;
    targetName = nombre;
    currentUrlParams.est = id_establecimiento;
    currentUrlParams.cur = id_curso;

    // Resetear Inputs
    if(inputName) inputName.value = '';
    if(inputReason) inputReason.value = '';
    if(btnDelete) btnDelete.classList.remove('active');
    
    if(modal) modal.style.display = 'flex';

    // CONFIGURACIÓN SEGÚN TIPO
    // Si es USUARIO o ESTUDIANTE => Pedir Motivo
    if (tipo === 'estudiante' || tipo === 'usuario') {
        let label = (tipo === 'usuario') ? `Va a desactivar al usuario <strong>${nombre}</strong>.` : `Va a eliminar al estudiante <strong>${nombre}</strong>.`;
        warningBox.innerHTML = `${label}<br>Esta acción requiere un motivo justificativo.`;
        
        if(divNameInput) divNameInput.style.display = 'none';
        if(divReasonInput) divReasonInput.style.display = 'block';
        if(inputReason) inputReason.focus();

    } else {
        // Si es ESTRUCTURA (Curso/Colegio) => Pedir Nombre
        if(nameDisplay) nameDisplay.textContent = nombre;
        
        if(divNameInput) divNameInput.style.display = 'block';
        if(divReasonInput) divReasonInput.style.display = 'none';
        if(inputName) inputName.focus();

        if (tipo === 'establecimiento') {
            warningBox.innerHTML = `
                <i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>
                Se borrarán automáticamente todos los <strong>CURSOS y ESTUDIANTES</strong> de este establecimiento.<br>
            `;
        } else if (tipo === 'curso') {
            warningBox.innerHTML = `
                <i class="fa-solid fa-circle-exclamation"></i> <strong>PELIGRO:</strong><br>
                Se borrarán todos los <strong>ESTUDIANTES</strong> de este curso.<br>
            `;
        }
    }
}

function closeDeleteModal() {
    if(modal) modal.style.display = 'none';
}

function validateDeleteInput() {
    // Si es Usuario o Estudiante, validamos motivo
    if (deleteType === 'estudiante' || deleteType === 'usuario') {
        if (inputReason.value.trim().length > 0) {
            btnDelete.classList.add('active');
        } else {
            btnDelete.classList.remove('active');
        }
    } else {
        // Si es Curso o Colegio, validamos nombre
        if (inputName.value === targetName) {
            btnDelete.classList.add('active');
        } else {
            btnDelete.classList.remove('active');
        }
    }
}

function executeDelete() {
    let url = `dashboard_admin_bd.php?action=eliminar&tipo=${deleteType}&id=${deleteId}`;
    
    // Agregar motivo si corresponde
    if (deleteType === 'estudiante' || deleteType === 'usuario') {
        const motivo = encodeURIComponent(inputReason.value.trim());
        url += `&motivo=${motivo}`;
    } else {
        if (inputName.value !== targetName) return;
    }

    if (currentUrlParams.est) url += `&id_establecimiento=${currentUrlParams.est}`;
    if (currentUrlParams.cur) url += `&id_curso=${currentUrlParams.cur}`;
    
    window.location.href = url;
}