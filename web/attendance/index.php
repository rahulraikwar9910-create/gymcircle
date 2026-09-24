<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$att_res = api_request('GET', '/attendance');
$att = $att_res['data'] ?? [];

$today_count = count(array_filter($att, fn($a) => date('Y-m-d', strtotime($a['check_in_time'] ?? 'now')) === date('Y-m-d')));
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-clipboard-check text-orange-500 mr-2"></i>Attendance</h2>
  <div class="bg-orange-100 text-orange-700 text-sm font-semibold px-4 py-2 rounded-full">
    Today: <?= $today_count ?> check-in(s)
  </div>
</div>

<!-- Quick Check-In Box -->
<?php
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_email'])) {
    $res = api_request('POST', '/attendance/checkin', ['member_email' => trim($_POST['checkin_email'])]);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $msg = 'Check-in logged!';
    } else {
        $err = $res['data']['detail'] ?? 'Failed.';
    }
}
?>
<div class="bg-white rounded-xl shadow-sm p-5 mb-6 max-w-md">
  <h3 class="font-semibold text-gray-700 mb-3"><i class="fa fa-qrcode text-indigo-400 mr-2"></i>Quick Check-In</h3>
  <?php if ($msg): ?><div class="bg-green-50 text-green-700 px-3 py-2 rounded text-sm mb-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bg-red-50 text-red-600 px-3 py-2 rounded text-sm mb-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="POST" class="flex gap-2">
    <input name="checkin_email" type="email" required placeholder="Member email"
           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    <button class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg text-sm font-medium">Check In</button>
  </form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="px-6 py-3 border-b bg-gray-50 text-xs text-gray-400">
    Total <?= count($att) ?> attendance records
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
        <tr>
          <th class="px-6 py-3 text-left">Member</th>
          <th class="px-6 py-3 text-left">Date</th>
          <th class="px-6 py-3 text-left">Check In</th>
          <th class="px-6 py-3 text-left">Check Out</th>
          <th class="px-6 py-3 text-center">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($att)): ?>
          <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No attendance records found.</td></tr>
        <?php else: ?>
          <?php foreach ($att as $a): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3 font-medium text-gray-800">
                <?= htmlspecialchars(($a['client']['user']['first_name'] ?? '') . ' ' . ($a['client']['user']['last_name'] ?? '—')) ?>
              </td>
              <td class="px-6 py-3 text-gray-500"><?= date('d M Y', strtotime($a['check_in_time'] ?? 'now')) ?></td>
              <td class="px-6 py-3 text-gray-700 font-medium"><?= date('h:i A', strtotime($a['check_in_time'] ?? 'now')) ?></td>
              <td class="px-6 py-3 text-gray-500"><?= $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '—' ?></td>
              <td class="px-6 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Present</span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
