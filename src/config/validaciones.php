<?php
function validarRut($rut) {
    // Eliminar puntos y guiones
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    
    // Verificar largo mínimo
    if (strlen($rut) < 8) return false;
    
    $dv = substr($rut, -1);
    $numero = substr($rut, 0, strlen($rut) - 1);
    $i = 2;
    $suma = 0;
    
    foreach (array_reverse(str_split($numero)) as $v) {
        if ($i == 8) $i = 2;
        $suma += $v * $i;
        $i++;
    }
    
    $dvr = 11 - ($suma % 11);
    if ($dvr == 11) $dvr = 0;
    if ($dvr == 10) $dvr = 'K';
    
    return strtoupper($dv) == strtoupper($dvr);
}

function validarSoloLetras($texto) {
    // Permite letras, espacios y tildes/ñ
    return preg_match('/^[a-zA-ZñÑáéíóúÁÉÍÓÚ\s]+$/u', $texto);
}
?>