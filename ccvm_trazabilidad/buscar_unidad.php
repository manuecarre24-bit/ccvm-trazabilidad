<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);
header('Content-Type: application/json; charset=utf-8');

$serial = trim($_GET['serial'] ?? '');
if ($serial === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Escribe un código para buscar.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT u.*, ov.cliente, ov.numero_orden,
            COALESCE(u.fecha_entrega, ov.fecha_entrega) AS fecha_entrega_final
     FROM unidades u JOIN ordenes_venta ov ON ov.id = u.orden_venta_id
     WHERE u.serial = ?"
);
$stmt->execute([$serial]);
$unidad = $stmt->fetch();

if ($unidad) {
    $stmtHist = $pdo->prepare(
        "SELECT e.nombre AS estacion, us.nombre AS usuario, s.fecha_hora, s.resultado
         FROM escaneos s
         JOIN estaciones e ON e.id = s.estacion_id
         JOIN usuarios us ON us.id = s.usuario_id
         WHERE s.unidad_id = ?
         ORDER BY s.fecha_hora ASC"
    );
    $stmtHist->execute([$unidad['id']]);
    $historial = array_map(function ($h) {
        $h['fecha_hora'] = date('d/m/Y h:i a', strtotime($h['fecha_hora']));
        return $h;
    }, $stmtHist->fetchAll());

    echo json_encode([
        'ok'                 => true,
        'tipo_item'          => 'nuevo',
        'serial'             => $unidad['serial'],
        'cliente'            => $unidad['cliente'],
        'numero_orden'       => $unidad['numero_orden'],
        'tipo_transformador' => $unidad['tipo_transformador'],
        'kva'                => $unidad['kva'],
        'fases'              => $unidad['fases'],
        'tipo_aceite'        => $unidad['tipo_aceite'],
        'fecha_entrega'      => date('d/m/Y', strtotime($unidad['fecha_entrega_final'])),
        'entregado'          => (bool)$unidad['entregado'],
        'historial'          => $historial,
    ]);
    exit;
}

// No se encontró como unidad de producción normal: buscar entre
// los casos de garantía/reparación por su código.
$stmtCaso = $pdo->prepare('SELECT * FROM casos_garantia WHERE codigo = ?');
$stmtCaso->execute([$serial]);
$caso = $stmtCaso->fetch();

if (!$caso) {
    echo json_encode(['ok' => false, 'mensaje' => "No se encontró ninguna unidad con el código \"$serial\"."]);
    exit;
}

$stmtHist = $pdo->prepare(
    "SELECT e.nombre AS estacion, us.nombre AS usuario, s.fecha_hora, s.resultado
     FROM escaneos s
     JOIN estaciones e ON e.id = s.estacion_id
     JOIN usuarios us ON us.id = s.usuario_id
     WHERE s.caso_garantia_id = ?
     ORDER BY s.fecha_hora ASC"
);
$stmtHist->execute([$caso['id']]);
$historial = array_map(function ($h) {
    $h['fecha_hora'] = date('d/m/Y h:i a', strtotime($h['fecha_hora']));
    return $h;
}, $stmtHist->fetchAll());

$etiquetasEstado = [
    'llegada' => 'En llegada', 'desencube' => 'En desencube',
    'diagnostico' => 'En diagnóstico', 'en_produccion' => 'En producción',
    'cerrado' => 'Cerrado',
];

echo json_encode([
    'ok'                 => true,
    'tipo_item'          => $caso['tipo'],
    'id_caso'            => (int)$caso['id'],
    'serial'             => $caso['codigo'],
    'cliente'            => $caso['cliente'],
    'numero_orden'       => $caso['marca_propia'] ? 'Propia (CCVM)' : ($caso['marca_nombre'] ?: 'Otra marca'),
    'tipo_transformador' => $etiquetasEstado[$caso['estado']] ?? $caso['estado'],
    'kva'                => $caso['kva'],
    'fases'              => $caso['fases'],
    'tipo_aceite'        => $caso['tipo_aceite'],
    'fecha_entrega'      => $caso['fecha_cierre'] ? date('d/m/Y', strtotime($caso['fecha_cierre'])) : '—',
    'entregado'          => $caso['estado'] === 'cerrado',
    'historial'          => $historial,
]);
