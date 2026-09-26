<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

// ---------------------------------------------------------------------
// 1) Posición actual de cada unidad activa (no entregada)
// ---------------------------------------------------------------------
$sqlActuales = "
    SELECT u.id, u.serial, u.kva, u.fases, u.tipo_transformador,
           ov.cliente, ov.numero_orden,
           est.id AS estacion_id, est.nombre AS estacion_nombre, est.linea, est.orden,
           ult.fecha_hora AS actualizado
    FROM unidades u
    JOIN ordenes_venta ov ON ov.id = u.orden_venta_id
    LEFT JOIN (
        SELECT s1.unidad_id, s1.estacion_id, s1.fecha_hora
        FROM escaneos s1
        INNER JOIN (
            SELECT unidad_id, MAX(fecha_hora) AS max_fecha FROM escaneos GROUP BY unidad_id
        ) s2 ON s2.unidad_id = s1.unidad_id AND s2.max_fecha = s1.fecha_hora
    ) ult ON ult.unidad_id = u.id
    LEFT JOIN estaciones est ON est.id = ult.estacion_id
    WHERE u.entregado = 0
    ORDER BY est.orden IS NULL DESC, est.orden ASC, ult.fecha_hora ASC
";
$unidadesActivas = $pdo->query($sqlActuales)->fetchAll();
foreach ($unidadesActivas as &$u) { $u['tipo_item'] = 'nuevo'; }
unset($u);

// ---------------------------------------------------------------------
// 1b) Casos de garantía/reparación activos (no cerrados), con su última
//     estación si ya están pasando por producción. Se muestran igual
//     que las unidades normales, pero con una etiqueta de tipo.
// ---------------------------------------------------------------------
$sqlGarantiasActivas = "
    SELECT cg.id, cg.codigo AS serial, cg.kva, cg.fases,
           cg.tipo AS tipo_item,
           cg.cliente, NULL AS numero_orden,
           est.id AS estacion_id, est.nombre AS estacion_nombre, est.linea, est.orden,
           ult.fecha_hora AS actualizado
    FROM casos_garantia cg
    LEFT JOIN (
        SELECT s1.caso_garantia_id, s1.estacion_id, s1.fecha_hora
        FROM escaneos s1
        INNER JOIN (
            SELECT caso_garantia_id, MAX(fecha_hora) AS max_fecha
            FROM escaneos WHERE caso_garantia_id IS NOT NULL
            GROUP BY caso_garantia_id
        ) s2 ON s2.caso_garantia_id = s1.caso_garantia_id AND s2.max_fecha = s1.fecha_hora
    ) ult ON ult.caso_garantia_id = cg.id
    LEFT JOIN estaciones est ON est.id = ult.estacion_id
    WHERE cg.estado != 'cerrado'
";
$garantiasActivasMapa = $pdo->query($sqlGarantiasActivas)->fetchAll();

$unidadesActivas = array_merge($unidadesActivas, $garantiasActivasMapa);

$sinIniciar = array_filter($unidadesActivas, fn($u) => $u['estacion_id'] === null);
$porEstacion = [];
foreach ($unidadesActivas as $u) {
    if ($u['estacion_id'] !== null) {
        $porEstacion[$u['estacion_id']][] = $u;
    }
}

// ---------------------------------------------------------------------
// 2) Catálogo de estaciones (para dibujar las columnas del mapa)
// ---------------------------------------------------------------------
$estaciones = $pdo->query(
    "SELECT * FROM estaciones WHERE nombre != 'Gerencia' ORDER BY linea, orden"
)->fetchAll();
$lineaMetal = array_filter($estaciones, fn($e) => $e['linea'] === 'metalmecanica');
$lineaBobinas = array_filter($estaciones, fn($e) => $e['linea'] === 'bobinas');
$lineaCompartida = array_filter($estaciones, fn($e) => $e['linea'] === 'compartida');

// ---------------------------------------------------------------------
// 3) KPIs
// ---------------------------------------------------------------------
$totalActivas   = count($unidadesActivas);
$clientesEnEspera = count(array_unique(array_column($unidadesActivas, 'cliente')));
$totalRechazosMes = $pdo->query(
    "SELECT COUNT(*) FROM escaneos WHERE resultado='rechazado' AND MONTH(fecha_hora)=MONTH(CURDATE()) AND YEAR(fecha_hora)=YEAR(CURDATE())"
)->fetchColumn();
$totalEntregadasMes = $pdo->query(
    "SELECT COUNT(*) FROM unidades WHERE entregado=1 AND MONTH(fecha_creacion)=MONTH(CURDATE())"
)->fetchColumn();

// ---------------------------------------------------------------------
// 4) Producción mensual por estación (para gráficos + mejor área/mes)
// ---------------------------------------------------------------------
$sqlProdMensual = "
    SELECT DATE_FORMAT(s.fecha_hora, '%Y-%m') AS mes, e.nombre AS estacion, COUNT(*) AS total
    FROM escaneos s
    JOIN estaciones e ON e.id = s.estacion_id
    WHERE s.resultado = 'aprobado' AND s.fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes, e.id
    ORDER BY mes ASC, total DESC
