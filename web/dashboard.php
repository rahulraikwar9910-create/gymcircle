<?php
require_once __DIR__ . '/layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$metrics_res = api_request('GET', '/payments/dashboard-metrics');
$m = $metrics_res['data'] ?? [];

$members_res = api_request('GET', '/clients');
$members = $members_res['data'] ?? [];

$plans_res = api_request('GET', '/memberships/plans');
$plans = $plans_res['data'] ?? [];

// Handle check-in form
$checkin_msg = '';
$checkin_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_email'])) {
    $email = trim($_POST['checkin_email']);
    $res = api_request('POST', '/attendance/checkin', ['member_email' => $email]);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $checkin_msg = 'Check-in logged successfully!';
    } else {
        $checkin_err = $res['data']['detail'] ?? 'Check-in failed.';
    }
}
?>

<div class="flex justify-between items-center mb-6">
  <div>
    <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
    <p class="text-gray-400 text-sm mt-0.5"><?= date('l, d F Y') ?></p>
  </div>
  <a href="/members/add.php"
     class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
    <i class="fa fa-plus"></i> Add Member
  </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-green-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Today's Collection</p>
    <p class="text-2xl font-bold text-gray-800 mt-1">₹<?= number_format((float)($m['today_collections'] ?? 0), 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Monthly Collection</p>
    <p class="text-2xl font-bold text-gray-800 mt-1">₹<?= number_format((float)($m['monthly_collections'] ?? 0), 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Pending Collection</p>
    <p class="text-2xl font-bold text-gray-800 mt-1">₹<?= number_format((float)($m['pending_collections'] ?? 0), 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-red-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Overdue Accounts</p>
    <p class="text-2xl font-bold text-gray-800 mt-1"><?= $m['overdue_accounts'] ?? 0 ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-purple-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Expiring (7 days)</p>
    <p class="text-2xl font-bold text-gray-800 mt-1"><?= $m['expiring_memberships_count'] ?? 0 ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Members Table -->
  <div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
      <h3 class="font-semibold text-gray-700"><i class="fa fa-users text-blue-500 mr-2"></i>Members (<?= count($members) ?>)</h3>
      <a href="/members/index.php" class="text-sm text-indigo-600 hover:underline">View All →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
          <tr>
            <th class="px-6 py-3 text-left">Name</th>
            <th class="px-6 py-3 text-left">Phone</th>
            <th class="px-6 py-3 text-center">Status</th>
            <th class="px-6 py-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($members)): ?>
            <tr>
              <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                No members yet. <a href="/members/add.php" class="text-indigo-600 hover:underline">Add first member →</a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach (array_slice($members, 0, 8) as $mem): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-3 font-medium text-gray-800">
                  <?= htmlspecialchars(($mem['user']['first_name'] ?? '') . ' ' . ($mem['user']['last_name'] ?? '')) ?>
                </td>
                <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($mem['user']['phone'] ?? '—') ?></td>
                <td class="px-6 py-3 text-center">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= ($mem['is_active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= ($mem['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td class="px-6 py-3 text-center">
                  <a href="/members/detail.php?id=<?= urlencode($mem['id']) ?>" class="text-indigo-600 text-xs hover:underline">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="space-y-5">
    <!-- Check-In Console -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-clipboard-check text-green-500 mr-2"></i>Quick Check-In</h3>
      <?php if ($checkin_msg): ?>
        <div class="bg-green-50 text-green-700 px-3 py-2 rounded-lg text-sm mb-3"><?= htmlspecialchars($checkin_msg) ?></div>
      <?php endif; ?>
      <?php if ($checkin_err): ?>
        <div class="bg-red-50 text-red-600 px-3 py-2 rounded-lg text-sm mb-3"><?= htmlspecialchars($checkin_err) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input name="checkin_email" type="email" placeholder="Member email or phone"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-indigo-400 outline-none">
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white text-sm py-2.5 rounded-lg font-medium">
          <i class="fa fa-check mr-1"></i> Verify & Log Attendance
        </button>
      </form>
    </div>

    <!-- Quick Links -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-bolt text-yellow-500 mr-2"></i>Quick Actions</h3>
      <div class="space-y-2">
        <a href="/members/add.php" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
          <i class="fa fa-user-plus text-indigo-500 w-4"></i> Add New Member
        </a>
        <a href="/fees/collect.php" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
          <i class="fa fa-indian-rupee-sign text-green-500 w-4"></i> Collect Fee
        </a>
        <a href="/memberships/plans.php" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
          <i class="fa fa-id-card text-blue-500 w-4"></i> Membership Plans
        </a>
        <a href="/attendance/index.php" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
          <i class="fa fa-clipboard-list text-orange-500 w-4"></i> Attendance Report
        </a>
        <a href="/trainers/index.php" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
          <i class="fa fa-dumbbell text-purple-500 w-4"></i> Manage Trainers
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

