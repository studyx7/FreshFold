<?php
require_once 'freshfold_config.php';
requireLogin();
requireUserType('student');

$error = '';
$success = '';

// Get student gender
$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$current_user = $user->getUserById($_SESSION['user_id']);
$gender = $current_user['gender'] ?? 'male'; // fallback to male

// Define laundry items for boys and girls
$boy_items = [
    "Shirt", "Pants", "Jeans", "T. Shirts", "Play Pant", "Bermuda", "Inner (Ban)", "Bedsheet", "Blanket", "Lunkey", "Over Coat", "Thorth", "Turkey", "Pillow", "Sweater"
];
$girl_items = [
    "Churidar Top", "Churidar Pant", "Churidar Shalls", "Pants", "Shirts", "T. Shirts", "Over Coat", "Top", "Play Pant", "Bermuda", "Saree", "Midi", "Turkey", "Thorth", "Sweates", "Bedsheet", "Blanket", "Pillow", "Shimmies"
];

// Calculate pickup date logic
function getPickupDate($db) {
    $today = date('Y-m-d');
    // Count requests for today
    $stmt = $db->prepare("SELECT COUNT(*) FROM laundry_requests WHERE created_at >= ? AND created_at < ?");
    $stmt->execute([
        $today . " 00:00:00",
        $today . " 23:59:59"
    ]);
    $today_requests = $stmt->fetchColumn();

    // If more than 30 requests today, add 4 days, else 3 days
    $days_to_add = ($today_requests >= 30) ? 4 : 3;
    $pickup_date = date('Y-m-d', strtotime("+$days_to_add days"));

    // If pickup date is Sunday, move to Monday
    while (date('w', strtotime($pickup_date)) == 0) {
        $pickup_date = date('Y-m-d', strtotime($pickup_date . ' +1 day'));
    }
    return $pickup_date;
}

$calculated_pickup_date = getPickupDate($db);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_date = $_POST['pickup_date']; // hidden field, not editable
    $special_instructions = $_POST['special_instructions'] ?? '';
    $items = $_POST['items'] ?? [];
    $total_items = array_sum($items);

    // Validate total items
    if($total_items > 20) {
        $error = 'You cannot submit more than 20 clothes in a single request.';
    } elseif($total_items < 1) {
        $error = 'Please enter at least one item.';
    } else {
        // Create laundry request
        $laundryRequest = new LaundryRequest($db);
        $request_id = $laundryRequest->createRequest($_SESSION['user_id'], $pickup_date, $special_instructions);

        if($request_id) {
            // Insert items
            $item_names = ($gender === 'female') ? $girl_items : $boy_items;
            foreach($items as $idx => $qty) {
                $qty = intval($qty);
                if($qty > 0 && isset($item_names[$idx])) {
                    $stmt = $db->prepare("INSERT INTO laundry_items (request_id, item_type, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$request_id, $item_names[$idx], $qty]);
                }
            }
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
    <title>New Laundry Request - FreshFold</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            z-index: 10;
            position: relative;
        }
        .request-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1.000) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .laundry-table th, .laundry-table td { text-align: center; vertical-align: middle; }
        .laundry-table input[type="number"] { width: 60px; margin: 0 auto; }
        .total-warning { color: #dc3545; font-weight: 600; }
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
            .particles {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="particles" id="particles"></div>
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
<div class="main-content">
    <div class="request-card">
        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i>New Laundry Request</h2>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" id="laundryForm" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Pickup Date</label>
                <input type="text" class="form-control" value="<?php echo date('l, M j, Y', strtotime($calculated_pickup_date)); ?>" readonly>
                <input type="hidden" name="pickup_date" value="<?php echo $calculated_pickup_date; ?>">
                <div class="form-text text-success">Your pickup date is automatically set based on workload and holidays.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Laundry Items (Max 20 per request)</label>
                <table class="table table-bordered laundry-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Item</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $items = ($gender === 'female') ? $girl_items : $boy_items;
                    foreach($items as $i => $item): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($item); ?></td>
                            <td>
                                <input type="number" min="0" max="20" name="items[<?php echo $i; ?>]" value="0" class="form-control item-qty" required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">TOTAL</th>
                            <th><span id="totalQty">0</span></th>
                        </tr>
                    </tfoot>
                </table>
                <div id="totalWarning" class="total-warning"></div>
            </div>
            <div class="mb-3">
                <label for="special_instructions" class="form-label">Special Instructions (Optional)</label>
                <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg flex-fill">
                    Submit Request
                </button>
                <a href="dashboard_page.php" class="btn btn-outline-secondary btn-lg flex-fill">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
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

const qtyInputs = document.querySelectorAll('.item-qty');
const totalQty = document.getElementById('totalQty');
const totalWarning = document.getElementById('totalWarning');
const form = document.getElementById('laundryForm');

function updateTotal() {
    let total = 0;
    qtyInputs.forEach(input => {
        let val = parseInt(input.value) || 0;
        total += val;
    });
    totalQty.textContent = total;
    if (total > 20) {
        totalWarning.textContent = "You cannot submit more than 20 clothes in a single request.";
    } else {
        totalWarning.textContent = "";
    }
}

qtyInputs.forEach(input => {
    input.addEventListener('input', updateTotal);
});

form.addEventListener('submit', function(e) {
    let total = 0;
    qtyInputs.forEach(input => {
        let val = parseInt(input.value) || 0;
        total += val;
    });
    if (total > 20) {
        totalWarning.textContent = "You cannot submit more than 20 clothes in a single request.";
        e.preventDefault();
    } else if (total < 1) {
        totalWarning.textContent = "Please enter at least one item.";
        e.preventDefault();
    }
});
</script>
</body>
</html>