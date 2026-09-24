<?php
require_once __DIR__ . '/helpers.php';

// Already logged in? Redirect
if (isset($_SESSION['access_token'])) {
    $is_sa = false;
    foreach (($_SESSION['user_roles'] ?? []) as $r) {
        if ($r['role'] === 'SUPER_ADMIN') { $is_sa = true; break; }
    }
    header('Location: ' . ($is_sa ? '/superadmin/dashboard.php' : '/dashboard.php'));
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $fname    = trim($_POST['first_name'] ?? '');
    $lname    = trim($_POST['last_name']  ?? '');
    $gym_name = trim($_POST['gym_name']   ?? '');
    $gym_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['gym_slug'] ?? '')));

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$email || !$fname || !$gym_name || !$gym_slug) {
        $error = 'All required fields must be filled.';
    } else {
        $res = api_request('POST', '/auth/register/owner', [
            'email'     => $email,
            'password'  => $password,
            'first_name'=> $fname,
            'last_name' => $lname,
            'gym_name'  => $gym_name,
            'gym_slug'  => $gym_slug,
        ]);

        if ($res['status'] === 200 || $res['status'] === 201) {
            $success = true;
        } else {
            $error = $res['data']['detail'] ?? 'Registration failed. Email or slug may already be taken.';
        }
    }
}

// Auto-generate slug from gym name via JS
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register Your Gym — GymCircle</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-10">
  <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-10">

    <!-- Logo -->
    <div class="text-center mb-6">
      <h1 class="text-3xl font-bold text-indigo-600">GymCircle</h1>
      <p class="text-gray-500 text-sm mt-1">Register your gym and start managing today</p>
    </div>

    <?php if ($success === true): ?>
      <!-- Success State -->
      <div class="text-center py-8">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa fa-check-circle text-green-500 text-4xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Gym Registered! 🎉</h2>
        <p class="text-gray-500 text-sm mb-6">Your gym account has been created successfully.</p>
        <a href="/index.php"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-xl text-sm transition">
          <i class="fa fa-sign-in-alt mr-2"></i> Login to Dashboard
        </a>
      </div>

    <?php else: ?>

      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5 text-sm">
          <i class="fa fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">

        <!-- Owner Info -->
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase mb-3 tracking-wide">Owner Information</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
              <input name="first_name" type="text" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
              <input name="last_name" type="text" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
          <input name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
            <input name="password" type="password" required minlength="8"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                   placeholder="Min. 8 characters">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
            <input name="confirm" type="password" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
        </div>

        <hr class="border-gray-100">

        <!-- Gym Info -->
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase mb-3 tracking-wide">Gym Information</p>

          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Gym Name <span class="text-red-500">*</span></label>
            <input id="gym_name" name="gym_name" type="text" required
                   value="<?= htmlspecialchars($_POST['gym_name'] ?? '') ?>"
                   placeholder="e.g. FitZone Premium Gym"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                   oninput="autoSlug()">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Gym URL Slug <span class="text-red-500">*</span>
              <span class="text-gray-400 font-normal text-xs">(used in your public page URL)</span>
            </label>
            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-400">
              <span class="bg-gray-50 px-3 py-2.5 text-xs text-gray-400 border-r border-gray-300 whitespace-nowrap">gymcircle.com/</span>
              <input id="gym_slug" name="gym_slug" type="text" required
                     value="<?= htmlspecialchars($_POST['gym_slug'] ?? '') ?>"
                     placeholder="fitzone-premium"
                     class="flex-1 px-3 py-2.5 text-sm outline-none bg-white"
                     oninput="cleanSlug()">
            </div>
            <p class="text-xs text-gray-400 mt-1">
              Your public page: <span id="slug_preview" class="text-indigo-500 font-medium">localhost:8080/public/gym.php?slug=...</span>
            </p>
          </div>
        </div>

        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm transition">
          <i class="fa fa-rocket mr-2"></i> Register My Gym — Free
        </button>

        <p class="text-center text-gray-500 text-sm">
          Already have an account?
          <a href="/index.php" class="text-indigo-600 hover:underline font-medium">Sign In</a>
        </p>

      </form>
    <?php endif; ?>
  </div>
</body>
</html>
<script>
function autoSlug() {
  const name = document.getElementById('gym_name').value;
  const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  document.getElementById('gym_slug').value = slug;
  updatePreview(slug);
}
function cleanSlug() {
  const s = document.getElementById('gym_slug');
  const v = s.value.toLowerCase().replace(/[^a-z0-9-]+/g, '');
  s.value = v;
  updatePreview(v);
}
function updatePreview(slug) {
  document.getElementById('slug_preview').textContent =
    'localhost:8080/public/gym.php?slug=' + (slug || '...');
}
</script>

