USE nutridata;

-- ======================================================================================
-- 1. LIMPIEZA TOTAL Y SEGURA (RESET DE FÁBRICA)
-- ======================================================================================
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM Alerta;                  ALTER TABLE Alerta AUTO_INCREMENT = 1;
DELETE FROM RegistroNutricional;     ALTER TABLE RegistroNutricional AUTO_INCREMENT = 1;
DELETE FROM Estudiante;              ALTER TABLE Estudiante AUTO_INCREMENT = 1;
DELETE FROM Curso;                   ALTER TABLE Curso AUTO_INCREMENT = 1;
DELETE FROM Establecimiento;         ALTER TABLE Establecimiento AUTO_INCREMENT = 1;
DELETE FROM Direccion;               ALTER TABLE Direccion AUTO_INCREMENT = 1;
DELETE FROM Reporte;                 ALTER TABLE Reporte AUTO_INCREMENT = 1;
DELETE FROM Usuario;                 ALTER TABLE Usuario AUTO_INCREMENT = 1;
DELETE FROM Comuna;                  ALTER TABLE Comuna AUTO_INCREMENT = 1;
DELETE FROM Rol;                     ALTER TABLE Rol AUTO_INCREMENT = 1;
DELETE FROM Region;                  ALTER TABLE Region AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ======================================================================================
-- 2. INFRAESTRUCTURA GEOGRÁFICA (REGIÓN DEL BIOBÍO - 33 COMUNAS)
-- ======================================================================================
INSERT INTO Region (Region) VALUES ('Biobío'); -- ID 1

INSERT INTO Comuna (Comuna, Id_Region) VALUES 
('Concepción', 1), ('Coronel', 1), ('Chiguayante', 1), ('Florida', 1), ('Hualpén', 1), ('Hualqui', 1), 
('Lota', 1), ('Penco', 1), ('San Pedro de la Paz', 1), ('Santa Juana', 1), ('Talcahuano', 1), ('Tomé', 1),
('Arauco', 1), ('Cañete', 1), ('Contulmo', 1), ('Curanilahue', 1), ('Lebu', 1), ('Los Álamos', 1), ('Tirúa', 1),
('Alto Biobío', 1), ('Antuco', 1), ('Cabrero', 1), ('Laja', 1), ('Los Ángeles', 1), ('Mulchén', 1), 
('Nacimiento', 1), ('Negrete', 1), ('Quilaco', 1), ('Quilleco', 1), ('San Rosendo', 1), ('Santa Bárbara', 1), 
('Tucapel', 1), ('Yumbel', 1);

INSERT INTO Rol (Nombre) VALUES ('administradorBD'), ('profesor'), ('administradorDAEM');

-- ======================================================================================
-- 3. EQUIPO HUMANO (USUARIOS)
-- ======================================================================================
-- Admins (Clave: 12345)
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(1, '11111111-1', 'Diego', 'AdminBD', '12345', '+56900000001', 'admin@nutridata.cl', 1),
(3, '99999999-9', 'Carlos', 'DirectorDAEM', '12345', '+56900000002', 'director@daem.cl', 1);

-- 10 Profesores para cubrir la región
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(2, '12345678-1', 'Juan', 'Pérez', '12345', '+56911111111', 'juan.perez@escuela.cl', 1),    -- ID 3
(2, '12345678-2', 'Maria', 'González', '12345', '+56922222222', 'maria.gonz@escuela.cl', 1),   -- ID 4
(2, '12345678-3', 'Pedro', 'Tapia', '12345', '+56933333333', 'pedro.tapia@escuela.cl', 1),   -- ID 5
(2, '12345678-4', 'Ana', 'Muñoz', '12345', '+56944444444', 'ana.munoz@escuela.cl', 1),       -- ID 6
(2, '12345678-5', 'Laura', 'Silva', '12345', '+56955555555', 'laura.silva@escuela.cl', 1),   -- ID 7
(2, '12345678-6', 'Roberto', 'Lagos', '12345', '+56966666666', 'roberto.lagos@escuela.cl', 1), -- ID 8
(2, '12345678-7', 'Patricia', 'Castro', '12345', '+56977777777', 'paty.castro@escuela.cl', 1), -- ID 9
(2, '12345678-8', 'Felipe', 'Vidal', '12345', '+56988888888', 'felipe.vidal@escuela.cl', 1),   -- ID 10
(2, '12345678-9', 'Camila', 'Rojas', '12345', '+56999999999', 'camila.rojas@escuela.cl', 1),   -- ID 11
(2, '10000000-0', 'Sergio', 'Nuñez', '12345', '+56900001111', 'sergio.nunez@escuela.cl', 1);   -- ID 12

