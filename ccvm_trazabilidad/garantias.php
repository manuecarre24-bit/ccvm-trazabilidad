<?php require 'config.php';
requerirRol(ROLES_DASHBOARD);

$casos = $pdo->query(
    "SELECT c.*, u.nombre AS creado_por_nombre
     FROM casos_garantia c JOIN usuarios u ON u.id = c.creado_por
     ORDER BY FIELD(c.estado,'llegada','desencube','diagnostico','en_produccion','cerrado'), c.fecha_llegada DESC"
)->fetchAll();

$etiquetasEstado = [
    'llegada'       => ['Llegada', 'amarillo'],
    'desencube'     => ['Desencube', 'amarillo'],
    'diagnostico'   => ['Diagnóstico', 'amarillo'],
    'en_produccion' => ['En producción', 'azul'],
    'cerrado'       => ['Cerrado', 'verde'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Garantías y reparaciones</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Garantías y reparaciones</span></div>
        <div>
            <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <?php if (in_array($_SESSION['usuario_rol'], ROLES_GARANTIAS, true)): ?>
        <div style="margin-bottom:16px;">
            <a href="garantia_nueva.php" class="btn-guardar-lote" style="text-decoration:none;display:inline-block;">+ Nuevo caso</a>
        </div>
        <?php endif; ?>

        <div class="tabla-scroll">
        <table class="tabla-ordenes">
            <thead><tr><th>Código</th><th>Cliente</th><th>Tipo</th><th>Marca</th><th>Estado</th><th>Llegada</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($casos as $c): [$label, $color] = $etiquetasEstado[$c['estado']]; ?>
                <tr>
                    <td><b><?= htmlspecialchars($c['codigo']) ?></b></td>
                    <td><?= htmlspecialchars($c['cliente']) ?></td>
                    <td><?= $c['tipo'] === 'garantia' ? 'Garantía' : 'Reparación' ?></td>
                    <td><?= $c['marca_propia'] ? 'Propia (CCVM)' : htmlspecialchars($c['marca_nombre'] ?: 'Otra marca') ?></td>
                    <td><span class="pill pill-<?= $color === 'verde' ? 'aprobado' : ($color === 'azul' ? 'pendiente-azul' : 'pendiente') ?>"><?= $label ?></span></td>
                    <td><?= date('d/m/Y', strtotime($c['fecha_llegada'])) ?></td>
                    <td><a href="garantia_detalle.php?id=<?= $c['id'] ?>">Ver</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$casos): ?><tr><td colspan="7" class="vacio">Todavía no hay casos de garantía o reparación registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
