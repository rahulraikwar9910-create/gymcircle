<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER']);

$trainers_res = api_request('GET', '/trainers');
$trainers = $trainers_res['data'] ?? [];

$invite_msg = $invite_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'email' => trim($_POST['email'] ?? ''),
        'role'  => 'TRAINER',
    ];
    $res = api_request('POST', '/auth/invite', $data);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $invite_msg = 'Invite token generated: ' . ($res['data']['token'] ?? '');
    } else {
        $invite_err = $res['data']['detail'] ?? 'Failed to generate invite.';
    }
}
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-dumbbell text-purple-500 mr-2"></i>Trainers</h2>
  <span class="bg-purple-100 text-purple-700 text-sm font-semibold px-3 py-1 rounded-full"><?= count($trainers) ?> trainers</span>
</div>

<!-- Invite Trainer -->
<div class="bg-white rounded-xl shadow-sm p-5 mb-6 max-w-lg">
  <h3 class="font-semibold text-gray-700 mb-3"><i class="fa fa-envelope text-indigo-400 mr-2"></i>Invite a Trainer</h3>
  <?php if ($invite_msg): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded text-sm mb-3">
      <?= htmlspecialchars($invite_msg) ?>
    </div>
  <?php endif; ?>
  <?php if ($invite_err): ?>
    <div class="bg-red-50 text-red-600 px-3 py-2 rounded text-sm mb-3"><?= htmlspecialchars($invite_err) ?></div>
  <?php endif; ?>
  <form method="POST" class="flex gap-2">
    <input name="email" type="email" required placeholder="Trainer email"
           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    <button class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
      <i class="fa fa-paper-plane mr-1"></i> Send Invite
    </button>
  </form>
</div>

<!-- Trainers List -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
        <tr>
          <th class="px-6 py-3 text-left">Trainer</th>
          <th class="px-6 py-3 text-left">Specializations</th>
          <th class="px-6 py-3 text-left">Experience</th>
          <th class="px-6 py-3 text-right">Rate/Hr</th>
          <th class="px-6 py-3 text-center">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($trainers)): ?>
          <tr>
            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
              No trainers yet. Invite one above!
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($trainers as $t): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-700 font-bold text-xs">
                    <?= strtoupper(substr($t['user']['first_name'] ?? 'T', 0, 1)) ?>
                  </div>
                  <div>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars(($t['user']['first_name'] ?? '') . ' ' . ($t['user']['last_name'] ?? '')) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($t['user']['email'] ?? '') ?></p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3 text-gray-600">
                <?php $specs = $t['specializations'] ?? []; ?>
                <?php if (empty($specs)): ?>
                  <span class="text-gray-300">—</span>
                <?php else: ?>
                  <?php foreach ($specs as $s): ?>
                    <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full mr-1"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($t['experience_years'] ?? '—') ?> yrs</td>
              <td class="px-6 py-3 text-right font-medium text-gray-800">
                <?= !empty($t['hourly_rate']) ? '₹' . number_format((float)$t['hourly_rate'], 0) : '—' ?>
              </td>
              <td class="px-6 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= ($t['is_available'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                  <?= ($t['is_available'] ?? false) ? 'Available' : 'Unavailable' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
