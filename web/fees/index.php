<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$fees_res = api_request('GET', '/fees/ledger');
$fees = $fees_res['data'] ?? [];

$total_credit = array_sum(array_column(array_filter($fees, fn($f) => ($f['entry_type'] ?? '') === 'CREDIT'), 'amount'));
$total_debit  = array_sum(array_column(array_filter($fees, fn($f) => ($f['entry_type'] ?? '') === 'DEBIT'),  'amount'));
?>

<div class="flex justify-between items-center mb-6">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-indian-rupee-sign text-green-500 mr-2"></i>Fee Ledger</h2>
  <a href="/fees/collect.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
    <i class="fa fa-plus mr-1"></i> Collect Fee
  </a>
</div>

<!-- Summary -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-green-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Total Collected</p>
    <p class="text-2xl font-bold text-green-600 mt-1">₹<?= number_format($total_credit, 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-red-400">
    <p class="text-xs text-gray-500 uppercase font-medium">Total Expenses</p>
    <p class="text-2xl font-bold text-red-500 mt-1">₹<?= number_format($total_debit, 2) ?></p>
  </div>
  <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-indigo-500">
    <p class="text-xs text-gray-500 uppercase font-medium">Net Balance</p>
    <p class="text-2xl font-bold text-indigo-600 mt-1">₹<?= number_format($total_credit - $total_debit, 2) ?></p>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
        <tr>
          <th class="px-6 py-3 text-left">Date</th>
          <th class="px-6 py-3 text-left">Member</th>
          <th class="px-6 py-3 text-left">Description</th>
          <th class="px-6 py-3 text-left">Mode</th>
          <th class="px-6 py-3 text-right">Amount</th>
          <th class="px-6 py-3 text-center">Type</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($fees)): ?>
          <tr>
            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
              No records yet. <a href="/fees/collect.php" class="text-green-600 hover:underline">Collect first fee →</a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($fees as $f): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-3 text-gray-500"><?= date('d M Y', strtotime($f['created_at'] ?? 'now')) ?></td>
              <td class="px-6 py-3 font-medium text-gray-800">
                <?= htmlspecialchars(($f['client']['user']['first_name'] ?? '') . ' ' . ($f['client']['user']['last_name'] ?? '—')) ?>
              </td>
              <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($f['description'] ?? '—') ?></td>
              <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($f['payment_mode'] ?? '—') ?></td>
              <td class="px-6 py-3 text-right font-bold <?= ($f['entry_type'] ?? '') === 'CREDIT' ? 'text-green-600' : 'text-red-500' ?>">
                <?= ($f['entry_type'] ?? '') === 'CREDIT' ? '+' : '-' ?>₹<?= number_format((float)($f['amount'] ?? 0), 2) ?>
              </td>
              <td class="px-6 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= ($f['entry_type'] ?? '') === 'CREDIT' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                  <?= htmlspecialchars($f['entry_type'] ?? '—') ?>
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
