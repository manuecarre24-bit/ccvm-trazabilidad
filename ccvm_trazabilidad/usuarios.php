<?php require 'config.php';
requerirRol(ROLES_USUARIOS);

$error = '';
$ok    = isset($_GET['ok']);

// ---------------------------------------------------------------------
// Guardar (crear o editar)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $pin         = trim($_POST['pin'] ?? '');
    $estacionId  = (int)($_POST['estacion_id'] ?? 0);
    $rol         = $_POST['rol'] ?? 'operario';
    $activo      = isset($_POST['activo']) ? 1 : 0;

    $rolesValidos = ['operario', 'jefe_planta', 'gerencia', 'programador'];

    if ($nombre === '' || $pin === '' || $estacionId <= 0 || !in_array($rol, $rolesValidos, true)) {
        $error = 'Completa todos los campos correctamente.';
    } else {
        // Verifica que el PIN no esté usado por otro usuario
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE pin = ? AND id != ?');
        $chk->execute([$pin, $id]);
        if ($chk->fetch()) {
            $error = "El código \"$pin\" ya lo tiene otro usuario. Elige otro.";
        } else {
            if ($id > 0) {
                $upd = $pdo->prepare(
                    'UPDATE usuarios SET nombre=?, pin=?, estacion_id=?, rol=?, activo=? WHERE id=?'
                );
                $upd->execute([$nombre, $pin, $estacionId, $rol, $activo, $id]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO usuarios (nombre, pin, estacion_id, rol, activo) VALUES (?,?,?,?,?)'
                );
                $ins->execute([$nombre, $pin, $estacionId, $rol, $activo]);
            }
            header('Location: usuarios.php?ok=1');
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// Usuario a editar (si se pidió por GET)
// ---------------------------------------------------------------------
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}

$estaciones = $pdo->query('SELECT id, nombre FROM estaciones ORDER BY linea, orden')->fetchAll();
$usuarios = $pdo->query(
    'SELECT u.*, e.nombre AS estacion_nombre FROM usuarios u
     JOIN estaciones e ON e.id = u.estacion_id
     ORDER BY u.rol, u.pin'
)->fetchAll();

$etiquetasRol = [
    'operario'    => 'Operario',
    'jefe_planta' => 'Jefe de planta',
    'gerencia'    => 'Gerencia',
    'programador' => 'Programador',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Usuarios</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-dashboard">
    <div class="topbar">
        <div class="marca">CCVM <span><?= htmlspecialchars($_SESSION['usuario_nombre']) ?> · Usuarios</span></div>
        <div>
            <a class="salir" href="panel.php" style="margin-right:10px;">Panel</a>
            <a class="salir" href="logout.php">Salir</a>
        </div>
    </div>

    <div class="contenedor-dashboard">

        <?php if ($ok): ?><div class="aviso-ok">✔ Usuario guardado.</div><?php endif; ?>
        <?php if ($error): ?><div class="aviso-error">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <h3 class="titulo-seccion"><?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
        <form method="post" class="caja-lote fila-form">
            <input type="hidden" name="id" value="<?= $editando['id'] ?? '' ?>">
            <div>
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>" required>
            </div>
            <div>
                <label>Código (PIN)</label>
                <input type="text" name="pin" value="<?= htmlspecialchars($editando['pin'] ?? '') ?>" required>
            </div>
            <div>
                <label>Estación</label>
                <select name="estacion_id" required>
                    <?php foreach ($estaciones as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= ($editando['estacion_id'] ?? null) == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Rol</label>
                <select name="rol" required>
                    <?php foreach ($etiquetasRol as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($editando['rol'] ?? 'operario') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo-activo">
                <label><input type="checkbox" name="activo" <?= (!isset($editando) || $editando['activo']) ? 'checked' : '' ?>> Activo</label>
            </div>
            <div class="acciones-lote">
                <button type="submit" class="btn-guardar-lote"><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
                <?php if ($editando): ?><a href="usuarios.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </div>
        </form>

        <h3 class="titulo-seccion">Usuarios del sistema</h3>
        <div class="tabla-scroll">
        <table class="tabla-ordenes">
            <thead><tr><th>PIN</th><th>Nombre</th><th>Estación</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['pin']) ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['estacion_nombre']) ?></td>
                    <td><?= $etiquetasRol[$u['rol']] ?? $u['rol'] ?></td>
                    <td><?= $u['activo'] ? '<span class="pill pill-aprobado">Sí</span>' : '<span class="pill pill-rechazado">No</span>' ?></td>
                    <td><a href="usuarios.php?editar=<?= $u['id'] ?>">Editar</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
