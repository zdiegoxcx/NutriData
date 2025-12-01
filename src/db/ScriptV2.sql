-- ======================================================================================
-- SCRIPT UNIFICADO NUTRIDATA (Estructura + Datos + Correcciones)
-- ======================================================================================

DROP DATABASE IF EXISTS nutridata;
CREATE DATABASE nutridata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nutridata;

SET FOREIGN_KEY_CHECKS = 0;

-- ======================================================================================
-- 1. CREACIÓN DE TABLAS (Estructura consolidada)
-- ======================================================================================

CREATE TABLE Region (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Region VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE Rol (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE Comuna (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Comuna VARCHAR(255),
    Id_Region INT,
    FOREIGN KEY (Id_Region) REFERENCES Region(Id)
) ENGINE=InnoDB;

CREATE TABLE Usuario (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Rol INT,
    Rut VARCHAR(20), -- Aumentado para soportar puntos y guion
    Nombre VARCHAR(255),
    Apellido VARCHAR(255),
    Contraseña VARCHAR(255),
    Telefono VARCHAR(255),
    Email VARCHAR(255),
    Estado TINYINT(1) DEFAULT 1,
    FechaEliminacion DATE NULL,
    MotivoEliminacion TEXT NULL,
    FOREIGN KEY (Id_Rol) REFERENCES Rol(Id)
) ENGINE=InnoDB;

CREATE TABLE Direccion (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Comuna INT,
    Direccion VARCHAR(255),
    FOREIGN KEY (Id_Comuna) REFERENCES Comuna(Id)
) ENGINE=InnoDB;

CREATE TABLE Establecimiento (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Direccion INT,
    Nombre VARCHAR(255),
    Estado TINYINT(1) DEFAULT 1,
    FechaEliminacion DATETIME NULL,
    FOREIGN KEY (Id_Direccion) REFERENCES Direccion(Id)
) ENGINE=InnoDB;

CREATE TABLE Reporte (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Usuario INT,
    Descripcion TEXT,
    Fecha DATE,
    FOREIGN KEY (Id_Usuario) REFERENCES Usuario(Id)
) ENGINE=InnoDB;

CREATE TABLE Curso (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Establecimiento INT,
    Nombre VARCHAR(255),
    Id_Profesor INT,
    Estado TINYINT(1) DEFAULT 1,
    FechaEliminacion DATETIME NULL,
    FOREIGN KEY (Id_Establecimiento) REFERENCES Establecimiento(Id),
    FOREIGN KEY (Id_Profesor) REFERENCES Usuario(Id)
) ENGINE=InnoDB;

CREATE TABLE Estudiante (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Curso INT,
    Rut VARCHAR(20), -- Aumentado para puntos y guion
    Nombres VARCHAR(255),          -- Actualizado desde 'Nombre'
    ApellidoPaterno VARCHAR(255),  -- Actualizado desde 'Apellido'
    ApellidoMaterno VARCHAR(255),  -- Nueva columna agregada
    Sexo CHAR(1),                  -- Nueva columna agregada (M/F)
    FechaNacimiento DATE,
    Estado TINYINT(1) DEFAULT 1,
    FechaEliminacion DATE NULL,
    MotivoEliminacion TEXT NULL,
    FOREIGN KEY (Id_Curso) REFERENCES Curso(Id)
) ENGINE=InnoDB;

CREATE TABLE RegistroNutricional (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Profesor INT,
    Id_Estudiante INT,
    Altura DECIMAL(5, 2),
    Peso DECIMAL(5, 2),
    MotivoDescuento VARCHAR(255),
    PesoDescuento DECIMAL(5, 2),
    Observaciones TEXT,
    IMC DECIMAL(5, 2),
    Diagnostico VARCHAR(50), -- Nueva columna agregada
    FechaMedicion DATE,
    FOREIGN KEY (Id_Profesor) REFERENCES Usuario(Id),
    FOREIGN KEY (Id_Estudiante) REFERENCES Estudiante(Id)
) ENGINE=InnoDB;

CREATE TABLE Alerta (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_RegistroNutricional INT,
    Nombre VARCHAR(255),
    Descripcion TEXT,
    ObservacionesSeguimiento TEXT, -- Nueva columna agregada
    Estado TINYINT(1) DEFAULT 1,   -- 1: Pendiente, 0: Atendida
    FOREIGN KEY (Id_RegistroNutricional) REFERENCES RegistroNutricional(Id)
) ENGINE=InnoDB;

-- ======================================================================================
-- 2. POBLADO DE DATOS (Con RUTs formateados y estructura nueva)
-- ======================================================================================

-- REGIONES Y COMUNAS (Región del Biobío)
INSERT INTO Region (Region) VALUES ('Biobío'); -- ID 1

INSERT INTO Comuna (Comuna, Id_Region) VALUES 
('Concepción', 1), ('Coronel', 1), ('Chiguayante', 1), ('Florida', 1), ('Hualpén', 1), ('Hualqui', 1), 
('Lota', 1), ('Penco', 1), ('San Pedro de la Paz', 1), ('Santa Juana', 1), ('Talcahuano', 1), ('Tomé', 1),
('Arauco', 1), ('Cañete', 1), ('Contulmo', 1), ('Curanilahue', 1), ('Lebu', 1), ('Los Álamos', 1), ('Tirúa', 1),
('Alto Biobío', 1), ('Antuco', 1), ('Cabrero', 1), ('Laja', 1), ('Los Ángeles', 1), ('Mulchén', 1), 
('Nacimiento', 1), ('Negrete', 1), ('Quilaco', 1), ('Quilleco', 1), ('San Rosendo', 1), ('Santa Bárbara', 1), 
('Tucapel', 1), ('Yumbel', 1);

-- ROLES
INSERT INTO Rol (Nombre) VALUES ('administradorBD'), ('profesor'), ('administradorDAEM');

-- USUARIOS
-- Nota: Contraseña '12345' hasheada. RUTs con formato XX.XXX.XXX-X
INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(1, '21.910.343-3', 'Diego', 'AdminBD', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56900000001', 'admin@nutridata.cl', 1),
(3, '99.999.999-9', 'Carlos', 'DirectorDAEM', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56900000002', 'nutridata.daem@gmail.com', 1),
-- Profesores
(2, '12.345.678-1', 'Juan', 'Pérez', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56911111111', 'juan.perez@escuela.cl', 1),
(2, '12.345.678-2', 'Maria', 'González', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56922222222', 'maria.gonz@escuela.cl', 1),
(2, '12.345.678-3', 'Pedro', 'Tapia', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56933333333', 'pedro.tapia@escuela.cl', 1),
(2, '12.345.678-4', 'Ana', 'Muñoz', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56944444444', 'ana.munoz@escuela.cl', 1),
(2, '12.345.678-5', 'Laura', 'Silva', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56955555555', 'laura.silva@escuela.cl', 1),
(2, '12.345.678-6', 'Roberto', 'Lagos', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56966666666', 'roberto.lagos@escuela.cl', 1),
(2, '12.345.678-7', 'Patricia', 'Castro', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56977777777', 'paty.castro@escuela.cl', 1),
(2, '12.345.678-8', 'Felipe', 'Vidal', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56988888888', 'felipe.vidal@escuela.cl', 1),
(2, '12.345.678-9', 'Camila', 'Rojas', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56999999999', 'camila.rojas@escuela.cl', 1),
(2, '10.000.000-0', 'Sergio', 'Nuñez', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '+56900001111', 'sergio.nunez@escuela.cl', 1);

-- ESTABLECIMIENTOS Y DIRECCIONES
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

-- CURSOS
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

-- ESTUDIANTES
-- Se agrega ApellidoMaterno (generado para completitud) y Sexo. RUT con formato.
INSERT INTO Estudiante (Id_Curso, Rut, Nombres, ApellidoPaterno, ApellidoMaterno, Sexo, FechaNacimiento, Estado) VALUES 
-- Curso 1 (1° Básico A - Concepción)
(1, '26.000.001-1', 'Lucas', 'Ramírez', 'Soto', 'M', '2017-03-10', 1),
(1, '26.000.002-2', 'Sofía', 'López', 'Gómez', 'F', '2017-05-15', 1),
(1, '26.000.003-3', 'Mateo', 'Díaz', 'Vidal', 'M', '2017-08-20', 1),
(1, '26.000.004-4', 'Isabella', 'Torres', 'Muñoz', 'F', '2017-01-05', 1),
(1, '26.000.005-5', 'Benjamín', 'Vargas', 'Rojas', 'M', '2017-11-30', 1),
(1, '26.000.006-6', 'Emma', 'Castillo', 'Pérez', 'F', '2017-04-12', 1),

-- Curso 3 (4° Medio A - Concepción)
(3, '21.000.001-1', 'Martín', 'Castro', 'Silva', 'M', '2006-02-14', 1),
(3, '21.000.002-2', 'Emilia', 'Ruiz', 'Nuñez', 'F', '2006-06-22', 1),
(3, '21.000.003-3', 'Joaquín', 'Herrera', 'Lagos', 'M', '2005-12-01', 1),
(3, '21.000.004-4', 'Agustina', 'Morales', 'Tapia', 'F', '2006-04-10', 1),
(3, '21.000.005-5', 'Tomás', 'Reyes', 'Carrasco', 'M', '2006-09-18', 1),
(3, '21.000.006-6', 'Nicolas', 'Parra', 'Jara', 'M', '2006-01-20', 1),

-- Curso 4 (2° Básico A - Talcahuano)
(4, '25.000.001-1', 'Florencia', 'Mendoza', 'Soto', 'F', '2016-03-03', 1),
(4, '25.000.002-2', 'Vicente', 'Guzmán', 'Rivas', 'M', '2016-07-07', 1),
(4, '25.000.003-3', 'Maite', 'Salazar', 'Gallardo', 'F', '2016-10-12', 1),
(4, '25.000.004-4', 'Gaspar', 'Ortega', 'Vergara', 'M', '2016-01-25', 1),
(4, '25.000.005-5', 'Josefa', 'Navarro', 'Cortes', 'F', '2016-05-30', 1),

-- Curso 7 (1° Básico C - Los Ángeles)
(7, '26.500.001-1', 'Julieta', 'Vergara', 'Araya', 'F', '2017-02-15', 1),
(7, '26.500.002-2', 'Simón', 'Cortes', 'Molina', 'M', '2017-06-20', 1),
(7, '26.500.003-3', 'Catalina', 'Araya', 'Sepúlveda', 'F', '2017-09-10', 1),
(7, '26.500.004-4', 'Cristóbal', 'Molina', 'Paredes', 'M', '2017-12-05', 1),
(7, '26.500.005-5', 'Amanda', 'Sepúlveda', 'Espinoza', 'F', '2017-03-30', 1),

-- Curso 10 (Kinder A - San Pedro)
(10, '27.000.001-1', 'Agustín', 'Paredes', 'Fuentes', 'M', '2018-05-05', 1),
(10, '27.000.002-2', 'Trinidad', 'Espinoza', 'Lagos', 'F', '2018-08-08', 1),
(10, '27.000.003-3', 'Alonso', 'Fuentes', 'Vargas', 'M', '2018-02-02', 1),
(10, '27.000.004-4', 'Isidora', 'Lagos', 'Torres', 'F', '2018-11-11', 1),

-- Curso 13 (1° Medio TP - Coronel)
(13, '23.000.001-1', 'Felipe', 'Carrasco', 'Díaz', 'M', '2008-03-15', 1),
(13, '23.000.002-2', 'Valentina', 'Jara', 'Ramírez', 'F', '2008-07-20', 1),
(13, '23.000.003-3', 'Bastián', 'Rivas', 'Castillo', 'M', '2008-09-10', 1),
(13, '23.000.004-4', 'Antonia', 'Soto', 'Ruiz', 'F', '2008-01-25', 1),
(13, '23.000.005-5', 'Matías', 'Gallardo', 'Herrera', 'M', '2008-12-30', 1);

-- REGISTROS NUTRICIONALES Y DIAGNÓSTICOS
-- CASO 1: Lucas (Normal)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(3, 1, '2024-03-15', 1.18, 22.00, 15.80, 'Normal', 'Inicio año normal'),
(3, 1, CURDATE(), 1.21, 24.00, 16.39, 'Normal', 'Cierre año exitoso');

-- CASO 2: Sofía (Obesidad)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(3, 2, '2024-03-15', 1.15, 28.00, 21.17, 'Sobrepeso', 'Sobrepeso leve'),
(3, 2, CURDATE(), 1.16, 36.00, 26.75, 'Obesidad', 'Obesidad detectada. Se cita apoderado.');

-- CASO 3: Martín (Bajo Peso)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(6, 7, '2024-03-10', 1.70, 60.00, 20.76, 'Normal', 'Normal'),
(6, 7, CURDATE(), 1.75, 50.00, 16.33, 'Bajo Peso', 'Desnutrición visible. Alerta activada.');

-- CASO 4: Gaspar (Obesidad Severa)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(4, 14, '2024-03-20', 1.28, 42.00, 25.63, 'Obesidad', 'Obesidad'),
(4, 14, CURDATE(), 1.30, 46.00, 27.22, 'Obesidad Severa', 'Obesidad severa mantenida.');

-- CASO 5: Agustín (Normal)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(11, 23, CURDATE(), 1.08, 19.50, 16.72, 'Normal', 'Crecimiento ok');

-- CASO 6: Felipe (Bajo Peso)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(12, 27, CURDATE(), 1.66, 46.00, 16.69, 'Bajo Peso', 'Bajo peso preocupante');

-- Relleno otros estudiantes (Datos al día de hoy)
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, FechaMedicion, Altura, Peso, IMC, Diagnostico, Observaciones) VALUES 
(3, 3, CURDATE(), 1.20, 23.00, 15.97, 'Normal', 'Ok'), 
(3, 4, CURDATE(), 1.19, 24.00, 16.95, 'Normal', 'Ok'),
(6, 8, CURDATE(), 1.65, 55.00, 20.20, 'Normal', 'Ok'),
(12, 31, CURDATE(), 1.65, 80.00, 29.38, 'Obesidad', 'Obesidad moderada');

-- ALERTAS (Vinculadas a los casos de riesgo recientes)
-- Alerta Sofía
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 2 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad detectada (IMC: 26.75).', 1);

-- Alerta Martín
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 7 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Bajo Peso severo (IMC: 16.33).', 1);

-- Alerta Gaspar
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 14 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad Severa (IMC: 27.22).', 1);

-- Alerta Felipe
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 27 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Bajo Peso persistente (IMC: 16.69).', 1);

-- Alerta Matías (Obesidad)
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
((SELECT Id FROM RegistroNutricional WHERE Id_Estudiante = 31 ORDER BY FechaMedicion DESC LIMIT 1), 
'Riesgo de Malnutrición', 'Obesidad detectada (IMC: 29.38).', 1);

SET FOREIGN_KEY_CHECKS = 1;