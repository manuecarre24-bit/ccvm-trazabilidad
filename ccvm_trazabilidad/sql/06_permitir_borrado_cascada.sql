-- =====================================================================
-- CCVM Trazabilidad - Migración v4 → v5
-- Permite borrar unidades y casos de garantía de prueba sin el error
-- "#1451 Cannot delete or update a parent row: a foreign key
-- constraint fails". Antes, para borrar una unidad tocaba borrar
-- primero, a mano, cada escaneo asociado a ella.
--
-- Qué hace cada cambio:
--   - escaneos.unidad_id y escaneos.caso_garantia_id: ON DELETE CASCADE
--     → si borras una unidad o un caso, sus escaneos se borran solos.
--   - casos_garantia_fotos.caso_id: ON DELETE CASCADE
--     → si borras un caso, sus fotos (los registros) se borran solos.
--   - casos_garantia.unidad_id: ON DELETE SET NULL
--     → si borras una unidad ligada a un caso de garantía, el caso NO
--       se borra, solo se desvincula de esa unidad (por si el caso
--       tiene su propio historial que quieres conservar).
--
-- IMPORTANTE: los nombres de las llaves (ibfk_1, ibfk_2, etc.) son los
-- que MariaDB genera automáticamente si creaste las tablas con el
-- script 01_ccvm_trazabilidad.sql tal cual. Si algún ALTER de abajo te
-- da error de "llave no existe", entra a phpMyAdmin → esa tabla →
-- pestaña "Estructura" → "Relaciones" (Relation view) para ver el
-- nombre real de esa llave, y reemplázalo aquí antes de correrlo.
-- =====================================================================

USE ccvm_trazabilidad;

-- escaneos.unidad_id
ALTER TABLE escaneos DROP FOREIGN KEY escaneos_ibfk_1;
ALTER TABLE escaneos ADD CONSTRAINT escaneos_ibfk_1
    FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE;

-- escaneos.caso_garantia_id
ALTER TABLE escaneos DROP FOREIGN KEY escaneos_ibfk_2;
ALTER TABLE escaneos ADD CONSTRAINT escaneos_ibfk_2
    FOREIGN KEY (caso_garantia_id) REFERENCES casos_garantia(id) ON DELETE CASCADE;

-- casos_garantia_fotos.caso_id
ALTER TABLE casos_garantia_fotos DROP FOREIGN KEY casos_garantia_fotos_ibfk_1;
ALTER TABLE casos_garantia_fotos ADD CONSTRAINT casos_garantia_fotos_ibfk_1
    FOREIGN KEY (caso_id) REFERENCES casos_garantia(id) ON DELETE CASCADE;

-- casos_garantia.unidad_id (aquí NO se borra el caso, solo se desvincula)
ALTER TABLE casos_garantia DROP FOREIGN KEY casos_garantia_ibfk_1;
ALTER TABLE casos_garantia ADD CONSTRAINT casos_garantia_ibfk_1
    FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL;
