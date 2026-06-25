<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();
requireMethod('GET');

$database = new Database();
$db = $database->getConnection();

$producto_id = isset($_GET['producto_id']) ? intval($_GET['producto_id']) : 0;

if ($producto_id <= 0) {
    jsonError('producto_id is required', 400);
}

$sql = "SELECT p.id, p.precio, p.url, p.fecha_actualizacion, 
               s.id as supermercado_id, s.nome as supermercado_nome
        FROM precios p
        JOIN supermercados s ON p.supermercado_id = s.id
        WHERE p.producto_id = :producto_id
        ORDER BY p.precio ASC";

$stmt = $db->prepare($sql);
$stmt->execute([':producto_id' => $producto_id]);
$result = $stmt->fetchAll();

jsonResponse($result);