            </div>
        </div>

        <!-- Pie de página -->
        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex items-center mb-4 md:mb-0">
                        <!-- SVG Logo pequeño -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                            <circle cx="12" cy="8" r="2"></circle>
                            <path d="M5 12a7 7 0 0 0 14 0"></path>
                        </svg>
                        <span class="ml-2 text-sm font-semibold text-gray-600">Gestionus</span>
                    </div>
                    <div class="text-center md:text-right">
                        <p class="text-sm text-gray-500">
                            &copy; <?php echo date('Y'); ?> Gestionus - Unidad de Seguros
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Todos los derechos reservados
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript para el menú móvil -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('active');
                });
            }
        });
    </script>
    
    <?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>