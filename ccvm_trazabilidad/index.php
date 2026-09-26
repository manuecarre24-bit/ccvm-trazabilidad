<?php require 'config.php';

if (!usuarioAutenticado()) {
    header('Location: login.php');
    exit;
}

$destino = in_array($_SESSION['usuario_rol'], ROLES_DASHBOARD, true)
    ? 'panel.php'
    : 'escanear.php';

header("Location: $destino");
exit;
