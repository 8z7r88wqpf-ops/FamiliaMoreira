<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    jsonError('Invalid JSON body', 400);
}

$rows = $data['productos'] ?? $data['items'] ?? $data;
if (!is_array($rows) || (array_is_list($rows) === false && isset($rows['nome']))) {
    $rows = [$rows];
}

$summary = ['created' => 0, 'updated' => 0, 'errors' => []];

$db->beginTransaction();
try {
    foreach ($rows as $index => $row) {
        try {
            if (!is_array($row)) {
                throw new InvalidArgumentException('row must be an object');
            }

            $supermercado = $row['supermercado'] ?? $row['mercado'] ?? '';
            $supermercadoId = getOrCreateSupermercado($db, $supermercado);
            $productoId = getOrCreateProducto($db, $row);
            $result = upsertPrecio($db, $productoId, $supermercadoId, $row);
            $summary[$result['action']]++;
        } catch (Exception $e) {
            $summary['errors'][] = ['row' => $index + 1, 'error' => $e->getMessage()];
        }
    }

    if (count($rows) > 0 && count($summary['errors']) === count($rows)) {
        throw new RuntimeException('No rows imported');
    }

    $db->commit();
    jsonResponse($summary);
} catch (Exception $e) {
    $db->rollBack();
    jsonError($e->getMessage(), 400);
}