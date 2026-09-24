<?php
// Public gym showcase page — no login required
require_once __DIR__ . '/../helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><body style='text-align:center;padding:80px;font-family:sans-serif'><h2 style='color:#6366f1'>GymCircle</h2><p>Gym not found. Please check the link.</p></body></html>";
    exit;
}

// Use a temp token-less approach: find gym via slug by checking all gyms
// First: try the by-slug endpoint
$gym = null; $gym_id = null; $plans = []; $images = [];

// Try fetching gym by slug from public API if exists
// Fallback: use a simple file cache for gym_id lookup
$cache_file = __DIR__ . '/../uploads/' . preg_replace('/[^a-z0-9\-]/', '', $slug) . '/gym_meta.json';

if (file_exists($cache_file)) {
    $meta   = json_decode(file_get_contents($cache_file), true);
    $gym_id = $meta['gym_id'] ?? null;
}

// If no cache, try fetching from admin API (works if super admin token in session)
if (!$gym_id) {
    // Login as super admin to get token for public lookup
    $login = api_request('POST', '/auth/login', [
        'email'    => 'superadmin@gymcircle.com',
        'password' => 'admin123'
    ]);
    if ($login['status'] === 200) {
        $sa_token = $login['data']['access_token'] ?? null;
        if ($sa_token) {
            // Temporarily override session token
            $orig_token = $_SESSION['access_token'] ?? null;
            $orig_gym   = $_SESSION['gym_id'] ?? null;
            $_SESSION['access_token'] = $sa_token;
            unset($_SESSION['gym_id']);

            $all_gyms_res = api_request('GET', '/admin/gyms');
            $all_gyms     = $all_gyms_res['data'] ?? [];

            foreach ($all_gyms as $g) {
                if (($g['slug'] ?? '') === $slug) {
                    $gym_id = $g['gym_id'] ?? null;
                    break;
                }
            }

            // Restore session
            if ($orig_token) $_SESSION['access_token'] = $orig_token;
            else             unset($_SESSION['access_token']);
            if ($orig_gym)   $_SESSION['gym_id'] = $orig_gym;

            // Cache for next time
            if ($gym_id) {
                $dir = __DIR__ . '/../uploads/' . preg_replace('/[^a-z0-9\-]/', '', $slug) . '/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . 'gym_meta.json', json_encode(['gym_id' => $gym_id]));
            }
        }
    }
}

if (!$gym_id) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><body style='text-align:center;padding:80px;font-family:sans-serif'><h2 style='color:#6366f1'>GymCircle</h2><p>Gym \"" . htmlspecialchars($slug) . "\" not found.</p><p style='color:#94a3b8'>Make sure the gym has been registered.</p></body></html>";
    exit;
}

// Fetch full gym details
$gym_res = api_request('GET', '/gyms/' . $gym_id);
$gym     = $gym_res['data'] ?? [];

// Fetch plans — set gym_id in session temporarily
$orig_gym          = $_SESSION['gym_id'] ?? null;
$_SESSION['gym_id'] = $gym_id;
$plans_res          = api_request('GET', '/memberships/plans');
$plans              = $plans_res['data'] ?? [];
if ($orig_gym) $_SESSION['gym_id'] = $orig_gym;
else           unset($_SESSION['gym_id']);

