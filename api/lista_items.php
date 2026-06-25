<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['lista_id']) || !isset($data['producto_id']) || !isset($data['cantidad'])) {
            jsonError('lista_id, producto_id and cantidad are required', 400);
        }

        $precioId = isset($data['precio_id']) && intval($data['precio_id']) > 0 ? intval($data['precio_id']) : null;

        if ($precioId !== null) {
            $stmt = $db->prepare("SELECT id FROM precios WHERE id = :precio_id AND producto_id = :producto_id");
            $stmt->execute([':precio_id' => $precioId, ':producto_id' => $data['producto_id']]);
            if (!$stmt->fetch()) {
                jsonError('precio_id does not belong to producto_id', 400);
            }
        }

        $stmt = $db->prepare("
            SELECT id, cantidad FROM lista_items
            WHERE lista_id = :lista_id
              AND producto_id = :producto_id
              AND (precio_id <=> :precio_id)
        ");
        $stmt->execute([
            ':lista_id' => $data['lista_id'],
            ':producto_id' => $data['producto_id'],
            ':precio_id' => $precioId,
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newCantidad = (int)$existing['cantidad'] + intval($data['cantidad']);
            $stmt = $db->prepare("UPDATE lista_items SET cantidad = :cantidad WHERE id = :id");
            $stmt->execute([':cantidad' => $newCantidad, ':id' => $existing['id']]);
            jsonResponse(['message' => 'Quantity updated', 'id' => (int)$existing['id'], 'cantidad' => $newCantidad]);
        } else {
            $stmt = $db->prepare("INSERT INTO lista_items (lista_id, producto_id, precio_id, cantidad) VALUES (:lista_id, :producto_id, :precio_id, :cantidad)");
            $stmt->execute([
                ':lista_id' => $data['lista_id'],
                ':producto_id' => $data['producto_id'],
                ':precio_id' => $precioId,
                ':cantidad' => intval($data['cantidad']),
            ]);
            jsonResponse(['message' => 'Item added', 'id' => (int)$db->lastInsertId()], 201);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id']) || !isset($data['cantidad'])) {
            jsonError('id and cantidad are required', 400);
        }

        $stmt = $db->prepare("UPDATE lista_items SET cantidad = :cantidad WHERE id = :id");
        $stmt->execute([':cantidad' => intval($data['cantidad']), ':id' => intval($data['id'])]);

        jsonResponse(['message' => 'Quantity updated']);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id <= 0) {
            jsonError('Item ID is required', 400);
        }

        $stmt = $db->prepare("DELETE FROM lista_items WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonResponse(['message' => 'Item removed']);
        break;

    default:
        jsonError('Method not allowed', 405);
}