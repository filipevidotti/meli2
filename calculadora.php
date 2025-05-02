<?php
// calculadora.php
require_once('init.php');

// Proteger a página
protegerPagina();

// Obter produto_id e anuncio_id se fornecidos
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : null;
$anuncio_id = isset($_GET['anuncio_id']) ? (int)$_GET['anuncio_id'] : null;

// Dados iniciais
$produto = null;
$anuncio = null;

// Carregar dados do produto se fornecido
if ($produto_id) {
    $sql = "SELECT * FROM produtos WHERE id = ? AND usuario_id = ?";
    $produto = fetchSingle($sql, [$produto_id, $_SESSION['user_id']]);
}

// Carregar dados do anúncio se fornecido
if ($anuncio_id) {
    $sql = "SELECT * FROM anuncios_ml WHERE id = ? AND usuario_id = ?";
    $anuncio = fetchSingle($sql, [$anuncio_id, $_SESSION['user_id']]);
    
    // Se o anúncio estiver vinculado a um produto, carregar dados do produto
    if ($anuncio && $anuncio['produto_id'] && !$produto) {
        $sql = "SELECT * FROM produtos WHERE id = ? AND usuario_id = ?";
        $produto = fetchSingle($sql, [$anuncio['produto_id'], $_SESSION['user_id']]);
    }
}

// Incluir cabeçalho
$page_title = 'Calculadora de Lucros';
include(INCLUDES_DIR . '/header.php');
?>