";
$prodMensualRaw = $pdo->query($sqlProdMensual)->fetchAll();

$mesesTotales = [];   // mes => total general
$mejorAreaPorMes = []; // mes => ['estacion'=>..,'total'=>..]
foreach ($prodMensualRaw as $fila) {
    $mesesTotales[$fila['mes']] = ($mesesTotales[$fila['mes']] ?? 0) + (int)$fila['total'];
    if (!isset($mejorAreaPorMes[$fila['mes']])) {
        $mejorAreaPorMes[$fila['mes']] = ['estacion' => $fila['estacion'], 'total' => (int)$fila['total']];
    }
}
ksort($mesesTotales);
ksort($mejorAreaPorMes);

function nombreMes(string $ym): string {
    $meses = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
    [$y, $m] = explode('-', $ym);
    return $meses[$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Panel de trazabilidad</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Mapa de seguimiento</span></div>
        <div>
            <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <!-- Buscador de ficha por código -->
        <div class="buscador" id="buscar">
            <input type="text" id="buscar-serial" placeholder="Buscar unidad por código (ej: CCVM-000001)" autocomplete="off">
            <button onclick="buscarUnidad()">Buscar</button>
        </div>
        <div id="ficha-resultado" class="ficha-resultado" style="display:none;"></div>

        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <span class="kpi-valor"><?= $totalActivas ?></span>
                <span class="kpi-etiqueta">Unidades en proceso</span>
            </div>
            <div class="kpi">
                <span class="kpi-valor"><?= $clientesEnEspera ?></span>
                <span class="kpi-etiqueta">Clientes con unidades a la espera</span>
            </div>
            <div class="kpi kpi-alerta">
                <span class="kpi-valor"><?= $totalRechazosMes ?></span>
                <span class="kpi-etiqueta">Rechazos este mes</span>
            </div>
            <div class="kpi kpi-ok">
                <span class="kpi-valor"><?= $totalEntregadasMes ?></span>
                <span class="kpi-etiqueta">Entregadas este mes</span>
            </div>
        </div>

        <!-- Mapa de seguimiento -->
        <h3 class="titulo-seccion">Mapa de seguimiento del proceso</h3>
        <p class="subtitulo-seccion">Cada tarjeta es una unidad activa, ubicada en la última estación por la que pasó.</p>

        <div class="mapa-linea">
            <span class="etiqueta-linea">Sin iniciar</span>
            <div class="columna-estacion columna-sin-iniciar">
                <?php foreach ($sinIniciar as $u): ?>
                    <?= tarjetaUnidad($u) ?>
                <?php endforeach; ?>
                <?php if (!$sinIniciar): ?><p class="vacio-columna">—</p><?php endif; ?>
            </div>
        </div>

        <div class="mapa-linea">
            <span class="etiqueta-linea">Línea Metalmecánica</span>
            <div class="fila-columnas">
                <?php foreach ($lineaMetal as $est): ?>
                <div class="columna-estacion">
                    <h4><?= htmlspecialchars($est['nombre']) ?></h4>
                    <?php foreach (($porEstacion[$est['id']] ?? []) as $u): ?>
                        <?= tarjetaUnidad($u) ?>
                    <?php endforeach; ?>
                    <?php if (empty($porEstacion[$est['id']])): ?><p class="vacio-columna">—</p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mapa-linea">
            <span class="etiqueta-linea">Línea Bobinas</span>
            <div class="fila-columnas">
                <?php foreach ($lineaBobinas as $est): ?>
                <div class="columna-estacion">
                    <h4><?= htmlspecialchars($est['nombre']) ?></h4>
                    <?php foreach (($porEstacion[$est['id']] ?? []) as $u): ?>
                        <?= tarjetaUnidad($u) ?>
                    <?php endforeach; ?>
                    <?php if (empty($porEstacion[$est['id']])): ?><p class="vacio-columna">—</p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mapa-linea">
            <span class="etiqueta-linea">Flujo compartido</span>
            <div class="fila-columnas">
                <?php foreach ($lineaCompartida as $est): ?>
                <div class="columna-estacion">
                    <h4><?= htmlspecialchars($est['nombre']) ?></h4>
                    <?php foreach (($porEstacion[$est['id']] ?? []) as $u): ?>
                        <?= tarjetaUnidad($u) ?>
                    <?php endforeach; ?>
                    <?php if (empty($porEstacion[$est['id']])): ?><p class="vacio-columna">—</p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gráficos de producción -->
        <h3 class="titulo-seccion">Producción mensual</h3>
        <div class="graficos">
            <div class="grafico-caja">
                <canvas id="graficoProduccion"></canvas>
            </div>
            <div class="mejor-area-caja">
                <h4>Área con mejor producción por mes</h4>
                <table class="tabla-mejor-area">
                    <thead><tr><th>Mes</th><th>Área</th><th>Registros aprobados</th></tr></thead>
                    <tbody>
                        <?php foreach ($mejorAreaPorMes as $mes => $info): ?>
                        <tr>
                            <td><?= nombreMes($mes) ?></td>
                            <td><?= htmlspecialchars($info['estacion']) ?></td>
                            <td><?= $info['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$mejorAreaPorMes): ?>
                        <tr><td colspan="3" class="vacio">Aún no hay suficientes registros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
function tarjetaUnidad(array $u): string {
    $hace = $u['actualizado'] ? '· ' . date('d/m h:i a', strtotime($u['actualizado'])) : '';

    $etiquetas = ['nuevo' => 'Nuevo', 'garantia' => 'Garantía', 'reparacion' => 'Reparación'];
    $tipo = $u['tipo_item'] ?? 'nuevo';
    $badge = '<span class="badge-tipo badge-' . $tipo . '">' . $etiquetas[$tipo] . '</span>';

    $envoltura = $tipo === 'nuevo' ? 'div' : 'a';
    $href = $tipo === 'nuevo' ? '' : ' href="garantia_detalle.php?id=' . (int)$u['id'] . '"';

    return "<$envoltura class=\"tarjeta-unidad\"$href>"
        . $badge
        . '<b>' . htmlspecialchars($u['serial']) . '</b>'
        . '<small>' . htmlspecialchars($u['cliente']) . '</small>'
        . '<small class="fecha-tarjeta">' . $hace . '</small>'
        . "</$envoltura>";
}
?>

<script>
const labelsMeses = <?= json_encode(array_map('nombreMes', array_keys($mesesTotales))) ?>;
const datosMeses  = <?= json_encode(array_values($mesesTotales)) ?>;

new Chart(document.getElementById('graficoProduccion'), {
    type: 'bar',
    data: {
        labels: labelsMeses,
        datasets: [{
            label: 'Unidades aprobadas por mes (todas las áreas)',
            data: datosMeses,
            backgroundColor: '#2f6fed'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

async function buscarUnidad() {
    const serial = document.getElementById('buscar-serial').value.trim();
    const caja = document.getElementById('ficha-resultado');
    if (!serial) return;

    caja.style.display = 'block';
    caja.innerHTML = 'Buscando...';

    try {
        const resp = await fetch('buscar_unidad.php?serial=' + encodeURIComponent(serial));
        const data = await resp.json();

        if (!data.ok) {
            caja.innerHTML = `<p class="error-ficha">${data.mensaje}</p>`;
            return;
        }

        let historial = data.historial.map(h =>
            `<tr><td>${h.estacion}</td><td>${h.usuario}</td><td>${h.fecha_hora}</td>
             <td><span class="pill pill-${h.resultado}">${h.resultado === 'aprobado' ? 'Aprobado' : 'Rechazado'}</span></td></tr>`
        ).join('');

        const etiquetasTipo = { nuevo: 'Nuevo', garantia: 'Garantía', reparacion: 'Reparación' };
        const badgeTipo = `<span class="badge-tipo badge-${data.tipo_item}">${etiquetasTipo[data.tipo_item] ?? 'Nuevo'}</span>`;
        const enlaceCaso = data.tipo_item !== 'nuevo'
            ? ` · <a href="garantia_detalle.php?id=${data.id_caso}">Ver caso completo →</a>`
            : '';

        caja.innerHTML = `
            <div class="ficha">
                <h3>${badgeTipo} ${data.serial} <span class="pill ${data.entregado ? 'pill-aprobado' : 'pill-pendiente'}">${data.entregado ? 'Entregado' : 'En proceso'}</span>${enlaceCaso}</h3>
                <div class="ficha-datos">
                    <div><b>Cliente:</b> ${data.cliente}</div>
                    <div><b>${data.tipo_item === 'nuevo' ? 'Orden de venta' : 'Marca'}:</b> ${data.numero_orden}</div>
                    <div><b>${data.tipo_item === 'nuevo' ? 'Tipo' : 'Estado del caso'}:</b> ${data.tipo_transformador ?? '-'}</div>
                    <div><b>kVA:</b> ${data.kva ?? '-'}</div>
                    <div><b>Fases:</b> ${data.fases}</div>
                    <div><b>Aceite:</b> ${data.tipo_aceite}</div>
                    <div><b>Fecha entrega:</b> ${data.fecha_entrega}</div>
                </div>
                <h4>Historial de estaciones</h4>
                <table class="tabla-historial-ficha">
                    <thead><tr><th>Estación</th><th>Responsable</th><th>Fecha y hora</th><th>Resultado</th></tr></thead>
                    <tbody>${historial || '<tr><td colspan="4" class="vacio">Sin escaneos todavía.</td></tr>'}</tbody>
                </table>
            </div>`;
    } catch (e) {
        caja.innerHTML = '<p class="error-ficha">No se pudo conectar con el servidor.</p>';
    }
}

document.getElementById('buscar-serial').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') buscarUnidad();
});
</script>
</body>
</html>
