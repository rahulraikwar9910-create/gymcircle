<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['SUPER_ADMIN']);

$gym_id = $_GET['id'] ?? '';
if (!$gym_id) { header('Location: gyms.php'); exit; }

// Fetch gym details
$gym_res = api_request('GET', '/gyms/' . $gym_id);
$gym = $gym_res['data'] ?? null;

// Fetch members for this gym
$_SESSION['gym_id'] = $gym_id; // temp set for API call
$members_res = api_request('GET', '/clients');
$members = $members_res['data'] ?? [];

$plans_res = api_request('GET', '/memberships/plans');
$plans = $plans_res['data'] ?? [];
unset($_SESSION['gym_id']);
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/superadmin/gyms.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Gyms</a>
</div>

<?php if (!$gym): ?>
  <div class="bg-red-50 text-red-600 p-4 rounded-xl">Gym not found.</div>
<?php else: ?>

<!-- Gym Info Card -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
  <div class="flex justify-between items-start">
    <div>
      <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($gym['name']) ?></h2>
      <p class="text-gray-500 text-sm mt-1">Slug: <span class="font-mono bg-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($gym['slug']) ?></span></p>
    </div>
    <span class="px-3 py-1 rounded-full text-xs font-bold <?= ($gym['status'] ?? '') === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
      <?= htmlspecialchars($gym['status'] ?? 'UNKNOWN') ?>
    </span>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
    <?php $fields = ['email'=>'Email','phone'=>'Phone','city'=>'City','state'=>'State','address'=>'Address','pincode'=>'Pincode']; ?>
    <?php foreach ($fields as $key => $label): ?>
      <?php if (!empty($gym[$key])): ?>
        <div>
          <p class="text-xs text-gray-400 uppercase"><?= $label ?></p>
          <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($gym[$key]) ?></p>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <div>
      <p class="text-xs text-gray-400 uppercase">Registered</p>
      <p class="text-sm font-medium text-gray-700"><?= date('d M Y', strtotime($gym['created_at'] ?? 'now')) ?></p>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Members -->
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between">
      <h3 class="font-semibold text-gray-700"><i class="fa fa-users text-blue-500 mr-2"></i>Members (<?= count($members) ?>)</h3>
    </div>
    <div class="overflow-y-auto max-h-72">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-400">
          <tr><th class="px-4 py-2 text-left">Name</th><th class="px-4 py-2 text-left">Email</th><th class="px-4 py-2">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($members)): ?>
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No members yet.</td></tr>
          <?php else: ?>
            <?php foreach ($members as $mem): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium"><?= htmlspecialchars(($mem['user']['first_name'] ?? '') . ' ' . ($mem['user']['last_name'] ?? '')) ?></td>
                <td class="px-4 py-2 text-gray-500 text-xs"><?= htmlspecialchars($mem['user']['email'] ?? '') ?></td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= ($mem['is_active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>"><?= ($mem['is_active'] ?? false) ? 'Active' : 'Inactive' ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Membership Plans -->
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b">
      <h3 class="font-semibold text-gray-700"><i class="fa fa-id-card text-green-500 mr-2"></i>Membership Plans (<?= count($plans) ?>)</h3>
    </div>
    <div class="overflow-y-auto max-h-72">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-400">
          <tr><th class="px-4 py-2 text-left">Plan</th><th class="px-4 py-2 text-center">Duration</th><th class="px-4 py-2 text-right">Price</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($plans)): ?>
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No plans yet.</td></tr>
          <?php else: ?>
            <?php foreach ($plans as $p): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                <td class="px-4 py-2 text-center"><?= $p['duration_days'] ?> days</td>
                <td class="px-4 py-2 text-right font-bold text-green-600">₹<?= number_format((float)$p['price'], 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
