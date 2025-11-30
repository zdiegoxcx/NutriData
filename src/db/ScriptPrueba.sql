USE nutridata;

-- ===================================================
-- 1. LIMPIEZA DE DATOS (ESTRATEGIA SEGURA)
-- ===================================================
SET FOREIGN_KEY_CHECKS = 0; -- Desactivar seguridad temporalmente

-- Usamos DELETE en lugar de TRUNCATE para evitar el error #1701
DELETE FROM Alerta;
ALTER TABLE Alerta AUTO_INCREMENT = 1;

DELETE FROM RegistroNutricional;
ALTER TABLE RegistroNutricional AUTO_INCREMENT = 1;

DELETE FROM Estudiante;
ALTER TABLE Estudiante AUTO_INCREMENT = 1;

DELETE FROM Curso;
ALTER TABLE Curso AUTO_INCREMENT = 1;

DELETE FROM Establecimiento;
ALTER TABLE Establecimiento AUTO_INCREMENT = 1;

DELETE FROM Direccion;
ALTER TABLE Direccion AUTO_INCREMENT = 1;

DELETE FROM Reporte;
ALTER TABLE Reporte AUTO_INCREMENT = 1;

DELETE FROM Usuario;
ALTER TABLE Usuario AUTO_INCREMENT = 1;

DELETE FROM Comuna;
ALTER TABLE Comuna AUTO_INCREMENT = 1;

DELETE FROM Rol;
ALTER TABLE Rol AUTO_INCREMENT = 1;

DELETE FROM Region;
ALTER TABLE Region AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1; -- Reactivar seguridad
-- ===================================================


-- ===================================================
-- 2. POBLADO DE DATOS (INSERTS)
-- ===================================================

-- A. INSERTAR REGIONES Y COMUNAS
INSERT INTO Region (Region) VALUES 
('Metropolitana'), 
('Valparaíso'), 
('Biobío');

INSERT INTO Comuna (Comuna, Id_Region) VALUES 
('Santiago', 1),
('Providencia', 1),
('Puente Alto', 1),
('Valparaíso', 2),
('Viña del Mar', 2),
('Concepción', 3);

-- B. INSERTAR ROLES (CRÍTICO PARA LOGIN)
INSERT INTO Rol (Nombre) VALUES 
('administradorBD'), 
('profesor'), 
('administradorDAEM');

-- C. INSERTAR USUARIOS (Clave para todos: 12345)
-- Admin BD (ID 1)
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(1, '11111111-1', 'Diego', 'Admin', '12345', '+56911111111', 'adminbd@nutridata.cl', 1);

-- Profesor 1 (ID 2)
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(2, '22222222-2', 'Juan', 'Pérez', '12345', '+56922222222', 'juan.perez@escuela.cl', 1);

-- Profesor 2 (ID 3)
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(2, '33333333-3', 'Maria', 'González', '12345', '+56933333333', 'maria.gonzalez@escuela.cl', 1);

-- Admin DAEM (ID 4)
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(3, '44444444-4', 'Carlos', 'Daem', '12345', '+56944444444', 'director@daem.cl', 1);

-- D. INSERTAR DIRECCIONES Y ESTABLECIMIENTOS
-- Liceo Santiago
INSERT INTO Direccion (Id_Comuna, Direccion) VALUES (1, 'Av. Libertador Bernardo O Higgins 123');
INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES (1, 'Liceo Bicentenario Santiago');

-- Colegio Providencia
INSERT INTO Direccion (Id_Comuna, Direccion) VALUES (2, 'Av. Pedro de Valdivia 500');
INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES (2, 'Colegio Providencia');

-- E. INSERTAR CURSOS
-- Cursos Liceo Santiago (Profesor Juan)
INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES (1, '1° Básico A', 2);
INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES (1, '2° Básico A', 2);

-- Curso Colegio Providencia (Profesora Maria)
INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES (2, '4° Medio B', 3);

-- F. INSERTAR ESTUDIANTES
-- Estudiantes 1° Básico (IDs 1, 2, 3)
INSERT INTO Estudiante (Id_Curso, Rut, Nombre, Apellido, FechaNacimiento, Estado) VALUES 
(1, '25000001-1', 'Lucas', 'Ramírez', '2016-03-15', 1), 
(1, '25000002-2', 'Sofía', 'López', '2016-05-20', 1),   
(1, '25000003-3', 'Mateo', 'Díaz', '2016-07-10', 1);    

-- Estudiantes 4° Medio (IDs 4, 5)
INSERT INTO Estudiante (Id_Curso, Rut, Nombre, Apellido, FechaNacimiento, Estado) VALUES 
(3, '22000001-1', 'Valentina', 'Rojas', '2006-02-14', 1), 
(3, '22000002-2', 'Benjamín', 'Soto', '2006-08-30', 1);   

-- G. INSERTAR REGISTROS NUTRICIONALES
-- 1. Normal
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, FechaMedicion) 
VALUES (2, 1, 1.20, 25.00, NULL, 0, 'Estado saludable', 17.36, CURDATE());

-- 2. Obesidad (Generará alerta)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, FechaMedicion) 
VALUES (2, 2, 1.15, 35.00, 'Ropa invierno', 1.0, 'Descuento aplicado', 26.46, CURDATE());

-- 3. Bajo Peso (Generará alerta)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, FechaMedicion) 
VALUES (3, 4, 1.65, 45.00, NULL, 0, 'Observación de decaimiento', 16.53, CURDATE());

-- H. INSERTAR ALERTAS (Sincronizadas con los registros de riesgo)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) 
VALUES (2, 'Riesgo de Malnutrición', 'Estudiante con Exceso de Peso (IMC: 26.46).', 1);

INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) 
VALUES (3, 'Riesgo de Malnutrición', 'Estudiante con Bajo Peso (IMC: 16.53).', 1);