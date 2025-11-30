<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php");
    exit;
}

// Parámetros GET para navegación
$vista = $_GET['vista'] ?? 'cursos';
$id_curso = $_GET['id_curso'] ?? null;
$id_estudiante = $_GET['id_estudiante'] ?? null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Profesor - NutriData</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>NutriData</h2>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-category">Profesor</div>

            <a href="dashboard_profesor.php?vista=cursos"
               class="nav-item <?= ($vista == 'cursos') ? 'active' : '' ?>">
               <i class="fa-solid fa-chalkboard-user"></i> Mis Cursos
            </a>
        </nav>
    </aside>


    <!-- CONTENIDO -->
    <main class="main-content">

        <!-- HEADER -->
        <header class="header">
            <div class="header-user">
                <?= htmlspecialchars($_SESSION['user_nombre']); ?>
            </div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </header>


        <section class="content-body">
            <div class="content-container">

            <?php
            // MENSAJES
            if (isset($_SESSION['error'])) {
                echo '<div class="mensaje error">'.$_SESSION['error'].'</div>';
                unset($_SESSION['error']);
            }
            ?>


            <?php
            // ===========================================================
            //                     VISTA: MIS CURSOS
            // ===========================================================
            if ($vista === 'cursos') {

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-chalkboard'></i> Mis Cursos</h1>";
                echo '</div>';

                $stmt = $pdo->prepare("
                    SELECT c.Id, c.Nombre, e.Nombre AS Establecimiento
                    FROM Curso c
                    JOIN Establecimiento e ON c.Id_Establecimiento = e.Id
                    WHERE c.Id_Profesor = ?
                    ORDER BY c.Nombre
                ");
                $stmt->execute([$_SESSION['user_id']]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Establecimiento</th>
                            <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                          <td>".htmlspecialchars($row['Nombre'])."</td>
                          <td>".htmlspecialchars($row['Establecimiento'])."</td>
                          <td class='actions'>
                            <a class='btn-action btn-view'
                               href='dashboard_profesor.php?vista=estudiantes&id_curso=".$row['Id']."'>
                               <i class='fa-solid fa-users'></i>
                            </a>
                          </td>
                          </tr>";
                }

                echo "</tbody></table></div>";
            }


            // ===========================================================
            //            VISTA: ESTUDIANTES DEL CURSO
            // ===========================================================
            elseif ($vista === 'estudiantes' && $id_curso) {

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-children'></i> Estudiantes</h1>";
                echo "</div>";

                $stmt = $pdo->prepare("
                    SELECT Id, Rut, Nombre, Apellido
                    FROM Estudiante
                    WHERE Id_Curso = ?
                    ORDER BY Apellido, Nombre
                ");
                $stmt->execute([$id_curso]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>RUT</th>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                          <td>".htmlspecialchars($row['Rut'])."</td>
                          <td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td>
                          <td class='actions'>
                            <a class='btn-action btn-view'
                               href='dashboard_profesor.php?vista=mediciones&id_estudiante=".$row['Id']."'>
                               <i class='fa-solid fa-notes-medical'></i>
                            </a>
                          </td>
                          </tr>";
                }

                echo "</tbody></table></div>";
            }


            // ===========================================================
            //           VISTA: MEDICIONES DEL ESTUDIANTE
            // ===========================================================
            elseif ($vista === 'mediciones' && $id_estudiante) {

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-notes-medical'></i> Mediciones</h1>";
                echo "<a href='registrar_medicion.php?id_estudiante=$id_estudiante' class='btn-create'>
                        <i class='fa-solid fa-plus'></i> Nueva Medición
                      </a>";
                echo '</div>';

                $stmt = $pdo->prepare("
                    SELECT FechaMedicion as Fecha, Peso, Altura, IMC, Observaciones
                    FROM RegistroNutricional
                    WHERE Id_Estudiante = ?
                    ORDER BY FechaMedicion DESC
                ");
                $stmt->execute([$id_estudiante]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Peso (KG)</th>
                            <th>Altura (M)</th>
                            <th>IMC</th>
                            <th>Observaciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    echo "<tr>
                            <td>" . htmlspecialchars($row['Fecha']) . "</td>
                            <td>" . htmlspecialchars($row['Peso']) . "</td>
                            <td>" . htmlspecialchars($row['Altura']) . "</td>
                            <td>" . htmlspecialchars($row['IMC']) . "</td>
                            <td>" . htmlspecialchars($row['Observaciones']) . "</td>
                          </tr>";
                }

                echo "</tbody></table></div>";
            }

            ?>

            </div> <!-- content-container -->
        </section>

    </main>
</div>

</body>
</html>
