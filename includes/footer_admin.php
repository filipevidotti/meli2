            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script>
        // Script para toggle do sidebar em dispositivos móveis
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            if (navbarToggler) {
                navbarToggler.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
            
            // Script para os submenus
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.classList.toggle('expanded');
                    const submenu = this.nextElementSibling;
                    if (submenu.classList.contains('submenu')) {
                        if (submenu.style.display === 'block') {
                            submenu.style.display = 'none';
                        } else {
                            submenu.style.display = 'block';
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