-- ======================================================================================
-- 4. RED DE ESTABLECIMIENTOS (5 Colegios en la Región)
-- ======================================================================================
INSERT INTO Direccion (Id_Comuna, Direccion) VALUES 
(1, 'Av. Paicaví 1234'),      -- Concepción
(11, 'Calle Valdivia 555'),   -- Talcahuano
(24, 'Av. Alemania 890'),     -- Los Ángeles
(9, 'Los Canelos 400'),       -- San Pedro de la Paz
(2, 'Manuel Montt 100');      -- Coronel

INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES 
(1, 'Liceo Bicentenario Concepción'),   -- ID 1
(2, 'Colegio Puerto Talcahuano'),       -- ID 2
(3, 'Escuela Básica Los Ángeles'),      -- ID 3
(4, 'Instituto San Pedro'),             -- ID 4
(5, 'Colegio Industrial Coronel');      -- ID 5

-- ======================================================================================
-- 5. OFERTA ACADÉMICA (15 Cursos)
-- ======================================================================================
INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES 
-- Liceo Concepción
(1, '1° Básico A', 3), (1, '1° Básico B', 8), (1, '4° Medio A', 6),
-- Colegio Talcahuano
(2, '2° Básico A', 4), (2, '3° Medio B', 7), (2, '4° Medio C', 9),
-- Escuela Los Ángeles
(3, '1° Básico C', 5), (3, '5° Básico A', 5), (3, '8° Básico B', 10),
-- Instituto San Pedro
(4, 'Kinder A', 11), (4, '1° Básico A', 11), (4, '2° Básico A', 12),
-- Colegio Coronel
(5, '1° Medio TP', 12), (5, '2° Medio TP', 10), (5, '3° Medio TP', 8);

-- ======================================================================================
-- 6. MATRÍCULA DE ESTUDIANTES (~70 Alumnos variados)
-- ======================================================================================
INSERT INTO Estudiante (Id_Curso, Rut, Nombre, Apellido, FechaNacimiento, Estado) VALUES 
-- Curso 1 (1° Básico A - Concepción)
(1, '26000001-1', 'Lucas', 'Ramírez', '2017-03-10', 1), (1, '26000002-2', 'Sofía', 'López', '2017-05-15', 1),
(1, '26000003-3', 'Mateo', 'Díaz', '2017-08-20', 1), (1, '26000004-4', 'Isabella', 'Torres', '2017-01-05', 1),
(1, '26000005-5', 'Benjamín', 'Vargas', '2017-11-30', 1), (1, '26000006-6', 'Emma', 'Castillo', '2017-04-12', 1),

-- Curso 3 (4° Medio A - Concepción)
(3, '21000001-1', 'Martín', 'Castro', '2006-02-14', 1), (3, '21000002-2', 'Emilia', 'Ruiz', '2006-06-22', 1),
(3, '21000003-3', 'Joaquín', 'Herrera', '2005-12-01', 1), (3, '21000004-4', 'Agustina', 'Morales', '2006-04-10', 1),
(3, '21000005-5', 'Tomás', 'Reyes', '2006-09-18', 1), (3, '21000006-6', 'Nicolas', 'Parra', '2006-01-20', 1),

-- Curso 4 (2° Básico A - Talcahuano)
(4, '25000001-1', 'Florencia', 'Mendoza', '2016-03-03', 1), (4, '25000002-2', 'Vicente', 'Guzmán', '2016-07-07', 1),
(4, '25000003-3', 'Maite', 'Salazar', '2016-10-12', 1), (4, '25000004-4', 'Gaspar', 'Ortega', '2016-01-25', 1),
(4, '25000005-5', 'Josefa', 'Navarro', '2016-05-30', 1),

