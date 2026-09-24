<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER']);

$trainers_res = api_request('GET', '/trainers');
$trainers     = $trainers_res['data'] ?? [];

$invite_msg = $invite_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'invite') {
    $res = api_request('POST', '/auth/invite', ['email' => trim($_POST['email'] ?? ''), 'role' => 'TRAINER']);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $token = $res['data']['token'] ?? '';
        $invite_msg = 'Invite token: ' . $token;
    } else {
        $invite_err = $res['data']['detail'] ?? 'Failed to generate invite.';
    }
}
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-dumbbell text-purple-500 mr-2"></i>Trainers</h2>
  <span class="bg-purple-100 text-purple-700 text-sm font-semibold px-3 py-1 rounded-full"><?= count($trainers) ?> trainers</span>
</div>

<!-- Invite Box -->
<div class="bg-white rounded-xl shadow-sm p-5 mb-6 max-w-lg">
  <h3 class="font-semibold text-gray-700 mb-3"><i class="fa fa-envelope text-indigo-400 mr-2"></i>Invite a Trainer</h3>
  <?php if ($invite_msg): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded text-sm mb-3 break-all"><?= htmlspecialchars($invite_msg) ?></div>
  <?php endif; ?>
  <?php if ($invite_err): ?>
    <div class="bg-red-50 text-red-600 px-3 py-2 rounded text-sm mb-3"><?= htmlspecialchars($invite_err) ?></div>
  <?php endif; ?>
  <form method="POST" class="flex gap-2">
    <input type="hidden" name="_action" value="invite">
    <input name="email" type="email" required placeholder="Trainer email"
           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    <button class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
      <i class="fa fa-paper-plane mr-1"></i> Invite
    </button>
  </form>
</div>

<!-- Trainers Grid -->
<?php if (empty($trainers)): ?>
  <div class="bg-white rounded-xl p-10 text-center text-gray-400">No trainers yet. Invite one above!</div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($trainers as $t):
      $tid  = $t['id'] ?? '';
      $name = ($t['user']['first_name'] ?? '') . ' ' . ($t['user']['last_name'] ?? '');
      $specs = $t['specializations'] ?? [];
      $pub_url = '/public/trainer.php?id=' . urlencode($tid);
      $edit_url = '/trainers/edit_profile.php?id=' . urlencode($tid);
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-purple-200 hover:shadow-md transition overflow-hidden">
      <div class="bg-gradient-to-r from-purple-500 to-indigo-600 p-5 text-white">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 bg-white/30 rounded-full flex items-center justify-center text-2xl font-bold">
            <?= strtoupper(substr($name,0,1)) ?>
          </div>
          <div>
            <h3 class="font-bold text-lg"><?= htmlspecialchars($name) ?></h3>
            <p class="text-purple-200 text-xs"><?= htmlspecialchars($t['user']['email'] ?? '') ?></p>
          </div>
        </div>
      </div>
      <div class="p-5">
        <!-- Specializations -->
        <div class="flex flex-wrap gap-1 mb-3">
          <?php if (empty($specs)): ?>
            <span class="text-xs text-gray-400">No specializations added</span>
          <?php else: ?>
            <?php foreach ($specs as $s): ?>
              <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full"><?= htmlspecialchars($s['name']??'') ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 mb-4">
          <div><i class="fa fa-clock text-indigo-400 mr-1"></i><?= $t['experience_years'] ?? '—' ?> yrs exp</div>
          <div><i class="fa fa-indian-rupee-sign text-green-500 mr-1"></i><?= !empty($t['hourly_rate']) ? number_format((float)$t['hourly_rate'],0).'/hr' : '—' ?></div>
          <div>
            <span class="<?= ($t['is_available']??false) ? 'text-green-600' : 'text-red-500' ?>">
              <i class="fa fa-circle text-xs mr-1"></i><?= ($t['is_available']??false) ? 'Available' : 'Unavailable' ?>
            </span>
          </div>
        </div>

        <div class="flex gap-2">
          <a href="<?= $edit_url ?>"
             class="flex-1 text-center bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 rounded-lg font-medium">
            <i class="fa fa-edit mr-1"></i> Edit Profile
          </a>
          <a href="<?= $pub_url ?>" target="_blank"
             class="flex-1 text-center bg-purple-100 hover:bg-purple-200 text-purple-700 text-xs py-2 rounded-lg font-medium">
            <i class="fa fa-eye mr-1"></i> Public Page
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

