
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



-- CAMBIOS REGISTRO

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