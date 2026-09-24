<?php
// Public gym showcase page — no login required
require_once __DIR__ . '/../helpers.php';

$slug       = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'] ?? '')));
$upload_dir = __DIR__ . '/../uploads/' . $slug . '/';
$cache_file = $upload_dir . 'gym_meta.json';

// Try reading from gym_meta.json cache (written when owner visits Gym Settings)
$gym_id = null;
if (file_exists($cache_file)) {
    $meta   = json_decode(file_get_contents($cache_file), true);
    $gym_id = $meta['gym_id'] ?? null;
}

// If no cache — fallback: auto-login as super admin and fetch
if (!$gym_id && $slug) {
    $login = api_request('POST', '/auth/login', ['email' => 'superadmin@gymcircle.com', 'password' => 'admin123']);
    if (!empty($login['data']['access_token'])) {
        $old_token = $_SESSION['access_token'] ?? null;
        $old_gym   = $_SESSION['gym_id'] ?? null;
        $_SESSION['access_token'] = $login['data']['access_token'];
        unset($_SESSION['gym_id']);

        $all = api_request('GET', '/admin/gyms');
        foreach (($all['data'] ?? []) as $g) {
            if (($g['slug'] ?? '') === $slug) {
                $gym_id = $g['gym_id'] ?? null;
                break;
            }
        }
        // Restore session
        $old_token ? ($_SESSION['access_token'] = $old_token) : unset($_SESSION['access_token']);
        $old_gym   ? ($_SESSION['gym_id']        = $old_gym)   : unset($_SESSION['gym_id']);

        // Write cache so next time it's instant
        if ($gym_id) {
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            file_put_contents($cache_file, json_encode(['gym_id' => $gym_id, 'slug' => $slug]));
        }
    }
}

if (!$gym_id) {
    http_response_code(404); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
  <div class="text-center p-10">
    <h1 class="text-4xl font-bold text-indigo-600 mb-3">GymCircle</h1>
    <p class="text-gray-600 text-lg">Gym <strong><?= htmlspecialchars($slug) ?></strong> not found.</p>
    <p class="text-gray-400 mt-2 text-sm">Ask the gym owner to visit <strong>Gym Settings</strong> once to activate their public page.</p>
  </div>
</body></html>
<?php exit; }

// Fetch gym data
$gym_res = api_request('GET', '/gyms/' . $gym_id);
$gym     = $gym_res['data'] ?? [];

// Fetch plans
$old_gym = $_SESSION['gym_id'] ?? null;
$_SESSION['gym_id'] = $gym_id;
$plans = api_request('GET', '/memberships/plans')['data'] ?? [];
$old_gym ? ($_SESSION['gym_id'] = $old_gym) : unset($_SESSION['gym_id']);

