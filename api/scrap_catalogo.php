<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

class SupermercadoCatalogoScraper {
    
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private function fetchUrl(string $url, bool $json = false): ?string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: ' . ($json ? 'application/json' : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
                'Accept-Language: pt-PT,pt;q=0.9,en;q=0.7',
            ],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            return null;
        }
        return $body;
    }

    /**
     * Scrapea catálogo de Mercadona usando su API pública v2
     */
    public function scrapMercadona(): array {
        $inserted = 0;
        $results = [];

        // Mercadona API - buscar productos populares por categoría
        $categories = [
            'laticinios' => 'Laticínios',
            'carne' => 'Carnes',
            'peixe' => 'Peixaria',
            'bebidas' => 'Bebidas',
        ];

        foreach ($categories as $search => $catName) {
            // Buscar productos por término
            $url = "https://www.mercadona.pt/api/v1/products/search?query=" . urlencode($search);
            $json = $this->fetchUrl($url, true);
            if (!$json) continue;

            $data = json_decode($json, true);
            if (!$data || !isset($data['results'])) continue;

            foreach ($data['results'] as $product) {
                $name = $product['display_name'] ?? $product['name'] ?? $product['slug'] ?? '';
                $marca = $product['brand'] ?? $product['manufacturer'] ?? '';
                $price = $product['price_instructions']['unit']['price'] ?? 
                         $product['price_instructions']['unit_price'] ?? 
                         $product['price_instructions']['bulk_price'] ?? 0;

                if (empty($name)) continue;

                $productUrl = 'https://www.mercadona.pt' . ($product['share_url'] ?? $product['slug'] ?? '');

                try {
                    $productoId = getOrCreateProducto($this->db, [
                        'nome' => $name,
                        'marca' => $marca,
                        'categoria' => $catName,
                    ]);

                    if ($price > 0 && is_numeric($price)) {
                        $supermercadoId = getOrCreateSupermercado($this->db, 'Mercadona');
                        upsertPrecio($this->db, $productoId, $supermercadoId, [
                            'precio' => (float)$price,
                            'url' => $productUrl,
                        ]);
                    }

                    $inserted++;
                    $results[] = ['name' => $name, 'brand' => $marca, 'category' => $catName, 'price' => $price, 'action' => 'inserted'];
                } catch (Exception $e) {
                    $results[] = ['name' => $name, 'error' => $e->getMessage()];
                }
            }

            sleep(1);
        }

        return ['supermercado' => 'Mercadona', 'productos_insertados' => $inserted, 'total' => count($results)];
    }

    /**
     * Scrapea catálogo de Pingo Doce (tiene estructura diferente)
     */
    public function scrapPingoDoce(): array {
        $inserted = 0;
        $results = [];

        // Obtener categorías del sitemap
        $sitemapUrl = 'https://www.pingodoce.pt/produtos/';
        $html = $this->fetchUrl($sitemapUrl);
        if (!$html) throw new RuntimeException('No se pudo acceder a Pingo Doce');

        $categorias = [
            'mercearia' => 'Mercearia',
            'bebidas' => 'Bebidas',
            'carne' => 'Carnes',
            'peixe' => 'Peixaria',
            'fruta-e-legumes' => 'Frutas e Legumes',
            'laticinios-e-ovos' => 'Laticínios',
            'padaria' => 'Padaria',
            'congelados' => 'Congelados',
            'higiene' => 'Higiene',
            'limpeza' => 'Limpeza',
        ];

        foreach ($categorias as $slug => $catName) {
            for ($page = 1; $page <= 2; $page++) {
                $url = "https://www.pingodoce.pt/produtos/?s={$slug}&paged={$page}";
                $html = $this->fetchUrl($url);
                if (!$html) break;

                // Extraer productos de Pingo Doce
                if (preg_match_all('/<div[^>]*class="[^"]*product-item[^"]*"[^>]*>.*?<h3[^>]*class="[^"]*product-title[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>.*?<a[^>]*href="([^"]+)"[^>]*>/is', $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $name = trim(strip_tags($m[1]));
                        $price = normalizePrice($m[2]);
                        $productUrl = $m[3];
                        if (strpos($productUrl, 'http') !== 0) {
                            $productUrl = 'https://www.pingodoce.pt' . $productUrl;
                        }

                        if (empty($name)) continue;

                        try {
                            $productoId = getOrCreateProducto($this->db, [
                                'nome' => $name,
                                'marca' => '',
                                'categoria' => $catName,
                            ]);

                            if ($price > 0) {
                                $supermercadoId = getOrCreateSupermercado($this->db, 'Pingo Doce');
                                upsertPrecio($this->db, $productoId, $supermercadoId, [
                                    'precio' => $price,
                                    'url' => $productUrl,
                                ]);
                            }

                            $inserted++;
                            $results[] = ['name' => $name, 'category' => $catName, 'price' => $price, 'action' => 'inserted'];
                        } catch (Exception $e) {
                            $results[] = ['name' => $name, 'error' => $e->getMessage()];
                        }
                    }
                }

                sleep(1);
            }
        }

        return ['supermercado' => 'Pingo Doce', 'productos_insertados' => $inserted, 'total' => count($results)];
    }

    /**
     * Scrapea catálogo de Lidl
     */
    public function scrapLidl(): array {
        $inserted = 0;
        $results = [];

        $categorias = [
            'alimentos' => 'Mercearia',
            'bebidas' => 'Bebidas',
            'laticinios' => 'Laticínios',
            'frescos' => 'Frescos',
            'higiene-e-beleza' => 'Higiene',
            'limpeza' => 'Limpeza',
        ];

        foreach ($categorias as $slug => $catName) {
            $url = "https://www.lidl.pt/c/{$slug}/s?q=";
            $html = $this->fetchUrl($url);
            if (!$html) continue;

            // Patrón Lidl
            if (preg_match_all('/<a[^>]*href="(\/[^"]+)"[^>]*>.*?<h3[^>]*class="[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price__value[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = trim(strip_tags($m[2]));
                    $price = normalizePrice($m[3]);
                    $productUrl = 'https://www.lidl.pt' . $m[1];

                    if (empty($name)) continue;

                    try {
                        $productoId = getOrCreateProducto($this->db, [
                            'nome' => $name,
                            'marca' => '',
                            'categoria' => $catName,
                        ]);

                        if ($price > 0) {
                            $supermercadoId = getOrCreateSupermercado($this->db, 'Lidl');
                            upsertPrecio($this->db, $productoId, $supermercadoId, [
                                'precio' => $price,
                                'url' => $productUrl,
                            ]);
                        }

                        $inserted++;
                        $results[] = ['name' => $name, 'category' => $catName, 'price' => $price, 'action' => 'inserted'];
                    } catch (Exception $e) {
                        $results[] = ['name' => $name, 'error' => $e->getMessage()];
                    }
                }
            }

            sleep(1);
        }

        return ['supermercado' => 'Lidl', 'productos_insertados' => $inserted, 'total' => count($results)];
    }
}

// ====== MAIN ======
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$supermercado = $data['supermercado'] ?? 'all';

$scraper = new SupermercadoCatalogoScraper($db);
$resultados = [];

try {
    if ($supermercado === 'all' || $supermercado === 'mercadona') {
        $resultados[] = $scraper->scrapMercadona();
    }

    if ($supermercado === 'all' || $supermercado === 'pingodoce') {
        $resultados[] = $scraper->scrapPingoDoce();
    }

    if ($supermercado === 'all' || $supermercado === 'lidl') {
        $resultados[] = $scraper->scrapLidl();
    }

    jsonResponse([
        'success' => true,
        'message' => 'Scraping completo.',
        'resultados' => $resultados,
    ]);
} catch (Exception $e) {
    jsonError('Erro no scraping: ' . $e->getMessage(), 500);
}