<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['SUPER_ADMIN']);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['gym_slug'] ?? '')));
    $res = api_request('POST', '/auth/register/owner', [
        'email'      => trim($_POST['email'] ?? ''),
        'password'   => trim($_POST['password'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'gym_name'   => trim($_POST['gym_name']   ?? ''),
        'gym_slug'   => $gym_slug,
    ]);
    if ($res['status'] === 200 || $res['status'] === 201) {
        $success = 'Gym owner registered successfully! They can now login at localhost:8080';
    } else {
        $error = $res['data']['detail'] ?? 'Registration failed.';
    }
}
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/superadmin/owners.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Owners</a>
</div>

<h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa fa-plus-circle text-indigo-500 mr-2"></i>Add New Gym Owner</h2>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl mb-5">
    <p class="font-semibold"><i class="fa fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></p>
    <a href="/superadmin/owners.php" class="text-sm underline mt-1 inline-block">View all owners →</a>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-5 py-4 rounded-xl mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
  <form method="POST" class="space-y-5">

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
        <input name="first_name" type="text" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
        <input name="last_name" type="text" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
      <input name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
      <input name="password" type="text" required value="<?= htmlspecialchars($_POST['password'] ?? '') ?>"
             placeholder="Temporary password for owner"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      <p class="text-xs text-gray-400 mt-1">Share this with the gym owner so they can login.</p>
    </div>

    <hr class="border-gray-100">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Gym Name *</label>
      <input id="gname" name="gym_name" type="text" required value="<?= htmlspecialchars($_POST['gym_name'] ?? '') ?>"
             placeholder="e.g. FitZone Premium Gym"
             class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
             oninput="autoSlug()">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Gym Slug * <span class="text-gray-400 font-normal text-xs">(URL-friendly name)</span></label>
      <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-400">
        <span class="bg-gray-50 px-3 py-2.5 text-xs text-gray-400 border-r border-gray-300">gym.com/</span>
        <input id="gslug" name="gym_slug" type="text" required value="<?= htmlspecialchars($_POST['gym_slug'] ?? '') ?>"
               placeholder="fitzone-premium"
               class="flex-1 px-3 py-2.5 text-sm outline-none" oninput="cleanSlug()">
      </div>
    </div>

    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm">
      <i class="fa fa-plus mr-1"></i> Create Gym Owner Account
    </button>
  </form>
</div>

<script>
function autoSlug(){const s=document.getElementById('gname').value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('gslug').value=s;}
function cleanSlug(){const el=document.getElementById('gslug');el.value=el.value.toLowerCase().replace(/[^a-z0-9-]/g,'');}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

