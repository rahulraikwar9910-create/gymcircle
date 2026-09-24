<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['SUPER_ADMIN']);

// Platform Metrics
$metrics = api_request('GET', '/admin/metrics');
$m = $metrics['data'] ?? [];

// All Gyms
$gyms_res = api_request('GET', '/admin/gyms');
$gyms = $gyms_res['data'] ?? [];
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6">
  <i class="fa fa-gauge text-indigo-500 mr-2"></i>Platform Dashboard
</h2>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-indigo-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Total Gyms</p>
    <p class="text-3xl font-bold text-gray-800 mt-1"><?= $m['total_gyms_count'] ?? 0 ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-green-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Total Users</p>
    <p class="text-3xl font-bold text-gray-800 mt-1"><?= $m['total_users_count'] ?? 0 ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Active Memberships</p>
    <p class="text-3xl font-bold text-gray-800 mt-1"><?= $m['active_memberships_count'] ?? 0 ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Platform Revenue</p>
    <p class="text-2xl font-bold text-gray-800 mt-1">₹<?= number_format((float)($m['total_platform_revenue'] ?? 0), 2) ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-purple-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Platform Fees Collected</p>
    <p class="text-2xl font-bold text-gray-800 mt-1">₹<?= number_format((float)($m['total_platform_fees_collected'] ?? 0), 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-orange-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Active Trainers</p>
    <p class="text-3xl font-bold text-gray-800 mt-1"><?= $m['active_trainers_count'] ?? 0 ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-pink-500">
    <p class="text-xs text-gray-500 font-medium uppercase">Users by Role</p>
    <div class="mt-1 space-y-1">
      <?php foreach (($m['users_by_role'] ?? []) as $role => $count): ?>
        <div class="flex justify-between text-sm">
          <span class="text-gray-600"><?= htmlspecialchars($role) ?></span>
          <span class="font-bold text-gray-800"><?= $count ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- All Gyms Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
    <h3 class="font-semibold text-gray-700">All Gyms</h3>
    <a href="/superadmin/gyms.php" class="text-sm text-indigo-600 hover:underline">View All →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500">
        <tr>
          <th class="px-6 py-3 text-left">Gym Name</th>
          <th class="px-6 py-3 text-left">Slug</th>
          <th class="px-6 py-3 text-center">Members</th>
          <th class="px-6 py-3 text-right">Revenue</th>
          <th class="px-6 py-3 text-center">Details</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($gyms)): ?>
          <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No gyms registered yet.</td></tr>
        <?php else: ?>
          <?php foreach (array_slice($gyms, 0, 5) as $g): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars((string)($g['name'] ?? '')) ?></td>
              <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars((string)($g['slug'] ?? '')) ?></td>
              <td class="px-6 py-3 text-center"><?= (int)($g['active_members_count'] ?? 0) ?></td>
              <td class="px-6 py-3 text-right font-semibold text-green-600">₹<?= number_format((float)($g['total_revenue_generated'] ?? 0), 2) ?></td>
              <td class="px-6 py-3 text-center">
                <a href="/superadmin/gym_detail.php?id=<?= urlencode((string)($g['gym_id'] ?? '')) ?>"
                   class="text-indigo-600 hover:underline text-xs">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
