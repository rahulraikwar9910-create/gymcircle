<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: /members/index.php'); exit; }

$mem_res  = api_request('GET', '/clients/' . $id);
$mem      = $mem_res['data'] ?? null;

$fees_res = api_request('GET', '/fees/ledger?client_profile_id=' . $id);
$fees     = $fees_res['data'] ?? [];

$att_res  = api_request('GET', '/attendance?client_profile_id=' . $id);
$att      = $att_res['data'] ?? [];

$mship_res = api_request('GET', '/memberships?client_profile_id=' . $id);
$mships    = $mship_res['data'] ?? [];
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/members/index.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Members</a>
</div>

<?php if (!$mem): ?>
  <div class="bg-red-50 text-red-600 p-4 rounded-xl">Member not found.</div>
<?php else:
  $user = $mem['user'] ?? [];
  $fullname = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
?>

<!-- Profile Card -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
  <div class="flex items-start justify-between">
    <div class="flex items-center gap-5">
      <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 text-2xl font-bold">
        <?= strtoupper(substr($user['first_name'] ?? 'M', 0, 1)) ?>
      </div>
      <div>
        <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($fullname) ?></h2>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($user['email'] ?? '—') ?></p>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($user['phone'] ?? '—') ?></p>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="/fees/collect.php?member_id=<?= urlencode($id) ?>&name=<?= urlencode($fullname) ?>"
         class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg">
        <i class="fa fa-indian-rupee-sign mr-1"></i> Collect Fee
      </a>
      <a href="/memberships/assign.php?member_id=<?= urlencode($id) ?>&name=<?= urlencode($fullname) ?>"
         class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
        <i class="fa fa-id-card mr-1"></i> Assign Plan
      </a>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-5 border-t border-gray-100">
    <?php $info = ['gender'=>'Gender','dob'=>'Date of Birth','address'=>'Address','emergency_contact'=>'Emergency Contact','bmi'=>'BMI']; ?>
    <?php foreach ($info as $key => $label): ?>
      <?php if (!empty($mem[$key])): ?>
        <div>
          <p class="text-xs text-gray-400 uppercase"><?= $label ?></p>
          <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($mem[$key]) ?></p>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <div>
      <p class="text-xs text-gray-400 uppercase">Status</p>
      <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= ($mem['is_active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
        <?= ($mem['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
      </span>
    </div>
    <div>
      <p class="text-xs text-gray-400 uppercase">Joined</p>
      <p class="text-sm font-medium text-gray-700"><?= date('d M Y', strtotime($mem['created_at'] ?? 'now')) ?></p>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Memberships -->
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
      <h3 class="font-semibold text-gray-700"><i class="fa fa-id-card text-blue-500 mr-2"></i>Memberships</h3>
      <a href="/memberships/assign.php?member_id=<?= urlencode($id) ?>&name=<?= urlencode($fullname) ?>" class="text-xs text-indigo-600 hover:underline">+ Assign</a>
    </div>
    <div class="p-4 space-y-2">
      <?php if (empty($mships)): ?>
        <p class="text-sm text-gray-400 text-center py-4">No memberships assigned yet.</p>
      <?php else: ?>
        <?php foreach ($mships as $ms): ?>
          <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ms['plan']['name'] ?? '—') ?></p>
              <p class="text-xs text-gray-400"><?= date('d M Y', strtotime($ms['start_date'] ?? '')) ?> → <?= date('d M Y', strtotime($ms['end_date'] ?? '')) ?></p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= ($ms['status'] ?? '') === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
              <?= htmlspecialchars($ms['status'] ?? '—') ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Fee History -->
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b flex justify-between items-center">
      <h3 class="font-semibold text-gray-700"><i class="fa fa-indian-rupee-sign text-green-500 mr-2"></i>Fee History</h3>
      <a href="/fees/collect.php?member_id=<?= urlencode($id) ?>&name=<?= urlencode($fullname) ?>" class="text-xs text-green-600 hover:underline">+ Collect</a>
    </div>
    <div class="p-4 space-y-2">
      <?php if (empty($fees)): ?>
        <p class="text-sm text-gray-400 text-center py-4">No fee records found.</p>
      <?php else: ?>
        <?php foreach (array_slice($fees, 0, 5) as $f): ?>
          <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($f['description'] ?? 'Fee') ?></p>
              <p class="text-xs text-gray-400"><?= date('d M Y', strtotime($f['created_at'] ?? '')) ?></p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold <?= ($f['entry_type'] ?? '') === 'CREDIT' ? 'text-green-600' : 'text-red-500' ?>">
                <?= ($f['entry_type'] ?? '') === 'CREDIT' ? '+' : '-' ?>₹<?= number_format((float)($f['amount'] ?? 0), 0) ?>
              </p>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($f['payment_mode'] ?? '') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Attendance -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6">
  <div class="px-6 py-4 border-b">
    <h3 class="font-semibold text-gray-700"><i class="fa fa-clipboard-check text-orange-500 mr-2"></i>Recent Attendance</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-400">
        <tr><th class="px-6 py-2 text-left">Date</th><th class="px-6 py-2 text-left">Check In</th><th class="px-6 py-2 text-left">Check Out</th><th class="px-6 py-2 text-center">Status</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($att)): ?>
          <tr><td colspan="4" class="px-6 py-6 text-center text-gray-400">No attendance records.</td></tr>
        <?php else: ?>
          <?php foreach (array_slice($att, 0, 10) as $a): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-2"><?= date('d M Y', strtotime($a['check_in_time'] ?? 'now')) ?></td>
              <td class="px-6 py-2"><?= date('h:i A', strtotime($a['check_in_time'] ?? 'now')) ?></td>
              <td class="px-6 py-2"><?= $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '—' ?></td>
              <td class="px-6 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Present</span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
