<?php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../views/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Fetch user by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // Check if user account status is active
            if (isset($user['status']) && strtolower($user['status']) !== 'active') {
                $error = "Your account is currently disabled. Please contact an administrator.";
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

                header("Location: ../views/dashboard.php");
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-gray-200">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-red-700 tracking-wide uppercase">VERMEX PEST SOLUTIONS</h1>
            <p class="text-xs text-gray-500 font-medium">Transaction Processing & Inventory System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" required
                    class="w-full px-4 py-2 border rounded-lg text-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent transition duration-200"
                    placeholder="Enter your username">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-4 py-2 border rounded-lg text-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent transition duration-200"
                    placeholder="Enter your password">
            </div>

            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 text-white font-bold py-2.5 rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                Log In
            </button>
        </form>

        <div class="mt-6 text-center border-t pt-4">
            <p class="text-xs text-gray-400">Vermex Pest Solutions &copy; <?= date('Y') ?></p>
        </div>
    </div>

</body>

</html>