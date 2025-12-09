<?php
/**
 * Script de Poblado Masivo para NutriData (VERSIÓN CORREGIDA FINAL)
 * Soluciona el error de Foreign Key 'Id_Rol'
 */

require_once __DIR__ . '/../src/config/db.php';

// Configuración para scripts pesados
set_time_limit(0);
ini_set('memory_limit', '2048M');

$pdo = getConnection();
$password_hash = '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa'; // Clave: 12345

// --- DATOS ALEATORIOS ---
$nombres = ['Juan', 'Pedro', 'Diego', 'Jose', 'Luis', 'Carlos', 'Andres', 'Felipe', 'Sebastian', 'Cristian', 'Maria', 'Ana', 'Claudia', 'Carolina', 'Daniela', 'Francisca', 'Camila', 'Valentina', 'Sofia', 'Isabella', 'Martina', 'Fernanda', 'Javiera', 'Constanza'];
$apellidos = ['Silva', 'Gonzalez', 'Rojas', 'Muñoz', 'Diaz', 'Perez', 'Soto', 'Contreras', 'Morales', 'Lopez', 'Rodriguez', 'Martinez', 'Fuentes', 'Valenzuela', 'Araya', 'Sepulveda', 'Espinoza', 'Vargas', 'Castillo', 'Tapia', 'Reyes', 'Gutierrez', 'Castro', 'Pizarro'];
$comunas_biobio = [
    'Concepción', 'Talcahuano', 'San Pedro de la Paz', 'Chiguayante', 'Hualpén', 'Coronel', 'Lota', 
    'Penco', 'Tomé', 'Florida', 'Hualqui', 'Santa Juana', 'Los Ángeles', 'Cabrero', 'Tucapel', 
    'Antuco', 'Quilleco', 'Alto Biobío', 'Santa Bárbara', 'Quilaco', 'Mulchén', 'Negrete', 
    'Nacimiento', 'Laja', 'San Rosendo', 'Yumbel', 'Arauco', 'Curanilahue', 'Lebu', 'Los Álamos'
];
$niveles = ['1° Básico', '2° Básico', '3° Básico', '4° Básico', '5° Básico', '6° Básico', '7° Básico', '8° Básico', '1° Medio', '2° Medio', '3° Medio', '4° Medio'];
$letras = ['A', 'B', 'C'];

// --- FUNCIONES ---
function generarRut($id_base) {
    $numero = 20000000 + $id_base; 
    $i = 2; $suma = 0;
    foreach (array_reverse(str_split($numero)) as $v) {
        if ($i == 8) $i = 2;
        $suma += $v * $i;
        $i++;
    }
    $dvr = 11 - ($suma % 11);
    $dv = ($dvr == 11) ? '0' : (($dvr == 10) ? 'K' : $dvr);
    return number_format($numero, 0, '', '.') . "-" . $dv;
}

function calcularDiagnostico($imc) {
    if ($imc < 16) return 'Bajo Peso';
    if ($imc >= 16 && $imc < 24) return 'Normal';
    if ($imc >= 24 && $imc < 29) return 'Sobrepeso';
    if ($imc >= 29 && $imc < 34) return 'Obesidad';
    return 'Obesidad Severa';
}

echo "<h1>🚀 Iniciando Carga Masiva v2...</h1><pre>";

// 1. LIMPIEZA TOTAL (Fuera de transacción)
try {
    echo "Limpiando base de datos...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tablas = ['Alerta', 'RegistroNutricional', 'Estudiante', 'Curso', 'Establecimiento', 'Direccion', 'Reporte', 'Usuario', 'Comuna', 'Rol', 'Region'];
    foreach ($tablas as $t) $pdo->exec("TRUNCATE TABLE $t");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
} catch (Exception $e) {
    die("❌ Error al limpiar: " . $e->getMessage());
}

