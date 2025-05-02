    </div> <!-- Fechando container content-container -->
</div> <!-- Fechando main -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openNav() {
    document.getElementById("mySidebar").style.width = "250px";
    document.getElementById("main").style.marginLeft = "250px";
}

function closeNav() {
    document.getElementById("mySidebar").style.width = "0";
    document.getElementById("main").style.marginLeft= "0";
}

// Função para formatar valores como moeda
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Funções para calcular valores na calculadora
function calcularTarifas() {
    const precoVenda = parseFloat(document.getElementById('preco_venda').value) || 0;
    const custoAquisicao = parseFloat(document.getElementById('custo_aquisicao').value) || 0;
    const peso = parseFloat(document.getElementById('peso').value) || 0;
    const tipoAnuncio = document.getElementById('tipo_anuncio').value;
    const categoriaEspecial = document.getElementById('categoria_especial').checked;
    const tipoEnvio = document.getElementById('tipo_envio').value;
    const tipoSupermercado = document.getElementById('tipo_supermercado').checked;
    const regiao = document.getElementById('regiao').value;
    const custoExtra = parseFloat(document.getElementById('custo_extra').value) || 0;

    let custoFixo = 0;
    let taxaML = 0;
    let custoFrete = 0;

    // Cálculo de custo fixo
    if (precoVenda < 79) {
        if (precoVenda < 29) {
            custoFixo = 6.25;
        } else if (precoVenda < 50) {
            custoFixo = 6.50;
        } else {
            custoFixo = 6.75;
        }
    }

    // Taxa do Mercado Livre
    if (tipoAnuncio === 'classico') {
        taxaML = precoVenda * 0.12;
    } else { // premium
        taxaML = precoVenda * 0.17;
    }

    // Custo do frete
    if (tipoSupermercado && precoVenda < 199) {
        custoFrete = calcularFreteSupermercado(peso, regiao, precoVenda);
    } else {
        custoFrete = calcularFreteNormal(peso, regiao, precoVenda, categoriaEspecial, tipoEnvio);
    }

    // Cálculo dos resultados
    const custoTotal = custoAquisicao + custoFixo + taxaML + custoFrete + custoExtra;
    const lucro = precoVenda - custoTotal;
    const margemLucro = (lucro / precoVenda) * 100;

    // Atualizar os resultados na página
    document.getElementById('custo_fixo').innerText = formatCurrency(custoFixo);
    document.getElementById('taxa_ml').innerText = formatCurrency(taxaML);
    document.getElementById('custo_frete').innerText = formatCurrency(custoFrete);
    document.getElementById('custo_total').innerText = formatCurrency(custoTotal);
    document.getElementById('lucro').innerText = formatCurrency(lucro);
    document.getElementById('lucro').className = lucro >= 0 ? 'profit-positive' : 'profit-negative';
    document.getElementById('margem_lucro').innerText = margemLucro.toFixed(2) + '%';
    document.getElementById('margem_lucro').className = margemLucro >= 0 ? 'profit-positive' : 'profit-negative';

    document.getElementById('result_box').style.display = 'block';
}