-- Curso 7 (1° Básico C - Los Ángeles)
(7, '26500001-1', 'Julieta', 'Vergara', '2017-02-15', 1), (7, '26500002-2', 'Simón', 'Cortes', '2017-06-20', 1),
(7, '26500003-3', 'Catalina', 'Araya', '2017-09-10', 1), (7, '26500004-4', 'Cristóbal', 'Molina', '2017-12-05', 1),
(7, '26500005-5', 'Amanda', 'Sepúlveda', '2017-03-30', 1),

-- Curso 10 (Kinder A - San Pedro)
(10, '27000001-1', 'Agustín', 'Paredes', '2018-05-05', 1), (10, '27000002-2', 'Trinidad', 'Espinoza', '2018-08-08', 1),
(10, '27000003-3', 'Alonso', 'Fuentes', '2018-02-02', 1), (10, '27000004-4', 'Isidora', 'Lagos', '2018-11-11', 1),

-- Curso 13 (1° Medio TP - Coronel) - Riesgo Social
(13, '23000001-1', 'Felipe', 'Carrasco', '2008-03-15', 1), (13, '23000002-2', 'Valentina', 'Jara', '2008-07-20', 1),
(13, '23000003-3', 'Bastián', 'Rivas', '2008-09-10', 1), (13, '23000004-4', 'Antonia', 'Soto', '2008-01-25', 1),
(13, '23000005-5', 'Matías', 'Gallardo', '2008-12-30', 1);

-- ======================================================================================
-- 7. HISTORIAL MÉDICO (Simulación de mediciones durante el año)
-- Fechas: Marzo (Inicio), Junio (Mitad), Septiembre (Fiestas Patrias), Noviembre (Cierre)
-- ======================================================================================

-- CASO 1: Lucas (Normal -> Normal)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(3, 1, '2024-03-15', 1.18, 22.00, 15.80, 'Inicio año normal'),
(3, 1, '2024-06-15', 1.19, 23.00, 16.24, 'Crecimiento estable'),
(3, 1, '2024-09-10', 1.20, 23.50, 16.32, 'Todo bien'),
(3, 1, CURDATE(), 1.21, 24.00, 16.39, 'Cierre año exitoso');

-- CASO 2: Sofía (Sobrepeso -> Obesidad) ¡ALERTA!
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(3, 2, '2024-03-15', 1.15, 28.00, 21.17, 'Sobrepeso leve'),
(3, 2, '2024-06-15', 1.15, 30.00, 22.68, 'Aumento peso'),
(3, 2, '2024-09-10', 1.16, 33.00, 24.52, 'Riesgo alto'),
(3, 2, CURDATE(), 1.16, 36.00, 26.75, 'Obesidad detectada. Se cita apoderado.');

-- CASO 3: Martín (Normal -> Bajo Peso) ¡ALERTA!
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(6, 7, '2024-03-10', 1.70, 60.00, 20.76, 'Normal'),
(6, 7, '2024-06-20', 1.72, 58.00, 19.60, 'Disminución leve'),
(6, 7, '2024-09-15', 1.74, 54.00, 17.83, 'Bajo peso'),
(6, 7, CURDATE(), 1.75, 50.00, 16.33, 'Desnutrición visible. Alerta activada.');

-- CASO 4: Gaspar (Talcahuano - Obesidad Severa desde inicio)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(4, 14, '2024-03-20', 1.28, 42.00, 25.63, 'Obesidad'),
(4, 14, '2024-08-15', 1.29, 44.00, 26.44, 'Sin cambios'),
(4, 14, CURDATE(), 1.30, 46.00, 27.22, 'Obesidad severa mantenida.');

-- CASO 5: Agustín (Kinder - Normal)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(11, 23, '2024-04-05', 1.05, 18.00, 16.33, 'Normal'),
(11, 23, CURDATE(), 1.08, 19.50, 16.72, 'Crecimiento ok');

