<?php
require_once __DIR__ . '/helpers.php';

// Agar already logged in ho to redirect
if (isset($_SESSION['access_token'])) {
    $is_sa = false;
    foreach (($_SESSION['user_roles'] ?? []) as $r) {
        if ($r['role'] === 'SUPER_ADMIN') { $is_sa = true; break; }
    }
    header('Location: ' . ($is_sa ? '/superadmin/dashboard.php' : '/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $res = api_request('POST', '/auth/login', [
        'email'    => $email,
        'password' => $password
    ]);

    if ($res['status'] === 200 && isset($res['data']['access_token'])) {
        $_SESSION['access_token']  = $res['data']['access_token'];
        $_SESSION['refresh_token'] = $res['data']['refresh_token'] ?? '';

        // Get user profile
        $me = api_request('GET', '/auth/me');
        if ($me['status'] === 200) {
            $_SESSION['user']       = $me['data'];
            $_SESSION['user_roles'] = $me['data']['gym_roles'] ?? [];

            // Set gym context for owner/manager/receptionist
            $gym_id = null; $gym_name = '';
            foreach ($_SESSION['user_roles'] as $r) {
                if (in_array($r['role'], ['GYM_OWNER','GYM_MANAGER','RECEPTIONIST','TRAINER','CLIENT']) && $r['gym_id']) {
                    $gym_id = $r['gym_id'];
                    break;
                }
            }

            if ($gym_id) {
                $_SESSION['gym_id'] = $gym_id;
                $gym = api_request('GET', '/gyms/' . $gym_id);
                if ($gym['status'] === 200) {
                    $_SESSION['gym_name'] = $gym['data']['name'] ?? 'My Gym';
                }
            }

            // Redirect based on role
            $is_sa = false;
            foreach ($_SESSION['user_roles'] as $r) {
                if ($r['role'] === 'SUPER_ADMIN') { $is_sa = true; break; }
            }
            header('Location: ' . ($is_sa ? '/superadmin/dashboard.php' : '/dashboard.php'));
            exit;
        }
    } else {
        $error = $res['data']['detail'] ?? 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>GymCircle — Sign In</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-lg p-10 w-full max-w-md">
    <h1 class="text-3xl font-bold text-indigo-600 text-center mb-1">GymCircle</h1>
    <p class="text-gray-500 text-center text-sm mb-6">Manage your gym, members, and trainers</p>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
        <input name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input name="password" type="password" required
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>
      <button type="submit"
              class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-lg transition text-sm">
        Sign In
      </button>
    </form>

    <div class="mt-6 p-3 bg-gray-50 rounded-lg text-xs text-gray-500 text-center">
      <p><strong>Gym Owner:</strong> owner_a@gymcircle.com / password123</p>
      <p class="mt-1"><strong>Super Admin:</strong> superadmin@gymcircle.com / admin123</p>
    </div>

    <div class="mt-4 text-center">
      <p class="text-sm text-gray-500">New gym owner?
        <a href="/register.php" class="text-indigo-600 hover:underline font-semibold">Register your gym →</a>
      </p>
    </div>
  </div>
</body>
</html>


