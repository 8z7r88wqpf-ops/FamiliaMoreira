<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $db->query("SELECT * FROM listas_compra ORDER BY id DESC");
        $listas = $stmt->fetchAll();

        foreach ($listas as &$lista) {
            $stmt = $db->prepare("
                SELECT li.id, li.cantidad, li.precio_id as selected_precio_id,
                       p.id as producto_id, p.nome as producto_nome, p.marca, p.categoria,
                       pr.id as precio_id, pr.precio, pr.supermercado_id, s.nome as supermercado_nome
                FROM lista_items li
                JOIN productos p ON li.producto_id = p.id
                LEFT JOIN precios pr ON pr.producto_id = p.id
                LEFT JOIN supermercados s ON pr.supermercado_id = s.id
                WHERE li.lista_id = :lista_id
                ORDER BY p.nome, pr.precio ASC
            ");
            $stmt->execute([':lista_id' => $lista['id']]);
            $items = $stmt->fetchAll();

            $grouped = [];
            foreach ($items as $item) {
                $key = $item['id'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => (int)$item['id'],
                        'producto_id' => (int)$item['producto_id'],
                        'producto_nome' => $item['producto_nome'],
                        'marca' => $item['marca'],
                        'categoria' => $item['categoria'],
                        'cantidad' => (int)$item['cantidad'],
                        'selected_precio_id' => $item['selected_precio_id'] ? (int)$item['selected_precio_id'] : null,
                        'selected_precio' => null,
                        'precios' => [],
                    ];
                }
                if ($item['precio'] !== null) {
                    $priceData = [
                        'precio_id' => (int)$item['precio_id'],
                        'precio' => (float)$item['precio'],
                        'supermercado_id' => (int)$item['supermercado_id'],
                        'supermercado_nome' => $item['supermercado_nome'],
                    ];
                    $grouped[$key]['precios'][] = $priceData;

                    if ($item['selected_precio_id'] !== null && (int)$item['selected_precio_id'] === (int)$item['precio_id']) {
                        $grouped[$key]['selected_precio'] = $priceData;
                    }
                }
            }

            foreach ($grouped as &$groupedItem) {
                if ($groupedItem['selected_precio'] === null && count($groupedItem['precios']) > 0) {
                    $groupedItem['selected_precio'] = $groupedItem['precios'][0];
                    $groupedItem['selected_precio_id'] = $groupedItem['precios'][0]['precio_id'];
                }
            }
            unset($groupedItem);

            $lista['items'] = array_values($grouped);

            $totals = [];
            foreach ($lista['items'] as $item) {
                if ($item['selected_precio'] !== null) {
                    $itemTotal = (float)$item['selected_precio']['precio'] * (int)$item['cantidad'];
                    $supermercado = $item['selected_precio']['supermercado_nome'];
                    $totals[$supermercado] = ($totals[$supermercado] ?? 0) + $itemTotal;
                }
            }
            foreach ($totals as $supermercado => $total) {
                $totals[$supermercado] = round($total, 2);
            }

            $lista['totals_by_supermarket'] = $totals;
            $lista['total_geral'] = round(array_sum($totals), 2);
            $lista['items_count'] = array_sum(array_map(fn($item) => (int)$item['cantidad'], $lista['items']));
        }

        jsonResponse($listas);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['nombre']) || empty(trim($data['nombre'] ?? ''))) {
            jsonError('List name is required', 400);
        }

        $stmt = $db->prepare("INSERT INTO listas_compra (nombre) VALUES (:nombre)");
        $stmt->execute([':nombre' => trim($data['nombre'])]);

        jsonResponse(['id' => (int)$db->lastInsertId(), 'nombre' => trim($data['nombre'])], 201);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id <= 0) {
            jsonError('List ID is required', 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM lista_items WHERE lista_id = :id");
            $stmt->execute([':id' => $id]);

            $stmt = $db->prepare("DELETE FROM listas_compra WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $db->commit();
            jsonResponse(['message' => 'List deleted successfully']);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Delete list error: ' . $e->getMessage());
            jsonError('Failed to delete list', 500);
        }
        break;

    default:
        jsonError('Method not allowed', 405);
}