function calcularFreteNormal(peso, regiao, precoVenda, categoriaEspecial, tipoEnvio) {
    // Tabela de fretes baseada nas informações fornecidas
    const tabelaFreteSul = {
        '0.3': {normal: 39.90, desc50: 19.95, desc25: 29.93},
        '0.5': {normal: 42.90, desc50: 21.45, desc25: 32.18},
        '1': {normal: 44.90, desc50: 22.45, desc25: 33.68},
        '2': {normal: 46.90, desc50: 23.45, desc25: 35.18},
        '3': {normal: 49.90, desc50: 24.95, desc25: 37.43},
        '4': {normal: 53.90, desc50: 26.95, desc25: 40.43},
        '5': {normal: 56.90, desc50: 28.45, desc25: 42.68},
        '9': {normal: 88.90, desc50: 44.45, desc25: 66.68},
        '13': {normal: 131.90, desc50: 65.95, desc25: 98.93},
        '17': {normal: 146.90, desc50: 73.45, desc25: 110.18},
        '23': {normal: 171.90, desc50: 85.95, desc25: 128.93},
        '30': {normal: 197.90, desc50: 98.95, desc25: 148.43},
        '40': {normal: 203.90, desc50: 101.95, desc25: 152.93},
        '50': {normal: 210.90, desc50: 105.45, desc25: 158.18},
        '60': {normal: 224.90, desc50: 112.45, desc25: 168.68},
        '70': {normal: 240.90, desc50: 120.45, desc25: 180.68},
        '80': {normal: 251.90, desc50: 125.95, desc25: 188.93},
        '90': {normal: 279.90, desc50: 139.95, desc25: 209.93},
        '100': {normal: 319.90, desc50: 159.95, desc25: 239.93},
        '125': {normal: 357.90, desc50: 178.95, desc25: 268.43},
        '150': {normal: 379.90, desc50: 189.95, desc25: 284.93},
        '151+': {normal: 498.90, desc50: 249.45, desc25: 374.18}
    };
    
    const tabelaFreteNordeste = {
        '0.3': {normal: 62.90, desc50: 31.45, desc25: 47.18},
        '0.5': {normal: 68.10, desc50: 34.05, desc25: 51.08},
        '1': {normal: 72.10, desc50: 36.05, desc25: 54.08},
        '2': {normal: 85.70, desc50: 42.85, desc25: 64.28},
        '3': {normal: 110.80, desc50: 55.40, desc25: 83.10},
        '4': {normal: 118.40, desc50: 59.20, desc25: 88.80},
        '5': {normal: 123.60, desc50: 61.80, desc25: 92.70},
        '9': {normal: 138.30, desc50: 69.15, desc25: 103.73},
        '13': {normal: 189.80, desc50: 94.90, desc25: 142.35},
        '17': {normal: 250.10, desc50: 125.05, desc25: 187.58},
        '23': {normal: 281.10, desc50: 140.55, desc25: 210.83},
        '30': {normal: 293.40, desc50: 146.70, desc25: 220.05},
        '40': {normal: 294.90, desc50: 147.45, desc25: 221.18},
        '50': {normal: 296.90, desc50: 148.45, desc25: 222.68},
        '60': {normal: 300.90, desc50: 150.45, desc25: 225.68},
        '70': {normal: 308.90, desc50: 154.45, desc25: 231.68},
        '80': {normal: 311.90, desc50: 155.95, desc25: 233.93},
        '90': {normal: 332.90, desc50: 166.45, desc25: 249.68},
        '100': {normal: 364.90, desc50: 182.45, desc25: 273.68},
        '125': {normal: 390.90, desc50: 195.45, desc25: 293.18},
        '150': {normal: 416.90, desc50: 208.45, desc25: 312.68},
        '151+': {normal: 546.10, desc50: 273.05, desc25: 409.58}
    };

    // Determinar qual tabela usar com base na região
    const tabelaFrete = regiao === 'sul' ? tabelaFreteSul : tabelaFreteNordeste;
    
    // Determinar qual faixa de peso usar
    let faixaPeso = '151+'; // Valor padrão para pesos maiores
    const pesoKeys = Object.keys(tabelaFrete).map(Number).sort((a, b) => a - b);
    
    for (const key of pesoKeys) {
        if (peso <= key) {
            faixaPeso = key.toString();
            break;
        }
    }
    
    // Determinar coluna de preço com base no valor do produto
    let coluna = 'normal'; // Padrão para produtos < 79
    
    if (precoVenda >= 79) {
        coluna = 'desc50'; // 50% de desconto para produtos >= 79
    }
    
    if (categoriaEspecial) {
        coluna = 'desc25'; // 25% de desconto para categorias especiais
    }
    
    // Se for Full, verificar se há taxa específica (não implementado completamente neste exemplo)
    if (tipoEnvio === 'full') {
        // Aqui você poderia ter uma lógica específica para o tipo Full
        // Por simplicidade, estamos mantendo o mesmo valor
    }
    
    return tabelaFrete[faixaPeso][coluna];
}

function calcularFreteSupermercado(peso, regiao, precoVenda) {
    // Implementação simplificada para produtos de supermercado
    // Você pode ajustar de acordo com as regras específicas
    const valorBase = calcularFreteNormal(peso, regiao, precoVenda, false, 'normal');
    
    // Para supermercado, se for abaixo de 199, cobra frete normal
    // Se for acima, frete grátis (valor zero)
    return precoVenda >= 199 ? 0 : valorBase;
}
</script>

</body>
</html>
