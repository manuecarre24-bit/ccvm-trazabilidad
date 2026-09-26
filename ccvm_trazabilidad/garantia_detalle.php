<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT c.*, u.nombre AS creado_por_nombre FROM casos_garantia c
     JOIN usuarios u ON u.id = c.creado_por WHERE c.id = ?"
);
$stmt->execute([$id]);
$caso = $stmt->fetch();
if (!$caso) { die('Caso no encontrado.'); }

$stmtFotos = $pdo->prepare(
    "SELECT f.*, u.nombre AS subida_por_nombre FROM casos_garantia_fotos f
     JOIN usuarios u ON u.id = f.subida_por WHERE f.caso_id = ? ORDER BY f.fecha"
);
$stmtFotos->execute([$id]);
$fotos = $stmtFotos->fetchAll();
$fotosPorEtapa = [];
foreach ($fotos as $f) { $fotosPorEtapa[$f['etapa']][] = $f; }

$stmtHist = $pdo->prepare(
    "SELECT e.nombre AS estacion, us.nombre AS usuario, s.fecha_hora, s.resultado
     FROM escaneos s JOIN estaciones e ON e.id = s.estacion_id JOIN usuarios us ON us.id = s.usuario_id
     WHERE s.caso_garantia_id = ? ORDER BY s.fecha_hora"
);
$stmtHist->execute([$id]);
$historialProduccion = $stmtHist->fetchAll();

$puedeGestionar = in_array($_SESSION['usuario_rol'], ROLES_GARANTIAS, true);
$error = $_GET['error'] ?? '';

$etiquetasEstado = [
    'llegada' => 'Llegada', 'desencube' => 'Desencube', 'diagnostico' => 'Diagnóstico',
    'en_produccion' => 'En producción', 'cerrado' => 'Cerrado',
];

