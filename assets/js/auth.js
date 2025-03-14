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
        
        // Autenticar con Firebase
        firebase.auth().signInWithEmailAndPassword(email, password)
          .then((userCredential) => {
            // Usuario ha iniciado sesión
            const user = userCredential.user;
            
            // Obtener token de autenticación
            return user.getIdToken();
          })
          .then((token) => {
            // Verificar el rol del usuario en nuestro backend
            fetch('api/users.php?action=check_role', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
              }
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Crear sesión PHP
                fetch('auth/login.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                  },
                  body: JSON.stringify({
                    email: email,
                    uid: user.uid,
                    role: data.role
                  })
                })
                .then(response => response.json())
                .then(sessionData => {
                  if (sessionData.success) {
                    // Redirigir según el rol
                    if (data.role === 'admin') {
                      window.location.href = 'admin/index.php';
                    } else {
                      window.location.href = 'user/index.php';
                    }
                  } else {
                    showError('Error al iniciar sesión en el sistema');
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Ingresar';
                  }
                });
              } else {
                showError('Usuario no autorizado o no existe en el sistema');
                loginBtn.disabled = false;
                loginBtn.textContent = 'Ingresar';
              }
            });
          })
          .catch((error) => {
            // Manejar errores de autenticación
            const errorCode = error.code;
            let errorMsg = 'Error al iniciar sesión';
            
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
            
            showError(errorMsg);
            loginBtn.disabled = false;
            loginBtn.textContent = 'Ingresar';
          });
      });
    }
  });