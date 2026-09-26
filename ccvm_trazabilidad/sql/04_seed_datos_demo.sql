-- =====================================================================
-- Datos de ejemplo adicionales, con escaneos repartidos en los últimos
-- meses para que las gráficas del dashboard tengan contenido real.
-- Se puede ejecutar varias veces (usa seriales y órdenes distintas).
-- Ejecutar DESPUÉS de 01, 02 y 03.
-- =====================================================================

USE ccvm_trazabilidad;

INSERT INTO ordenes_venta (numero_orden, cliente, fecha_creacion, fecha_entrega) VALUES
 ('OV-0002', 'Electrificadora del Norte', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
 ('OV-0003', 'Enerca S.A.', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), CURDATE()),
 ('OV-0004', 'Cootransnorte', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY));

-- Unidades del cliente 1 (OV-0002) - lote ya entregado hace un mes
INSERT INTO unidades (serial, orden_venta_id, tipo_transformador, kva, fases, tipo_aceite, fecha_creacion, entregado)
SELECT 'CCVM-000002', id, 'Monofásico convencional', 25, 'monofasico', 'mineral', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 1 FROM ordenes_venta WHERE numero_orden='OV-0002'
UNION ALL
SELECT 'CCVM-000003', id, 'Trifásico pedestal', 112.5, 'trifasico', 'vegetal', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 1 FROM ordenes_venta WHERE numero_orden='OV-0002';

-- Unidades del cliente 2 (OV-0003) - en proceso, mes anterior
INSERT INTO unidades (serial, orden_venta_id, tipo_transformador, kva, fases, tipo_aceite, fecha_creacion, entregado)
SELECT 'CCVM-000004', id, 'Trifásico pedestal', 150, 'trifasico', 'mineral', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 0 FROM ordenes_venta WHERE numero_orden='OV-0003'
UNION ALL
SELECT 'CCVM-000005', id, 'Monofásico convencional', 37.5, 'monofasico', 'mineral', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 0 FROM ordenes_venta WHERE numero_orden='OV-0003';

-- Unidades del cliente 3 (OV-0004) - recién creadas, mes actual
INSERT INTO unidades (serial, orden_venta_id, tipo_transformador, kva, fases, tipo_aceite, fecha_creacion, entregado)
SELECT 'CCVM-000006', id, 'Trifásico pedestal', 75, 'trifasico', 'mineral', CURDATE(), 0 FROM ordenes_venta WHERE numero_orden='OV-0004'
UNION ALL
SELECT 'CCVM-000007', id, 'Trifásico pedestal', 112.5, 'trifasico', 'vegetal', CURDATE(), 0 FROM ordenes_venta WHERE numero_orden='OV-0004';

-- Escaneos: unidad 2 completó todo el recorrido hace ~2 meses (entregada)
INSERT INTO escaneos (unidad_id, estacion_id, usuario_id, fecha_hora, resultado)
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 60 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Metalmecánica'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 59 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Lavado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 58 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Granallado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 57 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Pintura'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 56 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Emblemado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 55 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Encube'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 54 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Laboratorio de pruebas'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 53 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000002' AND e.nombre='Alistamiento final';

-- Unidad 3: quedó a mitad de camino (rechazo en laboratorio)
INSERT INTO escaneos (unidad_id, estacion_id, usuario_id, fecha_hora, resultado)
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 50 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000003' AND e.nombre='Embobinado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 48 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000003' AND e.nombre='Ensamble'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 46 DAY), 'rechazado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000003' AND e.nombre='Laboratorio de pruebas';

-- Unidad 4: avanzó el mes pasado, va en Pintura
INSERT INTO escaneos (unidad_id, estacion_id, usuario_id, fecha_hora, resultado)
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 25 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000004' AND e.nombre='Metalmecánica'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 20 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000004' AND e.nombre='Lavado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 15 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000004' AND e.nombre='Granallado'
UNION ALL
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000004' AND e.nombre='Pintura';

-- Unidad 5: recién arrancó, va en Embobinado
INSERT INTO escaneos (unidad_id, estacion_id, usuario_id, fecha_hora, resultado)
SELECT u.id, e.id, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000005' AND e.nombre='Embobinado';

-- Unidad 6: va en Metalmecánica (recién ingresó hoy)
INSERT INTO escaneos (unidad_id, estacion_id, usuario_id, fecha_hora, resultado)
SELECT u.id, e.id, 1, NOW(), 'aprobado' FROM unidades u, estaciones e WHERE u.serial='CCVM-000006' AND e.nombre='Metalmecánica';

-- Unidad 7 (CCVM-000007) queda SIN escanear todavía: recién creada, a la espera.
