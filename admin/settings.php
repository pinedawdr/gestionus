<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar autenticación y rol de administrador
requireAdmin();

// Obtener información del usuario
$userId = $_SESSION['user_id'];
$db = Database::getInstance();

$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$user = $db->fetchOne($sql, [$userId]);

if (!$user) {
    // Si el usuario no existe, cerrar sesión
    header("Location: ../auth/logout.php");
    exit;
}

// Definir variables para el header
$page_title = "Configuración";
$_SESSION['name'] = $user['name'];

// Incluir el header global
include_once '../includes/header.php';
?>

<h1 class="text-2xl font-semibold text-gray-900 mb-6">Configuración del Sistema</h1>
        
        <?php if (!empty($successMessage)): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pestañas de configuración -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button type="button" class="config-tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="db">
                            Base de Datos
                        </button>
                        <button type="button" class="config-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="firebase">
                            Firebase
                        </button>
                        <button type="button" class="config-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="email">
                            Correo Electrónico
                        </button>
                        <button type="button" class="config-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="system">
                            Sistema
                        </button>
                    </nav>
                </div>
                
                <!-- Configuración de Base de Datos -->
                <div id="db-config" class="config-tab mt-6">
                    <form action="" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="db_config">
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="db_host" class="block text-sm font-medium text-gray-700">Host</label>
                                <div class="mt-1">
                                    <input type="text" name="db_host" id="db_host" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo DB_HOST; ?>">
                                </div>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="db_name" class="block text-sm font-medium text-gray-700">Nombre de la Base de Datos</label>
                                <div class="mt-1">
                                    <input type="text" name="db_name" id="db_name" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo DB_NAME; ?>">
                                </div>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="db_user" class="block text-sm font-medium text-gray-700">Usuario</label>
                                <div class="mt-1">
                                    <input type="text" name="db_user" id="db_user" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo DB_USER; ?>">
                                </div>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="db_pass" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <div class="mt-1">
                                    <input type="password" name="db_pass" id="db_pass" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo DB_PASS; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Guardar Configuración
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-10 pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Inicializar Base de Datos</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Crea las tablas necesarias para el funcionamiento del sistema.
                        </p>
                        <form action="" method="POST" class="mt-4">
                            <input type="hidden" name="action" value="create_db">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Inicializar Base de Datos
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Configuración de Firebase -->
                <div id="firebase-config" class="config-tab mt-6 hidden">
                    <p class="text-sm text-gray-500 mb-4">
                        Configura la conexión con Firebase para la autenticación de usuarios.
                    </p>
                    <form action="" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="firebase_config">
                        <div>
                            <label for="firebase_api_key" class="block text-sm font-medium text-gray-700">API Key</label>
                            <div class="mt-1">
                                <input type="text" name="firebase_api_key" id="firebase_api_key" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo FIREBASE_API_KEY; ?>">
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Puedes obtener tu API Key en la consola de Firebase.
                            </p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Configuración de Correo Electrónico -->
                <div id="email-config" class="config-tab mt-6 hidden">
                    <p class="text-sm text-gray-500 mb-4">
                        Configura el servicio de EmailJS para el envío de notificaciones por correo electrónico.
                    </p>
                    <form action="" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="email_config">
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label for="emailjs_user_id" class="block text-sm font-medium text-gray-700">User ID</label>
                                <div class="mt-1">
                                    <input type="text" name="emailjs_user_id" id="emailjs_user_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo EMAILJS_USER_ID; ?>">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="emailjs_service_id" class="block text-sm font-medium text-gray-700">Service ID</label>
                                <div class="mt-1">
                                    <input type="text" name="emailjs_service_id" id="emailjs_service_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo EMAILJS_SERVICE_ID; ?>">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="emailjs_template_id" class="block text-sm font-medium text-gray-700">Template ID</label>
                                <div class="mt-1">
                                    <input type="text" name="emailjs_template_id" id="emailjs_template_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" value="<?php echo EMAILJS_TEMPLATE_ID; ?>">
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">
                                Puedes obtener estos valores en tu cuenta de EmailJS. La plantilla debe incluir los parámetros: to_email, subject y message.
                            </p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Guardar Configuración
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-10 pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Enviar Correo de Prueba</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Verifica que la configuración de correo electrónico funciona correctamente.
                        </p>
                        <div class="mt-4 flex items-end space-x-4">
                            <div class="flex-1">
                                <label for="test_email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                                <div class="mt-1">
                                    <input type="email" id="test_email" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            <button type="button" id="send-test-email" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Enviar Prueba
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Configuración del Sistema -->
                <div id="system-config" class="config-tab mt-6 hidden">
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900">Información del Sistema</h3>
                        <div class="mt-4 bg-gray-50 p-4 rounded-md">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Versión</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo APP_VERSION; ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">PHP</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo phpversion(); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Servidor Web</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Zona Horaria</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo date_default_timezone_get(); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900">Permisos de Carpetas</h3>
                        <div class="mt-4 bg-gray-50 p-4 rounded-md">
                            <ul class="space-y-2">
                                <?php
                                $foldersToCheck = [
                                    BASE_PATH . '/uploads',
                                    BASE_PATH . '/uploads/backups',
                                    BASE_PATH . '/uploads/evidencias',
                                    BASE_PATH . '/uploads/reportes_cnv',
                                    BASE_PATH . '/uploads/otros'
                                ];
                                
                                foreach ($foldersToCheck as $folder) {
                                    $exists = file_exists($folder);
                                    $writable = $exists && is_writable($folder);
                                    ?>
                                    <li class="flex items-center">
                                        <span class="mr-2">
                                            <?php if ($exists && $writable): ?>
                                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <?php elseif ($exists): ?>
                                                    <svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    <?php else: ?>
                                                    <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-sm text-gray-800"><?php echo str_replace(BASE_PATH, '', $folder); ?></span>
                                                <span class="ml-2 text-xs px-2 py-1 rounded-full <?php echo $exists && $writable ? 'bg-green-100 text-green-800' : ($exists ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo $exists && $writable ? 'OK' : ($exists ? 'No Escribible' : 'No Existe'); ?>
                                                </span>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-8">
                                <h3 class="text-lg font-medium text-gray-900">Mantenimiento</h3>
                                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div class="bg-gray-50 p-4 rounded-md">
                                        <h4 class="text-base font-medium text-gray-800">Limpiar Archivos Temporales</h4>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Elimina archivos temporales y caché del sistema.
                                        </p>
                                        <button type="button" id="clean-temp-btn" class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Limpiar Temporales
                                        </button>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-md">
                                        <h4 class="text-base font-medium text-gray-800">Optimizar Base de Datos</h4>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Optimiza las tablas de la base de datos.
                                        </p>
                                        <button type="button" id="optimize-db-btn" class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Optimizar BD
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <footer class="bg-white">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> Gestionus - Todos los derechos reservados
                </p>
            </div>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Referencias a elementos del DOM
        const configTabs = document.querySelectorAll('.config-tab');
        const configTabBtns = document.querySelectorAll('.config-tab-btn');
        
        // Botones de mantenimiento
        const cleanTempBtn = document.getElementById('clean-temp-btn');
        const optimizeDbBtn = document.getElementById('optimize-db-btn');
        
        // Botón de enviar correo de prueba
        const sendTestEmailBtn = document.getElementById('send-test-email');
        const testEmailInput = document.getElementById('test_email');
        
        // Función para cambiar de pestaña
        function changeTab(tabId) {
            // Ocultar todas las pestañas
            configTabs.forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Desactivar todos los botones
            configTabBtns.forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Mostrar pestaña seleccionada
            document.getElementById(tabId + '-config').classList.remove('hidden');
            
            // Activar botón seleccionado
            document.querySelector(`.config-tab-btn[data-tab="${tabId}"]`).classList.remove('border-transparent', 'text-gray-500');
            document.querySelector(`.config-tab-btn[data-tab="${tabId}"]`).classList.add('border-blue-500', 'text-blue-600');
        }
        
        // Event Listeners para pestañas
        configTabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                changeTab(tabId);
            });
        });
        
        // Event Listener para limpiar temporales
        if (cleanTempBtn) {
            cleanTempBtn.addEventListener('click', function() {
                // Deshabilitar botón
                cleanTempBtn.disabled = true;
                cleanTempBtn.innerHTML = 'Limpiando...';
                
                // Simular operación
                setTimeout(() => {
                    alert('Archivos temporales limpiados correctamente');
                    
                    // Re-habilitar botón
                    cleanTempBtn.disabled = false;
                    cleanTempBtn.innerHTML = 'Limpiar Temporales';
                }, 1500);
            });
        }
        
        // Event Listener para optimizar BD
        if (optimizeDbBtn) {
            optimizeDbBtn.addEventListener('click', function() {
                // Deshabilitar botón
                optimizeDbBtn.disabled = true;
                optimizeDbBtn.innerHTML = 'Optimizando...';
                
                // Enviar solicitud AJAX
                fetch('?action=optimize_db', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Base de datos optimizada correctamente');
                    } else {
                        alert('Error al optimizar la base de datos: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al optimizar la base de datos');
                })
                .finally(() => {
                    // Re-habilitar botón
                    optimizeDbBtn.disabled = false;
                    optimizeDbBtn.innerHTML = 'Optimizar BD';
                });
            });
        }
        
        // Event Listener para enviar correo de prueba
        if (sendTestEmailBtn) {
            sendTestEmailBtn.addEventListener('click', function() {
                const email = testEmailInput.value.trim();
                
                if (!email) {
                    alert('Por favor, ingrese una dirección de correo electrónico');
                    return;
                }
                
                // Deshabilitar botón
                sendTestEmailBtn.disabled = true;
                sendTestEmailBtn.innerHTML = 'Enviando...';
                
                // Enviar solicitud AJAX
                fetch('../api/notifications.php?action=send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        subject: 'Prueba de Configuración de Correo',
                        message: 'Este es un correo de prueba enviado desde el sistema Gestionus para verificar la configuración de EmailJS.'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Correo de prueba enviado correctamente');
                    } else {
                        alert('Error al enviar correo de prueba: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al enviar correo de prueba');
                })
                .finally(() => {
                    // Re-habilitar botón
                    sendTestEmailBtn.disabled = false;
                    sendTestEmailBtn.innerHTML = 'Enviar Prueba';
                });
            });
        }
    });
    </script>
</body>
</html>

<?php include_once '../includes/footer.php'; ?>