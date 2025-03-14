<?php
// Script para corregir problemas de inclusión de header en archivos PHP
// Guarda este archivo como fix_all_pages.php en la raíz de tu proyecto

function fixPhpFile($filepath) {
    echo "Procesando archivo: $filepath\n";
    
    // Leer el contenido actual del archivo
    $content = file_get_contents($filepath);
    if ($content === false) {
        echo "Error: No se pudo leer el archivo $filepath\n";
        return false;
    }
    
    // Crear copia de seguridad del archivo original
    $backupFile = $filepath . '.bak';
    if (!file_put_contents($backupFile, $content)) {
        echo "Error: No se pudo crear copia de seguridad para $filepath\n";
        return false;
    }
    echo "Copia de seguridad creada: $backupFile\n";
    
    // Verificar si el archivo ya tiene la estructura correcta
    if (strpos($content, '<?php') === 0 && 
        (strpos($content, '$page_title') !== false || strpos($content, 'include_once') !== false) && 
        strpos($content, '../includes/header.php') !== false &&
        strpos($content, '?>
// Definir variables para el header') === false) {
        echo "El archivo ya parece tener la estructura correcta.\n";
        return true;
    }
    
    // Obtener la parte del código PHP inicial (si existe)
    preg_match('/^<\?php(.*?)(\?>|$)/s', $content, $matches);
    $initialPhp = isset($matches[1]) ? $matches[1] : '';
    
    // Obtener nombre de la página para el título
    $pageName = ucfirst(basename($filepath, '.php'));
    switch ($pageName) {
        case 'Users':
            $pageTitle = "Gestión de Usuarios";
            break;
        case 'Documents':
            $pageTitle = "Gestión de Documentos";
            break;
        case 'Reports':
            $pageTitle = "Reportes";
            break;
        case 'Settings':
            $pageTitle = "Configuración";
            break;
        default:
            $pageTitle = $pageName;
    }
    
    // Construir la nueva estructura del archivo
    $newContent = "<?php\n";
    $newContent .= "session_start();\n";
    $newContent .= "require_once '../includes/config.php';\n";
    $newContent .= "require_once '../includes/db.php';\n";
    $newContent .= "require_once '../includes/functions.php';\n\n";
    
    $newContent .= "// Verificar autenticación y rol de administrador\n";
    $newContent .= "requireAdmin();\n\n";
    
    $newContent .= "// Obtener información del usuario\n";
    $newContent .= "\$userId = \$_SESSION['user_id'];\n";
    $newContent .= "\$db = Database::getInstance();\n\n";
    
    $newContent .= "\$sql = \"SELECT * FROM users WHERE id = ? LIMIT 1\";\n";
    $newContent .= "\$user = \$db->fetchOne(\$sql, [\$userId]);\n\n";
    
    $newContent .= "if (!\$user) {\n";
    $newContent .= "    // Si el usuario no existe, cerrar sesión\n";
    $newContent .= "    header(\"Location: ../auth/logout.php\");\n";
    $newContent .= "    exit;\n";
    $newContent .= "}\n\n";
    
    $newContent .= "// Definir variables para el header\n";
    $newContent .= "\$page_title = \"$pageTitle\";\n";
    $newContent .= "\$_SESSION['name'] = \$user['name'];\n\n";
    
    $newContent .= "// Incluir el header global\n";
    $newContent .= "include_once '../includes/header.php';\n";
    $newContent .= "?>\n\n";
    
    // Eliminar cualquier texto de instrucción PHP visible y extraer el contenido HTML de la página
    $content = preg_replace('/\/\/ Definir variables.*?include_once.*?header\.php.*?;.*?>/s', '', $content);
    $content = preg_replace('/\?>[\s\n]*\/\/ Definir variables.*?include_once.*?header\.php.*?;.*?>/s', '', $content);
    $content = preg_replace('/^.*?<h1/s', '<h1', $content);
    
    // Añadir el contenido HTML existente
    $newContent .= $content;
    
    // Asegurarse de que hay un include del footer al final
    if (strpos($content, '../includes/footer.php') === false) {
        $newContent .= "\n\n<?php include_once '../includes/footer.php'; ?>";
    }
    
    // Guardar el archivo con la nueva estructura
    if (file_put_contents($filepath, $newContent)) {
        echo "Archivo actualizado correctamente.\n";
        return true;
    } else {
        echo "Error: No se pudo escribir en el archivo $filepath\n";
        return false;
    }
}

// Obtener todos los archivos PHP del directorio admin
$adminFiles = glob('admin/*.php');

echo "Iniciando corrección de archivos...\n\n";

foreach ($adminFiles as $file) {
    // Omitir index.php si ya está funcionando correctamente
    if ($file === 'admin/index.php') {
        echo "Omitiendo admin/index.php (asumiendo que ya funciona correctamente)\n";
        continue;
    }
    
    echo "\n--------------------------------------------------\n";
    fixPhpFile($file);
}

// También corregir archivos del directorio user si es necesario
$userFiles = glob('user/*.php');

foreach ($userFiles as $file) {
    echo "\n--------------------------------------------------\n";
    fixPhpFile($file);
}

echo "\n--------------------------------------------------\n";
echo "Proceso completado.\n";
echo "IMPORTANTE: Verifica las páginas en tu navegador para asegurarte de que todo funcione correctamente.\n";
echo "Si encuentras problemas, puedes restaurar los archivos originales usando los archivos .bak creados.\n";
?>