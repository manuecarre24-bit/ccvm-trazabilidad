<?php require 'config.php';

if (usuarioAutenticado()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if ($pin === '') {
        $error = 'Ingresa tu código.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.rol, u.estacion_id, e.nombre AS estacion_nombre
             FROM usuarios u
             JOIN estaciones e ON e.id = u.estacion_id
             WHERE u.pin = ? AND u.activo = 1'
        );
        $stmt->execute([$pin]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $_SESSION['usuario_id']       = $usuario['id'];
            $_SESSION['usuario_nombre']   = $usuario['nombre'];
            $_SESSION['usuario_rol']      = $usuario['rol'];
            $_SESSION['estacion_id']      = $usuario['estacion_id'];
            $_SESSION['estacion_nombre']  = $usuario['estacion_nombre'];

            // Jefe de planta, gerencia y programador van directo al panel; operarios, a escanear
            $destino = in_array($usuario['rol'], ROLES_DASHBOARD, true)
                ? 'panel.php'
                : 'escanear.php';

            header("Location: $destino");
            exit;
        } else {
            $error = 'Código incorrecto o usuario inactivo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCVM - Ingreso</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pantalla-login">
    <div class="login-box">
        <h1>CCVM Trazabilidad</h1>
        <p class="sub">Ingresa tu código para iniciar tu turno</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="password" name="pin" placeholder="••••" inputmode="numeric" maxlength="10" autofocus required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
