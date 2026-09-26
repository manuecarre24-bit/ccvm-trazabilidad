<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

$totalActivas = $pdo->query("SELECT COUNT(*) FROM unidades WHERE entregado = 0")->fetchColumn();
$clientesEnEspera = $pdo->query(
    "SELECT COUNT(DISTINCT ov.cliente) FROM ordenes_venta ov
     JOIN unidades u ON u.orden_venta_id = ov.id WHERE u.entregado = 0"
)->fetchColumn();
$rechazosMes = $pdo->query(
    "SELECT COUNT(*) FROM escaneos WHERE resultado='rechazado' AND MONTH(fecha_hora)=MONTH(CURDATE()) AND YEAR(fecha_hora)=YEAR(CURDATE())"
)->fetchColumn();
$garantiasActivas = $pdo->query("SELECT COUNT(*) FROM casos_garantia WHERE estado != 'cerrado'")->fetchColumn();

$tarjetas = [
    ['icono' => '🗺', 'titulo' => 'Mapa de seguimiento', 'desc' => 'Dónde está cada cuba ahora mismo, por estación', 'url' => 'dashboard.php', 'roles' => ROLES_DASHBOARD],
    ['icono' => '📊', 'titulo' => 'Reporte mensual', 'desc' => 'Ventas, pedidos y producción por zona — exportable a Word o PDF', 'url' => 'reportes.php', 'roles' => ROLES_DASHBOARD],
    ['icono' => '🛠', 'titulo' => 'Garantías y reparaciones', 'desc' => 'Casos en llegada, diagnóstico, producción o cierre', 'url' => 'garantias.php', 'roles' => ROLES_DASHBOARD],
    ['icono' => '📦', 'titulo' => 'Cargar lote', 'desc' => 'Nuevo contrato o rangos de series', 'url' => 'lote.php', 'roles' => ROLES_LOTE],
    ['icono' => '🔍', 'titulo' => 'Buscar cuba', 'desc' => 'Ficha e historial completo por código', 'url' => 'dashboard.php#buscar', 'roles' => ROLES_DASHBOARD],
    ['icono' => '👥', 'titulo' => 'Usuarios', 'desc' => 'Códigos, estaciones y roles', 'url' => 'usuarios.php', 'roles' => ROLES_USUARIOS],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Panel</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span></div>
        <div>
            <?php if ($_SESSION['usuario_rol'] === 'programador'): ?>
                <a class="salir" href="escanear.php" style="margin-right:10px;">Ir a escanear</a>
            <?php endif; ?>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <div class="kpis">
            <div class="kpi">
                <span class="kpi-valor"><?= (int)$totalActivas ?></span>
                <span class="kpi-etiqueta">Unidades en proceso</span>
            </div>
            <div class="kpi">
                <span class="kpi-valor"><?= (int)$clientesEnEspera ?></span>
                <span class="kpi-etiqueta">Clientes con unidades a la espera</span>
            </div>
            <div class="kpi kpi-alerta">
                <span class="kpi-valor"><?= (int)$rechazosMes ?></span>
                <span class="kpi-etiqueta">Rechazos este mes</span>
            </div>
            <div class="kpi kpi-warning">
                <span class="kpi-valor"><?= (int)$garantiasActivas ?></span>
                <span class="kpi-etiqueta">Garantías/reparaciones activas</span>
            </div>
        </div>

        <div class="grid-menu">
            <?php foreach ($tarjetas as $t): if (!in_array($_SESSION['usuario_rol'], $t['roles'], true)) continue; ?>
            <a href="<?= $t['url'] ?>" class="tarjeta-menu">
                <span class="tarjeta-menu-icono"><?= $t['icono'] ?></span>
                <span class="tarjeta-menu-titulo"><?= htmlspecialchars($t['titulo']) ?></span>
                <span class="tarjeta-menu-desc"><?= htmlspecialchars($t['desc']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
