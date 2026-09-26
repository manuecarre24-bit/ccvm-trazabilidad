-- =====================================================================
-- Usuarios de prueba: uno por estación (operario), código = PIN
-- + jefe de planta y gerencia, para probar el flujo completo.
-- Ejecutar DESPUÉS de 01_ccvm_trazabilidad.sql
-- =====================================================================

USE ccvm_trazabilidad;

INSERT INTO usuarios (nombre, pin, estacion_id, rol)
SELECT 'Operario Metalmecánica', '1001', id, 'operario' FROM estaciones WHERE nombre = 'Metalmecánica'
UNION ALL
SELECT 'Operario Lavado', '1002', id, 'operario' FROM estaciones WHERE nombre = 'Lavado'
UNION ALL
SELECT 'Operario Granallado', '1003', id, 'operario' FROM estaciones WHERE nombre = 'Granallado'
UNION ALL
SELECT 'Operario Pintura', '1004', id, 'operario' FROM estaciones WHERE nombre = 'Pintura'
UNION ALL
SELECT 'Operario Emblemado', '1005', id, 'operario' FROM estaciones WHERE nombre = 'Emblemado'
UNION ALL
SELECT 'Operario Embobinado', '1006', id, 'operario' FROM estaciones WHERE nombre = 'Embobinado'
UNION ALL
SELECT 'Operario Ensamble', '1007', id, 'operario' FROM estaciones WHERE nombre = 'Ensamble'
UNION ALL
SELECT 'Operario Conexiones', '1008', id, 'operario' FROM estaciones WHERE nombre = 'Conexiones'
UNION ALL
SELECT 'Operario Horno', '1009', id, 'operario' FROM estaciones WHERE nombre = 'Horno'
UNION ALL
SELECT 'Operario Encube', '1010', id, 'operario' FROM estaciones WHERE nombre = 'Encube'
UNION ALL
SELECT 'Operario Laboratorio', '1011', id, 'operario' FROM estaciones WHERE nombre = 'Laboratorio de pruebas'
UNION ALL
SELECT 'Operario Alistamiento', '1012', id, 'operario' FROM estaciones WHERE nombre = 'Alistamiento final';

-- Jefe de planta y gerencia (ven el dashboard completo, no escanean)
INSERT INTO usuarios (nombre, pin, estacion_id, rol)
SELECT 'Jefe de Planta', '9001', id, 'jefe_planta' FROM estaciones WHERE nombre = 'Gerencia'
UNION ALL
SELECT 'Gerencia CDM', '9002', id, 'gerencia' FROM estaciones WHERE nombre = 'Gerencia';
