<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
$db = (new Database())->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $id = (int)($_GET['id'] ?? 0);
        $search = $_GET['search'] ?? '';
        $comStock = $_GET['com_stock'] ?? '';

        if ($id) {
            $stmt = $db->prepare("SELECT * FROM receitas WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $receita = $stmt->fetch();
            if (!$receita) jsonError('Receita não encontrada', 404);

            $stmt = $db->prepare("SELECT ri.*, p.nome as produto_nome, p.marca FROM receita_ingredientes ri JOIN productos p ON p.id = ri.producto_id WHERE ri.receita_id = :rid");
            $stmt->execute([':rid' => $id]);
            $receita['ingredientes'] = $stmt->fetchAll();

            // Verificar stock
            $stockOk = 0;
            foreach ($receita['ingredientes'] as &$ing) {
                $st = $db->prepare("SELECT SUM(quantidade) as total FROM stock_cozinha WHERE producto_id = :pid");
                $st->execute([':pid' => $ing['producto_id']]);
                $stock = $st->fetch();
                $ing['tem_stock'] = ($stock && $stock['total'] >= $ing['quantidade']);
                if ($ing['tem_stock']) $stockOk++;
            }
            $receita['stock_percent'] = count($receita['ingredientes']) > 0 ? round(($stockOk / count($receita['ingredientes'])) * 100) : 0;
            jsonResponse($receita);
        }

        $sql = "SELECT r.*, (SELECT COUNT(*) FROM receita_ingredientes WHERE receita_id = r.id) as num_ingredientes FROM receitas r WHERE 1=1";
        $params = [];

        if ($search) {
            $sql .= " AND (r.nome LIKE :search OR r.descricao LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $sql .= " ORDER BY r.nome ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $receitas = $stmt->fetchAll();

        // Se for para filtrar por stock
        if ($comStock === 'sim') {
            $receitas = array_filter($receitas, function($r) use ($db) {
                $st = $db->prepare("SELECT COUNT(*) as total FROM receita_ingredientes ri JOIN stock_cozinha s ON s.producto_id = ri.producto_id AND s.quantidade >= ri.quantidade WHERE ri.receita_id = :rid");
                $st->execute([':rid' => $r['id']]);
                $count = $st->fetch();
                return $count && $count['total'] > 0;
            });
            $receitas = array_values($receitas);
        }

        jsonResponse($receitas);
        break;

    case 'POST':
        // Criar receita ou sugerir
        $action = $data['action'] ?? 'criar';

        if ($action === 'sugerir') {
            // Sugerir receitas baseadas no stock
            $stmt = $db->query("
                SELECT p.id, p.nome, p.categoria, SUM(s.quantidade) as stock_total
                FROM stock_cozinha s
                JOIN productos p ON p.id = s.producto_id
                GROUP BY p.id, p.nome, p.categoria
                HAVING stock_total > 0
                ORDER BY RAND()
                LIMIT 10
            ");
            $stockItems = $stmt->fetchAll();

            // Buscar receitas que usam esses produtos
            $ids = array_column($stockItems, 'id');
            if (empty($ids)) jsonResponse([]);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                SELECT DISTINCT r.*, COUNT(ri.id) as match_count
                FROM receitas r
                JOIN receita_ingredientes ri ON ri.receita_id = r.id
                WHERE ri.producto_id IN ($placeholders)
                GROUP BY r.id
                ORDER BY match_count DESC
                LIMIT 10
            ");
            $stmt->execute($ids);
            jsonResponse($stmt->fetchAll());
        }

        // Criar receita
        $nome = $data['nome'] ?? '';
        $descricao = $data['descricao'] ?? '';
        $tempoPreparo = (int)($data['tempo_preparo'] ?? 0);
        $tempoCozimento = (int)($data['tempo_cozimento'] ?? 0);
        $porcoes = (int)($data['porcoes'] ?? 4);
        $instrucoes = $data['instrucoes'] ?? '';
        $ingredientes = $data['ingredientes'] ?? [];

        if (!$nome) jsonError('Nome é obrigatório', 400);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO receitas (nome, descricao, tempo_preparo, tempo_cozimento, porcoes, instrucoes) VALUES (:nome, :desc, :tp, :tc, :por, :inst)");
            $stmt->execute([':nome' => $nome, ':desc' => $descricao, ':tp' => $tempoPreparo, ':tc' => $tempoCozimento, ':por' => $porcoes, ':inst' => $instrucoes]);
            $id = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO receita_ingredientes (receita_id, producto_id, quantidade, unidade, opcional) VALUES (:rid, :pid, :qtd, :un, :opt)");
            foreach ($ingredientes as $ing) {
                $stmt->execute([':rid' => $id, ':pid' => (int)$ing['producto_id'], ':qtd' => (float)($ing['quantidade'] ?? 1), ':un' => $ing['unidade'] ?? 'un', ':opt' => (int)($ing['opcional'] ?? 0)]);
            }

            $db->commit();
            jsonResponse(['id' => $id, 'action' => 'created']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError($e->getMessage(), 400);
        }
        break;

    case 'DELETE':
        $id = (int)$_GET['id'] ?? 0;
        $stmt = $db->prepare("DELETE FROM receitas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        jsonResponse(['success' => true]);
        break;
}