function fotoTag(array $foto): string {
    return '<a href="' . htmlspecialchars($foto['ruta_archivo']) . '" target="_blank">'
        . '<img src="' . htmlspecialchars($foto['ruta_archivo']) . '" class="foto-caso" alt="Foto del caso"></a>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Caso <?= htmlspecialchars($caso['codigo']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Caso <?= htmlspecialchars($caso['codigo']) ?></span></div>
        <div>
            <a class="salir" href="garantias.php" style="margin-right:10px;">Garantías</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">
        <?php if ($error): ?><div class="aviso-error">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="ficha-resultado">
            <h3><?= htmlspecialchars($caso['codigo']) ?> <span class="pill pill-pendiente-azul"><?= $etiquetasEstado[$caso['estado']] ?></span></h3>
            <div class="ficha-datos">
                <div><b>Cliente:</b> <?= htmlspecialchars($caso['cliente']) ?></div>
                <div><b>Tipo:</b> <?= $caso['tipo'] === 'garantia' ? 'Garantía' : 'Reparación' ?></div>
                <div><b>Marca:</b> <?= $caso['marca_propia'] ? 'Propia (CCVM)' : htmlspecialchars($caso['marca_nombre'] ?: 'Otra marca') ?></div>
                <div><b>kVA:</b> <?= $caso['kva'] ?> · <?= $caso['fases'] === 'trifasico' ? 'Trifásico' : 'Monofásico' ?></div>
                <div><b>Aceite:</b> <?= $caso['tipo_aceite'] === 'mineral' ? 'Mineral' : 'Vegetal' ?></div>
                <div><b>Llegada:</b> <?= date('d/m/Y h:i a', strtotime($caso['fecha_llegada'])) ?> · <?= htmlspecialchars($caso['creado_por_nombre']) ?></div>
            </div>
        </div>

        <h3 class="titulo-seccion">Paso 1 · Llegada</h3>
        <p><?= nl2br(htmlspecialchars($caso['descripcion_llegada'])) ?></p>
        <?php foreach (($fotosPorEtapa['llegada'] ?? []) as $f): echo fotoTag($f); endforeach; ?>

        <?php if (in_array($caso['estado'], ['desencube','diagnostico','en_produccion','cerrado'], true) || !empty($fotosPorEtapa['desencube'])): ?>
        <h3 class="titulo-seccion">Paso 2 · Desencube</h3>
        <?php foreach (($fotosPorEtapa['desencube'] ?? []) as $f): echo fotoTag($f); endforeach; ?>
        <?php endif; ?>

        <?php if ($caso['diagnostico']): ?>
        <h3 class="titulo-seccion">Paso 3 · Diagnóstico</h3>
        <p><?= nl2br(htmlspecialchars($caso['diagnostico'])) ?></p>
        <p><b>Decisión:</b> <?= $caso['decision'] === 'nuevo' ? 'Crear pieza nueva (mismo código y emblemas)' : 'Reformar el existente' ?></p>
        <?php foreach (($fotosPorEtapa['diagnostico'] ?? []) as $f): echo fotoTag($f); endforeach; ?>
        <?php endif; ?>

        <?php if (in_array($caso['estado'], ['en_produccion','cerrado'], true)): ?>
        <h3 class="titulo-seccion">Paso 4 · Paso por producción</h3>
        <p class="subtitulo-seccion">El código <b><?= htmlspecialchars($caso['codigo']) ?></b> ya puede escanearse en las estaciones normales de planta; cada escaneo queda marcado como este caso.</p>
        <div class="tabla-scroll">
        <table class="tabla-historial-ficha">
            <thead><tr><th>Estación</th><th>Responsable</th><th>Fecha y hora</th><th>Resultado</th></tr></thead>
            <tbody>
                <?php foreach ($historialProduccion as $h): ?>
                <tr><td><?= htmlspecialchars($h['estacion']) ?></td><td><?= htmlspecialchars($h['usuario']) ?></td><td><?= date('d/m/Y h:i a', strtotime($h['fecha_hora'])) ?></td>
                    <td><span class="pill pill-<?= $h['resultado'] ?>"><?= $h['resultado'] === 'aprobado' ? 'Aprobado' : 'Rechazado' ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (!$historialProduccion): ?><tr><td colspan="4" class="vacio">Todavía no ha pasado por ninguna estación.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?php if ($caso['estado'] === 'cerrado'): ?>
        <h3 class="titulo-seccion">Paso 5 · Cierre</h3>
        <p><?= nl2br(htmlspecialchars($caso['resumen_trabajo'])) ?></p>
        <?php foreach (($fotosPorEtapa['cierre'] ?? []) as $f): echo fotoTag($f); endforeach; ?>
        <p class="subtitulo-seccion">Cerrado el <?= date('d/m/Y h:i a', strtotime($caso['fecha_cierre'])) ?></p>
        <?php endif; ?>

        <?php if ($puedeGestionar && $caso['estado'] !== 'cerrado'): ?>
        <h3 class="titulo-seccion">Siguiente paso</h3>

        <?php if ($caso['estado'] === 'llegada'): ?>
        <form method="post" action="garantia_accion.php" enctype="multipart/form-data" class="caja-lote">
            <input type="hidden" name="accion" value="desencube"><input type="hidden" name="id" value="<?= $id ?>">
            <label>Foto del desencube</label>
            <input type="file" name="foto" accept="image/*" capture="environment" style="margin-bottom:12px;">
            <div class="acciones-lote"><button type="submit" class="btn-guardar-lote">Registrar desencube</button></div>
        </form>
        <?php elseif ($caso['estado'] === 'desencube'): ?>
        <form method="post" action="garantia_accion.php" enctype="multipart/form-data" class="caja-lote">
            <input type="hidden" name="accion" value="diagnostico"><input type="hidden" name="id" value="<?= $id ?>">
            <label>Diagnóstico (qué se dañó / qué aplica)</label>
            <textarea name="diagnostico" rows="3" style="width:100%;padding:10px;border:2px solid #dde3ee;border-radius:8px;font-size:14px;margin-bottom:12px;" required></textarea>
            <label>Decisión</label>
            <select name="decision" style="margin-bottom:12px;" required>
                <option value="reformar">Reformar la cuba existente</option>
                <option value="nuevo">Crear pieza nueva (mismo código y emblemas)</option>
            </select>
            <label>Foto del daño</label>
            <input type="file" name="foto" accept="image/*" capture="environment" style="margin-bottom:12px;">
            <div class="acciones-lote"><button type="submit" class="btn-guardar-lote">Guardar diagnóstico</button></div>
        </form>
        <?php elseif ($caso['estado'] === 'diagnostico'): ?>
        <form method="post" action="garantia_accion.php" class="caja-lote">
            <input type="hidden" name="accion" value="produccion"><input type="hidden" name="id" value="<?= $id ?>">
            <p class="subtitulo-seccion">Al pasar a producción, el código <b><?= htmlspecialchars($caso['codigo']) ?></b> queda habilitado para escanearse en Metalmecánica, Embobinado y las demás estaciones normales.</p>
            <div class="acciones-lote"><button type="submit" class="btn-guardar-lote">Pasar a producción</button></div>
        </form>
        <?php elseif ($caso['estado'] === 'en_produccion'): ?>
        <form method="post" action="garantia_accion.php" enctype="multipart/form-data" class="caja-lote">
            <input type="hidden" name="accion" value="cierre"><input type="hidden" name="id" value="<?= $id ?>">
            <label>Qué se le realizó a la cuba (proceso, reemplazos, mejoras)</label>
            <textarea name="resumen" rows="4" style="width:100%;padding:10px;border:2px solid #dde3ee;border-radius:8px;font-size:14px;margin-bottom:12px;" required></textarea>
            <label>Foto final</label>
            <input type="file" name="foto" accept="image/*" capture="environment" style="margin-bottom:12px;">
            <div class="acciones-lote"><button type="submit" class="btn-guardar-lote">Cerrar caso</button></div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
