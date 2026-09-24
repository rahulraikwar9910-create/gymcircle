<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER']);

$gym_id  = $_SESSION['gym_id'] ?? '';
$gym_slug = $_SESSION['gym_slug'] ?? 'gym';
$gym_res = api_request('GET', '/gyms/' . $gym_id);
$gym     = $gym_res['data'] ?? [];
$gym_slug = $gym['slug'] ?? 'gym';

// Upload dir for this gym
$upload_dir = __DIR__ . '/../uploads/' . $gym_slug . '/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Auto-save gym_meta.json so public page can find gym by slug
file_put_contents($upload_dir . 'gym_meta.json', json_encode([
    'gym_id'   => $gym_id,
    'gym_name' => $gym['name'] ?? '',
    'slug'     => $gym_slug,
    'saved_at' => date('c'),
]));


$success = $error = '';

// Handle gym info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'update_info') {
    $data = [
        'name'    => trim($_POST['name']    ?? ''),
        'email'   => trim($_POST['email']   ?? '') ?: null,
        'phone'   => trim($_POST['phone']   ?? '') ?: null,
        'address' => trim($_POST['address'] ?? '') ?: null,
        'city'    => trim($_POST['city']    ?? '') ?: null,
        'state'   => trim($_POST['state']   ?? '') ?: null,
        'pincode' => trim($_POST['pincode'] ?? '') ?: null,
    ];
    $res = api_request('PATCH', '/gyms/' . $gym_id, $data);
    if ($res['status'] === 200) {
        $success = 'Gym settings updated!';
        $_SESSION['gym_name'] = $res['data']['name'] ?? $gym['name'];
        $gym = $res['data'];
    } else {
        $error = $res['data']['detail'] ?? 'Update failed.';
    }
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'upload_image') {
    if (!empty($_FILES['gym_image']['name'])) {
        $file      = $_FILES['gym_image'];
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','webp'];
        $label     = preg_replace('/[^a-z0-9_]/', '_', strtolower($_POST['image_label'] ?? 'gym'));

        if (!in_array($ext, $allowed)) {
            $error = 'Only JPG, PNG, WEBP images allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Image must be under 5MB.';
        } else {
            $filename = $label . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $success = 'Image uploaded: ' . $filename;
            } else {
                $error = 'Upload failed. Check folder permissions.';
            }
        }
    } else {
        $error = 'Please select an image to upload.';
    }
}

// Handle image delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'delete_image') {
    $fname = basename($_POST['filename'] ?? '');
    $fpath = $upload_dir . $fname;
    if ($fname && file_exists($fpath)) {
        unlink($fpath);
        $success = 'Image deleted.';
    }
}

// Load existing images
$images = [];
if (is_dir($upload_dir)) {
    foreach (glob($upload_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $img) {
        $images[] = basename($img);
    }
}

// Public link
$public_url = 'http://localhost:8080/public/gym.php?slug=' . urlencode($gym_slug);
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fa fa-gear text-gray-500 mr-2"></i>Gym Settings</h2>

<?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Public Link Banner -->
<div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 flex items-center justify-between">
  <div>
    <p class="text-sm font-semibold text-indigo-700"><i class="fa fa-link mr-2"></i>Public Gym Page (Share with clients)</p>
    <p class="text-xs text-indigo-500 mt-0.5"><?= $public_url ?></p>
  </div>
  <a href="<?= $public_url ?>" target="_blank"
     class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg whitespace-nowrap">
    <i class="fa fa-eye mr-1"></i> Preview Page
  </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <!-- Gym Info Form -->
  <div class="bg-white rounded-xl shadow-sm p-6">
    <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-building text-blue-500 mr-2"></i>Gym Information</h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="_action" value="update_info">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Gym Name *</label>
        <input name="name" type="text" required value="<?= htmlspecialchars($gym['name'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
          <input name="email" type="email" value="<?= htmlspecialchars($gym['email'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
          <input name="phone" type="tel" value="<?= htmlspecialchars($gym['phone'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Address</label>
        <input name="address" type="text" value="<?= htmlspecialchars($gym['address'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">City</label>
          <input name="city" value="<?= htmlspecialchars($gym['city'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">State</label>
          <input name="state" value="<?= htmlspecialchars($gym['state'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Pincode</label>
          <input name="pincode" value="<?= htmlspecialchars($gym['pincode'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        </div>
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-save mr-1"></i> Save Settings
      </button>
    </form>
  </div>

  <!-- Image Upload -->
  <div class="bg-white rounded-xl shadow-sm p-6">
    <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-images text-green-500 mr-2"></i>Gym Photos & Equipment</h3>

    <form method="POST" enctype="multipart/form-data" class="space-y-3 mb-5">
      <input type="hidden" name="_action" value="upload_image">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Image Label</label>
        <select name="image_label" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          <option value="gym_front">Gym Front / Entrance</option>
          <option value="gym_hall">Main Gym Hall</option>
          <option value="cardio">Cardio Area</option>
          <option value="weights">Weights / Free Weights</option>
          <option value="equipment">Equipment</option>
          <option value="locker_room">Locker Room</option>
          <option value="pool">Pool / Sauna</option>
          <option value="yoga">Yoga / Aerobics Room</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Select Image (JPG/PNG/WEBP, max 5MB)</label>
        <input name="gym_image" type="file" accept=".jpg,.jpeg,.png,.webp" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
      </div>
      <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-upload mr-1"></i> Upload Image
      </button>
    </form>

    <!-- Uploaded Images Grid -->
    <?php if (empty($images)): ?>
      <p class="text-sm text-gray-400 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
        No images uploaded yet.
      </p>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($images as $img): ?>
          <div class="relative group rounded-lg overflow-hidden border border-gray-200">
            <img src="/uploads/<?= urlencode($gym_slug) ?>/<?= urlencode($img) ?>"
                 alt="<?= htmlspecialchars($img) ?>"
                 class="w-full h-28 object-cover">
            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
              <form method="POST" onsubmit="return confirm('Delete this image?')"
                    class="opacity-0 group-hover:opacity-100 transition">
                <input type="hidden" name="_action"  value="delete_image">
                <input type="hidden" name="filename" value="<?= htmlspecialchars($img) ?>">
                <button type="submit" class="bg-red-600 text-white text-xs px-3 py-1.5 rounded-lg">
                  <i class="fa fa-trash mr-1"></i> Delete
                </button>
              </form>
            </div>
            <p class="text-xs text-gray-500 px-2 py-1 truncate bg-gray-50"><?= htmlspecialchars($img) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
