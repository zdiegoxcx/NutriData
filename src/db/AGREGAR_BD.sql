
-- Es necesario para que funcione las alertas
USE nutridata;
ALTER TABLE Alerta ADD COLUMN ObservacionesSeguimiento TEXT AFTER Descripcion;