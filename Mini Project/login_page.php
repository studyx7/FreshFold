<?php
require_once 'freshfold_config.php';

// Redirect if already logged in
if(User::isLoggedIn()) {
    redirect('dashboard_page.php');
}

$error = '';

if($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    $username = $_POST['username'];
    $password = $_POST['password'];

    if($user->login($username, $password)) {
        // Redirect based on user type
        $user_type = $_SESSION['user_type'] ?? '';
        if ($user_type === 'admin') {
            redirect('admin_dashboard.php');
        } elseif ($user_type === 'staff') {
            redirect('staff_dashboard.php');
        } else {
            redirect('dashboard_page.php'); // student dashboard
        }
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FreshFold</title>
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
            max-width: 420px;
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
        .form-control {
            border-radius: 14px;
            border: 2px solid #e9ecef;
            padding: 14px 18px;
            font-size: 17px;
            background: rgba(255,255,255,0.7);
            transition: all 0.3s;
        }
        .form-control:focus {
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
                        <i class="fas fa-tshirt"></i>
                        <h1 class="brand-title">FreshFold</h1>
                        <p class="brand-subtitle">Smart Laundry Management System</p>
                    </div>
                    <?php if($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required autofocus>
                            <label for="username">Username or Email</label>
                        </div>
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                        <div class="text-center">
                            <small class="text-muted">
                                Don't have an account?
                                <a href="register_page.php" class="text-decoration-none">Register here</a>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>