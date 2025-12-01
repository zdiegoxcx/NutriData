function darFormatoRut(input) {
    // 1. Guardar posición del cursor
    let cursor = input.selectionStart;
    
    // 2. Limpiar todo lo que no sea número o K
    let valor = input.value.replace(/[^0-9kK]/g, '').toUpperCase();
    
    // --- NUEVO: LIMITAR EL LARGO ---
    // Un RUT chileno tiene máximo 9 dígitos (8 cuerpo + 1 verificador)
    // Ejemplo máximo: 99.999.999-K
    if (valor.length > 9) {
        valor = valor.slice(0, 9);
    }
    // -------------------------------

    // 3. Si está vacío, terminar
    if (valor.length === 0) {
        input.value = "";
        return;
    }

    // 4. Separar cuerpo y dígito verificador
    let cuerpo = valor;
    let dv = "";
    
    if (valor.length > 1) {
        cuerpo = valor.slice(0, -1);
        dv = valor.slice(-1);
    }
    
    // 5. Poner puntos al cuerpo
    input.value = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + (valor.length > 1 ? "-" + dv : "");
}