-- Cambia esto por el nombre de tu base de datos
USE `nutridata`;

-- Desactivar checks de FK por si existen relaciones
SET FOREIGN_KEY_CHECKS = 0;

-- Insertar la 8ª región (Biobío)
INSERT INTO `region` (`Id`, `Region`)
VALUES (8, 'Biobío')
ON DUPLICATE KEY UPDATE `Region` = VALUES(`Region`);

-- Insertar comunas de la Región del Biobío (Id_Region = 8)
-- Ajusta los Id de comuna si ya tienes convention diferente.
INSERT INTO `comuna` (`Id`, `Comuna`, `Id_Region`) VALUES
(1,  'Concepción',           8),
(2,  'Coronel',              8),
(3,  'Chiguayante',          8),
(4,  'Florida',              8),
(5,  'Hualqui',              8),
(6,  'Hualpén',              8),
(7,  'Lota',                 8),
(8,  'Penco',                8),
(9,  'San Pedro de la Paz',  8),
(10, 'Santa Juana',          8),
(11, 'Talcahuano',           8),
(12, 'Tomé',                 8),
(13, 'Arauco',               8),
(14, 'Cañete',               8),
(15, 'Contulmo',             8),
(16, 'Lebu',                 8),
(17, 'Los Álamos',           8),
(18, 'Tirúa',                8),
(19, 'Alto Biobío',         8),
(20, 'Antuco',              8),
(21, 'Cabrero',             8),
(22, 'Laja',                8),
(23, 'Los Ángeles',         8),
(24, 'Mulchén',             8),
(25, 'Nacimiento',          8),
(26, 'Negrete',             8),
(27, 'Quilaco',             8),
(28, 'Quilleco',            8),
(29, 'San Rosendo',         8),
(30, 'Santa Bárbara',       8),
(31, 'Tucapel',             8),
(32, 'Yumbel',              8)
ON DUPLICATE KEY UPDATE `Comuna` = VALUES(`Comuna`), `Id_Region` = VALUES(`Id_Region`);

-- Reactivar checks de FK
SET FOREIGN_KEY_CHECKS = 1;
