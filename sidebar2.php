<?php 
// Verificar se o array de menu está definido
if (!isset($menu)) {
    require_once 'config.php';
}
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose">
        <i class="fas fa-times"></i>
    </button>
    
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <?php foreach ($menu as $key => $item): ?>
                <li class="nav-item">
                    <?php if (empty($item['submenu'])): ?>
                        <!-- Item sem submenu -->
                        <a class="nav-link <?php echo $item['active'] ? 'active' : ''; ?>" href="<?php echo $base_url.'/'.$item['url']; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span class="nav-text"><?php echo $item['title']; ?></span>
                        </a>
                    <?php else: ?>
                        <!-- Item com submenu -->
                        <a class="nav-link <?php echo $item['active'] ? 'active' : ''; ?>" href="#" 
                           data-bs-toggle="collapse" data-bs-target="#submenu-<?php echo $key; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span class="nav-text"><?php echo $item['title']; ?></span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                        </a>
                        
                        <div class="submenu collapse <?php echo $item['active'] ? 'show' : ''; ?>" id="submenu-<?php echo $key; ?>">
                            <ul class="nav flex-column">
                                <?php foreach ($item['submenu'] as $subitem): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $subitem['active'] ? 'active' : ''; ?>" 
                                           href="<?php echo $base_url.'/'.$subitem['url']; ?>">
                                            <span><?php echo $subitem['title']; ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- Início do conteúdo principal -->
<main class="main-content" id="mainContent">