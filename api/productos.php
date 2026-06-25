<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

        // Construir búsqueda más robusta: dividir en palabras y buscar cada palabra
        // en `nome`, `marca` y `descripcion` usando collation insensible (utf8mb4_unicode_ci).
        $sql = "SELECT * FROM productos WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            // Normalizar espacios y dividir en palabras
            $words = preg_split('/\s+/', $search);
            $wordConds = [];
            foreach ($words as $i => $w) {
                $key = ':w' . $i;
                // Buscar la palabra en varias columnas (insensible a mayúsculas/acentos)
                $wordConds[] = "(nome COLLATE utf8mb4_unicode_ci LIKE $key OR marca COLLATE utf8mb4_unicode_ci LIKE $key OR COALESCE(descripcion,'') COLLATE utf8mb4_unicode_ci LIKE $key)";
                $params[$key] = '%' . $w . '%';
            }
            // Requerir que todas las palabras aparezcan (AND entre palabras)
            if (!empty($wordConds)) {
                $sql .= ' AND (' . implode(' AND ', $wordConds) . ')';
            }
        }

        if (!empty($categoria)) {
            $sql .= " AND categoria = :categoria";
            $params[':categoria'] = $categoria;
        }

        // Ampliar límite para mostrar más resultados relevantes
        $sql .= " ORDER BY nome LIMIT 200";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        echo json_encode($result);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}