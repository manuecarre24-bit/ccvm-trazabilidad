<?php require 'config.php';
requerirRol(ROLES_LOTE);
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

$numeroOrden   = trim($input['numero_orden'] ?? '');
$cliente       = trim($input['cliente'] ?? '');
$fechaEntrega  = trim($input['fecha_entrega'] ?? '');
$rangos        = $input['rangos'] ?? [];

if ($numeroOrden === '' || $cliente === '' || $fechaEntrega === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Completa N° de orden, cliente y fecha de entrega general.']);
    exit;
}
if (!$rangos || !is_array($rangos)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Agrega al menos un rango de series.']);
    exit;
}

// Límite de seguridad: evita que un rango mal escrito (ej. 1 a 10000000) tumbe el servidor
$TOTAL_MAXIMO = 20000;

try {
    $pdo->beginTransaction();

    // Crear la orden si no existe; si ya existe, se reutiliza (se le agregan más unidades)
    $stmt = $pdo->prepare('SELECT id FROM ordenes_venta WHERE numero_orden = ?');
    $stmt->execute([$numeroOrden]);
    $ordenId = $stmt->fetchColumn();

    if (!$ordenId) {
        $ins = $pdo->prepare(
            'INSERT INTO ordenes_venta (numero_orden, cliente, fecha_creacion, fecha_entrega)
             VALUES (?, ?, CURDATE(), ?)'
        );
        $ins->execute([$numeroOrden, $cliente, $fechaEntrega]);
        $ordenId = $pdo->lastInsertId();
    }

    $insertUnidad = $pdo->prepare(
        'INSERT INTO unidades (serial, orden_venta_id, tipo_transformador, kva, fases, tipo_aceite, fecha_entrega)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $existeSerial = $pdo->prepare('SELECT 1 FROM unidades WHERE serial = ?');

    $creadas = 0;
    $repetidas = 0;
    $totalPedido = 0;

    foreach ($rangos as $r) {
        $prefijo  = trim($r['prefijo'] ?? '');
        $desde    = (int)($r['desde'] ?? 0);
        $hasta    = (int)($r['hasta'] ?? 0);
        $kva      = ($r['kva'] ?? '') !== '' ? (float)$r['kva'] : null;
        $fases    = ($r['fases'] ?? '') === 'monofasico' ? 'monofasico' : 'trifasico';
        $aceite   = ($r['tipo_aceite'] ?? '') === 'vegetal' ? 'vegetal' : 'mineral';
        $tipo     = trim($r['tipo_transformador'] ?? '') ?: null;
        $fEntrega = trim($r['fecha_entrega'] ?? '') ?: null;

        if ($desde <= 0 || $hasta <= 0 || $hasta < $desde) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => "Rango inválido: desde $desde hasta $hasta."]);
            exit;
        }

        $totalPedido += ($hasta - $desde + 1);
        if ($totalPedido > $TOTAL_MAXIMO) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => "El lote supera el máximo permitido de $TOTAL_MAXIMO unidades por carga. Divídelo en varias cargas."]);
            exit;
        }

        for ($n = $desde; $n <= $hasta; $n++) {
            $serial = $prefijo !== '' ? $prefijo . $n : (string)$n;

            $existeSerial->execute([$serial]);
            if ($existeSerial->fetchColumn()) {
                $repetidas++;
                continue;
            }

            $insertUnidad->execute([$serial, $ordenId, $tipo, $kva, $fases, $aceite, $fEntrega]);
            $creadas++;
        }
    }

    $pdo->commit();

    echo json_encode(['ok' => true, 'creadas' => $creadas, 'repetidas' => $repetidas]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar el lote: ' . $e->getMessage()]);
}
