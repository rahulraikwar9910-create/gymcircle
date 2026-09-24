<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER']);

$plans_res = api_request('GET', '/memberships/plans');
$plans = $plans_res['data'] ?? [];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'delete') {
    $del_res = api_request('DELETE', '/memberships/plans/' . $_POST['plan_id']);
    $success = $del_res['status'] === 200 ? 'Plan deleted.' : '';
    $error   = $del_res['status'] !== 200 ? ($del_res['data']['detail'] ?? 'Delete failed.') : '';
    $plans_res = api_request('GET', '/memberships/plans');
    $plans = $plans_res['data'] ?? [];
}

// Duration label helper
function duration_label(int $days): string {
    if ($days <= 31)  return 'Monthly';
    if ($days <= 45)  return '6 Weeks';
    if ($days <= 92)  return 'Quarterly';
    if ($days <= 185) return 'Half-Yearly';
    if ($days >= 360) return 'Yearly';
    return $days . ' days';
}

function duration_color(int $days): string {
    if ($days <= 31)  return 'bg-blue-100 text-blue-700';
    if ($days <= 92)  return 'bg-green-100 text-green-700';
    if ($days <= 185) return 'bg-orange-100 text-orange-700';
    if ($days >= 360) return 'bg-purple-100 text-purple-700';
    return 'bg-gray-100 text-gray-600';
}
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-id-card text-blue-500 mr-2"></i>Membership Plans</h2>
  <a href="/memberships/add_plan.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
    <i class="fa fa-plus mr-1"></i> Add Plan
  </a>
</div>

<?php if ($success): ?><div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="bg-red-50 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Duration Overview Row -->
<div class="grid grid-cols-4 gap-3 mb-6">
  <?php
  $durations = ['Monthly'=>30, 'Quarterly'=>90, 'Half-Yearly'=>180, 'Yearly'=>365];
  $colors = ['Monthly'=>'blue','Quarterly'=>'green','Half-Yearly'=>'orange','Yearly'=>'purple'];
  foreach ($durations as $label => $days):
    $found = array_filter($plans, fn($p) => abs($p['duration_days'] - $days) <= 5);
    $c = $colors[$label];
  ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
      <p class="text-xs font-semibold text-<?= $c ?>-600 uppercase"><?= $label ?></p>
      <p class="text-2xl font-bold text-gray-800 mt-1"><?= count($found) ?></p>
      <p class="text-xs text-gray-400 mt-0.5"><?= $days ?> days</p>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  <?php if (empty($plans)): ?>
    <div class="col-span-3 bg-white rounded-xl p-10 text-center text-gray-400">
      No plans yet. <a href="/memberships/add_plan.php" class="text-indigo-600 hover:underline">Create first plan →</a>
    </div>
  <?php else: ?>
    <?php foreach ($plans as $p):
      $days  = (int)($p['duration_days'] ?? 30);
      $dlabel = duration_label($days);
      $dcolor = duration_color($days);
    ?>
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-indigo-200 hover:shadow-md transition overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-500 to-indigo-700 p-5 text-white">
          <div class="flex justify-between items-start">
            <h3 class="font-bold text-lg"><?= htmlspecialchars($p['name']) ?></h3>
            <span class="text-2xl font-bold">₹<?= number_format((float)$p['price'], 0) ?></span>
          </div>
          <p class="text-indigo-200 text-sm mt-1"><?= htmlspecialchars($p['description'] ?? '') ?></p>
        </div>

        <!-- Body -->
        <div class="p-4 space-y-3">
          <div class="flex items-center justify-between">
            <span class="<?= $dcolor ?> text-xs font-bold px-3 py-1 rounded-full">
              <i class="fa fa-calendar mr-1"></i><?= $dlabel ?>
            </span>
            <span class="text-gray-500 text-xs"><?= $days ?> days</span>
          </div>

          <?php
          // Per-month rate calculation
          $per_month = $days > 0 ? round((float)$p['price'] / ($days / 30), 0) : 0;
          ?>
          <div class="bg-gray-50 rounded-lg px-3 py-2 flex justify-between items-center">
            <span class="text-xs text-gray-500">Effective rate</span>
            <span class="text-sm font-semibold text-gray-700">₹<?= number_format($per_month, 0) ?>/month</span>
          </div>

          <?php if (!empty($p['max_sessions'])): ?>
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <i class="fa fa-dumbbell text-purple-400"></i>
              <span><?= $p['max_sessions'] ?> sessions included</span>
            </div>
          <?php endif; ?>

          <div class="flex gap-2 pt-2 border-t border-gray-100">
            <a href="/memberships/assign.php?plan_id=<?= urlencode($p['id']) ?>"
               class="flex-1 text-center bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 rounded-lg">
              Assign to Member
            </a>
            <form method="POST" onsubmit="return confirm('Delete this plan?')">
              <input type="hidden" name="_action"  value="delete">
              <input type="hidden" name="plan_id" value="<?= htmlspecialchars($p['id']) ?>">
              <button class="bg-red-50 hover:bg-red-100 text-red-500 text-xs px-3 py-2 rounded-lg border border-red-200">
                <i class="fa fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

