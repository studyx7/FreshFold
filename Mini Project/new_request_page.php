<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$error = '';
$success = '';

if($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    $laundryRequest = new LaundryRequest($db);
    
    $pickup_date = $_POST['pickup_date'];
    $special_instructions = $_POST['special_instructions'] ?? '';
    
    // Validate pickup date (should be at least tomorrow)
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if($pickup_date < $tomorrow) {
        $error = 'Pickup date must be at least tomorrow.';
    } else {
        $request_id = $laundryRequest->createRequest($_SESSION['user_id'], $pickup_date, $special_instructions);
        
        if($request_id) {
            showAlert('Laundry request created successfully! Your request ID is #' . $request_id, 'success');
            redirect('my_requests_page.php');
        } else {
            $error = 'Failed to create request. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Request - FreshFold</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --sidebar-width: 250px;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, var(--primary-color) 0%, #1e3d6f 100%);
        }

        body {
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

        /* Floating particles background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 1; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.8; }
        }

        /* Sidebar with glassmorphism */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            overflow-y: auto;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .sidebar:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .sidebar .brand {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 30px;
            animation: slideInFromTop 0.8s ease-out;
            position: relative;
        }
        .sidebar .brand i {
            position: relative;
            display: inline-block;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
            transition: all 0.3s ease;
        }
        .sidebar .brand:hover i {
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5));
            transform: scale(1.05);
        }
        @keyframes slideInFromTop {
            0% { transform: translateY(-50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .sidebar .brand h4 {
            margin: 10px 0 5px 0;
            font-weight: 700;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        .sidebar .brand h4::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .sidebar .brand:hover h4::after {
            width: 100%;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border: none;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            text-decoration: none;
            margin: 2px 0;
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .sidebar .nav-link:hover::before {
            left: 100%;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            border-radius: 0 25px 25px 0;
            margin-right: 20px;
            transform: translateX(10px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link:hover i {
            transform: scale(1.2) rotate(5deg);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }

        /* Animated info and form cards */
        .info-card, .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .info-card.visible, .form-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .info-card::before, .form-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(44, 90, 160, 0.07), transparent);
            transition: transform 0.6s;
            transform: rotate(0deg);
        }
        .info-card:hover::before, .form-card:hover::before {
            transform: rotate(180deg);
        }
        .info-card:hover, .form-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 1);
        }

        .service-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }
        .btn-primary {
            background: var(--gradient-2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn-primary:hover::before {
            width: 300px;
            height: 300px;
        }
        .btn-primary:hover {
            background-color: #1e3d6f;
            transform: translateY(-2px) scale(1.03);
            color: white;
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.15);
        }
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
            font-size: 1rem;
        }
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .info-card, .form-card {
                padding: 18px 8px;
            }
            .particles {
                display: none;
            }
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>

<!-- Floating Particles Background -->
<div class="particles" id="particles"></div>

<!-- Mobile menu toggle -->
<button class="btn btn-primary d-md-none position-fixed" style="top: 20px; left: 20px; z-index: 1001;" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-tshirt fa-2x mb-2"></i>
        <h4>FreshFold</h4>
        <small>Laundry Management</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard_page.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link active" href="new_request_page.php">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
        <a class="nav-link" href="my_requests_page.php">
            <i class="fas fa-list"></i> My Requests
        </a>
        <a class="nav-link" href="issue_report_page.php">
            <i class="fas fa-exclamation-triangle"></i> Report Issue
        </a>
        <a class="nav-link" href="profile_page.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <hr style="border-color: rgba(255,255,255,0.2); margin: 20px;">
        <a class="nav-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4" style="color:white; text-shadow:2px 2px 8px rgba(44,90,160,0.2);">
                    <i class="fas fa-plus-circle me-2"></i>Create New Laundry Request
                </h2>
                
                <!-- Service Information -->
                <div class="info-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h4><i class="fas fa-info-circle me-2"></i>Service Information</h4>
                            <p class="mb-2">Standard laundry service includes washing, drying, and folding.</p>
                            <p class="mb-0">Pickup and delivery are handled by our professional staff.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-clock fa-3x mb-2 opacity-75"></i>
                            <h5>3-Day Service</h5>
                            <small>Standard turnaround time</small>
                        </div>
                    </div>
                </div>

                <!-- Request Form -->
                <div class="form-card">
                    <?php if($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="pickup_date" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>Preferred Pickup Date
                                    </label>
                                    <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                    <div class="form-text">Pickup will be scheduled between 9:00 AM - 5:00 PM</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-info-circle me-2"></i>Your Information
                                    </label>
                                    <div class="bg-light p-3 rounded">
                                        <strong><?php echo $_SESSION['full_name']; ?></strong><br>
                                        <small class="text-muted">
                                            Floor <?php echo $_SESSION['hostel_block']; ?> - Room <?php echo $_SESSION['room_number']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="special_instructions" class="form-label">
                                <i class="fas fa-sticky-note me-2"></i>Special Instructions (Optional)
                            </label>
                            <textarea class="form-control" id="special_instructions" name="special_instructions" 
                                      rows="4" placeholder="Any special care instructions for your laundry..."></textarea>
                            <div class="form-text">
                                Examples: Separate whites from colors, gentle cycle for delicates, air dry only, etc.
                            </div>
                        </div>

                        <!-- Service Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="service-item">
                                    <h6><i class="fas fa-tshirt me-2"></i>What's Included</h6>
                                    <ul class="mb-0">
                                        <li>Washing & Drying</li>
                                        <li>Folding & Organizing</li>
                                        <li>Free Pickup & Delivery</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="service-item">
                                    <h6><i class="fas fa-clock me-2"></i>Service Timeline</h6>
                                    <ul class="mb-0">
                                        <li>Day 1: Pickup from your room</li>
                                        <li>Day 2-3: Washing & Processing</li>
                                        <li>Day 4: Delivery to your room</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                            <a href="dashboard_page.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Floating particles effect
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 20;
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
        particlesContainer.appendChild(particle);
    }
}
createParticles();

// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Animate cards on scroll
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.info-card, .form-card').forEach(el => {
        observer.observe(el);
    });
}
addScrollAnimations();

// Set minimum date to tomorrow
document.getElementById('pickup_date').min = new Date(Date.now() + 86400000).toISOString().split('T')[0];
</script>
</body>
</html>