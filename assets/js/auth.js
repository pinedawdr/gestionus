// Esperar a que el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Referencias a elementos del DOM
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const errorMessage = document.getElementById('error-message');
    
    // Función para mostrar errores
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
        setTimeout(() => {
            errorMessage.classList.add('hidden');
        }, 5000);
    }
    
    // Listener para el botón de login
    if (loginBtn) {
        loginBtn.addEventListener('click', function() {
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();
            
            if (!email || !password) {
                showError('Por favor, complete todos los campos');
                return;
            }
            
            // Deshabilitar botón durante la autenticación
            loginBtn.disabled = true;
            loginBtn.textContent = 'Autenticando...';
            
            console.log('Iniciando autenticación con Firebase...');
            
            // Autenticar con Firebase
            firebase.auth().signInWithEmailAndPassword(email, password)
                .then((userCredential) => {
                    // Usuario ha iniciado sesión
                    const user = userCredential.user;
                    console.log('Autenticación con Firebase exitosa', user.uid);
                    
                    // Obtener token de autenticación
                    return user.getIdToken();
                })
                .then((token) => {
                    console.log('Token obtenido correctamente');
                    
                    // Verificar el rol del usuario en nuestro backend
                    console.log('Verificando rol del usuario...');
                    return fetch('api/users.php?action=check_role', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${token}`
                        }
                    })
                    .then(response => {
                        console.log('Respuesta de verificación de rol recibida:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Texto de respuesta no-JSON:', text);
                                throw new Error('Error en la respuesta del servidor: ' + response.status);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Datos de verificación de rol:', data);
                        
                        if (data.success) {
                            // Obtener el usuario de Firebase actual para usar en la sesión PHP
                            const user = firebase.auth().currentUser;
                            
                            // Crear sesión PHP
                            console.log('Creando sesión PHP...');
                            return fetch('auth/login.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': `Bearer ${token}`
                                },
                                body: JSON.stringify({
                                    email: user.email,
                                    uid: user.uid,
                                    role: data.role
                                })
                            });
                        } else {
                            throw new Error(data.message || 'Usuario no autorizado');
                        }
                    })
                    .then(response => {
                        console.log('Respuesta de creación de sesión recibida:', response.status);
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Texto de respuesta no-JSON:', text);
                                throw new Error('Error en la creación de sesión: ' + response.status);
                            });
                        }
                        return response.json();
                    })
                    .then(sessionData => {
                        console.log('Datos de sesión:', sessionData);
                        
                        if (sessionData.success) {
                            console.log('Redirigiendo al panel correspondiente...');
                            // Redirigir según el rol
                            if (sessionData.user.role === 'admin') {
                                window.location.href = 'admin/index.php';
                            } else {
                                window.location.href = 'user/index.php';
                            }
                        } else {
                            throw new Error(sessionData.message || 'Error al iniciar sesión en el sistema');
                        }
                    });
                })
                .catch((error) => {
                    // Manejar errores de autenticación
                    console.error('Error en el proceso de autenticación:', error);
                    
                    const errorCode = error.code;
                    let errorMsg = 'Error al iniciar sesión';
                    
                    if (error.code) {
                        // Error de Firebase
                        switch(errorCode) {
                            case 'auth/invalid-email':
                                errorMsg = 'El correo electrónico no es válido';
                                break;
                            case 'auth/user-disabled':
                                errorMsg = 'Este usuario ha sido deshabilitado';
                                break;
                            case 'auth/user-not-found':
                                errorMsg = 'No existe un usuario con este correo electrónico';
                                break;
                            case 'auth/wrong-password':
                                errorMsg = 'Contraseña incorrecta';
                                break;
                            default:
                                errorMsg = `Error al iniciar sesión: ${error.message}`;
                        }
                    } else {
                        // Error de nuestro backend o de la red
                        errorMsg = error.message || 'Error de conexión';
                    }
                    
                    console.log("Mensaje de error detallado:", errorMsg);
                    showError(errorMsg);
                    
                    // Re-habilitar botón de login
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Ingresar';
                });
        });
    }
  });