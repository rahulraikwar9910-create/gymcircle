<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$member_id   = $_GET['member_id'] ?? '';
$member_name = $_GET['name']      ?? '';

$members_res = api_request('GET', '/clients');
$members = $members_res['data'] ?? [];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_profile_id' => $_POST['member_id'],
        'amount'            => (float)$_POST['amount'],
        'entry_type'        => 'CREDIT',
        'payment_mode'      => $_POST['payment_mode'],
        'description'       => trim($_POST['description'] ?? 'Fee payment'),
        'transaction_date'  => $_POST['transaction_date'] ?? date('Y-m-d'),
    ];
    $res = api_request('POST', '/fees/ledger', $data);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $success = 'Fee collected successfully!';
    } else {
        $error = $res['data']['detail'] ?? 'Failed to record fee.';
    }
}
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/fees/index.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Ledger</a>
</div>

<h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa fa-indian-rupee-sign text-green-500 mr-2"></i>Collect Fee</h2>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex justify-between text-sm">
    <?= htmlspecialchars($success) ?>
    <a href="/fees/index.php" class="underline font-medium">View Ledger →</a>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
  <form method="POST" class="space-y-5">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Select Member <span class="text-red-500">*</span></label>
      <select name="member_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <option value="">— Select Member —</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= htmlspecialchars($m['id']) ?>" <?= $m['id'] === $member_id ? 'selected' : '' ?>>
            <?= htmlspecialchars(($m['user']['first_name'] ?? '') . ' ' . ($m['user']['last_name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) <span class="text-red-500">*</span></label>
        <input name="amount" type="number" min="1" step="0.01" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode <span class="text-red-500">*</span></label>
        <select name="payment_mode" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          <option value="CASH"   <?= ($_POST['payment_mode'] ?? '') === 'CASH'   ? 'selected' : '' ?>>Cash</option>
          <option value="UPI"    <?= ($_POST['payment_mode'] ?? '') === 'UPI'    ? 'selected' : '' ?>>UPI</option>
          <option value="CARD"   <?= ($_POST['payment_mode'] ?? '') === 'CARD'   ? 'selected' : '' ?>>Card</option>
          <option value="BANK_TRANSFER" <?= ($_POST['payment_mode'] ?? '') === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date</label>
      <input name="transaction_date" type="date" value="<?= date('Y-m-d') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Description / Notes</label>
      <input name="description" type="text" value="<?= htmlspecialchars($_POST['description'] ?? 'Monthly fee') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-check mr-1"></i> Collect Fee
      </button>
      <a href="/fees/index.php" class="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
