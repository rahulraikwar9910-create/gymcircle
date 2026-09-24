<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$search = trim($_GET['q'] ?? '');
$members_res = api_request('GET', '/clients');
$members = $members_res['data'] ?? [];

if ($search) {
    $members = array_filter($members, function($m) use ($search) {
        $name = strtolower(($m['user']['first_name'] ?? '') . ' ' . ($m['user']['last_name'] ?? ''));
        $email = strtolower($m['user']['email'] ?? '');
        $phone = $m['user']['phone'] ?? '';
        return str_contains($name, strtolower($search)) || str_contains($email, strtolower($search)) || str_contains($phone, $search);
    });
}
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-users text-blue-500 mr-2"></i>Members</h2>
  <a href="/members/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
    <i class="fa fa-plus mr-1"></i> Add Member
  </a>
</div>

<!-- Search -->
<form method="GET" class="mb-5">
  <div class="flex gap-3">
    <input name="q" value="<?= htmlspecialchars($search) ?>" type="text" placeholder="Search by name, email or phone..."
           class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    <button type="submit" class="bg-gray-700 text-white px-5 py-2.5 rounded-lg text-sm">Search</button>
    <?php if ($search): ?><a href="/members/index.php" class="px-4 py-2.5 rounded-lg border text-sm text-gray-600">Clear</a><?php endif; ?>
  </div>
</form>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="px-6 py-3 border-b bg-gray-50 text-xs text-gray-400">
    Showing <?= count($members) ?> member(s)
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
        <tr>
          <th class="px-6 py-3 text-left">Name</th>
          <th class="px-6 py-3 text-left">Email</th>
          <th class="px-6 py-3 text-left">Phone</th>
          <th class="px-6 py-3 text-left">Gender</th>
          <th class="px-6 py-3 text-center">Status</th>
          <th class="px-6 py-3 text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($members)): ?>
          <tr>
            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
              No members found. <a href="/members/add.php" class="text-indigo-600 hover:underline">Add first member →</a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($members as $mem): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3 font-semibold text-gray-800">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-xs">
                    <?= strtoupper(substr($mem['user']['first_name'] ?? 'M', 0, 1)) ?>
                  </div>
                  <?= htmlspecialchars(($mem['user']['first_name'] ?? '') . ' ' . ($mem['user']['last_name'] ?? '')) ?>
                </div>
              </td>
              <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($mem['user']['email'] ?? '—') ?></td>
              <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($mem['user']['phone'] ?? '—') ?></td>
              <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($mem['gender'] ?? '—') ?></td>
              <td class="px-6 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= ($mem['is_active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                  <?= ($mem['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="px-6 py-3 text-center">
                <a href="/members/detail.php?id=<?= urlencode($mem['id']) ?>"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg mr-1">View</a>
                <a href="/fees/collect.php?member_id=<?= urlencode($mem['id']) ?>&name=<?= urlencode(($mem['user']['first_name'] ?? '') . ' ' . ($mem['user']['last_name'] ?? '')) ?>"
                   class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1.5 rounded-lg">Fee</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
