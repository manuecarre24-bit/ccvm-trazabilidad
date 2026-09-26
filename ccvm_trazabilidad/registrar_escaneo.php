<?php require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuarioAutenticado()) {
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada, vuelve a ingresar.']);
    exit;
}

$serial      = trim($_POST['serial'] ?? '');
$estacionId  = (int)($_POST['estacion_id'] ?? 0);
$resultado   = ($_POST['resultado'] ?? 'aprobado') === 'rechazado' ? 'rechazado' : 'aprobado';
$usuarioId   = (int)$_SESSION['usuario_id'];

// Solo operario/jefe_planta/gerencia pueden marcar escaneos, y un operario
// normal solo puede registrar en SU estación (evita manipular el POST).
// El programador puede registrar en cualquier estación (pruebas/soporte).
if ($_SESSION['usuario_rol'] !== 'programador' && $estacionId !== (int)$_SESSION['estacion_id']) {
    echo json_encode(['ok' => false, 'mensaje' => 'No tienes permiso para registrar en esa estación.']);
    exit;
}

if ($serial === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Ingresa o escanea un código.']);
    exit;
}

// 1) Buscar primero en producción normal (el jefe de planta ya cargó sus datos con el lote)
$stmt = $pdo->prepare(
    'SELECT u.id, u.tipo_transformador, u.kva, u.fases, ov.cliente
     FROM unidades u JOIN ordenes_venta ov ON ov.id = u.orden_venta_id
     WHERE u.serial = ?'
);
$stmt->execute([$serial]);
$unidad = $stmt->fetch();

$unidadId = null;
$casoId   = null;
$etiquetaCaso = null;
$infoRespuesta = [];

if ($unidad) {
    $unidadId = $unidad['id'];
    $infoRespuesta = [
        'cliente'            => $unidad['cliente'],
        'tipo_transformador' => $unidad['tipo_transformador'],
        'kva'                => $unidad['kva'],
        'fases'              => $unidad['fases'],
    ];
} else {
    // 2) No es una unidad de producción normal: ¿es un caso de garantía/reparación
    //    que ya fue diagnosticado y está habilitado para pasar por planta?
    $stmtCaso = $pdo->prepare(
        "SELECT id, cliente, kva, fases, tipo, estado FROM casos_garantia WHERE codigo = ?"
    );
    $stmtCaso->execute([$serial]);
    $caso = $stmtCaso->fetch();

    if (!$caso) {
        echo json_encode(['ok' => false, 'mensaje' => "El código \"$serial\" no existe en el sistema. Pídele al jefe de planta que lo cargue primero."]);
        exit;
    }
    if ($caso['estado'] !== 'en_produccion') {
        echo json_encode(['ok' => false, 'mensaje' => "El código \"$serial\" es un caso de garantía/reparación que todavía no ha pasado diagnóstico. Avísale a quien gestiona garantías."]);
        exit;
    }

    $casoId = $caso['id'];
    $etiquetaCaso = $caso['tipo'] === 'garantia' ? 'Garantía' : 'Reparación';
    $infoRespuesta = [
        'cliente'            => $caso['cliente'],
        'tipo_transformador' => $etiquetaCaso,
        'kva'                => $caso['kva'],
        'fases'              => $caso['fases'],
    ];
}

// ¿Ya fue registrado en esta estación? (bloquea duplicados, unidad normal o caso de garantía)
if ($unidadId) {
    $stmtDup = $pdo->prepare(
        'SELECT s.fecha_hora, u.nombre AS operario FROM escaneos s JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.unidad_id = ? AND s.estacion_id = ?'
    );
    $stmtDup->execute([$unidadId, $estacionId]);
} else {
    $stmtDup = $pdo->prepare(
        'SELECT s.fecha_hora, u.nombre AS operario FROM escaneos s JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.caso_garantia_id = ? AND s.estacion_id = ?'
    );
    $stmtDup->execute([$casoId, $estacionId]);
}
$existente = $stmtDup->fetch();

if ($existente) {
    $hora = date('h:i a', strtotime($existente['fecha_hora']));
    echo json_encode([
        'ok' => false,
        'mensaje' => "Este código ya fue registrado aquí por {$existente['operario']} a las {$hora}. No se permite duplicar."
    ]);
    exit;
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO escaneos (unidad_id, caso_garantia_id, estacion_id, usuario_id, fecha_hora, resultado)
         VALUES (?, ?, ?, ?, NOW(), ?)'
    );
    $insert->execute([$unidadId, $casoId, $estacionId, $usuarioId, $resultado]);
} catch (PDOException $e) {
    // Choque con la llave única por una carrera entre dos escaneos casi simultáneos
    if ($e->getCode() === '23000') {
        echo json_encode(['ok' => false, 'mensaje' => 'Este código ya fue registrado en esta estación (duplicado).']);
        exit;
    }
    echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
    exit;
}

echo json_encode(array_merge([
    'ok'          => true,
    'serial'      => $serial,
    'operario'    => $_SESSION['usuario_nombre'],
    'hora'        => date('h:i a'),
    'resultado'   => $resultado,
    'es_garantia' => $etiquetaCaso,
], $infoRespuesta));
