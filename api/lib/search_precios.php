<?php
declare(strict_types=1);

require_once __DIR__ . '/precios_helpers.php';

/**
 * Clase para buscar precios automáticamente en las webs de los supermercados
 */
class SupermercadoSearch {

    private function fetchUrl(string $url): ?string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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

    // ========================================
    // BUSCADORES ESPECÍFICOS POR SUPERMERCADO
    // ========================================

    private function searchContinente(string $productName): array {
        $url = 'https://www.continente.pt/pesquisa/?q=' . urlencode($productName);
        $html = $this->fetchUrl($url);
        if (!$html) return [];

        $products = [];

        // Patrón 1: estructura principal con product-link
        if (preg_match_all('/<a[^>]*class="[^"]*product-link[^"]*"[^>]*href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*(?:price|ct-price-value)[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(strip_tags($m[2]));
                $price = normalizePrice($m[3]);
                $productUrl = 'https://www.continente.pt' . $m[1];
                if ($price > 0) {
                    $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                }
            }
        }

        // Patrón 2: estructura alternativa
        if (empty($products)) {
            if (preg_match_all('/<a[^>]*href="(\/produto\/[^"]+)"[^>]*>.*?<span[^>]*class="[^"]*title[^"]*"[^>]*>([^<]+)<\/span>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = trim(strip_tags($m[2]));
                    $price = normalizePrice($m[3]);
                    $productUrl = 'https://www.continente.pt' . $m[1];
                    if ($price > 0) {
                        $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                    }
                }
            }
        }

        return $products;
    }

    private function searchPingoDoce(string $productName): array {
        $url = 'https://www.pingodoce.pt/produtos/?s=' . urlencode($productName);
        $html = $this->fetchUrl($url);
        if (!$html) return [];

        $products = [];

        // Patrón 1: estructura con product-item
        if (preg_match_all('/<div[^>]*class="[^"]*product-item[^"]*"[^>]*>.*?<h3[^>]*class="[^"]*product-title[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>.*?<a[^>]*href="([^"]+)"[^>]*>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(strip_tags($m[1]));
                $price = normalizePrice($m[2]);
                $productUrl = $m[3];
                if (strpos($productUrl, 'http') !== 0) {
                    $productUrl = 'https://www.pingodoce.pt' . $productUrl;
                }
                if ($price > 0) {
                    $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                }
            }
        }

        // Patrón 2: estructura alternativa
        if (empty($products)) {
            if (preg_match_all('/<div[^>]*class="[^"]*product[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<img[^>]*alt="([^"]+)"[^>]*>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = trim($m[2]);
                    $price = normalizePrice($m[3]);
                    $productUrl = $m[1];
                    if (strpos($productUrl, 'http') !== 0) {
                        $productUrl = 'https://www.pingodoce.pt' . $productUrl;
                    }
                    if ($price > 0) {
                        $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                    }
                }
            }
        }

        return $products;
    }

    private function searchAuchan(string $productName): array {
        $url = 'https://www.auchan.pt/pt/produtos/?q=' . urlencode($productName);
        $html = $this->fetchUrl($url);
        if (!$html) return [];

        $products = [];

        if (preg_match_all('/<div[^>]*class="[^"]*product-item[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<h3[^>]*class="[^"]*product-title[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price-value[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(strip_tags($m[2]));
                $price = normalizePrice($m[3]);
                $productUrl = $m[1];
                if (strpos($productUrl, 'http') !== 0) {
                    $productUrl = 'https://www.auchan.pt' . $productUrl;
                }
                if ($price > 0) {
                    $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                }
            }
        }

        return $products;
    }

    private function searchMercadona(string $productName): array {
        $url = 'https://www.mercadona.pt/api/v1/products/search?query=' . urlencode($productName);
        $json = $this->fetchUrl($url);
        if (!$json) return [];

        $data = json_decode($json, true);
        if (!$data || empty($data['results'])) return [];

        $products = [];
        foreach ($data['results'] as $product) {
            $name = $product['display_name'] ?? $product['name'] ?? '';
            $price = null;
            if (isset($product['price_instructions']['unit']['price'])) {
                $price = $product['price_instructions']['unit']['price'];
            } elseif (isset($product['price_instructions']['unit_price'])) {
                $price = $product['price_instructions']['unit_price'];
            } elseif (isset($product['price_instructions']['bulk_price'])) {
                $price = $product['price_instructions']['bulk_price'];
            } elseif (isset($product['price']['value'])) {
                $price = $product['price']['value'];
            }

            if ($name && $price) {
                $products[] = [
                    'name' => $name,
                    'price' => (float)$price,
                    'url' => 'https://www.mercadona.pt' . ($product['share_url'] ?? ''),
                ];
            }
        }

        return $products;
    }

    private function searchLidl(string $productName): array {
        $url = 'https://www.lidl.pt/search?q=' . urlencode($productName);
        $html = $this->fetchUrl($url);
        if (!$html) return [];

        $products = [];

        // Patrón 1: estructura con enlace
        if (preg_match_all('/<a[^>]*href="(\/[^"]+)"[^>]*>.*?<h3[^>]*class="[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price__value[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(strip_tags($m[2]));
                $price = normalizePrice($m[3]);
                $productUrl = 'https://www.lidl.pt' . $m[1];
                if ($price > 0) {
                    $products[] = ['name' => $name, 'price' => $price, 'url' => $productUrl];
                }
            }
        }

        // Patrón 2: estructura con div
        if (empty($products)) {
            if (preg_match_all('/<div[^>]*class="[^"]*product-grid__item[^"]*"[^>]*>.*?<span[^>]*class="[^"]*price__value[^"]*"[^>]*>([^<]+)<\/span>.*?<span[^>]*class="[^"]*product__title[^"]*"[^>]*>([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = trim(strip_tags($m[2]));
                    $price = normalizePrice($m[1]);
                    if ($price > 0) {
                        $products[] = ['name' => $name, 'price' => $price, 'url' => $url];
                    }
                }
            }
        }

        return $products;
    }

    // ========================================
    // MÉTODO PRINCIPAL
    // ========================================

    /**
     * Busca TODOS los productos en el supermercado especificado
     *
     * @param string $productName Nombre del producto a buscar
     * @param string $supermercadoNome Nombre del supermercado
     * @return array Lista de ['name' => string, 'price' => float, 'url' => string]
     */
    public function searchAll(string $productName, string $supermercadoNome): array {
        $supermercadoNome = strtolower(trim($supermercadoNome));

        $searchMap = [
            'continente' => 'searchContinente',
            'pingo doce' => 'searchPingoDoce',
            'pingo doçe' => 'searchPingoDoce',
            'auchan' => 'searchAuchan',
            'mercadona' => 'searchMercadona',
            'lidl' => 'searchLidl',
        ];

        $method = $searchMap[$supermercadoNome] ?? null;

        if (!$method || !method_exists($this, $method)) {
            throw new InvalidArgumentException("Supermercado '{$supermercadoNome}' no soportado para búsqueda automática");
        }

        $results = $this->$method($productName);

        if (empty($results)) {
            throw new RuntimeException("No se encontraron productos para '{$productName}' en {$supermercadoNome}");
        }

        // Limitar a 30 resultados para no saturar la interfaz
        return array_slice($results, 0, 30);
    }
}