-- CASO 6: Felipe (Coronel - Bajo Peso Social)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(12, 27, '2024-05-10', 1.65, 48.00, 17.63, 'Delgadez'),
(12, 27, CURDATE(), 1.66, 46.00, 16.69, 'Bajo peso preocupante');

-- Relleno rápido para el resto de estudiantes (1 medición actual para estadística)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Observaciones) VALUES 
(3, 3, CURDATE(), 1.20, 23.00, 15.97, 'Ok'), (3, 4, CURDATE(), 1.19, 24.00, 16.95, 'Ok'),
(3, 5, CURDATE(), 1.22, 25.00, 16.80, 'Ok'), (3, 6, CURDATE(), 1.18, 21.00, 15.08, 'Ok'),
(6, 8, CURDATE(), 1.65, 55.00, 20.20, 'Ok'), (6, 9, CURDATE(), 1.70, 65.00, 22.49, 'Ok'),
(6, 10, CURDATE(), 1.68, 60.00, 21.26, 'Ok'), (6, 11, CURDATE(), 1.72, 68.00, 22.99, 'Ok'),
(6, 12, CURDATE(), 1.75, 70.00, 22.86, 'Ok'), (4, 13, CURDATE(), 1.25, 26.00, 16.64, 'Ok'),
(4, 15, CURDATE(), 1.24, 25.00, 16.26, 'Ok'), (4, 16, CURDATE(), 1.26, 27.00, 17.01, 'Ok'),
(4, 17, CURDATE(), 1.22, 35.00, 23.51, 'Riesgo sobrepeso'),
(5, 18, CURDATE(), 1.20, 22.00, 15.28, 'Ok'), (5, 19, CURDATE(), 1.19, 21.00, 14.83, 'Bajo pero sano'),
(5, 20, CURDATE(), 1.21, 24.00, 16.39, 'Ok'), (5, 21, CURDATE(), 1.23, 38.00, 25.12, 'Obesidad'),
(5, 22, CURDATE(), 1.20, 23.00, 15.97, 'Ok'), (11, 24, CURDATE(), 1.05, 17.00, 15.42, 'Ok'),
(11, 25, CURDATE(), 1.06, 22.00, 19.58, 'Rellenito'), (11, 26, CURDATE(), 1.04, 16.00, 14.79, 'Ok'),
(12, 28, CURDATE(), 1.60, 52.00, 20.31, 'Ok'), (12, 29, CURDATE(), 1.62, 55.00, 20.96, 'Ok'),
(12, 30, CURDATE(), 1.58, 45.00, 18.03, 'Bajo peso'), (12, 31, CURDATE(), 1.65, 80.00, 29.38, 'Obesidad');

-- ======================================================================================
-- 8. GENERACIÓN DE ALERTAS (Vinculadas a los casos de riesgo anteriores)
-- ======================================================================================

-- Alerta Sofía (Concepción)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 2 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad progresiva detectada (IMC: 26.75).', 1);

-- Alerta Martín (Concepción)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 7 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Bajo Peso severo en adolescente (IMC: 16.33).', 1);

-- Alerta Gaspar (Talcahuano)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 14 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad severa infantil (IMC: 27.22).', 1);

-- Alerta Felipe (Coronel)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 27 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Bajo Peso persistente (IMC: 16.69).', 1);

-- Alerta Matías (Coronel) - Obesidad Adolescente
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 31 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad (IMC: 29.38).', 1);

---------------------------
USE nutridata;

-- Agregar control de borrado a Establecimiento
ALTER TABLE Establecimiento ADD COLUMN Estado TINYINT(1) DEFAULT 1;
ALTER TABLE Establecimiento ADD COLUMN FechaEliminacion DATETIME NULL;

-- Agregar control de borrado a Curso
ALTER TABLE Curso ADD COLUMN Estado TINYINT(1) DEFAULT 1;
ALTER TABLE Curso ADD COLUMN FechaEliminacion DATETIME NULL;

-- (La tabla Estudiante ya tenía estos campos según tus scripts anteriores)