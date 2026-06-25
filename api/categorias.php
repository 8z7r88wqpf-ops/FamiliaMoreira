<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();
requireMethod('GET');

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
$result = $stmt->fetchAll();
$categorias = array_column($result, 'categoria');

jsonResponse($categorias);