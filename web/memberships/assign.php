<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$member_id   = $_GET['member_id'] ?? '';
$member_name = $_GET['name'] ?? '';
$plan_id_pre = $_GET['plan_id'] ?? '';

$plans_res = api_request('GET', '/memberships/plans');
$plans = $plans_res['data'] ?? [];

$members_res = api_request('GET', '/clients');
$members = $members_res['data'] ?? [];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_profile_id' => $_POST['member_id'],
        'plan_id'           => $_POST['plan_id'],
        'start_date'        => $_POST['start_date'],
    ];
    $res = api_request('POST', '/memberships', $data);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $success = 'Membership assigned successfully!';
    } else {
        $error = $res['data']['detail'] ?? 'Failed to assign membership.';
    }
}
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/memberships/plans.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Plans</a>
</div>

<h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa fa-id-card text-blue-500 mr-2"></i>Assign Membership Plan</h2>

<?php if ($success): ?>
  <div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
  <form method="POST" class="space-y-5">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Select Member <span class="text-red-500">*</span></label>
      <select name="member_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <option value="">— Select Member —</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= htmlspecialchars($m['id']) ?>" <?= $m['id'] === $member_id ? 'selected' : '' ?>>
            <?= htmlspecialchars(($m['user']['first_name'] ?? '') . ' ' . ($m['user']['last_name'] ?? '') . ' (' . ($m['user']['email'] ?? '') . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Select Plan <span class="text-red-500">*</span></label>
      <select name="plan_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <option value="">— Select Plan —</option>
        <?php foreach ($plans as $p): ?>
          <option value="<?= htmlspecialchars($p['id']) ?>" <?= $p['id'] === $plan_id_pre ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?> — <?= $p['duration_days'] ?> days — ₹<?= number_format((float)$p['price'], 0) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
      <input name="start_date" type="date" required value="<?= date('Y-m-d') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-check mr-1"></i> Assign Membership
      </button>
      <a href="/members/index.php" class="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

