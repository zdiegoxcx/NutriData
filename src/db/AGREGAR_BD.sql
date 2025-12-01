
-- Es necesario para que funcione las alertas
USE nutridata;
ALTER TABLE Alerta ADD COLUMN ObservacionesSeguimiento TEXT AFTER Descripcion;



-- AGREGAR SEXO PA
USE nutridata;

-- Agregamos la columna Sexo (M = Masculino, F = Femenino)
ALTER TABLE Estudiante ADD COLUMN Sexo CHAR(1) AFTER Apellido;

-- (Opcional) Actualizamos los datos existentes para que no queden vacíos
-- Asignamos 'M' o 'F' aleatoriamente para pruebas
UPDATE Estudiante SET Sexo = IF(RAND() > 0.5, 'M', 'F');




-- CAMBIOS EN REGISTRO NUTRICIONAL PARA DIAGNÓSTICO
USE nutridata;

-- 1. Agregar la columna de texto para el diagnóstico
ALTER TABLE RegistroNutricional ADD COLUMN Diagnostico VARCHAR(50) AFTER IMC;

-- 2. (Opcional) Rellenar datos antiguos con el criterio básico para no tener vacíos
UPDATE RegistroNutricional SET Diagnostico = CASE 
    WHEN IMC < 18.5 THEN 'Bajo Peso'
    WHEN IMC BETWEEN 18.5 AND 24.9 THEN 'Normal'
    WHEN IMC BETWEEN 25 AND 29.9 THEN 'Sobrepeso'
    WHEN IMC >= 30 THEN 'Obesidad'
    ELSE 'Normal'
END WHERE Diagnostico IS NULL;



-- 1. Renombrar Nombre a Nombres
ALTER TABLE Estudiante CHANGE COLUMN Nombre Nombres VARCHAR(255);

-- 2. Renombrar Apellido a ApellidoPaterno
ALTER TABLE Estudiante CHANGE COLUMN Apellido ApellidoPaterno VARCHAR(255);

-- 3. Agregar ApellidoMaterno después de ApellidoPaterno
ALTER TABLE Estudiante ADD COLUMN ApellidoMaterno VARCHAR(255) AFTER ApellidoPaterno;



-- ACTUALIZAR CONTRASEÑAS A HASH EN USUARIO
UPDATE Usuario 
SET Contraseña = '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa';