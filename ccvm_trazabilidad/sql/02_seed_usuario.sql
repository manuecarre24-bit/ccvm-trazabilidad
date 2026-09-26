-- =====================================================================
-- Tu usuario (programador): acceso total al sistema.
-- Cambia el PIN antes de usarlo en producción (usuarios.php una vez
-- adentro, o edítalo aquí antes de importar).
-- Ejecutar DESPUÉS de 01_ccvm_trazabilidad.sql
-- =====================================================================

USE ccvm_trazabilidad;

INSERT INTO usuarios (nombre, pin, estacion_id, rol)
SELECT 'Victor Carrero (Programador)', '1234', id, 'programador' FROM estaciones WHERE nombre = 'Gerencia';
