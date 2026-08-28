<?php
require_once __DIR__ . '/helpers.php';

$error = null;

if (isset($_SESSION['access_token'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $response = api_request('POST', '/auth/login', [
            'email' => $email,
            'password' => $password
        ]);

        if ($response['status'] === 200) {
            $_SESSION['access_token'] = $response['data']['access_token'];

            // Fetch user details for role checking
            $me_res = api_request('GET', '/auth/me');
            if ($me_res['status'] === 200) {
                $user = $me_res['data'];
                $_SESSION['user_roles'] = $user['gym_roles'];
                
                // Set default gym context if roles exist
                if (!empty($user['gym_roles'])) {
                    $_SESSION['gym_id'] = $user['gym_roles'][0]['gym_id'];
                }
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Failed to fetch user roles context.';
                session_destroy();
            }
        } else {
            $error = $response['data']['detail'] ?? 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymCircle - Owner & Staff Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-indigo-600">GymCircle</h1>
            <p class="text-gray-500">Manage your gym, members, and trainers</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="email" id="email" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</body>
</html>