<div class="container mt-4">
    <h2>Calculadora de Lucros - Mercado Livre</h2>
    
    <div class="card mb-4">
        <div class="card-body">
            <form id="calculadoraForm" method="POST" action="calcular_lucro.php">
                <input type="hidden" name="produto_id" value="<?php echo $produto_id ?? ''; ?>">
                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio_id ?? ''; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="mb-3">Informações do Produto</h4>
                        
                        <div class="mb-3">
                            <label for="nome_produto" class="form-label">Nome do Produto</label>
                            <input type="text" class="form-control" id="nome_produto" name="nome_produto" 
                                   value="<?php echo htmlspecialchars($produto['nome'] ?? ($anuncio['titulo'] ?? '')); ?>" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="preco_venda" class="form-label">Preço de Venda (R$)</label>
                                <input type="number" step="0.01" min="6" class="form-control" id="preco_venda" name="preco_venda" 
                                       value="<?php echo $anuncio['preco'] ?? ''; ?>" required>
                                <div class="form-text">Mínimo de R$6,00 conforme regras do ML</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="custo_produto" class="form-label">Custo do Produto (R$)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="custo_produto" name="custo_produto" 
                                       value="<?php echo $produto['custo'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="peso" class="form-label">Peso (kg)</label>
                                <input type="number" step="0.001" min="0" class="form-control" id="peso" name="peso" 
                                       value="<?php echo $produto['peso'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="despesas_extras" class="form-label">Despesas Extras (R$)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="despesas_extras" name="despesas_extras" value="0">
                                <div class="form-text">Publicidade, impostos, etc.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="mb-3">Configurações de Venda</h4>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Anúncio</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_anuncio" id="classico" value="classico" checked>
                                <label class="form-check-label" for="classico">
                                    Clássico (12% de taxa)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_anuncio" id="premium" value="premium">
                                <label class="form-check-label" for="premium">
                                    Premium (16% de taxa)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Região de Envio</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="regiao_envio" id="sul_sudeste" value="sul_sudeste" checked>
                                <label class="form-check-label" for="sul_sudeste">
                                    Sul/Sudeste/Centro-Oeste
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="regiao_envio" id="norte_nordeste" value="norte_nordeste">
                                <label class="form-check-label" for="norte_nordeste">
                                    Norte/Nordeste
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="produto_full" name="produto_full" value="1">
                                <label class="form-check-label" for="produto_full">
                                    Produto Full (Mercado Envios)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="categoria_especial" name="categoria_especial" value="1">
                                <label class="form-check-label" for="categoria_especial">
                                    Categoria Especial (25% de desconto em vez de 50%)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator"></i> Calcular Lucro
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mb-4" id="resultadosCard" style="display: none;">
        <div class="card-header">
            <h5>Resultados do Cálculo</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Preço de Venda:</th>
                                <td id="result_preco"></td>
                            </tr>
                            <tr>
                                <th>Taxa do Mercado Livre:</th>
                                <td id="result_taxa"></td>
                            </tr>
                            <tr>
                                <th>Custo do Produto:</th>
                                <td id="result_custo"></td>
                            </tr>
                            <tr>
                                <th>Despesas Extras:</th>
                                <td id="result_despesas"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="resultado-destaque p-4 text-center">
                        <h4>Lucro</h4>
                        <h1 id="result_lucro" class="display-4"></h1>
                        <h5 id="result_rentabilidade"></h5>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="button" id="salvarCalculo" class="btn btn-success me-2">
                    <i class="fas fa-save"></i> Salvar Cálculo
                </button>
                <button type="button" id="novoCalculo" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Cálculo
                </button>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Cálculos Salvos</h5>
            <button type="button" id="novoCalculoBtn" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Novo Cálculo
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="calculosSalvos" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Preço</th>
                            <th>Custo</th>
                            <th>Lucro</th>
                            <th>Rentabilidade</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Buscar cálculos de lucro do usuário
                        $sql = "SELECT * FROM calculos_lucro WHERE usuario_id = ? ORDER BY data_calculo DESC";
                        $calculos = fetchAll($sql, [$_SESSION['user_id']]);
                        
                        foreach ($calculos as $calculo) {
                            $classe_lucro = $calculo['lucro'] >= 0 ? 'text-success' : 'text-danger';
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($calculo['nome_produto']) . '</td>';
                            echo '<td>' . formatCurrency($calculo['preco_venda']) . '</td>';
                            echo '<td>' . formatCurrency($calculo['custo_produto']) . '</td>';
                            echo '<td class="' . $classe_lucro . '">' . formatCurrency($calculo['lucro']) . '</td>';
                            echo '<td class="' . $classe_lucro . '">' . formatPercentage($calculo['rentabilidade']) . '</td>';
                            echo '<td>' . formatDateTime($calculo['data_calculo']) . '</td>';
                            echo '<td>
                                    <a href="calculadora.php?id=' . $calculo['id'] . '" class="btn btn-sm btn-info" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                    <a href="editar_calculo.php?id=' . $calculo['id'] . '" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button type="button" class="btn btn-sm btn-danger delete-calculo" data-id="' . $calculo['id'] . '" title="Excluir"><i class="fas fa-trash"></i></button>
                                  </td>';
                            echo '</tr>';
                        }
                        
                        if (count($calculos) == 0) {
                            echo '<tr><td colspan="7" class="text-center">Nenhum cálculo salvo.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formulário de cálculo
    const calculadoraForm = document.getElementById('calculadoraForm');
    
    // Processar o formulário via AJAX
    calculadoraForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(calculadoraForm);
        
        fetch('calcular_lucro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('result_preco').textContent = data.preco_venda;
                document.getElementById('result_taxa').textContent = data.taxa_ml;
                document.getElementById('result_custo').textContent = data.custo_produto;
                document.getElementById('result_despesas').textContent = data.despesas_extras;
                document.getElementById('result_lucro').textContent = data.lucro;
                document.getElementById('result_lucro').className = data.lucro_class;
                document.getElementById('result_rentabilidade').textContent = data.rentabilidade;
                document.getElementById('result_rentabilidade').className = data.rentabilidade_class;
                
                // Mostrar resultados
                document.getElementById('resultadosCard').style.display = 'block';
                
                // Armazenar ID do cálculo para salvar
                document.getElementById('salvarCalculo').dataset.calculoId = data.calculo_id || '';
            } else {
                alert('Erro ao calcular: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao processar o cálculo.');
        });
    });
    
    // Botão para salvar o cálculo
    document.getElementById('salvarCalculo').addEventListener('click', function() {
        const calculoId = this.dataset.calculoId;
        
        fetch('salvar_calculo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                calculo_id: calculoId || null,
                produto_id: calculadoraForm.produto_id.value || null,
                anuncio_id: calculadoraForm.anuncio_id.value || null,
                nome_produto: calculadoraForm.nome_produto.value,
                preco_venda: calculadoraForm.preco_venda.value,
                custo_produto: calculadoraForm.custo_produto.value,
                despesas_extras: calculadoraForm.despesas_extras.value,
                tipo_anuncio: calculadoraForm.querySelector('input[name="tipo_anuncio"]:checked').value,
                regiao_envio: calculadoraForm.querySelector('input[name="regiao_envio"]:checked').value,
                produto_full: calculadoraForm.produto_full.checked ? 1 : 0,
                categoria_especial: calculadoraForm.categoria_especial.checked ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cálculo salvo com sucesso!');
                window.location.reload(); // Recarregar para mostrar o cálculo na lista
            } else {
                alert('Erro ao salvar: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao salvar o cálculo.');
        });
    });
    
    // Botão para novo cálculo
    const novosCalculoBtns = document.querySelectorAll('#novoCalculo, #novoCalculoBtn');
    novosCalculoBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            calculadoraForm.reset();
            document.getElementById('resultadosCard').style.display = 'none';
            document.getElementById('produto_id').value = '';
            document.getElementById('anuncio_id').value = '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    
    // Botões para excluir cálculo
    document.querySelectorAll('.delete-calculo').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Tem certeza que deseja excluir este cálculo?')) {
                const id = this.dataset.id;
                
                fetch('excluir_calculo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cálculo excluído com sucesso!');
                        this.closest('tr').remove();
                        
                        // Se não houver mais cálculos, mostrar a mensagem
                        const tbody = document.querySelector('#calculosSalvos tbody');
                        if (tbody.querySelectorAll('tr').length === 0) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = '<td colspan="7" class="text-center">Nenhum cálculo salvo.</td>';
                            tbody.appendChild(tr);
                        }
                    } else {
                        alert('Erro ao excluir: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Ocorreu um erro ao excluir o cálculo.');
                });
            }
        });
    });
});
</script>

<style>
.resultado-destaque {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
}

.text-success {
    font-weight: bold;
}

.text-danger {
    font-weight: bold;
}
</style>

<?php
// Incluir rodapé
include(INCLUDES_DIR . '/footer.php');
?>