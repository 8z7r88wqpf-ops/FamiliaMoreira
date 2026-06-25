<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

$csv = file_get_contents('php://input');
if (trim($csv) === '') {
    jsonError('CSV body is required', 400);
}

$handle = fopen('php://temp', 'r+');
fwrite($handle, $csv);
rewind($handle);

$headers = fgetcsv($handle, 0, ',');
if (!$headers || count($headers) < 4) {
    jsonError('CSV needs headers: nome,marca,categoria,supermercado,precio,url', 400);
}

$headers = array_map(fn($header) => strtolower(trim($header)), $headers);

$summary = ['created' => 0, 'updated' => 0, 'errors' => []];
$rowNumber = 1;

$db->beginTransaction();
try {
    while (($values = fgetcsv($handle, 0, ',')) !== false) {
        $rowNumber++;
        $row = array_combine($headers, array_pad($values, count($headers), null));
        try {
            $supermercadoId = getOrCreateSupermercado($db, $row['supermercado'] ?? '');
            $productoId = getOrCreateProducto($db, $row);
            $result = upsertPrecio($db, $productoId, $supermercadoId, $row);
            $summary[$result['action']]++;
        } catch (Exception $e) {
            $summary['errors'][] = ['row' => $rowNumber, 'error' => $e->getMessage()];
        }
    }

    $db->commit();
    jsonResponse($summary);
} catch (Exception $e) {
    $db->rollBack();
    jsonError($e->getMessage(), 400);
}