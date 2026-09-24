<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['SUPER_ADMIN']);

$gyms_res = api_request('GET', '/admin/gyms');
$gyms = array_values($gyms_res['data'] ?? []);
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-building text-indigo-500 mr-2"></i>All Gyms</h2>
  <span class="bg-indigo-100 text-indigo-700 text-sm font-semibold px-3 py-1 rounded-full"><?= count($gyms) ?> total</span>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
        <tr>
          <th class="px-6 py-3 text-left">#</th>
          <th class="px-6 py-3 text-left">Gym Name</th>
          <th class="px-6 py-3 text-left">Slug</th>
          <th class="px-6 py-3 text-center">Active Members</th>
          <th class="px-6 py-3 text-right">Total Revenue</th>
          <th class="px-6 py-3 text-left">Registered On</th>
          <th class="px-6 py-3 text-center">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($gyms)): ?>
          <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No gyms found.</td></tr>
        <?php else: ?>
          <?php foreach ($gyms as $i => $g): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3 text-gray-400"><?= $i + 1 ?></td>
              <td class="px-6 py-3 font-semibold text-gray-800"><?= htmlspecialchars((string)($g['name'] ?? '')) ?></td>
              <td class="px-6 py-3"><span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600 text-xs"><?= htmlspecialchars((string)($g['slug'] ?? '')) ?></span></td>
              <td class="px-6 py-3 text-center font-medium"><?= (int)($g['active_members_count'] ?? 0) ?></td>
              <td class="px-6 py-3 text-right font-bold text-green-600">₹<?= number_format((float)($g['total_revenue_generated'] ?? 0), 2) ?></td>
              <td class="px-6 py-3 text-left text-gray-400 text-xs"><?= date('d M Y', strtotime((string)($g['created_at'] ?? 'now'))) ?></td>
              <td class="px-6 py-3 text-center">
                <a href="/superadmin/gym_detail.php?id=<?= urlencode((string)($g['gym_id'] ?? '')) ?>"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg">
                  View Details
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
