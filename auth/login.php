<?php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Route based on existing session role if already logged in
    switch ($_SESSION['role'] ?? '') {
        case 'Chemical Custodian':
            header("Location: ../views/chemical-custodian/dashboard.php");
            exit();
        case 'Field Technician':
            header("Location: ../views/field-technician/dashboard.php");
            exit();
        case 'Billing Officer':
            header("Location: ../views/billing-officer/dashboard.php");
            exit();
        case 'Admin':
        default:
            header("Location: ../views/dashboard.php");
            exit();
    }
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        // Fetch user by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // Check if user account status is active
            if (isset($user['status']) && strtolower($user['status']) !== 'active') {
                $error = "Your account is currently disabled. Please contact a system administrator.";
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name']  = $user['last_name'];
                $_SESSION['role']       = $user['role'];

                // Update last_active timestamp
                $updateStmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Role-based redirection upon successful login
                switch ($user['role']) {
                    case 'Chemical Custodian':
                        header("Location: ../views/chemical-custodian/dashboard.php");
                        break;
                    case 'Field Technician':
                        header("Location: ../views/field-technician/dashboard.php");
                        break;
                    case 'Billing Officer':
                        header("Location: ../views/billing-officer/payments.php");
                        break;
                    case 'Admin':
                    default:
                        header("Location: ../views/dashboard.php");
                        break;
                }
                exit();
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vermex Pest Solutions</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gradient-to-br from-rose-950 via-zinc-950 to-black text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans antialiased relative overflow-hidden">

    <!-- Glowing Background Lights -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-red-700/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-rose-900/20 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Login Container Box -->
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-md border border-slate-800 rounded-2xl shadow-2xl overflow-hidden relative z-10">
        
        <!-- Top Crimson Accent Bar -->
        <div class="h-1.5 bg-gradient-to-r from-red-600 to-rose-700 w-full"></div>

        <div class="p-8 space-y-6">

            <!-- Brand Header -->
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-black text-white tracking-wider uppercase">VERMEX PEST SOLUTIONS</h1>
                <p class="text-xs text-slate-400 font-medium">Transaction Processing & Inventory System</p>
            </div>

            <!-- Error Notification Alert -->
            <?php if (!empty($error)): ?>
                <div class="bg-rose-950/80 border border-rose-800/80 text-rose-200 px-4 py-3 rounded-xl text-xs flex items-center justify-between shadow-sm animate-fade-in">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 flex-shrink-0"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white ml-2">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-4 text-xs">
                
                <!-- Username Input -->
                <div>
                    <label for="username" class="block text-slate-300 font-semibold mb-1.5">Username or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <input type="text" id="username" name="username" required
                            class="w-full pl-9 pr-3 py-2.5 bg-slate-950/70 border border-slate-800 text-white placeholder-slate-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-500 transition text-xs font-medium"
                            placeholder="Enter username or email">
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-slate-300 font-semibold">Password</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" id="password" name="password" required
                            class="w-full pl-9 pr-10 py-2.5 bg-slate-950/70 border border-slate-800 text-white placeholder-slate-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-500 transition text-xs font-medium"
                            placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition">
                            <i id="passwordEyeIcon" data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-red-700 to-rose-700 hover:from-red-600 hover:to-rose-600 text-white font-bold py-2.5 rounded-lg transition duration-200 shadow-lg shadow-red-950/50 flex items-center justify-center gap-2 text-xs mt-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>LOG IN</span>
                </button>
            </form>

            <!-- Footer Section -->
            <div class="border-t border-slate-800/80 pt-4 text-center">
                <p class="text-[11px] text-slate-500">
                    Vermex Pest Solutions &copy; <?= date('Y') ?> • Authorized Access Only
                </p>
            </div>

        </div>
    </div>

    <!-- Script Utilities -->
    <script>
        lucide.createIcons();

        function togglePasswordVisibility() {
            const passInput = document.getElementById('password');
            const eyeIcon = document.getElementById('passwordEyeIcon');

            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passInput.type = 'password';
                eyeIcon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
</body>

</html>