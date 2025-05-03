</main>
    
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">© <?php echo date('Y'); ?> CalcMeli - Todos os direitos reservados</p>
            <p class="small mb-0">Versão <?php echo $config['version'] ?? '1.0.0'; ?></p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Controle do Sidebar
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarTogglers = document.querySelectorAll('.sidebar-toggler');
            const sidebarClose = document.querySelector('.sidebar-close');
            const isMobile = window.innerWidth < 768;
            
            // Recuperar estado do sidebar do localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            // Aplicar estado do sidebar
            if (isMobile) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                mainContent.classList.remove('sidebar-expanded');
            } else {
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                    mainContent.classList.remove('sidebar-expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('sidebar-collapsed');
                    mainContent.classList.add('sidebar-expanded');
                }
            }
            
            // Ouvintes de evento para os togglers do sidebar
            sidebarTogglers.forEach(function(toggler) {
                toggler.addEventListener('click', function() {
                    if (isMobile) {
                        sidebar.classList.toggle('show');
                    } else {
                        sidebar.classList.toggle('collapsed');
                        mainContent.classList.toggle('sidebar-collapsed');
                        mainContent.classList.toggle('sidebar-expanded');
                        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                    }
                });
            });
            
            // Botão de fechar no sidebar
            if (sidebarClose) {
                sidebarClose.addEventListener('click', function() {
                    if (isMobile) {
                        sidebar.classList.remove('show');
                    } else {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('sidebar-collapsed');
                        mainContent.classList.remove('sidebar-expanded');
                        localStorage.setItem('sidebarCollapsed', 'true');
                    }
                });
            }
            
            // Detectar cliques fora do sidebar para fechar em dispositivos móveis
            document.addEventListener('click', function(event) {
                if (isMobile && sidebar.classList.contains('show')) {
                    const clickInsideSidebar = sidebar.contains(event.target);
                    const clickOnToggler = Array.from(sidebarTogglers).some(toggler => toggler.contains(event.target));
                    
                    if (!clickInsideSidebar && !clickOnToggler) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Expandir o submenu do item ativo
            const activeSubmenuLink = document.querySelector('.submenu .nav-link.active');
            if (activeSubmenuLink) {
                const parentCollapse = activeSubmenuLink.closest('.collapse');
                if (parentCollapse) {
                    const bsCollapse = new bootstrap.Collapse(parentCollapse, {
                        toggle: false
                    });
                    bsCollapse.show();
                }
            }
        });
    </script>
</body>
</html>