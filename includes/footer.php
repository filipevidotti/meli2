            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle para o menu em dispositivos móveis
            const sidebarToggler = document.getElementById('sidebarToggler');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggler) {
                sidebarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Controle dos submenus
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Obter o elemento submenu relacionado
                    const submenu = this.nextElementSibling;
                    const arrow = this.querySelector('.menu-arrow');
                    
                    // Toggle para a classe open
                    submenu.classList.toggle('open');
                    arrow.classList.toggle('rotated');
                });
            });
        });
    </script>
</body>
</html>
