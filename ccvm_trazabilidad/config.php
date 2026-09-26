<?php
// =====================================================================
// Conexión a la base de datos - CCVM Trazabilidad
// Ajusta estos datos si tu XAMPP usa otro usuario/clave de MySQL.
// =====================================================================

$DB_HOST = 'localhost';
$DB_NAME = 'ccvm_trazabilidad';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage() .
        '<br><br>Verifica que ejecutaste los scripts de /sql en este orden: ' .
        '01_ccvm_trazabilidad.sql → 02_seed_usuario.sql → 03_seed_usuarios_estaciones.sql → 04_seed_datos_demo.sql');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuarioAutenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function requerirLogin(): void
{
    if (!usuarioAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exige que el usuario tenga uno de los roles permitidos.
 * Si no, lo manda a la pantalla que sí le corresponde.
 */
function requerirRol(array $rolesPermitidos): void
{
    requerirLogin();
    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos, true)) {
        $destino = in_array($_SESSION['usuario_rol'], ROLES_DASHBOARD, true)
            ? 'panel.php'
            : 'escanear.php';
        header("Location: $destino");
        exit;
    }
}

/** Roles que pueden ver el panel/dashboard (consulta) */
const ROLES_DASHBOARD = ['jefe_planta', 'gerencia', 'programador'];

/** Roles que pueden cargar lotes/rangos de unidades nuevas */
const ROLES_LOTE = ['jefe_planta', 'programador'];

/** Único rol que puede administrar usuarios y códigos */
const ROLES_USUARIOS = ['programador'];

/** Roles que pueden gestionar casos de garantía/reparación (avanzar etapas) */
const ROLES_GARANTIAS = ['jefe_planta', 'programador'];
