<?php require 'config.php';
requerirRol(ROLES_LOTE);

$ordenes = $pdo->query(
    "SELECT ov.id, ov.numero_orden, ov.cliente, ov.fecha_entrega, COUNT(u.id) AS unidades
     FROM ordenes_venta ov LEFT JOIN unidades u ON u.orden_venta_id = ov.id
     GROUP BY ov.id ORDER BY ov.id DESC LIMIT 15"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Cargar lote</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Cargar lote</span></div>
        <div>
            <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <h3 class="titulo-seccion">Órdenes recientes</h3>
        <div class="tabla-scroll">
        <table class="tabla-ordenes">
            <thead><tr><th>N° Orden</th><th>Cliente</th><th>Entrega general</th><th>Unidades cargadas</th></tr></thead>
            <tbody>
                <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['numero_orden']) ?></td>
                    <td><?= htmlspecialchars($o['cliente']) ?></td>
                    <td><?= date('d/m/Y', strtotime($o['fecha_entrega'])) ?></td>
                    <td><?= $o['unidades'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$ordenes): ?>
                <tr><td colspan="4" class="vacio">Todavía no hay órdenes cargadas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <h3 class="titulo-seccion">Nuevo lote / agregar unidades a una orden</h3>
        <p class="subtitulo-seccion">
            Si el N° de orden ya existe, se le agregan las unidades a esa misma orden. Si es nuevo, se crea.
            Puedes agregar varios rangos con distinto kVA, fase o fecha de entrega (para envíos parciales).
        </p>

        <form id="form-lote" class="caja-lote">
            <div class="fila-form">
                <div>
                    <label>N° de orden</label>
                    <input type="text" name="numero_orden" placeholder="OV-0005" required>
                </div>
                <div>
                    <label>Cliente</label>
                    <input type="text" name="cliente" placeholder="Nombre del cliente" required>
                </div>
                <div>
                    <label>Fecha de entrega general</label>
                    <input type="date" name="fecha_entrega" required>
                </div>
            </div>

            <h4>Rangos de series</h4>
            <div id="rangos"></div>
            <button type="button" class="btn-agregar-rango" onclick="agregarRango()">+ Agregar rango</button>

            <div class="acciones-lote">
                <button type="submit" class="btn-guardar-lote">Guardar lote</button>
            </div>
        </form>

        <div id="resultado-lote" class="resultado-lote"></div>
    </div>

<template id="plantilla-rango">
    <div class="rango-fila">
        <div>
            <label>Prefijo (opcional)</label>
            <input type="text" class="r-prefijo" placeholder="ej: CCVM-">
        </div>
        <div>
            <label>Desde</label>
            <input type="number" class="r-desde" required>
        </div>
        <div>
            <label>Hasta</label>
            <input type="number" class="r-hasta" required>
        </div>
        <div>
            <label>kVA</label>
            <input type="number" step="0.01" class="r-kva" required>
        </div>
        <div>
            <label>Fase</label>
            <select class="r-fases">
                <option value="trifasico">Trifásico</option>
                <option value="monofasico">Monofásico</option>
            </select>
        </div>
        <div>
            <label>Aceite</label>
            <select class="r-aceite">
                <option value="mineral">Mineral</option>
                <option value="vegetal">Vegetal</option>
            </select>
        </div>
        <div>
            <label>Tipo (opcional)</label>
            <input type="text" class="r-tipo" placeholder="Pedestal, convencional...">
        </div>
        <div>
            <label>Entrega distinta (opcional)</label>
            <input type="date" class="r-fecha">
        </div>
        <button type="button" class="btn-quitar-rango" onclick="this.closest('.rango-fila').remove()">✕</button>
    </div>
</template>

<script>
function agregarRango() {
    const tpl = document.getElementById('plantilla-rango').content.cloneNode(true);
    document.getElementById('rangos').appendChild(tpl);
}
agregarRango(); // empieza con un rango visible

document.getElementById('form-lote').addEventListener('submit', async function (e) {
    e.preventDefault();
    const f = e.target;

    const rangos = [...document.querySelectorAll('.rango-fila')].map(fila => ({
        prefijo: fila.querySelector('.r-prefijo').value.trim(),
        desde: fila.querySelector('.r-desde').value,
        hasta: fila.querySelector('.r-hasta').value,
        kva: fila.querySelector('.r-kva').value,
        fases: fila.querySelector('.r-fases').value,
        tipo_aceite: fila.querySelector('.r-aceite').value,
        tipo_transformador: fila.querySelector('.r-tipo').value.trim(),
        fecha_entrega: fila.querySelector('.r-fecha').value,
    }));

    if (!rangos.length) {
        alert('Agrega al menos un rango de series.');
        return;
    }

    const payload = {
        numero_orden: f.numero_orden.value.trim(),
        cliente: f.cliente.value.trim(),
        fecha_entrega: f.fecha_entrega.value,
        rangos
    };

    const caja = document.getElementById('resultado-lote');
    caja.innerHTML = 'Guardando...';

    try {
        const resp = await fetch('lote_guardar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();

        if (data.ok) {
            caja.innerHTML = `<div class="aviso-ok">✔ Listo: ${data.creadas} unidades creadas` +
                (data.repetidas > 0 ? `, ${data.repetidas} ya existían y se omitieron` : '') + `.</div>`;
        } else {
            caja.innerHTML = `<div class="aviso-error">✖ ${data.mensaje}</div>`;
        }
    } catch (err) {
        caja.innerHTML = '<div class="aviso-error">✖ No se pudo conectar con el servidor.</div>';
    }
});
</script>
</body>
</html>
