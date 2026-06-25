<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();
requireMethod('GET');

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT * FROM supermercados ORDER BY nome ASC");
jsonResponse($stmt->fetchAll());