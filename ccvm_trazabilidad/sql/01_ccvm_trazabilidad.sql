-- =====================================================================
-- CCVM Trazabilidad v4 - Esquema base de datos
-- ADVERTENCIA: este script borra la base anterior (se agregó la línea
-- de Garantías y Reparaciones, que cambia la tabla de escaneos). Si ya
-- tienes datos reales cargados que no quieres perder, avísame antes de
-- correr esto.
-- Ejecutar PRIMERO, antes de cualquier archivo seed_*.sql
-- =====================================================================

DROP DATABASE IF EXISTS ccvm_trazabilidad;
CREATE DATABASE ccvm_trazabilidad
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE ccvm_trazabilidad;

CREATE TABLE estaciones (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE,
    linea   ENUM('metalmecanica','bobinas','compartida') NOT NULL,
    orden   INT NOT NULL,
    activa  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    pin          VARCHAR(10) NOT NULL UNIQUE,
    estacion_id  INT NOT NULL,
    rol          ENUM('operario','jefe_planta','gerencia','programador') NOT NULL DEFAULT 'operario',
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (estacion_id) REFERENCES estaciones(id)
) ENGINE=InnoDB;

CREATE TABLE ordenes_venta (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero_orden    VARCHAR(30) NOT NULL UNIQUE,
    cliente         VARCHAR(150) NOT NULL,
    fecha_creacion  DATE NOT NULL,
    fecha_entrega   DATE NOT NULL
) ENGINE=InnoDB;

CREATE TABLE unidades (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    serial              VARCHAR(30) NOT NULL UNIQUE,
    orden_venta_id      INT NOT NULL,
    tipo_transformador  VARCHAR(100),
    kva                 DECIMAL(8,2),
    fases               ENUM('monofasico','trifasico') NOT NULL,
    tipo_aceite         ENUM('mineral','vegetal') NOT NULL,
    fecha_creacion      DATE NOT NULL DEFAULT (CURRENT_DATE),
    fecha_entrega       DATE NULL,
    entregado           TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (orden_venta_id) REFERENCES ordenes_venta(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Garantías y reparaciones: tercera línea. Una cuba puede ser propia
-- (fabricada por CCVM, con historial en `unidades`) o de otra marca
-- (nunca pasó por producción propia). En ambos casos se conserva el
-- mismo código/serial que trae la cuba, y al reformar se deja "como
-- nueva" (emblemas y marca originales), así sea de otra empresa.
-- ---------------------------------------------------------------------
CREATE TABLE casos_garantia (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    codigo               VARCHAR(30) NOT NULL UNIQUE,
    unidad_id            INT NULL,  -- si es propia y existe en `unidades`, se liga aquí
    tipo                 ENUM('garantia','reparacion') NOT NULL,
    marca_propia         TINYINT(1) NOT NULL DEFAULT 1,
    marca_nombre         VARCHAR(100) NULL,  -- nombre de la otra marca, si no es propia
    cliente              VARCHAR(150) NOT NULL,
    kva                  DECIMAL(8,2),
    fases                ENUM('monofasico','trifasico') NOT NULL,
    tipo_aceite          ENUM('mineral','vegetal') NOT NULL,
    descripcion_llegada  TEXT,
    diagnostico          TEXT NULL,
    decision             ENUM('reformar','nuevo') NULL,
    resumen_trabajo      TEXT NULL,
    estado               ENUM('llegada','desencube','diagnostico','en_produccion','cerrado') NOT NULL DEFAULT 'llegada',
    creado_por           INT NOT NULL,
    fecha_llegada        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre         DATETIME NULL,
    FOREIGN KEY (unidad_id) REFERENCES unidades(id),
    FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE casos_garantia_fotos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    caso_id       INT NOT NULL,
    etapa         ENUM('llegada','desencube','diagnostico','cierre') NOT NULL,
    ruta_archivo  VARCHAR(255) NOT NULL,
    subida_por    INT NOT NULL,
    fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caso_id) REFERENCES casos_garantia(id),
    FOREIGN KEY (subida_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Escaneos: cada paso registrado. Puede pertenecer a una unidad de
-- producción normal (unidad_id) O a un caso de garantía/reparación
-- que está pasando por las estaciones normales (caso_garantia_id) —
-- nunca los dos a la vez.
-- ---------------------------------------------------------------------
CREATE TABLE escaneos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    unidad_id        INT NULL,
    caso_garantia_id INT NULL,
    estacion_id      INT NOT NULL,
    usuario_id       INT NOT NULL,
    fecha_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado        ENUM('aprobado','rechazado') NOT NULL DEFAULT 'aprobado',
    FOREIGN KEY (unidad_id) REFERENCES unidades(id),
    FOREIGN KEY (caso_garantia_id) REFERENCES casos_garantia(id),
    FOREIGN KEY (estacion_id) REFERENCES estaciones(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY uniq_unidad_estacion (unidad_id, estacion_id),
    UNIQUE KEY uniq_caso_estacion (caso_garantia_id, estacion_id),
    CONSTRAINT chk_escaneo_origen CHECK (
        (unidad_id IS NOT NULL AND caso_garantia_id IS NULL) OR
        (unidad_id IS NULL AND caso_garantia_id IS NOT NULL)
    )
) ENGINE=InnoDB;

-- =====================================================================
-- Catálogo de estaciones
-- =====================================================================
INSERT INTO estaciones (nombre, linea, orden) VALUES
 ('Metalmecánica', 'metalmecanica', 1),
 ('Lavado',        'metalmecanica', 2),
 ('Granallado',    'metalmecanica', 3),
 ('Pintura',       'metalmecanica', 4),
 ('Emblemado',     'metalmecanica', 5);

INSERT INTO estaciones (nombre, linea, orden) VALUES
 ('Embobinado',  'bobinas', 1),
 ('Ensamble',    'bobinas', 2),
 ('Conexiones',  'bobinas', 3),
 ('Horno',       'bobinas', 4);

INSERT INTO estaciones (nombre, linea, orden) VALUES
 ('Encube',                 'compartida', 6),
 ('Laboratorio de pruebas', 'compartida', 7),
 ('Alistamiento final',     'compartida', 8);

INSERT INTO estaciones (nombre, linea, orden) VALUES
 ('Gerencia', 'compartida', 99);
