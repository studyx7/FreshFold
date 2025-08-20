<?php
require_once 'freshfold_config.php';

// Redirect if already logged in
if(User::isLoggedIn()) {
    redirect('dashboard_page.php');
}

$error = '';
$success = '';

if($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    // Set user properties
    $user->username = $_POST['username'];
    $user->email = $_POST['email'];
    $user->password_hash = $_POST['password']; // Will be hashed in register method
    $user->full_name = $_POST['full_name'];
    $user->phone = $_POST['phone'];
    $user->user_type = 'student'; // Default to student
    $user->hostel_block = $_POST['floor'];
    $user->room_number = $_POST['room_number'];
    $user->gender = $_POST['gender'];
    
    if($user->register()) {
        $success = 'Registration successful! You can now login.';
    } else {
        $error = 'Registration failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --accent-color: #23d5ab;
            --glass-bg: rgba(255,255,255,0.18);
            --glass-border: rgba(255,255,255,0.25);
        }
        body {
            min-height: 100vh;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .particles {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 1;
        }
        .particle {
            position: absolute;
            width: 8px; height: 8px;
            background: rgba(255,255,255,0.7);
            border-radius: 50%;
            opacity: 0;
            animation: particleAnim 7s infinite;
        }
        @keyframes particleAnim {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-100vh) scale(0.5); opacity: 0; }
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
        }
        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            background: var(--glass-bg);
            border: 1.5px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: 0 12px 40px 0 rgba(44,90,160,0.10), 0 1.5px 8px 0 rgba(44,90,160,0.10);
            padding: 48px 36px 36px 36px;
            max-width: 600px;
            width: 100%;
            backdrop-filter: blur(18px);
            animation: fadeInUp 1s cubic-bezier(.39,.575,.565,1.000) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .brand-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-logo i {
            font-size: 3.2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
            filter: drop-shadow(0 2px 8px #23d5ab55);
        }
        .brand-title {
            color: var(--primary-color);
            font-weight: 800;
            font-size: 2.1rem;
            margin: 0;
            letter-spacing: 1px;
        }
        .brand-subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-top: 5px;
            font-weight: 500;
        }
        .form-floating {
            margin-bottom: 22px;
        }
        .form-control, .form-select {
            border-radius: 14px;
            border: 2px solid #e9ecef;
            padding: 14px 18px;
            font-size: 17px;
            background: rgba(255,255,255,0.7);
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.13);
            background: #fff;
        }
        .btn-primary {
            background: linear-gradient(90deg, #2c5aa0 60%, #23d5ab 100%);
            border: none;
            border-radius: 14px;
            padding: 13px 0;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 12px 0 #23d5ab33;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #23d5ab 0%, #2c5aa0 100%);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 24px 0 #23d5ab33;
        }
        .alert {
            border-radius: 12px;
            font-size: 1rem;
            margin-bottom: 18px;
        }
        .text-muted a {
            color: var(--primary-color) !important;
            font-weight: 600;
        }
        .text-muted a:hover {
            color: var(--accent-color) !important;
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .login-card { padding: 32px 10px 24px 10px; }
        }
    </style>
</head>
<body>
<div class="particles" id="particles"></div>
<div class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="login-card shadow-lg">
                    <div class="brand-logo">
                        <i class="fas fa-user-plus"></i>
                        <h1 class="brand-title">Register</h1>
                        <p class="brand-subtitle">Create your FreshFold account</p>
                    </div>
                    <form id="registerForm" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6">
                                <div id="usernameError" class="alert alert-danger py-1 px-2 d-none" style="margin-bottom:6px;"></div>
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <label for="username">Username</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="emailError" class="alert alert-danger py-1 px-2 d-none" style="margin-bottom:6px;"></div>
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <label for="email">Email</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    <label for="full_name">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                    <label for="phone">Phone Number</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <label for="gender">Gender</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="floor" name="floor" required>
                                        <option value="">Choose...</option>
                                        <option value="-1">-1</option>
                                        <option value="Ground Floor">Ground Floor</option>
                                        <option value="1st Floor">1st Floor</option>
                                        <option value="2nd Floor">2nd Floor</option>
                                        <option value="3rd Floor">3rd Floor</option>
                                        <option value="4th Floor">4th Floor</option>
                                    </select>
                                    <label for="floor">Floor</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="room_number" name="room_number" required>
                                    <label for="room_number">Room Number</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <label for="password">Password (minimum 6 characters)</label>
                        </div>
                        <div id="registerError" class="alert alert-danger d-none"></div>
                        <div id="registerSuccess" class="alert alert-success d-none"></div>
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </div>
                        <div class="text-center">
                            <small class="text-muted">
                                Already have an account? 
                                <a href="login_page.php" class="text-decoration-none">Login here</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 24;
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 7 + 's';
        particle.style.animationDuration = (Math.random() * 3 + 4) + 's';
        particlesContainer.appendChild(particle);
    }
}
createParticles();
</script>
<script>
document.getElementById('registerForm').onsubmit = function(e) {
    e.preventDefault();
    let form = this;
    let data = new FormData(form);
    let errorDiv = document.getElementById('registerError');
    let successDiv = document.getElementById('registerSuccess');
    errorDiv.classList.add('d-none');
    successDiv.classList.add('d-none');
    fetch('ajax_register.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                successDiv.textContent = result.message;
                successDiv.classList.remove('d-none');
                form.reset();
            } else {
                errorDiv.textContent = result.error;
                errorDiv.classList.remove('d-none');
            }
        })
        .catch(() => {
            errorDiv.textContent = "Network error. Please try again.";
            errorDiv.classList.remove('d-none');
        });
};
</script>
<script>
function checkField(type, value, errorDivId, inputId) {
    if (!value) {
        document.getElementById(errorDivId).classList.add('d-none');
        return;
    }
    fetch('ajax_check_user.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'type=' + encodeURIComponent(type) + '&value=' + encodeURIComponent(value)
    })
    .then(res => res.json())
    .then(data => {
        const errorDiv = document.getElementById(errorDivId);
        const input = document.getElementById(inputId);
        if (data.exists) {
            errorDiv.textContent = type === 'username' ? 'Username already taken.' : 'Email already registered.';
            errorDiv.classList.remove('d-none');
            input.classList.add('is-invalid');
        } else {
            errorDiv.classList.add('d-none');
            input.classList.remove('is-invalid');
        }
    });
}

// Check username on blur
document.getElementById('username').addEventListener('blur', function() {
    checkField('username', this.value, 'usernameError', 'username');
});

// Check email on blur
document.getElementById('email').addEventListener('blur', function() {
    checkField('email', this.value, 'emailError', 'email');
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>