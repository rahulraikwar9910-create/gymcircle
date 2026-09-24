<?php
require_once __DIR__ . '/../helpers.php';
require_login();

$user     = $_SESSION['user']    ?? [];
$roles    = $_SESSION['user_roles'] ?? [];
$gym_id   = $_SESSION['gym_id'] ?? null;
$gym_name = $_SESSION['gym_name'] ?? 'GymCircle';

$is_super_admin = false;
$is_owner       = false;
foreach ($roles as $r) {
    if ($r['role'] === 'SUPER_ADMIN') $is_super_admin = true;
    if ($r['role'] === 'GYM_OWNER')   $is_owner = true;
}

$current = basename($_SERVER['PHP_SELF']);
$dir     = basename(dirname($_SERVER['PHP_SELF']));
function active($pages) {
    global $current, $dir;
    foreach ((array)$pages as $p) {
        if (str_ends_with($p, '/') ? $dir === trim($p,'/') : $current === $p)
            return 'bg-indigo-700 text-white';
    }
    return 'text-indigo-100 hover:bg-indigo-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GymCircle</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex">

<!-- Sidebar -->
<aside class="w-64 bg-indigo-800 text-white flex flex-col min-h-screen fixed top-0 left-0 z-40">
  <div class="px-6 py-5 border-b border-indigo-700">
    <h1 class="text-2xl font-bold text-white">GymCircle</h1>
    <p class="text-indigo-300 text-xs mt-1"><?= htmlspecialchars($gym_name) ?></p>
  </div>

  <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
    <?php if ($is_super_admin): ?>
      <p class="text-indigo-400 text-xs uppercase font-semibold px-3 mb-2">Super Admin</p>
      <a href="/superadmin/dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['dashboard.php']) ?>">
        <i class="fa fa-gauge w-4"></i> Platform Dashboard
      </a>
      <a href="/superadmin/gyms.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['gyms.php']) ?>">
        <i class="fa fa-building w-4"></i> All Gyms
      </a>
      <a href="/superadmin/owners.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['owners.php']) ?>">
        <i class="fa fa-users-gear w-4"></i> Gym Owners
      </a>
      <hr class="border-indigo-700 my-3">
    <?php endif; ?>

    <?php if ($is_owner || $gym_id): ?>
      <p class="text-indigo-400 text-xs uppercase font-semibold px-3 mb-2">Gym Management</p>
      <a href="/dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['dashboard.php']) ?>">
        <i class="fa fa-home w-4"></i> Dashboard
      </a>
      <a href="/members/index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['members/']) ?>">
        <i class="fa fa-users w-4"></i> Members
      </a>
      <a href="/memberships/plans.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['plans.php','add_plan.php']) ?>">
        <i class="fa fa-id-card w-4"></i> Membership Plans
      </a>
      <a href="/fees/index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['fees/']) ?>">
        <i class="fa fa-indian-rupee-sign w-4"></i> Fee Collection
      </a>
      <a href="/attendance/index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['attendance/']) ?>">
        <i class="fa fa-clipboard-check w-4"></i> Attendance
      </a>
      <a href="/trainers/index.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['trainers/']) ?>">
        <i class="fa fa-dumbbell w-4"></i> Trainers
      </a>
      <a href="/profile/gym.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= active(['gym.php']) ?>">
        <i class="fa fa-gear w-4"></i> Gym Settings
      </a>
    <?php endif; ?>
  </nav>

  <div class="px-4 py-4 border-t border-indigo-700">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">
        <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
      </div>
      <div>
        <p class="text-sm font-medium"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
        <p class="text-xs text-indigo-400"><?= $is_super_admin ? 'Super Admin' : 'Gym Owner' ?></p>
      </div>
    </div>
    <a href="/logout.php" class="block w-full text-center bg-indigo-700 hover:bg-red-600 text-white text-sm py-2 rounded-lg transition">
      <i class="fa fa-sign-out-alt mr-1"></i> Sign Out
    </a>
  </div>
</aside>

<!-- Main Content wrapper -->
<div class="ml-64 flex-1 flex flex-col min-h-screen">
  <main class="flex-1 p-6">

