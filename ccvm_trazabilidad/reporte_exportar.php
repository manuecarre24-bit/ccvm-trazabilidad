<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) { $mes = date('Y-m'); }
$formato = $_GET['formato'] ?? 'word';

function datosReporte(PDO $pdo, string $mes): array {
    $pedidos = $pdo->prepare(
        "SELECT ov.numero_orden, ov.cliente, COUNT(u.id) AS unidades, SUM(u.entregado) AS entregadas
         FROM ordenes_venta ov JOIN unidades u ON u.orden_venta_id = ov.id
         WHERE DATE_FORMAT(ov.fecha_creacion, '%Y-%m') = ?
         GROUP BY ov.id ORDER BY ov.numero_orden"
    );
    $pedidos->execute([$mes]);

    $porEstacion = $pdo->prepare(
        "SELECT e.nombre AS estacion,
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

    return ['pedidos' => $pedidos->fetchAll(), 'estaciones' => $porEstacion->fetchAll(), 'totales' => $totales->fetch()];
}

function nombreMesLargo(string $ym): string {
    $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
              7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    [$y, $m] = explode('-', $ym);
    return $meses[(int)$m] . ' ' . $y;
}

$datos = datosReporte($pdo, $mes);
$tituloMes = nombreMesLargo($mes);
$nombreArchivo = 'Reporte_CCVM_' . $mes;

// =======================================================================
// WORD (.doc) — sin librerías externas: Word abre HTML guardado como .doc
// =======================================================================
if ($formato === 'word') {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.doc"');
    ?>
    <html><head><meta charset="utf-8"><style>
        body{font-family:Calibri,Arial,sans-serif;font-size:13px;color:#1c2536;}
        h1{font-size:20px;} h2{font-size:15px;margin-top:22px;color:#2f6fed;}
        table{border-collapse:collapse;width:100%;margin-top:8px;}
        th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:12px;}
        th{background:#1c2536;color:#fff;}
    </style></head><body>
        <h1>CCVM Trazabilidad — Reporte mensual</h1>
        <p><b><?= htmlspecialchars($tituloMes) ?></b></p>

        <table>
            <tr><th>Unidades creadas</th><th>Unidades entregadas</th><th>Rechazos</th><th>Clientes con pedidos</th></tr>
            <tr>
                <td><?= (int)$datos['totales']['unidades_creadas'] ?></td>
                <td><?= (int)$datos['totales']['unidades_entregadas'] ?></td>
                <td><?= (int)$datos['totales']['rechazos'] ?></td>
                <td><?= (int)$datos['totales']['clientes'] ?></td>
            </tr>
        </table>

        <h2>Pedidos del mes</h2>
        <table>
            <tr><th>N° Orden</th><th>Cliente</th><th>Unidades</th><th>Entregadas</th></tr>
            <?php foreach ($datos['pedidos'] as $p): ?>
            <tr><td><?= htmlspecialchars($p['numero_orden']) ?></td><td><?= htmlspecialchars($p['cliente']) ?></td><td><?= $p['unidades'] ?></td><td><?= $p['entregadas'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$datos['pedidos']): ?><tr><td colspan="4">Sin pedidos creados este mes.</td></tr><?php endif; ?>
        </table>

        <h2>Producción por zona/estación</h2>
        <table>
            <tr><th>Estación</th><th>Registros totales</th><th>Aprobados</th><th>Rechazados</th></tr>
            <?php foreach ($datos['estaciones'] as $e): ?>
            <tr><td><?= htmlspecialchars($e['estacion']) ?></td><td><?= $e['total'] ?></td><td><?= $e['aprobados'] ?></td><td><?= $e['rechazados'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$datos['estaciones']): ?><tr><td colspan="4">Sin movimientos registrados este mes.</td></tr><?php endif; ?>
        </table>
    </body></html>
    <?php
    exit;
}

// =======================================================================
// PDF — con FPDF (incluido en /lib/fpdf, sin composer ni internet)
// =======================================================================
if ($formato === 'pdf') {
    require __DIR__ . '/lib/fpdf/fpdf.php';

    class ReportePDF extends FPDF {
        public $tituloMes = '';
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('CCVM Trazabilidad - Reporte mensual'), 0, 1);
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 8, utf8_decode($this->tituloMes), 0, 1);
            $this->Ln(4);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
        }
        function tituloSeccion($texto) {
            $this->Ln(4);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(47, 111, 237);
            $this->Cell(0, 8, utf8_decode($texto), 0, 1);
            $this->SetTextColor(0, 0, 0);
        }
        function filaEncabezado($cols, $anchos) {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(28, 37, 54);
            $this->SetTextColor(255, 255, 255);
            foreach ($cols as $i => $c) { $this->Cell($anchos[$i], 7, utf8_decode($c), 1, 0, 'L', true); }
            $this->Ln();
            $this->SetTextColor(0, 0, 0);
        }
        function filaDatos($cols, $anchos) {
            $this->SetFont('Arial', '', 9);
            foreach ($cols as $i => $c) { $this->Cell($anchos[$i], 7, utf8_decode((string)$c), 1); }
            $this->Ln();
        }
    }

    $pdf = new ReportePDF();
    $pdf->tituloMes = $tituloMes;
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, utf8_decode(sprintf(
        'Unidades creadas: %d   |   Entregadas: %d   |   Rechazos: %d   |   Clientes: %d',
        (int)$datos['totales']['unidades_creadas'], (int)$datos['totales']['unidades_entregadas'],
        (int)$datos['totales']['rechazos'], (int)$datos['totales']['clientes']
    )), 0, 1);

    $pdf->tituloSeccion('Pedidos del mes');
    $pdf->filaEncabezado(['N Orden', 'Cliente', 'Unidades', 'Entregadas'], [35, 95, 30, 30]);
    if ($datos['pedidos']) {
        foreach ($datos['pedidos'] as $p) {
            $pdf->filaDatos([$p['numero_orden'], $p['cliente'], $p['unidades'], $p['entregadas']], [35, 95, 30, 30]);
        }
    } else {
        $pdf->filaDatos(['Sin pedidos creados este mes.', '', '', ''], [35, 95, 30, 30]);
    }

    $pdf->tituloSeccion('Produccion por zona/estacion');
    $pdf->filaEncabezado(['Estacion', 'Total', 'Aprobados', 'Rechazados'], [80, 30, 40, 40]);
    if ($datos['estaciones']) {
        foreach ($datos['estaciones'] as $e) {
            $pdf->filaDatos([$e['estacion'], $e['total'], $e['aprobados'], $e['rechazados']], [80, 30, 40, 40]);
        }
    } else {
        $pdf->filaDatos(['Sin movimientos registrados este mes.', '', '', ''], [80, 30, 40, 40]);
    }

    $pdf->Output('D', $nombreArchivo . '.pdf');
    exit;
}

http_response_code(400);
echo 'Formato no reconocido.';
