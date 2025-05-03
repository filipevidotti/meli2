<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>CalcMeli – Sidebar Recolhível</title>

  <!-- Bootstrap 5 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <!-- Font Awesome -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet"
  />

 
</head>

<body>
 

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>CalcMeli – Sidebar Recolhível</title>

  <!-- Bootstrap 5 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <!-- Font Awesome -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet"
  />

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
      top: 10px; right: -15px;
      width: 30px; height: 30px;
      border-radius: 50%;
      background-color: #343a40;
      border: 2px solid #fff;
      color: #fff;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: right 0.3s ease;
    }

    /* ====== Links e ícones ====== */
    .sidebar .nav-link {
      display: flex;
      align-items: center;
      color: #ced4da;
      padding: 0.75rem 1rem;
      white-space: nowrap;
      position: relative;
    }
    .sidebar .nav-link:hover {
      background-color: rgba(255,255,255,0.1);
      color: #fff;
    }
    .sidebar .nav-link i.main-icon {
      font-size: 1.2rem;
      width: 30px;
      text-align: center;
      margin-right: 10px;
    }

    /* seta de submenu */
    .submenu-toggle-icon {
      margin-left: auto;
      transition: transform 0.3s;
    }
    /* quando estiver descrito (classe collapsed adicionada pelo Bootstrap) → seta apontando para baixo */
    .sidebar .nav-link.collapsed .submenu-toggle-icon {
      transform: rotate(0deg);
    }
    /* quando expandido → seta apontando para cima */
    .sidebar .nav-link:not(.collapsed) .submenu-toggle-icon {
      transform: rotate(180deg);
    }

    /* esconder texto ao recolher sidebar */
    .sidebar.sidebar-collapsed .nav-link span.text {
      opacity: 0;
      visibility: hidden;
    }
    .sidebar.sidebar-collapsed .submenu-toggle-icon {
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
</head>

<body>
  <!-- Sidebar -->
   <?php require_once 'barra.php'; ?>


  <!-- Conteúdo principal -->
  <main id="main-content" class="main-content">
    <!-- Aqui vai seu conteúdo -->
  </main>

  <!-- Bootstrap + JS -->
  
