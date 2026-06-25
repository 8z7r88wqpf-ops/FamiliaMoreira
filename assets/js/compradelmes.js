// ========== STATE ==========
const state = {
    currentProductId: null,
    selectedPrecioId: null,
    currentListaId: null,
    autoSearchResults: [],
    searchAbortController: null,
};

// ========== API CLIENT ==========
const API = {
    async get(url) {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`API Error: ${res.status}`);
        return res.json();
    },
    async post(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        if (!res.ok) throw new Error(`API Error: ${res.status}`);
        return res.json();
    },
    async put(url, data) {
        const res = await fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        if (!res.ok) throw new Error(`API Error: ${res.status}`);
        return res.json();
    },
    async delete(url) {
        const res = await fetch(url, { method: 'DELETE' });
        if (!res.ok) throw new Error(`API Error: ${res.status}`);
        return res.json();
    },
};

// ========== UTILITIES ==========
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&',
        '<': '<',
        '>': '>',
        '"': '"',
        "'": '&#39;',
    }[char]));
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value || 0));
}

function jsString(value) {
    return String(value ?? '')
        .replace(/&/g, '&')
        .replace(/</g, '<')
        .replace(/>/g, '>')
        .replace(/"/g, '"')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'");
}

function getBadgeClass(nome) {
    const map = {
        'continente': 'badge-continente',
        'pingo doce': 'badge-pingodoce',
        'pingo doçe': 'badge-pingodoce',
        'auchan': 'badge-auchan',
        'mercadona': 'badge-mercadona',
        'lidl': 'badge-lidl',
    };
    return map[nome.toLowerCase()] || '';
}

// ========== TOAST ==========
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// ========== METRICS ==========
function updateMetrics(listas) {
    const listsCount = listas.length;
    const itemsCount = listas.reduce((sum, lista) => sum + Number(lista.items_count || 0), 0);
    const total = listas.reduce((sum, lista) => sum + Number(lista.total_geral || 0), 0);

    document.getElementById('metricLists').textContent = listsCount;
    document.getElementById('metricItems').textContent = itemsCount;
    document.getElementById('metricTotal').textContent = formatCurrency(total);
}

// ========== MODAL ==========
function showNewListModal() {
    document.getElementById('newListModal').classList.add('active');
    document.getElementById('newListName').value = '';
    document.getElementById('newListName').focus();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// ========== CATEGORIES ==========
async function loadCategories() {
    try {
        const categorias = await API.get('api/categorias.php');
        const select = document.getElementById('categoriaFilter');
        categorias.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat;
            opt.textContent = cat;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error('Failed to load categories:', e);
    }
}

// ========== AUTOMATION OPTIONS ==========
async function loadAutomationOptions() {
    try {
        const [productData, supermercados] = await Promise.all([
            API.get('api/productos.php?search='),
            API.get('api/supermercados.php'),
        ]);

        // Handle both old (array) and new ({productos: array}) response formats
        const productos = productData.productos || productData;

        const productSelect = document.getElementById('autoProductoSelect');
        const marketSelect = document.getElementById('autoSupermercadoSelect');

        productSelect.innerHTML = '<option value="">Produto...</option>';
        (productos || []).forEach(producto => {
            const opt = document.createElement('option');
            opt.value = producto.id;
            opt.textContent = `${producto.nome}${producto.marca ? ' - ' + producto.marca : ''}`;
            productSelect.appendChild(opt);
        });

        marketSelect.innerHTML = '<option value="">Supermercado...</option>';
        supermercados.forEach(supermercado => {
            const opt = document.createElement('option');
            opt.value = supermercado.id;
            opt.textContent = supermercado.nome;
            marketSelect.appendChild(opt);
        });
    } catch (e) {
        console.error('Failed to load automation options:', e);
    }
}

// ========== AUTOMATION SOURCES ==========
async function loadAutomationSources() {
    const container = document.getElementById('automationSources');
    try {
        const sources = await API.get('api/actualizar_precios.php');
        if (sources.length === 0) {
            container.innerHTML = '<div class="empty-state" style="padding:20px;"><p>Nenhuma fonte guardada.</p></div>';
            return;
        }

        container.innerHTML = sources.map(source => `
            <div class="source-row">
                <div>
                    <strong>${escapeHtml(source.producto_nome)}</strong>
                    <small>${escapeHtml(source.supermercado_nome)} • ${source.ultimo_precio ? formatCurrency(source.ultimo_precio) : 'Sem leitura'} • ${source.ultimo_estado || 'pendente'}</small>
                    <small>${escapeHtml(source.url)}</small>
                    ${source.ultimo_error ? `<small style="color:var(--danger);">${escapeHtml(source.ultimo_error)}</small>` : ''}
                </div>
                <button class="btn btn-secondary btn-sm" onclick="updateOneSource(${Number(source.id)})">Atualizar</button>
                <button class="btn btn-danger btn-sm" onclick="deleteAutomationSource(${Number(source.id)})">Eliminar</button>
            </div>
        `).join('');
    } catch (e) {
        container.innerHTML = '<div class="empty-state" style="padding:20px;"><p>Erro ao carregar fontes.</p></div>';
    }
}

// ========== AUTO SEARCH ==========
async function searchAutoPrice() {
    const productoId = document.getElementById('autoProductoSelect').value;
    const supermercadoId = document.getElementById('autoSupermercadoSelect').value;

    if (!productoId || !supermercadoId) {
        showToast('Escolha um produto e um supermercado', 'error');
        return;
    }

    const btn = document.getElementById('autoSearchBtn');
    btn.disabled = true;
    btn.textContent = '⏳ A buscar...';
    document.getElementById('autoSearchResults').style.display = 'none';
    state.autoSearchResults = [];

    try {
        const result = await API.post('api/actualizar_precios.php', {
            action: 'search_auto',
            producto_id: Number(productoId),
            supermercado_id: Number(supermercadoId),
        });

        state.autoSearchResults = result.productos || [];
        const supermercadoNome = result.supermercado || '';

        document.getElementById('autoResultsCount').textContent = state.autoSearchResults.length;

        const listDiv = document.getElementById('autoResultsList');
        if (state.autoSearchResults.length === 0) {
            listDiv.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray);">Nenhum produto encontrado</div>';
        } else {
            listDiv.innerHTML = state.autoSearchResults.map((p, idx) => `
                <div class="auto-result-row" data-index="${idx}" onclick="selectAutoResult(${idx})">
                    <div class="product-info">
                        <div class="product-name">${escapeHtml(p.name)}</div>
                        <small class="product-url">${escapeHtml(p.url)}</small>
                    </div>
                    <div class="price-section">
                        <span class="price">${formatCurrency(p.price)}</span>
                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();saveAutoResult(${idx})">Guardar</button>
                    </div>
                </div>
            `).join('');
        }

        document.getElementById('autoSearchResults').style.display = 'block';
        showToast(`${state.autoSearchResults.length} produtos encontrados em ${supermercadoNome}`, 'success');
    } catch (e) {
        showToast('Não foi possível buscar preços: ' + (e.message || 'erro'), 'error');
        document.getElementById('autoSearchResults').style.display = 'none';
    } finally {
        btn.disabled = false;
        btn.textContent = '🔍 Buscar preços';
    }
}

function selectAutoResult(idx) {
    document.querySelectorAll('.auto-result-row').forEach(row => {
        row.style.background = '';
        row.style.borderLeft = '';
    });
    const row = document.querySelector(`.auto-result-row[data-index="${idx}"]`);
    if (row) {
        row.style.background = '#eefaf5';
        row.style.borderLeft = '3px solid var(--primary)';
    }
}

async function saveAutoResult(idx) {
    const productoId = document.getElementById('autoProductoSelect').value;
    const supermercadoId = document.getElementById('autoSupermercadoSelect').value;
    const product = state.autoSearchResults[idx];

    if (!product) {
        showToast('Erro: produto não encontrado', 'error');
        return;
    }

    try {
        const result = await API.post('api/actualizar_precios.php', {
            action: 'add_source',
            producto_id: Number(productoId),
            supermercado_id: Number(supermercadoId),
            url: product.url,
        });

        showToast(`Guardado! ${escapeHtml(product.name)} - ${formatCurrency(product.price)}`, 'success');
        document.getElementById('autoSearchResults').style.display = 'none';
        state.autoSearchResults = [];
        await loadAutomationSources();
    } catch (e) {
        showToast('Erro ao guardar fonte', 'error');
    }
}

async function updateOneSource(id) {
    try {
        await API.post('api/actualizar_precios.php', { action: 'update_one', id });
        showToast('Preço atualizado');
        await loadAutomationSources();
        await loadListas();
    } catch (e) {
        showToast('Erro ao atualizar fonte', 'error');
        await loadAutomationSources();
    }
}

async function updateAllSources() {
    try {
        const result = await API.post('api/actualizar_precios.php', { action: 'update_all' });
        showToast(`${result.updated} fontes atualizadas${result.errors.length ? ', com erros' : ''}`);
        await loadAutomationSources();
        await loadListas();
    } catch (e) {
        showToast('Erro ao atualizar preços', 'error');
    }
}

async function deleteAutomationSource(id) {
    if (!confirm('Eliminar esta fonte de preço?')) return;
    try {
        await API.delete(`api/actualizar_precios.php?id=${id}`);
        showToast('Fonte eliminada');
        await loadAutomationSources();
    } catch (e) {
        showToast('Erro ao eliminar fonte', 'error');
    }
}

// ========== SEARCH ==========
let searchTimeout = null;
let currentPage = 1;
let totalPages = 1;
let isLoadingMore = false;

function renderProductList(productos) {
    return productos.map(p => `
        <div class="product-item" onclick="showPrices(${Number(p.id)}, '${jsString(p.nome)}', '${jsString(p.marca || '')}')">
            <div class="product-info">
                <h4>${escapeHtml(p.nome)}</h4>
                <small>${p.marca ? escapeHtml(p.marca) + ' • ' : ''}${escapeHtml(p.categoria || '')}</small>
            </div>
            <span class="price-tag">Ver preços</span>
        </div>
    `).join('');
}

async function loadProductos(search, categoria, page = 1, append = false) {
    const resultsDiv = document.getElementById('searchResults');
    
    if (!append) {
        resultsDiv.innerHTML = '<div class="loading">A carregar produtos...</div>';
    }

    try {
        let url = `api/productos.php?page=${page}&limit=100`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (categoria) url += `&categoria=${encodeURIComponent(categoria)}`;

        const data = await API.get(url);
        const productos = data.productos || data; // Compatible with both old and new format
        const total = data.total ?? productos.length;
        totalPages = data.pages ?? 1;
        currentPage = data.page ?? 1;

        if (productos.length === 0) {
            resultsDiv.innerHTML = `
                <div class="empty-state">
                    <div class="icon">${search || categoria ? '😕' : '📦'}</div>
                    <p>${search || categoria ? 'Nenhum produto encontrado' : 'Ainda não há produtos no catálogo'}</p>
                </div>`;
            return;
        }

        if (append) {
            resultsDiv.innerHTML += renderProductList(productos);
        } else {
            resultsDiv.innerHTML = renderProductList(productos);
        }

        // Add "Load more" button if there are more pages
        if (currentPage < totalPages) {
            const loadMore = document.createElement('div');
            loadMore.style.textAlign = 'center';
            loadMore.style.padding = '10px';
            loadMore.innerHTML = `<button class="btn btn-secondary btn-sm" onclick="loadMoreProducts()">Carregar mais (${totalPages - currentPage} páginas restantes)</button>`;
            resultsDiv.appendChild(loadMore);
        }

        // Show total count
        const totalInfo = document.createElement('div');
        totalInfo.style.cssText = 'text-align:center;padding:8px;font-size:12px;color:var(--gray);border-top:1px solid var(--line);';
        totalInfo.textContent = `${total} produtos encontrados`;
        resultsDiv.appendChild(totalInfo);
    } catch (e) {
        if (!append) {
            resultsDiv.innerHTML = '<div class="empty-state"><div class="icon">❌</div><p>Erro ao carregar produtos</p></div>';
        }
    }
}

async function loadMoreProducts() {
    if (isLoadingMore || currentPage >= totalPages) return;
    isLoadingMore = true;
    const search = document.getElementById('searchInput').value.trim();
    const categoria = document.getElementById('categoriaFilter').value;
    await loadProductos(search, categoria, currentPage + 1, true);
    isLoadingMore = false;
}

async function searchProducts() {
    const search = document.getElementById('searchInput').value.trim();
    const categoria = document.getElementById('categoriaFilter').value;

    clearTimeout(searchTimeout);

    // Cancel previous request if any
    if (state.searchAbortController) {
        state.searchAbortController.abort();
    }

    searchTimeout = setTimeout(async () => {
        await loadProductos(search, categoria, 1, false);
    }, search.length < 2 && !categoria ? 0 : 300);
}

// ========== PRICES ==========
async function showPrices(productoId, nome, marca) {
    state.currentProductId = productoId;
    state.selectedPrecioId = null;

    document.getElementById('priceComparison').style.display = 'block';
    document.getElementById('productInfo').innerHTML = `
        <h3>${escapeHtml(nome)}</h3>
        ${marca ? `<small style="color:var(--gray)">${escapeHtml(marca)}</small>` : ''}
    `;

    const priceList = document.getElementById('priceList');
    priceList.innerHTML = '<div class="loading">A carregar preços...</div>';

    try {
        const precios = await API.get(`api/precios.php?producto_id=${productoId}`);

        if (precios.length === 0) {
            priceList.innerHTML = '<div class="empty-state"><p>Sem preços disponíveis</p></div>';
            document.getElementById('addToListSection').style.display = 'none';
            return;
        }

        const minPrice = Math.min(...precios.map(p => parseFloat(p.precio)));

        priceList.innerHTML = precios.map(p => {
            const price = parseFloat(p.precio);
            const isBest = price === minPrice;
            const badgeClass = getBadgeClass(p.supermercado_nome);
            const updatedAt = p.fecha_actualizacion ? new Date(p.fecha_actualizacion.replace(' ', 'T')).toLocaleDateString('pt-PT') : null;
            return `
                <div class="price-row ${isBest ? 'best-price' : ''}" id="priceRow${Number(p.id)}">
                    <div>
                        <span class="supermercado-name">
                            <span class="badge ${badgeClass}">${escapeHtml(p.supermercado_nome)}</span>
                        </span>
                        ${isBest ? '<span style="color:var(--primary-dark);font-size:12px;margin-left:8px;">Melhor preço</span>' : ''}
                        <span class="price-meta">
                            ${updatedAt ? `Atualizado em ${updatedAt}` : ''}
                            ${p.url ? `${updatedAt ? ' • ' : ''}<a href="${escapeHtml(p.url)}" target="_blank" rel="noopener">Ver produto</a>` : ''}
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="precio-value ${!isBest ? 'high' : ''}">${formatCurrency(price)}</span>
                        <button class="btn btn-secondary btn-sm" onclick="selectPrice(${Number(p.id)})">Escolher</button>
                    </div>
                </div>
            `;
        }).join('');

        document.getElementById('addToListSection').style.display = 'flex';
        await loadListasSelect();
        selectPrice(precios[0].id);
    } catch (e) {
        priceList.innerHTML = '<div class="empty-state"><div class="icon">❌</div><p>Erro ao carregar preços</p></div>';
    }
}

function selectPrice(precioId) {
    state.selectedPrecioId = precioId;
    document.querySelectorAll('.price-row').forEach(row => row.classList.remove('selected-price'));
    const row = document.getElementById(`priceRow${precioId}`);
    if (row) row.classList.add('selected-price');
}

// ========== LISTAS ==========
async function loadListasSelect() {
    try {
        const listas = await API.get('api/listas.php');
        const select = document.getElementById('listaSelect');
        select.innerHTML = '<option value="">Selecionar lista...</option>';
        listas.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.id;
            opt.textContent = l.nombre;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error('Failed to load lists:', e);
    }
}

async function loadListas() {
    const container = document.getElementById('listasContainer');
    container.innerHTML = '<div class="loading">A carregar listas...</div>';

    try {
        const listas = await API.get('api/listas.php');
        updateMetrics(listas);

        if (listas.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <p>Crie uma nova lista para começar</p>
                </div>`;
            return;
        }

        container.innerHTML = listas.map(lista => `
            <div class="lista-item">
                <div class="lista-header">
                    <div>
                        <h3>${escapeHtml(lista.nombre)}</h3>
                        <div class="lista-summary">${Number(lista.items_count || 0)} produtos • ${formatCurrency(lista.total_geral || 0)}</div>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="deleteLista(${Number(lista.id)})">Eliminar</button>
                </div>

                <div class="lista-items-list">
                    ${lista.items && lista.items.length > 0 ? lista.items.map(item => `
                        <div class="lista-product-row">
                            <div class="product-details">
                                <strong>${escapeHtml(item.producto_nome)}</strong>
                                <small>${item.marca ? escapeHtml(item.marca) + ' • ' : ''}${escapeHtml(item.categoria || '')}</small>
                                ${item.selected_precio ? `
                                    <div class="best-price-info">
                                        Escolhido: ${formatCurrency(item.selected_precio.precio)}
                                        (${escapeHtml(item.selected_precio.supermercado_nome)})
                                    </div>
                                ` : '<div class="best-price-info" style="color:var(--gray)">Sem preços</div>'}
                            </div>
                            <div class="quantity-controls">
                                <button class="btn btn-sm btn-secondary" onclick="updateQuantity(${Number(item.id)}, ${Number(item.cantidad) - 1})">−</button>
                                <input type="number" value="${Number(item.cantidad)}" min="1"
                                       onchange="updateQuantity(${Number(item.id)}, this.value)"
                                       onfocus="this.select()">
                                <button class="btn btn-sm btn-secondary" onclick="updateQuantity(${Number(item.id)}, ${Number(item.cantidad) + 1})">+</button>
                                <button class="btn btn-sm btn-danger" onclick="removeItem(${Number(item.id)})" style="margin-left:5px;">Remover</button>
                            </div>
                        </div>
                    `).join('') : '<div style="color:var(--gray);padding:10px 0;">Lista vazia</div>'}
                </div>

                ${lista.totals_by_supermarket && Object.keys(lista.totals_by_supermarket).length > 0 ? `
                    <div class="totals-card">
                        <h4>Total por supermercado</h4>
                        ${Object.entries(lista.totals_by_supermarket).map(([supermercado, total]) => `
                            <div class="total-row">
                                <span>${escapeHtml(supermercado)}</span>
                                <span>${formatCurrency(total)}</span>
                            </div>
                        `).join('')}
                        <div class="total-row total-geral">
                            <span>Total Geral</span>
                            <span>${formatCurrency(lista.total_geral)}</span>
                        </div>
                    </div>
                ` : ''}
            </div>
        `).join('');
    } catch (e) {
        container.innerHTML = '<div class="empty-state"><div class="icon">❌</div><p>Erro ao carregar listas</p></div>';
    }
}

async function createList() {
    const name = document.getElementById('newListName').value.trim();
    if (!name) {
        showToast('Por favor insira um nome para a lista', 'error');
        return;
    }

    try {
        await API.post('api/listas.php', { nombre: name });
        closeModal('newListModal');
        showToast('Lista criada com sucesso!');
        await loadListas();
        await loadListasSelect();
    } catch (e) {
        showToast('Erro ao criar lista', 'error');
    }
}

async function deleteLista(id) {
    if (!confirm('Tem a certeza que deseja eliminar esta lista?')) return;

    try {
        await API.delete(`api/listas.php?id=${id}`);
        showToast('Lista eliminada');
        await loadListas();
        await loadListasSelect();
    } catch (e) {
        showToast('Erro ao eliminar lista', 'error');
    }
}

async function addToList() {
    const listaId = document.getElementById('listaSelect').value;
    const cantidad = parseInt(document.getElementById('addQuantity').value) || 1;

    if (!listaId) {
        showToast('Por favor selecione uma lista', 'error');
        return;
    }

    if (!state.currentProductId) {
        showToast('Nenhum produto selecionado', 'error');
        return;
    }

    try {
        await API.post('api/lista_items.php', {
            lista_id: parseInt(listaId),
            producto_id: state.currentProductId,
            precio_id: state.selectedPrecioId,
            cantidad: cantidad,
        });
        showToast('Produto adicionado à lista!');
        await loadListas();
    } catch (e) {
        showToast('Erro ao adicionar produto', 'error');
    }
}

async function updateQuantity(itemId, cantidad) {
    cantidad = parseInt(cantidad);
    if (cantidad < 1) return;

    try {
        await API.put('api/lista_items.php', { id: itemId, cantidad: cantidad });
        await loadListas();
    } catch (e) {
        showToast('Erro ao atualizar quantidade', 'error');
    }
}

async function removeItem(itemId) {
    if (!confirm('Remover este item da lista?')) return;

    try {
        await API.delete(`api/lista_items.php?id=${itemId}`);
        showToast('Item removido');
        await loadListas();
    } catch (e) {
        showToast('Erro ao remover item', 'error');
    }
}

// ========== KEYBOARD SHORTCUTS ==========
document.addEventListener('keydown', (e) => {
    // Escape to close modal
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal-overlay.active');
        if (modal) closeModal(modal.id);
    }
    // Ctrl+K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

// ========== SCRAP CATALOG FROM SUPERMARKETS ==========
async function scrapCatalogo(supermercado) {
    const btn = event.target;
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '⏳ A extrair...';

    const scrapDiv = document.getElementById('scrapResults');
    scrapDiv.style.display = 'block';
    scrapDiv.innerHTML = '<div class="loading">A extrair produtos do ' + (supermercado === 'all' ? 'todos os supermercados' : supermercado) + '...</div>';

    try {
        const result = await API.post('api/scrap_catalogo.php', { supermercado });
        let html = '<div style="padding:12px;background:var(--bg);border-radius:var(--radius);">';
        html += '<strong style="color:var(--primary);">✅ Scraping completo!</strong>';
        
        if (result.resultados) {
            result.resultados.forEach(r => {
                if (r.error) {
                    html += `<div style="margin-top:8px;font-size:13px;">⚠️ <strong>${r.supermercado}</strong>: ${escapeHtml(r.error)}</div>`;
                } else {
                    html += `<div style="margin-top:8px;font-size:13px;">✅ <strong>${escapeHtml(r.supermercado)}</strong>: ${r.productos_insertados} produtos adicionados (${r.total} processados)</div>`;
                }
            });
        }

        html += '</div>';
        scrapDiv.innerHTML = html;
        showToast('Scraping completo!', 'success');

        // Recarregar listas de produtos
        await Promise.all([
            loadCategories(),
            loadAutomationOptions(),
            loadProductos('', '', 1, false),
        ]);
    } catch (e) {
        scrapDiv.innerHTML = `<div style="padding:12px;color:var(--danger);">❌ Erro: ${escapeHtml(e.message)}</div>`;
        showToast('Erro no scraping', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// ========== GERAR PRECOS ==========
async function gerarPrecos(supermercado) {
    const btn = event.target;
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '⏳ A gerar preços...';

    const resultsDiv = document.getElementById('priceGenResults');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<div class="loading">A gerar preços realistas...</div>';

    try {
        const result = await API.post('api/gerar_precos.php', { supermercado });
        let html = '<div style="padding:12px;background:var(--bg);border-radius:var(--radius);">';
        html += '<strong style="color:var(--primary);">✅ Preços gerados!</strong>';
        html += `<div style="margin-top:8px;font-size:13px;">Total: <strong>${result.total_gerados}</strong> preços criados</div>`;

        if (result.resultados) {
            result.resultados.forEach(r => {
                if (r.mensaje) {
                    html += `<div style="margin-top:6px;font-size:13px;">ℹ️ <strong>${escapeHtml(r.supermercado)}</strong>: ${escapeHtml(r.mensaje)}</div>`;
                } else {
                    html += `<div style="margin-top:6px;font-size:13px;">✅ <strong>${escapeHtml(r.supermercado)}</strong>: ${r.gerados} preços gerados</div>`;
                }
            });
        }

        html += '</div>';
        resultsDiv.innerHTML = html;
        showToast(result.message || 'Preços gerados com sucesso!', 'success');
    } catch (e) {
        resultsDiv.innerHTML = `<div style="padding:12px;color:var(--danger);">❌ Erro: ${escapeHtml(e.message)}</div>`;
        showToast('Erro ao gerar preços', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// ========== BUSCAR PRECIOS MASIVO ==========
async function buscarPreciosMasivo(supermercado) {
    const btn = event.target;
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '⏳ A buscar preços...';

    const resultsDiv = document.getElementById('priceSearchResults');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<div class="loading">A buscar preços no ' + (supermercado === 'all' ? 'todos os supermercados...' : supermercado + '...') + ' (isto pode demorar alguns minutos)</div>';

    try {
        const result = await API.post('api/buscar_precios_masivo.php', { supermercado, limite: 50 });
        let html = '<div style="padding:12px;background:var(--bg);border-radius:var(--radius);">';
        html += '<strong style="color:var(--primary);">✅ Búsqueda completada!</strong>';
        html += `<div style="margin-top:8px;font-size:13px;">Total: <strong>${result.total_con_precio}</strong> preços encontrados de ${result.total_procesados} productos</div>`;

        if (result.resultados) {
            result.resultados.forEach(r => {
                if (r.mensaje) {
                    html += `<div style="margin-top:6px;font-size:13px;">ℹ️ <strong>${escapeHtml(r.supermercado)}</strong>: ${escapeHtml(r.mensaje)}</div>`;
                } else {
                    html += `<div style="margin-top:6px;font-size:13px;">✅ <strong>${escapeHtml(r.supermercado)}</strong>: ${r.con_precio} preços encontrados (${r.procesados} processados)</div>`;
                }
            });
        }

        html += '</div>';
        resultsDiv.innerHTML = html;
        showToast(result.message || 'Preços buscados com sucesso!', 'success');
    } catch (e) {
        resultsDiv.innerHTML = `<div style="padding:12px;color:var(--danger);">❌ Erro: ${escapeHtml(e.message)}</div>`;
        showToast('Erro ao buscar preços', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// ========== SEED CATALOG ==========
async function seedCatalogo() {
    if (!confirm('Pretende popular a base de dados com o catálogo completo de produtos? (Centenas de produtos organizados por categorias)')) return;
    
    try {
        const result = await API.post('api/seed_catalogo.php', {});
        showToast(result.message || 'Catálogo populado com sucesso!', 'success');
        // Recarregar produtos e categorias
        await Promise.all([
            loadCategories(),
            loadAutomationOptions(),
            loadProductos('', '', 1, false),
        ]);
    } catch (e) {
        showToast('Erro ao popular catálogo: ' + (e.message || 'erro'), 'error');
    }
}

// ========== INIT ==========
async function init() {
    await Promise.all([
        loadCategories(),
        loadAutomationOptions(),
        loadAutomationSources(),
        loadListas(),
        loadListasSelect(),
    ]);
    
    // Cargar todos los productos del catálogo al iniciar
    loadProductos('', '', 1, false);
}

document.addEventListener('DOMContentLoaded', init);