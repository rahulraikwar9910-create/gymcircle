<?php
require_once __DIR__ . '/../layout/header.php';
require_roles(['GYM_OWNER','GYM_MANAGER']);

$trainer_id = trim($_GET['id'] ?? '');
if (!$trainer_id) { header('Location: /trainers/index.php'); exit; }

// Fetch trainer
$res     = api_request('GET', '/trainers/' . $trainer_id);
$trainer = $res['data'] ?? [];
if (empty($trainer)) {
    echo "<div class='text-red-500 p-6'>Trainer not found.</div>";
    require_once __DIR__ . '/../layout/footer.php'; exit;
}

$name  = ($trainer['user']['first_name'] ?? '') . ' ' . ($trainer['user']['last_name'] ?? '');
$tid   = $trainer_id;

// Upload dir for trainer
$upload_dir = __DIR__ . '/../uploads/trainers/' . $tid . '/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Cache dir for trainer profile
$cache_dir  = __DIR__ . '/../uploads/trainers/';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

$success = $error = '';

// Service types for the form
$service_types = [
    ['key'=>'gym_pt',  'icon'=>'fa-building','color'=>'#4f46e5','title'=>'Gym (Personal Training)', 'desc_ph'=>'1-on-1 PT sessions at the gym with full equipment access.'],
    ['key'=>'home',    'icon'=>'fa-home',    'color'=>'#22c55e','title'=>'Home Training',           'desc_ph'=>'I come to your home. Effective training with minimal equipment.'],
    ['key'=>'online',  'icon'=>'fa-video',   'color'=>'#f97316','title'=>'Online Coaching',         'desc_ph'=>'Remote coaching via video call with custom workout & diet plans.'],
    ['key'=>'group',   'icon'=>'fa-users',   'color'=>'#8b5cf6','title'=>'Group Sessions',          'desc_ph'=>'Small group training sessions at gym or a local park.'],
    ['key'=>'diet',    'icon'=>'fa-apple-whole','color'=>'#10b981','title'=>'Diet & Nutrition Plan', 'desc_ph'=>'Customized diet plan with macros, meal planning & weekly review.'],
];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {

    if ($_POST['_action'] === 'update_profile') {
        $data = [
            'bio'              => trim($_POST['bio'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'hourly_rate'      => !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null,
            'is_available'     => isset($_POST['is_available']),
        ];
        $upd = api_request('PATCH', '/trainers/' . $tid, $data);
        if ($upd['status'] === 200) {
            $success = 'Profile updated!';
            $trainer = $upd['data'];
        } else {
            $error = $upd['data']['detail'] ?? 'Update failed.';
        }

        // Save services to cache JSON
        $services = [];
        foreach ($service_types as $svc) {
            $k = $svc['key'];
            if (!empty($_POST['svc_' . $k . '_enabled'])) {
                $services[] = [
                    'key'         => $k,
                    'icon'        => $svc['icon'],
                    'color'       => $svc['color'],
                    'title'       => $svc['title'],
                    'desc'        => trim($_POST['svc_' . $k . '_desc'] ?? $svc['desc_ph']),
                    'price_label' => trim($_POST['svc_' . $k . '_price'] ?? ''),
                ];
            }
        }

        // Save everything to cache for public page
        file_put_contents($cache_dir . $tid . '.json', json_encode([
            'trainer'  => $trainer,
            'services' => $services,
        ]));

        if (empty($error)) $success = 'Profile + services saved!';
    }

    if ($_POST['_action'] === 'upload_photo') {
        if (!empty($_FILES['trainer_photo']['name'])) {
            $file = $_FILES['trainer_photo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $error = 'Only JPG, PNG, WEBP allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Max 5MB.';
            } else {
                $fname = ($_POST['photo_type'] ?? 'photo') . '_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $upload_dir . $fname)
                    ? ($success = 'Photo uploaded!') : ($error = 'Upload failed.');
            }
        } else {
            $error = 'Please select a photo.';
        }
    }

    if ($_POST['_action'] === 'delete_photo') {
        $f = $upload_dir . basename($_POST['filename'] ?? '');
        if (file_exists($f)) { unlink($f); $success = 'Photo deleted.'; }
    }
}

// Load saved services from cache
$cache_data    = file_exists($cache_dir . $tid . '.json')
    ? json_decode(file_get_contents($cache_dir . $tid . '.json'), true)
    : [];
$saved_svcs    = $cache_data['services'] ?? [];
$saved_svc_map = [];
foreach ($saved_svcs as $s) $saved_svc_map[$s['key']] = $s;

// Load photos
$photos = [];
if (is_dir($upload_dir)) {
    foreach (glob($upload_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $f) {
        $photos[] = basename($f);
    }
}

$pub_url = 'http://localhost:8080/public/trainer.php?id=' . urlencode($tid);
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/trainers/index.php" class="text-indigo-600 hover:underline text-sm"><i class="fa fa-arrow-left mr-1"></i>Back to Trainers</a>
</div>

<div class="flex justify-between items-center mb-4">
  <h2 class="text-2xl font-bold text-gray-800"><i class="fa fa-user-pen text-purple-500 mr-2"></i><?= htmlspecialchars($name) ?></h2>
  <a href="<?= $pub_url ?>" target="_blank" class="bg-purple-600 hover:bg-purple-700 text-white text-sm px-4 py-2 rounded-lg">
    <i class="fa fa-eye mr-1"></i> View Public Page
  </a>
</div>

<!-- Public Link -->
<div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 flex items-center justify-between">
  <div>
    <p class="text-sm font-semibold text-indigo-700"><i class="fa fa-link mr-2"></i>Share this link with clients</p>
    <p class="text-xs text-indigo-500 mt-0.5 break-all"><?= $pub_url ?></p>
  </div>
  <button onclick="navigator.clipboard.writeText('<?= $pub_url ?>').then(()=>alert('Copied!'))"
          class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-2 rounded-lg whitespace-nowrap ml-3">
    <i class="fa fa-copy mr-1"></i> Copy
  </button>
</div>

<?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <!-- Profile Info + Services -->
  <div class="space-y-5">
    <div class="bg-white rounded-xl shadow-sm p-6">
      <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-user text-blue-500 mr-2"></i>Profile Info</h3>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="_action" value="update_profile">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Bio / About Me</label>
          <textarea name="bio" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"
                    placeholder="Tell clients about yourself, your philosophy, achievements..."><?= htmlspecialchars($trainer['bio'] ?? '') ?></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Experience (Years)</label>
            <input name="experience_years" type="number" min="0" value="<?= $trainer['experience_years'] ?? 0 ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Hourly Rate (₹)</label>
            <input name="hourly_rate" type="number" min="0" step="50" value="<?= $trainer['hourly_rate'] ?? '' ?>"
                   placeholder="e.g. 500"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input name="is_available" type="checkbox" id="avail" <?= ($trainer['is_available']??false)?'checked':'' ?> class="w-4 h-4 accent-green-600">
          <label for="avail" class="text-sm text-gray-700">Currently accepting new clients</label>
        </div>

        <!-- Services Section -->
        <div class="pt-4 border-t border-gray-100">
          <p class="text-sm font-semibold text-gray-700 mb-3"><i class="fa fa-briefcase text-purple-500 mr-2"></i>Services You Offer</p>
          <div class="space-y-3">
            <?php foreach ($service_types as $svc):
              $k    = $svc['key'];
              $sv   = $saved_svc_map[$k] ?? null;
              $on   = $sv !== null;
            ?>
              <div class="border border-gray-200 rounded-lg p-3">
                <div class="flex items-center gap-2 mb-2">
                  <input type="checkbox" name="svc_<?= $k ?>_enabled" id="svc_<?= $k ?>" <?= $on?'checked':'' ?>
                         class="w-4 h-4 accent-indigo-600" onchange="toggleSvc('<?= $k ?>')">
                  <label for="svc_<?= $k ?>" class="text-sm font-medium text-gray-800 flex items-center gap-2">
                    <span class="w-5 h-5 rounded flex items-center justify-center text-white text-xs" style="background:<?= $svc['color'] ?>">
                      <i class="fa <?= $svc['icon'] ?>"></i>
                    </span>
                    <?= $svc['title'] ?>
                  </label>
                </div>
                <div id="svcf_<?= $k ?>" class="<?= $on?'':'hidden' ?> space-y-2 pl-6">
                  <input type="text" name="svc_<?= $k ?>_price" value="<?= htmlspecialchars($sv['price_label']??'') ?>"
                         placeholder="Price (e.g. ₹500/hr, ₹5000/month)"
                         class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 outline-none">
                  <textarea name="svc_<?= $k ?>_desc" rows="2"
                            class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 outline-none resize-none"
                            placeholder="<?= $svc['desc_ph'] ?>"><?= htmlspecialchars($sv['desc']??'') ?></textarea>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg text-sm font-semibold">
          <i class="fa fa-save mr-1"></i> Save Profile & Services
        </button>
      </form>
    </div>
  </div>

  <!-- Photos -->
  <div class="bg-white rounded-xl shadow-sm p-6">
    <h3 class="font-semibold text-gray-700 mb-4"><i class="fa fa-images text-green-500 mr-2"></i>Photos</h3>

    <form method="POST" enctype="multipart/form-data" class="space-y-3 mb-5">
      <input type="hidden" name="_action" value="upload_photo">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Photo Type</label>
        <select name="photo_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
          <option value="profile">Profile Photo</option>
          <option value="training">Training in Action</option>
          <option value="certificate">Certificate / Award</option>
          <option value="before_after">Before & After (Client)</option>
          <option value="gym">At Gym</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Select Photo (JPG/PNG/WEBP, max 5MB)</label>
        <input name="trainer_photo" type="file" accept=".jpg,.jpeg,.png,.webp" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
      </div>
      <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg text-sm font-semibold">
        <i class="fa fa-upload mr-1"></i> Upload Photo
      </button>
    </form>

    <!-- Photos Grid -->
    <?php if (empty($photos)): ?>
      <p class="text-sm text-gray-400 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">No photos uploaded yet.</p>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($photos as $ph): ?>
          <div class="relative group rounded-lg overflow-hidden border border-gray-200">
            <img src="/uploads/trainers/<?= $tid ?>/<?= urlencode($ph) ?>"
                 class="w-full h-28 object-cover">
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-center justify-center">
              <form method="POST" onsubmit="return confirm('Delete?')"
                    class="opacity-0 group-hover:opacity-100 transition">
                <input type="hidden" name="_action" value="delete_photo">
                <input type="hidden" name="filename" value="<?= htmlspecialchars($ph) ?>">
                <button class="bg-red-600 text-white text-xs px-3 py-1.5 rounded-lg"><i class="fa fa-trash mr-1"></i>Delete</button>
              </form>
            </div>
            <p class="text-xs text-gray-500 px-2 py-1 truncate bg-gray-50"><?= htmlspecialchars($ph) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleSvc(key) {
  const cb = document.getElementById('svc_' + key);
  const fields = document.getElementById('svcf_' + key);
  fields.classList.toggle('hidden', !cb.checked);
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

