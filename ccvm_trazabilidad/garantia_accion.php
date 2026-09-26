<?php require 'config.php';
requerirRol(ROLES_GARANTIAS);

$accion = $_POST['accion'] ?? '';
$usuarioId = (int)$_SESSION['usuario_id'];

define('DIR_SUBIDAS', __DIR__ . '/uploads/garantias/');
if (!is_dir(DIR_SUBIDAS)) { mkdir(DIR_SUBIDAS, 0775, true); }

function guardarFoto(PDO $pdo, int $casoId, string $etapa, int $usuarioId): void {
    if (empty($_FILES['foto']['tmp_name']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        return; // la foto es opcional en cada paso
    }
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) { return; }

    $nombreArchivo = 'caso' . $casoId . '_' . $etapa . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $rutaDisco = DIR_SUBIDAS . $nombreArchivo;
    $rutaWeb = 'uploads/garantias/' . $nombreArchivo;

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDisco)) {
        $ins = $pdo->prepare(
            'INSERT INTO casos_garantia_fotos (caso_id, etapa, ruta_archivo, subida_por) VALUES (?,?,?,?)'
        );
        $ins->execute([$casoId, $etapa, $rutaWeb, $usuarioId]);
    }
}

// =========================================================================
if ($accion === 'crear') {
    $codigo   = trim($_POST['codigo'] ?? '');
    $cliente  = trim($_POST['cliente'] ?? '');
    $tipo     = ($_POST['tipo'] ?? '') === 'reparacion' ? 'reparacion' : 'garantia';
    $propia   = ($_POST['marca_propia'] ?? '1') === '0' ? 0 : 1;
    $marca    = trim($_POST['marca_nombre'] ?? '') ?: null;
    $kva      = ($_POST['kva'] ?? '') !== '' ? (float)$_POST['kva'] : null;
    $fases    = ($_POST['fases'] ?? '') === 'monofasico' ? 'monofasico' : 'trifasico';
    $aceite   = ($_POST['tipo_aceite'] ?? '') === 'vegetal' ? 'vegetal' : 'mineral';
    $desc     = trim($_POST['descripcion'] ?? '');

    if ($codigo === '' || $cliente === '' || $desc === '') {
        header('Location: garantia_nueva.php?error=' . urlencode('Completa código, cliente y descripción.'));
        exit;
    }

    $chk = $pdo->prepare('SELECT id FROM casos_garantia WHERE codigo = ?');
    $chk->execute([$codigo]);
    if ($chk->fetch()) {
        header('Location: garantia_nueva.php?error=' . urlencode("Ya existe un caso abierto o cerrado con el código \"$codigo\"."));
        exit;
    }

    // Si es propia, intenta ligarla a su unidad original (para conservar su historial de fabricación)
    $unidadId = null;
    if ($propia) {
        $u = $pdo->prepare('SELECT id FROM unidades WHERE serial = ?');
        $u->execute([$codigo]);
        $unidadId = $u->fetchColumn() ?: null;
    }

    $ins = $pdo->prepare(
        'INSERT INTO casos_garantia (codigo, unidad_id, tipo, marca_propia, marca_nombre, cliente, kva, fases, tipo_aceite, descripcion_llegada, creado_por)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $ins->execute([$codigo, $unidadId, $tipo, $propia, $marca, $cliente, $kva, $fases, $aceite, $desc, $usuarioId]);
    $casoId = $pdo->lastInsertId();

    guardarFoto($pdo, $casoId, 'llegada', $usuarioId);

    header('Location: garantia_detalle.php?id=' . $casoId);
    exit;
}

// A partir de aquí todas las acciones requieren un caso existente
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM casos_garantia WHERE id = ?');
$stmt->execute([$id]);
$caso = $stmt->fetch();
if (!$caso) { die('Caso no encontrado.'); }

if ($accion === 'desencube' && $caso['estado'] === 'llegada') {
    $pdo->prepare('UPDATE casos_garantia SET estado = "desencube" WHERE id = ?')->execute([$id]);
    guardarFoto($pdo, $id, 'desencube', $usuarioId);

} elseif ($accion === 'diagnostico' && $caso['estado'] === 'desencube') {
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $decision = ($_POST['decision'] ?? '') === 'nuevo' ? 'nuevo' : 'reformar';
    if ($diagnostico === '') {
        header('Location: garantia_detalle.php?id=' . $id . '&error=' . urlencode('Escribe el diagnóstico.'));
        exit;
    }
    $pdo->prepare('UPDATE casos_garantia SET estado="diagnostico", diagnostico=?, decision=? WHERE id=?')
        ->execute([$diagnostico, $decision, $id]);
    guardarFoto($pdo, $id, 'diagnostico', $usuarioId);

} elseif ($accion === 'produccion' && $caso['estado'] === 'diagnostico') {
    $pdo->prepare('UPDATE casos_garantia SET estado = "en_produccion" WHERE id = ?')->execute([$id]);

} elseif ($accion === 'cierre' && $caso['estado'] === 'en_produccion') {
    $resumen = trim($_POST['resumen'] ?? '');
    if ($resumen === '') {
        header('Location: garantia_detalle.php?id=' . $id . '&error=' . urlencode('Describe qué se le realizó a la cuba.'));
        exit;
    }
    $pdo->prepare('UPDATE casos_garantia SET estado="cerrado", resumen_trabajo=?, fecha_cierre=NOW() WHERE id=?')
        ->execute([$resumen, $id]);
    guardarFoto($pdo, $id, 'cierre', $usuarioId);
}

header('Location: garantia_detalle.php?id=' . $id);
exit;
