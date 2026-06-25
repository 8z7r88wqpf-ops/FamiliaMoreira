<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
$db = (new Database())->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $search = $_GET['search'] ?? '';
        $localizacao = $_GET['localizacao'] ?? '';
        $expirados = $_GET['expirados'] ?? '';

        $sql = "SELECT s.*, p.nome as produto_nome, p.marca, p.categoria 
                FROM stock_cozinha s JOIN productos p ON p.id = s.producto_id WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (p.nome LIKE :search OR p.marca LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($localizacao) {
            $sql .= " AND s.localizacao = :loc";
            $params[':loc'] = $localizacao;
        }
        if ($expirados === 'sim') {
            $sql .= " AND s.data_validade IS NOT NULL AND s.data_validade <= CURDATE()";
        }
        $sql .= " ORDER BY s.data_validade ASC, p.nome ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
        break;

    case 'POST':
        $db->beginTransaction();
        try {
            $productoId = (int)($data['producto_id'] ?? 0);
            $qtd = (float)($data['quantidade'] ?? 1);
            $unidade = $data['unidade'] ?? 'un';
            $validade = $data['data_validade'] ?? null;
            $local = $data['localizacao'] ?? 'Dispensa';
            $notas = $data['notas'] ?? '';

            // Verificar se já existe
            $stmt = $db->prepare("SELECT id, quantidade FROM stock_cozinha WHERE producto_id = :pid AND localizacao = :loc");
            $stmt->execute([':pid' => $productoId, ':loc' => $local]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("UPDATE stock_cozinha SET quantidade = quantidade + :qtd, data_validade = COALESCE(:val, data_validade), notas = :notas WHERE id = :id");
                $stmt->execute([':qtd' => $qtd, ':val' => $validade, ':notas' => $notas, ':id' => $existing['id']]);
                $id = $existing['id'];
                $action = 'updated';
            } else {
                $stmt = $db->prepare("INSERT INTO stock_cozinha (producto_id, quantidade, unidade, data_validade, localizacao, notas) VALUES (:pid, :qtd, :un, :val, :loc, :notas)");
                $stmt->execute([':pid' => $productoId, ':qtd' => $qtd, ':un' => $unidade, ':val' => $validade, ':loc' => $local, ':notas' => $notas]);
                $id = (int)$db->lastInsertId();
                $action = 'created';
            }

            $db->commit();
            jsonResponse(['id' => $id, 'action' => $action]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError($e->getMessage(), 400);
        }
        break;

    case 'PUT':
        $id = (int)$_GET['id'] ?? 0;
        $qtd = (float)($data['quantidade'] ?? 0);
        $stmt = $db->prepare("UPDATE stock_cozinha SET quantidade = :qtd WHERE id = :id");
        $stmt->execute([':qtd' => $qtd, ':id' => $id]);
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        $id = (int)$_GET['id'] ?? 0;
        $stmt = $db->prepare("DELETE FROM stock_cozinha WHERE id = :id");
        $stmt->execute([':id' => $id]);
        jsonResponse(['success' => true]);
        break;
}