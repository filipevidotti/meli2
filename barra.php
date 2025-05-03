 <style>
    /* ====== Sidebar fixa ====== */
    .sidebar {
      position: fixed;
      top: 0; bottom: 0; left: 0;
      width: 250px;
      background-color: #343a40;
      overflow: hidden;
      transition: width 0.3s ease;
      z-index: 1000;
    }
    .sidebar.sidebar-collapsed {
      width: 60px;
    }

    /* ====== Botão de toggle ====== */
    .toggle-btn {
      position: absolute;
      top: 15px;
      width: 30px; height: 30px;
      border-radius: 50%;
      background-color: #343a40;
      border: 2px solid #fff;
      color: #fff;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: left 0.3s ease, right 0.3s ease, transform 0.3s ease;
    }
    /* Expandido: botão à direita */
    .sidebar:not(.sidebar-collapsed) .toggle-btn {
      right: -15px;
      left: auto;
      transform: none;
    }
    /* Recolhido: botão centralizado */
    .sidebar.sidebar-collapsed .toggle-btn {
      left: 50%;
      right: auto;
      transform: translateX(-50%);
    }

    /* ====== Links e ícones ====== */
    .sidebar .nav-link {
      display: flex;
      align-items: center;
      color: #ced4da;
      padding: 0.75rem 1rem;
      white-space: nowrap;
      transition: background 0.2s;
    }
    .sidebar .nav-link:hover {
      background-color: rgba(255,255,255,0.1);
      color: #fff;
    }
    .sidebar .nav-link i {
      font-size: 1.2rem;
      width: 30px;
      text-align: center;
      margin-right: 10px;
    }

    /* ====== Ícone de seta para submenu ====== */
    .submenu-icon {
      transition: transform 0.3s ease;
    }
    .nav-link[aria-expanded="false"] .submenu-icon {
      transform: rotate(0deg);
    }
    .nav-link[aria-expanded="true"] .submenu-icon {
      transform: rotate(180deg);
    }

    /* ====== esconder texto quando sidebar está recolhida ====== */
    .sidebar.sidebar-collapsed .nav-link span.label {
      opacity: 0;
      visibility: hidden;
    }

    /* ====== Conteúdo principal ====== */
    .main-content {
      margin-left: 250px;
      transition: margin-left 0.3s ease;
      padding: 1.5rem;
    }
    .main-content.main-collapsed {
      margin-left: 60px;
    }
  </style>
     <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">CalcMeli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nome); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vendedor_config.php"><i class="fas fa-cog"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
<!-- Sidebar -->
  <div class="sidebar bg-dark" id="sidebar">
  

    <ul class="nav flex-column mt-5">
  <!-- Dashboard -->
  <li class="nav-item">
    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_dashboard.php">
      <i class="fas fa-home me-2"></i><span class="label">Dashboard</span>
    </a>
  </li>

  <!-- Produtos com submenu -->
  <li class="nav-item">
    <a class="nav-link collapsed d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#produtosSubmenu"
       role="button"
       aria-expanded="false"
       aria-controls="produtosSubmenu">
      <span class="d-flex align-items-center">
        <i class="fas fa-box me-2"></i><span class="label">Produtos</span>
      </span>
      <i class="fas fa-chevron-down submenu-icon"></i>
    </a>
    <ul class="collapse list-unstyled ps-4" id="produtosSubmenu">
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
          <span class="label">Cadastrar Produto</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos_lista.php">
          <span class="label">Listar Produtos</span>
        </a>
      </li>
    </ul>
  </li>

  <!-- Anúncios com submenu -->
  <li class="nav-item">
    <a class="nav-link collapsed d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#anunciosSubmenu"
       role="button"
       aria-expanded="false"
       aria-controls="anunciosSubmenu">
      <span class="d-flex align-items-center">
        <i class="fas fa-bullhorn me-2"></i><span class="label">Anúncios</span>
      </span>
      <i class="fas fa-chevron-down submenu-icon"></i>
    </a>
    <ul class="collapse list-unstyled ps-4" id="anunciosSubmenu">
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/qualidade_anuncio.php">
          <span class="label">Qualidade do Anúncio</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/meus_anuncios.php">
          <span class="label">Meus Anúncios</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/meus_catalogos.php">
          <span class="label">Meus Catálogos</span>
        </a>
      </li>
    </ul>
  </li>

  <!-- Calculadora -->
  <li class="nav-item">
    <a class="nav-link" href="<?php echo $base_url; ?>/calculadora.php">
      <i class="fas fa-calculator me-2"></i><span class="label">Calculadora</span>
    </a>
  </li>

  <!-- Vendas com submenu -->
  <li class="nav-item">
    <a class="nav-link collapsed d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#vendasSubmenu"
       role="button"
       aria-expanded="false"
       aria-controls="vendasSubmenu">
      <span class="d-flex align-items-center">
        <i class="fas fa-shopping-cart me-2"></i><span class="label">Vendas</span>
      </span>
      <i class="fas fa-chevron-down submenu-icon"></i>
    </a>
    <ul class="collapse list-unstyled ps-4" id="vendasSubmenu">
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_margem_contribuicao.php">
          <span class="label">Margem de Contribuição</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_analise_abc.php">
          <span class="label">Curva ABC</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/relatorio_anuncios_sem_venda.php">
          <span class="label">Anúncios Sem Venda</span>
        </a>
      </li>
    </ul>
  </li>

  <!-- Relatórios com submenu -->
  <li class="nav-item">
    <a class="nav-link collapsed d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#relatoriosSubmenu"
       role="button"
       aria-expanded="false"
       aria-controls="relatoriosSubmenu">
      <span class="d-flex align-items-center">
        <i class="fas fa-chart-bar me-2"></i><span class="label">Relatórios</span>
      </span>
      <i class="fas fa-chevron-down submenu-icon"></i>
    </a>
    <ul class="collapse list-unstyled ps-4" id="relatoriosSubmenu">
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_analise_abc.php">
          <span class="label">Curva ABC</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_coberturafull.php">
          <span class="label">Cobertura Full</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/relatorio_anuncios_sem_venda.php">
          <span class="label">Anúncios Sem Venda</span>
        </a>
      </li>
    </ul>
  </li>

  <!-- Configurações com submenu -->
  <li class="nav-item">
    <a class="nav-link collapsed d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#configSubmenu"
       role="button"
       aria-expanded="false"
       aria-controls="configSubmenu">
      <span class="d-flex align-items-center">
        <i class="fas fa-cog me-2"></i><span class="label">Configurações</span>
      </span>
      <i class="fas fa-chevron-down submenu-icon"></i>
    </a>
    <ul class="collapse list-unstyled ps-4" id="configSubmenu">
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
          <span class="label">Configuração</span>
        </a>
      </li>
      <li>
        <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_mercadolivre.php">
          <span class="label">Integração ML</span>
        </a>
      </li>
    </ul>
  </li>
</ul>

  </div>
