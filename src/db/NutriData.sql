CREATE DATABASE IF NOT EXISTS nutridata;
USE nutridata;

-- Tablas independientes (sin claves foráneas)
CREATE TABLE Region (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Region VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE Rol (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(255)
) ENGINE=InnoDB;

-- Tablas de primer nivel de dependencia
CREATE TABLE Comuna (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Comuna VARCHAR(255),
    Id_Region INT,
    FOREIGN KEY (Id_Region) REFERENCES Region(Id)
) ENGINE=InnoDB;

CREATE TABLE Usuario (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Rol INT,
    Rut VARCHAR(10),
    Nombre VARCHAR(255),
    Apellido VARCHAR(255),
    Contraseña VARCHAR(255),
    Telefono VARCHAR(255),
    Email VARCHAR(255),
    Estado BOOLEAN,
    FechaEliminacion DATE,
    MotivoEliminacion TEXT,
    FOREIGN KEY (Id_Rol) REFERENCES Rol(Id)
) ENGINE=InnoDB;

CREATE TABLE Direccion (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Comuna INT,
    Direccion VARCHAR(255),
    FOREIGN KEY (Id_Comuna) REFERENCES Comuna(Id)
) ENGINE=InnoDB;

-- Tablas de segundo nivel de dependencia
CREATE TABLE Establecimiento (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Direccion INT,
    Nombre VARCHAR(255),
    FOREIGN KEY (Id_Direccion) REFERENCES Direccion(Id)
) ENGINE=InnoDB;

CREATE TABLE Reporte (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Usuario INT,
    Descripcion TEXT,
    Fecha DATE,
    FOREIGN KEY (Id_Usuario) REFERENCES Usuario(Id)
) ENGINE=InnoDB;

-- Tablas de tercer nivel de dependencia
CREATE TABLE Curso (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Establecimiento INT,
    Nombre VARCHAR(255),
    Id_Profesor INT,
    FOREIGN KEY (Id_Establecimiento) REFERENCES Establecimiento(Id),
    FOREIGN KEY (Id_Profesor) REFERENCES Usuario(Id)
) ENGINE=InnoDB;

-- Tablas de cuarto nivel de dependencia
CREATE TABLE Estudiante (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Curso INT,
    Rut VARCHAR(10),
    Nombre VARCHAR(255),
    Apellido VARCHAR(255),
    FechaNacimiento DATE,
    Estado BOOLEAN,
    FechaEliminacion DATE,
    MotivoEliminacion TEXT,
    FOREIGN KEY (Id_Curso) REFERENCES Curso(Id)
) ENGINE=InnoDB;

-- Tablas de quinto nivel de dependencia
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
    FechaMedicion DATE,
    FOREIGN KEY (Id_Profesor) REFERENCES Usuario(Id),
    FOREIGN KEY (Id_Estudiante) REFERENCES Estudiante(Id)
) ENGINE=InnoDB;

-- Tablas de sexto nivel de dependencia
CREATE TABLE Alerta (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_RegistroNutricional INT,
    Nombre VARCHAR(255),
    Descripcion TEXT,
    Estado BOOLEAN,
    FOREIGN KEY (Id_RegistroNutricional) REFERENCES RegistroNutricional(Id)
) ENGINE=InnoDB;