// Load gym images
$clean_slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
$upload_dir = __DIR__ . '/../uploads/' . $clean_slug . '/';
$images     = [];
if (is_dir($upload_dir)) {
    foreach (glob($upload_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $img) {
        $images[] = basename($img);
    }
}

function dur_label(int $days): string {
    if ($days <= 31)  return 'Monthly';
    if ($days <= 92)  return 'Quarterly';
    if ($days <= 185) return 'Half-Yearly';
    if ($days >= 360) return 'Yearly';
    return $days . ' days';
}
function dur_color(int $days): string {
    if ($days <= 31)  return '#3b82f6';
    if ($days <= 92)  return '#22c55e';
    if ($days <= 185) return '#f97316';
    return '#a855f7';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($gym['name'] ?? 'Gym') ?> — GymCircle</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .hero-gradient{background:linear-gradient(135deg,#312e81 0%,#4f46e5 50%,#7c3aed 100%)}
    .plan-card{transition:transform .2s ease,box-shadow .2s ease}
    .plan-card:hover{transform:translateY(-5px);box-shadow:0 20px 40px rgba(99,102,241,.15)}
  </style>
</head>
<body class="bg-gray-50">

<!-- Hero -->
<div class="hero-gradient text-white">
  <div class="max-w-5xl mx-auto px-6 py-16 text-center">
    <div class="inline-flex items-center gap-2 bg-white bg-opacity-20 rounded-full px-4 py-1.5 text-xs font-semibold mb-5">
      <i class="fa fa-dumbbell"></i> GymCircle Partner Gym
    </div>
    <h1 class="text-5xl font-extrabold mb-3"><?= htmlspecialchars($gym['name'] ?? '') ?></h1>
    <?php $addr = implode(', ', array_filter([$gym['address']??null,$gym['city']??null,$gym['state']??null])); ?>
    <?php if ($addr): ?>
      <p class="text-indigo-200 text-lg mt-2"><i class="fa fa-location-dot mr-1"></i><?= htmlspecialchars($addr) ?></p>
    <?php endif; ?>
    <div class="flex justify-center gap-6 mt-5 flex-wrap">
      <?php if (!empty($gym['phone'])): ?>
        <span class="text-indigo-200 text-sm"><i class="fa fa-phone mr-1"></i><?= htmlspecialchars($gym['phone']) ?></span>
      <?php endif; ?>
      <?php if (!empty($gym['email'])): ?>
        <span class="text-indigo-200 text-sm"><i class="fa fa-envelope mr-1"></i><?= htmlspecialchars($gym['email']) ?></span>
      <?php endif; ?>
    </div>
    <a href="#plans"
       class="inline-block mt-8 bg-white text-indigo-700 font-bold px-8 py-3 rounded-full text-sm hover:bg-indigo-50 transition shadow-lg">
      View Plans &amp; Join <i class="fa fa-arrow-down ml-1"></i>
    </a>
  </div>
</div>

<!-- Stats -->
<div class="bg-white border-b shadow-sm">
  <div class="max-w-5xl mx-auto px-6 py-5 grid grid-cols-3 gap-4 text-center">
    <div><p class="text-2xl font-bold text-indigo-600"><?= count($plans) ?></p><p class="text-sm text-gray-500">Membership Plans</p></div>
    <div><p class="text-2xl font-bold text-indigo-600"><?= count($images) ?>+</p><p class="text-sm text-gray-500">Photos</p></div>
    <div><p class="text-2xl font-bold text-indigo-600">24/7</p><p class="text-sm text-gray-500">Support</p></div>
  </div>
</div>

<div class="max-w-5xl mx-auto px-6 py-10">

  <!-- Gallery -->
  <?php if (!empty($images)): ?>
  <div class="mb-12">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center"><i class="fa fa-images text-indigo-500 mr-2"></i>Gym Gallery</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <?php foreach ($images as $img): ?>
        <div class="rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition cursor-pointer"
             onclick="openLightbox('/uploads/<?= $clean_slug ?>/<?= urlencode($img) ?>')">
          <img src="/uploads/<?= $clean_slug ?>/<?= urlencode($img) ?>"
               alt="<?= htmlspecialchars($img) ?>"
               class="w-full h-52 object-cover hover:scale-105 transition duration-300">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Plans -->
  <div id="plans" class="scroll-mt-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center"><i class="fa fa-id-card text-indigo-500 mr-2"></i>Choose Your Membership</h2>
    <p class="text-gray-500 text-center text-sm mb-8">Flexible plans for every fitness goal. Join today!</p>

    <?php if (empty($plans)): ?>
      <div class="bg-white rounded-2xl p-12 text-center text-gray-400 shadow-sm">
        No plans yet — contact the gym directly.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-<?= min(count($plans), 3) ?> gap-6">
        <?php foreach ($plans as $idx => $p):
          $days    = (int)($p['duration_days'] ?? 30);
          $label   = dur_label($days);
          $color   = dur_color($days);
          $pm      = $days > 0 ? round((float)$p['price'] / ($days / 30), 0) : 0;
          $popular = $idx === 1 && count($plans) >= 3;
        ?>
        <div class="plan-card bg-white rounded-2xl shadow-sm border-2 <?= $popular ? 'border-indigo-500 relative' : 'border-gray-100' ?> overflow-hidden">
          <?php if ($popular): ?>
            <div class="absolute top-0 inset-x-0 bg-indigo-600 text-white text-xs font-bold text-center py-1">⭐ MOST POPULAR</div>
          <?php endif; ?>
          <div class="p-6 <?= $popular ? 'pt-8' : '' ?>">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-white mb-4"
                  style="background:<?= $color ?>">
              <i class="fa fa-calendar"></i> <?= $label ?>
            </span>
            <h3 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($p['name']) ?></h3>
            <?php if (!empty($p['description'])): ?>
              <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($p['description']) ?></p>
            <?php endif; ?>
            <div class="my-4">
              <div class="flex items-baseline gap-1">
                <span class="text-4xl font-extrabold text-gray-900">₹<?= number_format((float)$p['price'], 0) ?></span>
                <span class="text-gray-400 text-sm">/ <?= $days ?> days</span>
              </div>
              <p class="text-sm text-gray-400 mt-0.5">≈ ₹<?= number_format($pm, 0) ?>/month</p>
            </div>
            <ul class="space-y-2 mb-5 text-sm text-gray-600">
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i><?= $days ?> days access</li>
              <?php if (!empty($p['max_sessions'])): ?>
                <li><i class="fa fa-check-circle text-green-500 mr-2"></i><?= $p['max_sessions'] ?> sessions</li>
              <?php else: ?>
                <li><i class="fa fa-check-circle text-green-500 mr-2"></i>Unlimited sessions</li>
              <?php endif; ?>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i>All equipment access</li>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i>Expert trainer guidance</li>
            </ul>
            <button onclick="showModal('<?= htmlspecialchars(addslashes($p['name'])) ?>','<?= number_format((float)$p['price'],0) ?>','<?= $label ?>')"
                    class="w-full py-3 rounded-xl font-bold text-sm transition
                           <?= $popular ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-100 hover:bg-indigo-600 hover:text-white text-gray-800' ?>">
              Join Now — ₹<?= number_format((float)$p['price'], 0) ?>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Contact CTA -->
  <div class="mt-12 bg-indigo-600 rounded-2xl p-8 text-white text-center">
    <h3 class="text-2xl font-bold mb-2">Ready to Start Your Fitness Journey?</h3>
    <p class="text-indigo-200 mb-6">Contact us today or visit the gym directly.</p>
    <div class="flex justify-center gap-4 flex-wrap">
      <?php if (!empty($gym['phone'])): ?>
        <a href="tel:<?= htmlspecialchars($gym['phone']) ?>"
           class="bg-white text-indigo-700 font-bold px-6 py-3 rounded-full text-sm hover:bg-indigo-50 transition">
          <i class="fa fa-phone mr-2"></i><?= htmlspecialchars($gym['phone']) ?>
        </a>
      <?php endif; ?>
      <?php if (!empty($gym['email'])): ?>
        <a href="mailto:<?= htmlspecialchars($gym['email']) ?>"
           class="border-2 border-white text-white font-bold px-6 py-3 rounded-full text-sm hover:bg-white hover:text-indigo-700 transition">
          <i class="fa fa-envelope mr-2"></i><?= htmlspecialchars($gym['email']) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <p class="text-center text-gray-400 text-xs mt-8">
    Powered by <strong class="text-indigo-600">GymCircle</strong> — Smart Gym Management
  </p>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()"
     class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden items-center justify-center p-4 flex">
  <img id="lb_img" src="" class="max-h-screen max-w-full rounded-xl shadow-2xl">
</div>

<!-- Enquiry Modal -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4 flex">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
    <h3 class="text-xl font-bold text-gray-800 mb-1">Join — <span id="modal_plan" class="text-indigo-600"></span></h3>
    <p class="text-gray-500 text-sm mb-5">Fill your details and we'll contact you!</p>
    <div class="space-y-3">
      <input id="m_name"  type="text"  placeholder="Full Name *"      class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="m_phone" type="tel"   placeholder="Phone Number *"   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="m_email" type="email" placeholder="Email (optional)" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <div id="modal_ok" class="hidden bg-green-50 text-green-700 px-4 py-3 rounded-lg mt-4 text-sm"></div>
    <div class="flex gap-3 mt-5">
      <button onclick="submitEnquiry()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm">
        <i class="fa fa-paper-plane mr-1"></i> Send Enquiry
      </button>
      <button onclick="closeModal()" class="border border-gray-300 px-5 py-3 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
    </div>
  </div>
</div>

<script>
function openLightbox(src){document.getElementById('lb_img').src=src;document.getElementById('lightbox').style.display='flex';}
function closeLightbox(){document.getElementById('lightbox').style.display='none';}
function showModal(plan,price,dur){
  document.getElementById('modal_plan').textContent=plan+' (₹'+price+' / '+dur+')';
  document.getElementById('modal').style.display='flex';
  document.getElementById('modal_ok').classList.add('hidden');
  ['m_name','m_phone','m_email'].forEach(id=>document.getElementById(id).value='');
}
function closeModal(){document.getElementById('modal').style.display='none';}
function submitEnquiry(){
  const name=document.getElementById('m_name').value.trim();
  const phone=document.getElementById('m_phone').value.trim();
  if(!name||!phone){alert('Please enter name and phone.');return;}
  const el=document.getElementById('modal_ok');
  el.textContent='✅ Thank you '+name+'! We will contact you at '+phone+' soon.';
  el.classList.remove('hidden');
  setTimeout(closeModal,3000);
}
document.getElementById('lightbox').style.display='none';
document.getElementById('modal').style.display='none';
</script>
</body>
</html>
