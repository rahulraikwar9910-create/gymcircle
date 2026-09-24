<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER']);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'          => trim($_POST['name'] ?? ''),
        'description'   => trim($_POST['description'] ?? '') ?: null,
        'duration_days' => (int)($_POST['duration_days'] ?? 30),
        'price'         => (float)($_POST['price'] ?? 0),
        'max_sessions'  => !empty($_POST['max_sessions']) ? (int)$_POST['max_sessions'] : null,
    ];
    $res = api_request('POST', '/memberships/plans', $data);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $success = 'Plan created successfully!';
    } else {
        $error = $res['data']['detail'] ?? 'Failed to create plan.';
    }
}

$presets = [
    ['label'=>'Monthly',     'days'=>30,  'icon'=>'fa-calendar-day',   'color'=>'blue',   'desc'=>'1 Month'],
    ['label'=>'Quarterly',   'days'=>90,  'icon'=>'fa-calendar-week',  'color'=>'green',  'desc'=>'3 Months'],
    ['label'=>'Half-Yearly', 'days'=>180, 'icon'=>'fa-calendar-alt',   'color'=>'orange', 'desc'=>'6 Months'],
    ['label'=>'Yearly',      'days'=>365, 'icon'=>'fa-calendar-check', 'color'=>'purple', 'desc'=>'12 Months'],
];
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/memberships/plans.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Plans</a>
</div>

<h2 class="text-2xl font-bold text-gray-800 mb-2"><i class="fa fa-plus-circle text-indigo-500 mr-2"></i>Add Membership Plan</h2>
<p class="text-gray-500 text-sm mb-6">Select a duration preset or enter custom values below.</p>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-5 flex justify-between text-sm">
    <?= htmlspecialchars($success) ?>
    <a href="/memberships/plans.php" class="font-medium underline">View Plans →</a>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Duration Presets -->
<div class="grid grid-cols-4 gap-3 mb-6">
  <?php foreach ($presets as $p): ?>
    <button type="button" onclick="applyPreset('<?= $p['label'] ?>', <?= $p['days'] ?>)"
            class="preset-btn bg-white border-2 border-<?= $p['color'] ?>-200 hover:border-<?= $p['color'] ?>-500 hover:bg-<?= $p['color'] ?>-50 rounded-xl p-4 text-center transition cursor-pointer">
      <i class="fa <?= $p['icon'] ?> text-<?= $p['color'] ?>-500 text-xl mb-2 block"></i>
      <p class="font-bold text-gray-800 text-sm"><?= $p['label'] ?></p>
      <p class="text-xs text-gray-400"><?= $p['desc'] ?></p>
      <p class="text-xs font-semibold text-<?= $p['color'] ?>-600 mt-1"><?= $p['days'] ?> days</p>
    </button>
  <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-xl">
  <form method="POST" id="planForm" class="space-y-5">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Plan Name <span class="text-red-500">*</span></label>
      <input id="plan_name" name="name" type="text" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
             placeholder="e.g. Monthly Basic, Yearly Gold"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
      <textarea name="description" rows="2"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                placeholder="Optional — what's included, benefits, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (Days) <span class="text-red-500">*</span></label>
        <input id="plan_days" name="duration_days" type="number" min="1" required
               value="<?= htmlspecialchars($_POST['duration_days'] ?? '30') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <p id="duration_label" class="text-xs text-gray-400 mt-1"></p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Price (₹) <span class="text-red-500">*</span></label>
        <input id="plan_price" name="price" type="number" min="0" step="0.01" required
               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <p id="price_per_month" class="text-xs text-gray-400 mt-1"></p>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Max Sessions <span class="text-gray-400 font-normal">(optional)</span></label>
      <input name="max_sessions" type="number" min="0" value="<?= htmlspecialchars($_POST['max_sessions'] ?? '') ?>"
             placeholder="Leave blank for unlimited"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-save mr-1"></i> Create Plan
      </button>
      <a href="/memberships/plans.php" class="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</a>
    </div>
  </form>
</div>

<script>
const durationNames = {30:'Monthly', 90:'Quarterly', 180:'Half-Yearly', 365:'Yearly'};

function applyPreset(label, days) {
  document.getElementById('plan_days').value = days;
  if (!document.getElementById('plan_name').value) {
    document.getElementById('plan_name').value = label + ' Plan';
  }
  updateLabels();
}

function updateLabels() {
  const days  = parseInt(document.getElementById('plan_days').value) || 0;
  const price = parseFloat(document.getElementById('plan_price').value) || 0;

  // Duration label
  let dlabel = durationNames[days] || days + ' days';
  document.getElementById('duration_label').textContent = '→ ' + dlabel;

  // Per-month price
  if (days > 0 && price > 0) {
    const pm = Math.round(price / (days / 30));
    document.getElementById('price_per_month').textContent = '≈ ₹' + pm.toLocaleString() + '/month';
  }
}

document.getElementById('plan_days').addEventListener('input', updateLabels);
document.getElementById('plan_price').addEventListener('input', updateLabels);
updateLabels();
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