// 2. INSERCIÓN MASIVA
try {
    $pdo->beginTransaction();

    // --- A. ROLES Y GEOGRAFÍA (CRÍTICO: Insertar Roles PRIMERO) ---
    echo "Restaurando Roles y Región...\n";
    // IDs esperados: 1=AdminBD, 2=Profesor, 3=AdminDAEM
    $pdo->exec("INSERT INTO Rol (Nombre) VALUES ('administradorBD'), ('profesor'), ('administradorDAEM')");
    
    $pdo->exec("INSERT INTO Region (Region) VALUES ('Biobío')");
    $id_region = $pdo->lastInsertId();
    
    $ids_comunas = [];
    $stmt_com = $pdo->prepare("INSERT INTO Comuna (Comuna, Id_Region) VALUES (?, ?)");
    foreach ($comunas_biobio as $nom) {
        $stmt_com->execute([$nom, $id_region]);
        $ids_comunas[] = $pdo->lastInsertId();
    }

    // --- B. USUARIOS (50 Total) ---
    echo "Creando Usuarios...\n";
    
    // Admin BD (Rol 1)
    $pdo->prepare("INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Email, Estado) VALUES (1, '11.111.111-1', 'Diego', 'AdminBD', ?, 'admin@nutri.cl', 1)")->execute([$password_hash]);
    
    // Admin DAEM (Rol 3)
    $pdo->prepare("INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Email, Estado) VALUES (3, '22.222.222-2', 'Jefe', 'DAEM', ?, 'daem@nutri.cl', 1)")->execute([$password_hash]);
    
    // 48 Profesores (Rol 2)
    $ids_profesores = [];
    $stmt_user = $pdo->prepare("INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Email, Estado) VALUES (2, ?, ?, ?, ?, ?, 1)");
    
    for ($i=1; $i <= 48; $i++) {
        $rut = generarRut($i);
        $nom = $nombres[array_rand($nombres)];
        $ape = $apellidos[array_rand($apellidos)];
        $email = "profe.$i@escuela.cl";
        $stmt_user->execute([$rut, $nom, $ape, $password_hash, $email]);
        $ids_profesores[] = $pdo->lastInsertId();
    }

    // --- C. ESTABLECIMIENTOS (30) ---
    echo "Creando 30 Colegios...\n";
    $ids_colegios = [];
    $stmt_dir = $pdo->prepare("INSERT INTO Direccion (Id_Comuna, Direccion) VALUES (?, ?)");
    $stmt_est = $pdo->prepare("INSERT INTO Establecimiento (Id_Direccion, Nombre, Estado) VALUES (?, ?, 1)");

    for ($i=1; $i <= 30; $i++) {
        $id_comuna = $ids_comunas[array_rand($ids_comunas)];
        $stmt_dir->execute([$id_comuna, "Calle Falsa $i"]);
        $id_dir = $pdo->lastInsertId();
        
        $nombre_col = "Colegio N°$i de " . $comunas_biobio[$id_comuna % count($comunas_biobio)];
        $stmt_est->execute([$id_dir, $nombre_col]);
        $ids_colegios[] = $pdo->lastInsertId();
    }

    // --- D. CURSOS Y ALUMNOS ---
    echo "Generando Cursos y Estudiantes (Paciencia)...\n";
    
    $stmt_curso = $pdo->prepare("INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor, Estado) VALUES (?, ?, ?, 1)");
    $stmt_est = $pdo->prepare("INSERT INTO Estudiante (Id_Curso, Rut, Nombres, ApellidoPaterno, ApellidoMaterno, Sexo, FechaNacimiento, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt_med = $pdo->prepare("INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, IMC, Diagnostico, FechaMedicion) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_alert = $pdo->prepare("INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES (?, 'Riesgo Nutricional', ?, 1)");

    $total_estudiantes = 0;
    $rut_seq = 30000;

    foreach ($ids_colegios as $id_col) {
        // 12 Cursos por colegio
        foreach ($niveles as $nivel) {
            $profe_id = $ids_profesores[array_rand($ids_profesores)];
            $curso_nom = $nivel . " " . $letras[array_rand($letras)];
            
            $stmt_curso->execute([$id_col, $curso_nom, $profe_id]);
            $id_curso = $pdo->lastInsertId();

            // 25 Alumnos por curso
            for ($k=0; $k < 25; $k++) {
                $rut_seq++;
                $rut = generarRut($rut_seq);
                $nom = $nombres[array_rand($nombres)];
                $ape1 = $apellidos[array_rand($apellidos)];
                $ape2 = $apellidos[array_rand($apellidos)];
                $sexo = (rand(0,1) ? 'M' : 'F');
                $nac = (2025 - (6 + array_search($nivel, $niveles))) . "-" . rand(1,12) . "-" . rand(1,28);

                $stmt_est->execute([$id_curso, $rut, $nom, $ape1, $ape2, $sexo, $nac]);
                $id_alumno = $pdo->lastInsertId();
                $total_estudiantes++;

                // Medición
                $altura = rand(110, 180) / 100;
                $imc = rand(130, 350) / 10; // IMC 13.0 a 35.0
                $peso = round($imc * ($altura * $altura), 2);
                $diag = calcularDiagnostico($imc);

                $stmt_med->execute([$profe_id, $id_alumno, $altura, $peso, $imc, $diag, date('Y-m-d')]);
                
                // Alerta si es crítico
                if (strpos($diag, 'Obesidad') !== false || $diag == 'Bajo Peso') {
                    $id_reg = $pdo->lastInsertId();
                    $stmt_alert->execute([$id_reg, "Estudiante con $diag (IMC: $imc)"]);
                }
            }
        }
    }

    $pdo->commit();
    echo "¡TODO CORRECTO! Se insertaron los roles antes de los usuarios.\n";
    echo "Estudiantes creados: $total_estudiantes\n";
    echo "<a href='login.php'>Ir al Login</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage();
}
echo "</pre>";
?>