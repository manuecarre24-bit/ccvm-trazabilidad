-- =====================================================================
-- CCVM Trazabilidad - Migración v3 → v4
-- Agrega la línea de Garantías y Reparaciones a una base de datos que
-- YA tiene datos cargados (usuarios, pedidos, unidades, escaneos).
-- NO borra nada existente.
--
-- Úsalo en vez de 01_ccvm_trazabilidad.sql cuando quieres conservar la
-- base de datos actual. Ejecuta este único archivo una sola vez.
-- =====================================================================

USE ccvm_trazabilidad;

-- 1) Tablas nuevas para garantías y reparaciones
CREATE TABLE IF NOT EXISTS casos_garantia (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    codigo               VARCHAR(30) NOT NULL UNIQUE,
    unidad_id            INT NULL,
    tipo                 ENUM('garantia','reparacion') NOT NULL,
    marca_propia         TINYINT(1) NOT NULL DEFAULT 1,
    marca_nombre         VARCHAR(100) NULL,
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

CREATE TABLE IF NOT EXISTS casos_garantia_fotos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    caso_id       INT NOT NULL,
    etapa         ENUM('llegada','desencube','diagnostico','cierre') NOT NULL,
    ruta_archivo  VARCHAR(255) NOT NULL,
    subida_por    INT NOT NULL,
    fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caso_id) REFERENCES casos_garantia(id),
    FOREIGN KEY (subida_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 2) La tabla de escaneos necesita poder registrar un caso de garantía
--    en vez de una unidad de producción normal.
ALTER TABLE escaneos MODIFY unidad_id INT NULL;
ALTER TABLE escaneos ADD COLUMN IF NOT EXISTS caso_garantia_id INT NULL AFTER unidad_id;

-- Si tu MariaDB/MySQL no soporta "ADD COLUMN IF NOT EXISTS" y este script
-- ya se corrió antes, verás un error de "columna duplicada" en esa línea:
-- es seguro ignorarlo y seguir con el resto.

ALTER TABLE escaneos ADD CONSTRAINT fk_escaneos_caso_garantia
    FOREIGN KEY (caso_garantia_id) REFERENCES casos_garantia(id);

ALTER TABLE escaneos ADD UNIQUE KEY uniq_caso_estacion (caso_garantia_id, estacion_id);

ALTER TABLE escaneos ADD CONSTRAINT chk_escaneo_origen CHECK (
    (unidad_id IS NOT NULL AND caso_garantia_id IS NULL) OR
    (unidad_id IS NULL AND caso_garantia_id IS NOT NULL)
);

-- Listo. Tus usuarios, pedidos, unidades y escaneos existentes quedan intactos.
