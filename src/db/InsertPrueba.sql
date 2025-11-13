USE nutridata;

-- 1. Tablas independientes (sin claves foráneas)
INSERT INTO Region (Region) VALUES 
('Biobío'),
('Metropolitana'),
('Valparaíso');

INSERT INTO Rol (Nombre) VALUES 
('Administrador DAEM'),
('Profesor Ed. Física'),
('Administrador DB');

INSERT INTO Permiso (Nombre, Descripcion) VALUES 
('Crear Usuario', 'Permite crear nuevos usuarios en el sistema.'),
('Ver Reportes', 'Permite visualizar reportes nutricionales.'),
('Registrar Medicion', 'Permite ingresar un nuevo registro nutricional de un estudiante.');

-- 2. Tablas de primer nivel de dependencia
INSERT INTO Comuna (Comuna, Id_Region) VALUES 
('Concepción', 1),
('Los Ángeles', 1),
('Quilleco', 1),
('Santiago', 2);

INSERT INTO Rol_Permiso (Id_Rol, Id_Permiso) VALUES 
(1, 1), -- Admin DAEM puede Crear Usuario
(1, 2), -- Admin DAEM puede Ver Reportes
(2, 2), -- Profesor puede Ver Reportes
(2, 3), -- Profesor puede Registrar Medicion
(3, 1), -- Admin DB puede Crear Usuario
(3, 2), -- Admin DB puede Ver Reportes
(3, 3); -- Admin DB puede Registrar Medicion

INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES 
(1, '11.111.111-1', 'Admin', 'DAEM', 'hash_pass_123', '912345678', 'admin@daemquilleco.cl', 1),
(2, '22.222.222-2', 'Pedro', 'Parra', 'hash_pass_456', '987654321', 'pedro.parra@profe.cl', 1),
(3, '33.333.333-3', 'Root', 'Admin', 'hash_pass_789', '911223344', 'root@nutridata.cl', 1);

INSERT INTO Direccion (Id_Comuna, Direccion) VALUES 
(3, 'Calle Estadio 123, Quilleco'),
(1, 'O''Higgins 456, Concepción');

-- 3. Tablas de segundo nivel de dependencia
INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES 
(1, 'Liceo Bicentenario Quilleco'),
(2, 'Colegio Experimental');

INSERT INTO Reporte (Id_Usuario, Descripcion, Fecha) VALUES 
(1, 'Reporte general de IMC primer semestre 2025.', '2025-07-01');

-- 4. Tablas de tercer nivel de dependencia
INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES 
(1, '8vo Básico A', 2),
(1, '7mo Básico B', 2);

-- 5. Tablas de cuarto nivel de dependencia
INSERT INTO Estudiante (Id_Curso, Rut, Nombre, Apellido, FechaNacimiento, Estado) VALUES 
(1, '25.111.222-3', 'Ana', 'González', '2012-05-15', 1),
(1, '25.333.444-5', 'Luis', 'Martínez', '2012-03-10', 1),
(2, '26.123.123-K', 'Sofia', 'Rojas', '2013-11-20', 1);

-- 6. Tablas de quinto nivel de dependencia
INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, Observaciones, IMC, FechaMedicion) VALUES 
(2, 1, 1.50, 45.5, 'Medición de rutina.', 20.2, '2025-10-15');

INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, FechaMedicion) VALUES 
(2, 2, 1.55, 65.0, 'Ropa y zapatillas', 1.5, 'Alumno presenta sobrepeso. Peso real (63.5kg) usado para IMC.', 26.4, '2025-10-16');

INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, Observaciones, IMC, FechaMedicion) VALUES 
(2, 3, 1.45, 38.0, 'Bajo peso para la altura.', 18.0, '2025-10-17');

-- 7. Tablas de sexto nivel de dependencia
INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES 
(2, 'Sobrepeso Detectado', 'El IMC (26.4) del estudiante Luis Martínez supera el rango normal.', 1),
(3, 'Bajo Peso Detectado', 'El IMC (18.0) de la estudiante Sofia Rojas está en el límite inferior.', 1);