<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) { $mes = date('Y-m'); }

function datosReporte(PDO $pdo, string $mes): array {
    // Pedidos/órdenes creadas ese mes, con cuántas unidades tiene cada una y cuántas entregadas
    $pedidos = $pdo->prepare(
        "SELECT ov.numero_orden, ov.cliente, COUNT(u.id) AS unidades, SUM(u.entregado) AS entregadas
         FROM ordenes_venta ov JOIN unidades u ON u.orden_venta_id = ov.id
         WHERE DATE_FORMAT(ov.fecha_creacion, '%Y-%m') = ?
         GROUP BY ov.id ORDER BY ov.numero_orden"
    );
    $pedidos->execute([$mes]);

    // Producción del mes por estación (registros aprobados)
    $porEstacion = $pdo->prepare(
        "SELECT e.nombre AS estacion, e.linea,
                COUNT(*) AS total,
                SUM(CASE WHEN s.resultado='aprobado' THEN 1 ELSE 0 END) AS aprobados,
                SUM(CASE WHEN s.resultado='rechazado' THEN 1 ELSE 0 END) AS rechazados
         FROM escaneos s JOIN estaciones e ON e.id = s.estacion_id
         WHERE DATE_FORMAT(s.fecha_hora, '%Y-%m') = ?
         GROUP BY e.id ORDER BY e.linea, e.orden"
    );
    $porEstacion->execute([$mes]);

    $totales = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM unidades WHERE DATE_FORMAT(fecha_creacion,'%Y-%m')=?) AS unidades_creadas,
            (SELECT COUNT(*) FROM unidades WHERE entregado=1 AND DATE_FORMAT(fecha_creacion,'%Y-%m')=?) AS unidades_entregadas,
            (SELECT COUNT(*) FROM escaneos WHERE resultado='rechazado' AND DATE_FORMAT(fecha_hora,'%Y-%m')=?) AS rechazos,
            (SELECT COUNT(DISTINCT ov.cliente) FROM ordenes_venta ov WHERE DATE_FORMAT(ov.fecha_creacion,'%Y-%m')=?) AS clientes"
    );
    $totales->execute([$mes, $mes, $mes, $mes]);

    return [
        'pedidos'    => $pedidos->fetchAll(),
        'estaciones' => $porEstacion->fetchAll(),
        'totales'    => $totales->fetch(),
    ];
}

$datos = datosReporte($pdo, $mes);

function nombreMesLargo(string $ym): string {
    $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
              7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    [$y, $m] = explode('-', $ym);
    return $meses[(int)$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Reporte mensual</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Reporte mensual</span></div>
        <div>
            <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <form method="get" class="selector-mes">
            <label>Mes del reporte:</label>
            <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" onchange="this.form.submit()">
            <a class="btn-descarga btn-word" href="reporte_exportar.php?formato=word&mes=<?= urlencode($mes) ?>">Descargar en Word</a>
            <a class="btn-descarga btn-pdf" href="reporte_exportar.php?formato=pdf&mes=<?= urlencode($mes) ?>">Descargar en PDF</a>
        </form>

        <h3 class="titulo-seccion"><?= nombreMesLargo($mes) ?></h3>

        <div class="kpis">
            <div class="kpi"><span class="kpi-valor"><?= (int)$datos['totales']['unidades_creadas'] ?></span><span class="kpi-etiqueta">Unidades creadas</span></div>
            <div class="kpi kpi-ok"><span class="kpi-valor"><?= (int)$datos['totales']['unidades_entregadas'] ?></span><span class="kpi-etiqueta">Unidades entregadas</span></div>
            <div class="kpi kpi-alerta"><span class="kpi-valor"><?= (int)$datos['totales']['rechazos'] ?></span><span class="kpi-etiqueta">Rechazos</span></div>
            <div class="kpi"><span class="kpi-valor"><?= (int)$datos['totales']['clientes'] ?></span><span class="kpi-etiqueta">Clientes con pedidos</span></div>
        </div>

        <h3 class="titulo-seccion">Pedidos del mes</h3>
        <div class="tabla-scroll">
        <table class="tabla-ordenes">
            <thead><tr><th>N° Orden</th><th>Cliente</th><th>Unidades</th><th>Entregadas</th></tr></thead>
            <tbody>
                <?php foreach ($datos['pedidos'] as $p): ?>
                <tr><td><?= htmlspecialchars($p['numero_orden']) ?></td><td><?= htmlspecialchars($p['cliente']) ?></td><td><?= $p['unidades'] ?></td><td><?= $p['entregadas'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$datos['pedidos']): ?><tr><td colspan="4" class="vacio">Sin pedidos creados este mes.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <h3 class="titulo-seccion">Producción por zona/estación</h3>
        <div class="tabla-scroll">
        <table class="tabla-ordenes">
            <thead><tr><th>Estación</th><th>Registros totales</th><th>Aprobados</th><th>Rechazados</th></tr></thead>
            <tbody>
                <?php foreach ($datos['estaciones'] as $e): ?>
                <tr><td><?= htmlspecialchars($e['estacion']) ?></td><td><?= $e['total'] ?></td><td><?= $e['aprobados'] ?></td><td><?= $e['rechazados'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$datos['estaciones']): ?><tr><td colspan="4" class="vacio">Sin movimientos registrados este mes.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
