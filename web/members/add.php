<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER','RECEPTIONIST']);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'email'      => trim($_POST['email']      ?? ''),
        'phone'      => trim($_POST['phone']      ?? '') ?: null,
        'password'   => trim($_POST['password']   ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'gender'     => $_POST['gender']           ?? null,
        'dob'        => $_POST['dob']              ?? null ?: null,
        'address'    => trim($_POST['address']    ?? '') ?: null,
        'emergency_contact' => trim($_POST['emergency_contact'] ?? '') ?: null,
    ];

    $res = api_request('POST', '/clients', $data);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $success = 'Member added successfully!';
    } else {
        $error = $res['data']['detail'] ?? 'Failed to add member. Please check all fields.';
    }
}
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/members/index.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Members</a>
</div>

<h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa fa-user-plus text-indigo-500 mr-2"></i>Add New Member</h2>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-5 flex justify-between items-center">
    <?= htmlspecialchars($success) ?>
    <a href="/members/index.php" class="text-sm font-medium underline">View Members →</a>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6">
  <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
      <input name="first_name" type="text" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
      <input name="last_name" type="text" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
      <input name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
      <input name="phone" type="tel" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
      <input name="password" type="password" required
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
             placeholder="Minimum 8 characters">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
      <select name="gender" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <option value="">Select gender</option>
        <option value="MALE"   <?= ($_POST['gender'] ?? '') === 'MALE'   ? 'selected' : '' ?>>Male</option>
        <option value="FEMALE" <?= ($_POST['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
        <option value="OTHER"  <?= ($_POST['gender'] ?? '') === 'OTHER'  ? 'selected' : '' ?>>Other</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
      <input name="dob" type="date" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
      <input name="emergency_contact" type="tel" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div class="md:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
      <textarea name="address" rows="2"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                ><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
    </div>

    <div class="md:col-span-2 flex gap-3 pt-2">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-save mr-1"></i> Add Member
      </button>
      <a href="/members/index.php" class="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
