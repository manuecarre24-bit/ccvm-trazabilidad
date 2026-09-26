<?php require 'config.php';
requerirLogin();

// Solo el programador puede cambiar de estación libremente (para pruebas/soporte).
// Los operarios siempre trabajan en la estación que tienen asignada.
$estaciones = [];
if ($_SESSION['usuario_rol'] === 'programador') {
    $estaciones = $pdo->query('SELECT id, nombre FROM estaciones WHERE activa = 1 AND nombre != "Gerencia" ORDER BY linea, orden')->fetchAll();
}

$estacionActualId = (int)$_SESSION['estacion_id'];
if ($_SESSION['usuario_rol'] === 'programador' && isset($_POST['estacion_id'])) {
    $estacionActualId = (int)$_POST['estacion_id'];
}
$stmtEst = $pdo->prepare('SELECT nombre FROM estaciones WHERE id = ?');
$stmtEst->execute([$estacionActualId]);
$estacionActualNombre = $stmtEst->fetchColumn() ?: $_SESSION['estacion_nombre'];

// Cuántos potes iniciaron su proceso HOY en toda la planta (primer escaneo
// del día en una estación de arranque de línea: Metalmecánica o Embobinado).
$potesHoy = $pdo->query(
    "SELECT COUNT(DISTINCT s.unidad_id)
     FROM escaneos s
     JOIN estaciones e ON e.id = s.estacion_id
     WHERE DATE(s.fecha_hora) = CURDATE() AND e.orden = 1"
)->fetchColumn();

// Últimos registros hechos en esta estación hoy (quién y a qué hora)
$stmtHoy = $pdo->prepare(
    'SELECT u.serial, us.nombre AS operario, s.fecha_hora, s.resultado
     FROM escaneos s
     JOIN unidades u ON u.id = s.unidad_id
     JOIN usuarios us ON us.id = s.usuario_id
     WHERE s.estacion_id = ? AND DATE(s.fecha_hora) = CURDATE()
     ORDER BY s.fecha_hora DESC
     LIMIT 15'
);
$stmtHoy->execute([$estacionActualId]);
$registrosHoy = $stmtHoy->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>CCVM - Escaneo</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span></div>
        <div>
            <?php if (in_array($_SESSION['usuario_rol'], ROLES_DASHBOARD, true)): ?>
                <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <?php endif; ?>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-escaneo">

        <div class="contador-hoy">
            <span class="contador-numero"><?= (int)$potesHoy ?></span>
            <span class="contador-etiqueta">potes ingresados hoy a planta</span>
        </div>

        <?php if ($_SESSION['usuario_rol'] === 'programador'): ?>
        <form method="post" class="selector-estacion">
            <label>Estación actual:</label>
            <select name="estacion_id" onchange="this.form.submit()">
                <?php foreach ($estaciones as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $e['id'] == $estacionActualId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <div class="tarjeta-estacion">
            <p class="etiqueta-estacion">Estación</p>
            <h2><?= htmlspecialchars($estacionActualNombre) ?></h2>
        </div>

        <div class="caja-scan">
            <label for="serial">Escanea o escribe el código de la unidad</label>
            <input type="text" id="serial" placeholder="Código" autocomplete="off" autofocus>
            <?php if ($estacionActualNombre === 'Laboratorio de pruebas'): ?>
            <div class="botones-resultado">
                <button type="button" class="btn-ok" onclick="registrar(input.value.trim(),'aprobado')">Aprobar</button>
                <button type="button" class="btn-mal" onclick="registrar(input.value.trim(),'rechazado')">Rechazar</button>
            </div>
            <?php endif; ?>
            <div id="resultado-scan" class="resultado-scan"></div>
            <div id="ficha-scan" class="ficha-scan" style="display:none;"></div>
        </div>

        <div class="historial-hoy">
            <h3>Registros de hoy en esta estación</h3>
            <div class="tabla-scroll">
            <table>
                <thead>
                    <tr><th>Unidad</th><th>Responsable</th><th>Hora</th><th>Resultado</th></tr>
                </thead>
                <tbody id="tabla-historial">
                    <?php foreach ($registrosHoy as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['serial']) ?></td>
                        <td><?= htmlspecialchars($r['operario']) ?></td>
                        <td><?= date('h:i a', strtotime($r['fecha_hora'])) ?></td>
                        <td><span class="pill pill-<?= $r['resultado'] ?>"><?= $r['resultado'] === 'aprobado' ? 'Aprobado' : 'Rechazado' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$registrosHoy): ?>
                    <tr><td colspan="4" class="vacio">Todavía no hay registros hoy en esta estación.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

<script>
const input = document.getElementById('serial');
const resultadoDiv = document.getElementById('resultado-scan');
const fichaDiv = document.getElementById('ficha-scan');
const estacionId = <?= (int)$estacionActualId ?>;

input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        registrar(input.value.trim());
    }
});

async function registrar(serial, resultado) {
    if (!serial) return;
    resultadoDiv.className = 'resultado-scan';
    resultadoDiv.textContent = 'Registrando...';
    fichaDiv.style.display = 'none';

    const body = new URLSearchParams({ serial, estacion_id: estacionId });
    if (resultado) body.set('resultado', resultado);

    try {
        const resp = await fetch('registrar_escaneo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        const data = await resp.json();

        if (data.ok) {
            resultadoDiv.className = 'resultado-scan ok';
            resultadoDiv.innerHTML = `✔ ${data.serial} registrado por <b>${data.operario}</b> a las ${data.hora}`;
            fichaDiv.style.display = 'block';
            fichaDiv.innerHTML = `
                ${data.es_garantia ? `<div style="margin-bottom:6px"><span class="pill pill-pendiente-azul">${data.es_garantia}</span></div>` : ''}
                <div><b>Cliente:</b> ${data.cliente}</div>
                <div><b>Tipo:</b> ${data.tipo_transformador ?? '-'} · <b>kVA:</b> ${data.kva ?? '-'} · ${data.fases === 'trifasico' ? 'Trifásico' : 'Monofásico'}</div>`;
            agregarFila(data);
        } else {
            resultadoDiv.className = 'resultado-scan error';
            resultadoDiv.textContent = '✖ ' + data.mensaje;
        }
    } catch (err) {
        resultadoDiv.className = 'resultado-scan error';
        resultadoDiv.textContent = '✖ No se pudo conectar con el servidor.';
    }

    input.value = '';
    input.focus();
}

function agregarFila(data) {
    const tbody = document.getElementById('tabla-historial');
    const vacio = tbody.querySelector('.vacio');
    if (vacio) vacio.closest('tr').remove();
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${data.serial}</td><td>${data.operario}</td><td>${data.hora}</td>
        <td><span class="pill pill-${data.resultado}">${data.resultado === 'aprobado' ? 'Aprobado' : 'Rechazado'}</span></td>`;
    tbody.prepend(tr);
}
</script>
</body>
</html>
