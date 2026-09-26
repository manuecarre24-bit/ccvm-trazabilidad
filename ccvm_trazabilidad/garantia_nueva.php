<?php require 'config.php';
requerirRol(ROLES_GARANTIAS);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Nuevo caso de garantía/reparación</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Nuevo caso</span></div>
        <div>
            <a class="salir" href="garantias.php" style="margin-right:10px;">Garantías</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">
        <?php if ($error): ?><div class="aviso-error">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <h3 class="titulo-seccion">Paso 1 · Llegada del producto</h3>
        <p class="subtitulo-seccion">Registra los datos de la cuba tal como llega y una foto del estado en que llegó.</p>

        <form method="post" action="garantia_accion.php" enctype="multipart/form-data" class="caja-lote fila-form">
            <input type="hidden" name="accion" value="crear">
            <div>
                <label>Código / serial de la cuba</label>
                <input type="text" name="codigo" required>
            </div>
            <div>
                <label>Cliente</label>
                <input type="text" name="cliente" required>
            </div>
            <div>
                <label>Tipo de caso</label>
                <select name="tipo" required>
                    <option value="garantia">Garantía</option>
                    <option value="reparacion">Reparación</option>
                </select>
            </div>
            <div>
                <label>¿Es marca propia (CCVM)?</label>
                <select name="marca_propia" id="marca_propia" onchange="document.getElementById('campo-marca-nombre').style.display = this.value === '0' ? 'block' : 'none'">
                    <option value="1">Sí, propia</option>
                    <option value="0">No, otra marca</option>
                </select>
            </div>
            <div id="campo-marca-nombre" style="display:none;">
                <label>Nombre de la otra marca</label>
                <input type="text" name="marca_nombre" placeholder="Ej: Siemens, ABB...">
            </div>
            <div>
                <label>kVA</label>
                <input type="number" step="0.01" name="kva" required>
            </div>
            <div>
                <label>Fase</label>
                <select name="fases" required>
                    <option value="trifasico">Trifásico</option>
                    <option value="monofasico">Monofásico</option>
                </select>
            </div>
            <div>
                <label>Tipo de aceite</label>
                <select name="tipo_aceite" required>
                    <option value="mineral">Mineral</option>
                    <option value="vegetal">Vegetal</option>
                </select>
            </div>
            <div style="grid-column:1/-1;">
                <label>Descripción del estado de llegada</label>
                <textarea name="descripcion" rows="3" style="width:100%;padding:10px;border:2px solid #dde3ee;border-radius:8px;font-size:14px;" required></textarea>
            </div>
            <div style="grid-column:1/-1;">
                <label>Foto del estado de llegada</label>
                <input type="file" name="foto" accept="image/*" capture="environment">
            </div>
            <div class="acciones-lote">
                <button type="submit" class="btn-guardar-lote">Registrar llegada</button>
            </div>
        </form>
    </div>
</body>
</html>