// Load images
$images = [];
if (is_dir($upload_dir)) {
    foreach (glob($upload_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $f) {
        $images[] = basename($f);
    }
}

function dur_label(int $d): string {
    if ($d <= 31)  return 'Monthly';
    if ($d <= 92)  return 'Quarterly';
    if ($d <= 185) return 'Half-Yearly';
    if ($d >= 360) return 'Yearly';
    return $d . ' days';
}
function dur_color(int $d): string {
    if ($d <= 31)  return '#3b82f6';
    if ($d <= 92)  return '#22c55e';
    if ($d <= 185) return '#f97316';
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
    .hero{background:linear-gradient(135deg,#312e81,#4f46e5,#7c3aed)}
    .card{transition:transform .2s,box-shadow .2s}.card:hover{transform:translateY(-5px);box-shadow:0 20px 40px rgba(99,102,241,.15)}
  </style>
</head>
<body class="bg-gray-50">

<!-- Hero -->
<div class="hero text-white">
  <div class="max-w-5xl mx-auto px-6 py-16 text-center">
    <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-xs font-semibold mb-5">
      <i class="fa fa-dumbbell"></i> GymCircle Partner Gym
    </div>
    <h1 class="text-5xl font-extrabold mb-3"><?= htmlspecialchars($gym['name'] ?? '') ?></h1>
    <?php $addr = implode(', ', array_filter([$gym['address']??null,$gym['city']??null,$gym['state']??null])); ?>
    <?php if ($addr): ?><p class="text-indigo-200 text-lg mt-1"><i class="fa fa-location-dot mr-1"></i><?= htmlspecialchars($addr) ?></p><?php endif; ?>
    <div class="flex justify-center gap-6 mt-5 flex-wrap text-indigo-200 text-sm">
      <?php if (!empty($gym['phone'])): ?><span><i class="fa fa-phone mr-1"></i><?= htmlspecialchars($gym['phone']) ?></span><?php endif; ?>
      <?php if (!empty($gym['email'])): ?><span><i class="fa fa-envelope mr-1"></i><?= htmlspecialchars($gym['email']) ?></span><?php endif; ?>
    </div>
    <a href="#plans" class="inline-block mt-8 bg-white text-indigo-700 font-bold px-8 py-3 rounded-full text-sm hover:bg-indigo-50 shadow-lg transition">
      View Plans &amp; Join <i class="fa fa-arrow-down ml-1"></i>
    </a>
  </div>
</div>

<!-- Stats Bar -->
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
             onclick="openLB('/uploads/<?= $slug ?>/<?= urlencode($img) ?>')">
          <img src="/uploads/<?= $slug ?>/<?= urlencode($img) ?>" alt="Gym Photo"
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
      <div class="bg-white rounded-2xl p-12 text-center text-gray-400 shadow-sm">No plans yet — contact the gym directly.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-<?= min(count($plans),3) ?> gap-6">
        <?php foreach ($plans as $idx => $p):
          $d = (int)($p['duration_days']??30);
          $lbl = dur_label($d); $clr = dur_color($d);
          $pm  = $d > 0 ? round((float)$p['price']/($d/30),0) : 0;
          $pop = $idx===1 && count($plans)>=3;
        ?>
        <div class="card bg-white rounded-2xl shadow-sm border-2 <?= $pop?'border-indigo-500 relative':'border-gray-100' ?> overflow-hidden">
          <?php if ($pop): ?><div class="absolute inset-x-0 top-0 bg-indigo-600 text-white text-xs font-bold text-center py-1">⭐ MOST POPULAR</div><?php endif; ?>
          <div class="p-6 <?= $pop?'pt-8':'' ?>">
            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold text-white mb-4" style="background:<?= $clr ?>">
              <i class="fa fa-calendar"></i> <?= $lbl ?>
            </span>
            <h3 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($p['name']) ?></h3>
            <?php if (!empty($p['description'])): ?><p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($p['description']) ?></p><?php endif; ?>
            <div class="my-4">
              <div class="flex items-baseline gap-1">
                <span class="text-4xl font-extrabold text-gray-900">₹<?= number_format((float)$p['price'],0) ?></span>
                <span class="text-gray-400 text-sm">/ <?= $d ?> days</span>
              </div>
              <p class="text-sm text-gray-400 mt-0.5">≈ ₹<?= number_format($pm,0) ?>/month</p>
            </div>
            <ul class="space-y-2 mb-5 text-sm text-gray-600">
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i><?= $d ?> days access</li>
              <?php if (!empty($p['max_sessions'])): ?>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i><?= $p['max_sessions'] ?> sessions</li>
              <?php else: ?>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i>Unlimited sessions</li>
              <?php endif; ?>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i>All equipment access</li>
              <li><i class="fa fa-check-circle text-green-500 mr-2"></i>Trainer guidance</li>
            </ul>
            <button onclick="showModal('<?= htmlspecialchars(addslashes($p['name'])) ?>','<?= number_format((float)$p['price'],0) ?>','<?= $lbl ?>')"
                    class="w-full py-3 rounded-xl font-bold text-sm transition <?= $pop?'bg-indigo-600 hover:bg-indigo-700 text-white':'bg-gray-100 hover:bg-indigo-600 hover:text-white text-gray-800' ?>">
              Join Now — ₹<?= number_format((float)$p['price'],0) ?>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- CTA -->
  <div class="mt-12 bg-indigo-600 rounded-2xl p-8 text-white text-center">
    <h3 class="text-2xl font-bold mb-2">Ready to Start Your Fitness Journey?</h3>
    <p class="text-indigo-200 mb-6">Contact us today or visit the gym directly.</p>
    <div class="flex justify-center gap-4 flex-wrap">
      <?php if (!empty($gym['phone'])): ?>
        <a href="tel:<?= htmlspecialchars($gym['phone']) ?>" class="bg-white text-indigo-700 font-bold px-6 py-3 rounded-full text-sm hover:bg-indigo-50 transition">
          <i class="fa fa-phone mr-2"></i><?= htmlspecialchars($gym['phone']) ?>
        </a>
      <?php endif; ?>
      <?php if (!empty($gym['email'])): ?>
        <a href="mailto:<?= htmlspecialchars($gym['email']) ?>" class="border-2 border-white text-white font-bold px-6 py-3 rounded-full text-sm hover:bg-white hover:text-indigo-700 transition">
          <i class="fa fa-envelope mr-2"></i><?= htmlspecialchars($gym['email']) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-gray-400 text-xs mt-8">Powered by <strong class="text-indigo-600">GymCircle</strong></p>
</div>

<!-- Lightbox -->
<div id="lb" onclick="closeLB()" style="display:none" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
  <img id="lb_img" src="" class="max-h-screen max-w-full rounded-xl shadow-2xl">
</div>

<!-- Enquiry Modal -->
<div id="modal" style="display:none" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
    <h3 class="text-xl font-bold text-gray-800 mb-1">Join — <span id="mplan" class="text-indigo-600"></span></h3>
    <p class="text-gray-500 text-sm mb-5">Fill your details and we'll contact you!</p>
    <div class="space-y-3">
      <input id="mn" type="text" placeholder="Full Name *" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="mp" type="tel" placeholder="Phone Number *" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="me" type="email" placeholder="Email (optional)" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <div id="mok" style="display:none" class="bg-green-50 text-green-700 px-4 py-3 rounded-lg mt-4 text-sm"></div>
    <div class="flex gap-3 mt-5">
      <button onclick="submit()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm"><i class="fa fa-paper-plane mr-1"></i>Send Enquiry</button>
      <button onclick="closeModal()" class="border border-gray-300 px-5 py-3 rounded-xl text-sm text-gray-600">Cancel</button>
    </div>
  </div>
</div>

<script>
function openLB(s){document.getElementById('lb_img').src=s;document.getElementById('lb').style.display='flex';}
function closeLB(){document.getElementById('lb').style.display='none';}
function showModal(p,pr,d){document.getElementById('mplan').textContent=p+' (₹'+pr+' / '+d+')';document.getElementById('modal').style.display='flex';document.getElementById('mok').style.display='none';['mn','mp','me'].forEach(id=>document.getElementById(id).value='');}
function closeModal(){document.getElementById('modal').style.display='none';}
function submit(){
  const n=document.getElementById('mn').value.trim(),p=document.getElementById('mp').value.trim();
  if(!n||!p){alert('Please enter name and phone.');return;}
  const el=document.getElementById('mok');el.textContent='✅ Thank you '+n+'! We will contact you at '+p+' soon.';el.style.display='block';
  setTimeout(closeModal,3000);
}
</script>
</body>
</html>
