<?php
// Trainer public profile — no login required
require_once __DIR__ . '/../helpers.php';

$trainer_id = trim($_GET['id'] ?? '');
if (!$trainer_id) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><body style='text-align:center;padding:80px;font-family:sans-serif'><h2 style='color:#6366f1'>GymCircle</h2><p>Trainer not found.</p></body></html>";
    exit;
}

// Load trainer info from cache file
$cache_file  = __DIR__ . '/../uploads/trainers/' . $trainer_id . '.json';
$trainer     = [];
$services    = [];

if (file_exists($cache_file)) {
    $data     = json_decode(file_get_contents($cache_file), true);
    $trainer  = $data['trainer']  ?? [];
    $services = $data['services'] ?? [];
}

// Also try fetching via API (works if session token exists)
if (empty($trainer)) {
    $res = api_request('GET', '/trainers/' . $trainer_id);
    if ($res['status'] === 200) {
        $trainer = $res['data'] ?? [];
    }
}

if (empty($trainer)) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><body style='text-align:center;padding:80px;font-family:sans-serif'><h2 style='color:#6366f1'>GymCircle</h2><p>Trainer not found.</p></body></html>";
    exit;
}

$name    = ($trainer['user']['first_name'] ?? '') . ' ' . ($trainer['user']['last_name'] ?? '');
$email   = $trainer['user']['email']  ?? '';
$phone   = $trainer['user']['phone']  ?? '';
$bio     = $trainer['bio']            ?? '';
$exp     = $trainer['experience_years'] ?? 0;
$rate    = $trainer['hourly_rate']    ?? 0;
$specs   = $trainer['specializations'] ?? [];
$avail   = $trainer['is_available']   ?? false;

// Load photos
$photos_dir = __DIR__ . '/../uploads/trainers/' . $trainer_id . '/';
$photos = [];
if (is_dir($photos_dir)) {
    foreach (glob($photos_dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $f) {
        $photos[] = basename($f);
    }
}

// Default services if none saved
if (empty($services)) {
    $services = $trainer['services'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($name) ?> — Trainer on GymCircle</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .hero{background:linear-gradient(135deg,#1e1b4b,#4338ca,#6d28d9)}
    .card{transition:transform .2s,box-shadow .2s}.card:hover{transform:translateY(-5px);box-shadow:0 20px 40px rgba(99,102,241,.15)}
  </style>
</head>
<body class="bg-gray-50">

<!-- Hero -->
<div class="hero text-white">
  <div class="max-w-4xl mx-auto px-6 py-14">
    <div class="flex flex-col md:flex-row items-center gap-8">
      <!-- Avatar -->
      <div class="flex-shrink-0">
        <?php $photo_url = !empty($photos) ? '/uploads/trainers/'.$trainer_id.'/'.urlencode($photos[0]) : null; ?>
        <?php if ($photo_url): ?>
          <img src="<?= $photo_url ?>" alt="<?= htmlspecialchars($name) ?>"
               class="w-36 h-36 rounded-full object-cover border-4 border-white shadow-xl">
        <?php else: ?>
          <div class="w-36 h-36 rounded-full bg-indigo-400 flex items-center justify-center text-5xl font-bold border-4 border-white shadow-xl">
            <?= strtoupper(substr($name,0,1)) ?>
          </div>
        <?php endif; ?>
      </div>
      <!-- Info -->
      <div class="text-center md:text-left">
        <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-3 py-1 text-xs font-semibold mb-3">
          <i class="fa fa-dumbbell"></i> Certified Trainer — GymCircle
        </div>
        <h1 class="text-4xl font-extrabold mb-1"><?= htmlspecialchars($name) ?></h1>
        <?php if (!empty($specs)): ?>
          <p class="text-indigo-200 text-sm mb-3">
            <?= implode(' · ', array_map(fn($s) => htmlspecialchars($s['name'] ?? ''), $specs)) ?>
          </p>
        <?php endif; ?>
        <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-2 text-sm text-indigo-200">
          <?php if ($exp): ?><span><i class="fa fa-star mr-1"></i><?= $exp ?> years experience</span><?php endif; ?>
          <?php if ($rate): ?><span><i class="fa fa-indian-rupee-sign mr-1"></i><?= number_format((float)$rate, 0) ?>/hour</span><?php endif; ?>
          <span class="<?= $avail?'text-green-300':'text-red-300' ?>">
            <i class="fa fa-circle text-xs mr-1"></i><?= $avail ? 'Available' : 'Unavailable' ?>
          </span>
        </div>
        <a href="#services" class="inline-block mt-5 bg-white text-indigo-700 font-bold px-7 py-2.5 rounded-full text-sm hover:bg-indigo-50 shadow transition">
          View Services &amp; Book <i class="fa fa-arrow-down ml-1"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<div class="max-w-4xl mx-auto px-6 py-10">

  <!-- About -->
  <?php if ($bio): ?>
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-800 mb-3"><i class="fa fa-user text-indigo-500 mr-2"></i>About Me</h2>
    <p class="text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($bio)) ?></p>
  </div>
  <?php endif; ?>

  <!-- Specializations -->
  <?php if (!empty($specs)): ?>
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fa fa-award text-yellow-500 mr-2"></i>Specializations</h2>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($specs as $s): ?>
        <span class="bg-indigo-100 text-indigo-700 text-sm font-semibold px-4 py-2 rounded-full">
          <?= htmlspecialchars($s['name'] ?? '') ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Photos -->
  <?php if (count($photos) > 1): ?>
  <div class="mb-8">
    <h2 class="text-lg font-bold text-gray-800 mb-4 text-center"><i class="fa fa-images text-indigo-500 mr-2"></i>Training Gallery</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <?php foreach ($photos as $ph): ?>
        <div class="rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition cursor-pointer"
             onclick="openLB('/uploads/trainers/<?= $trainer_id ?>/<?= urlencode($ph) ?>')">
          <img src="/uploads/trainers/<?= $trainer_id ?>/<?= urlencode($ph) ?>"
               class="w-full h-44 object-cover hover:scale-105 transition duration-300">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Services -->
  <div id="services">
    <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center"><i class="fa fa-briefcase text-indigo-500 mr-2"></i>My Services</h2>
    <p class="text-gray-500 text-sm text-center mb-8">Choose the training format that works best for you.</p>

    <?php
    $svc_data = $trainer['cached_services'] ?? $services;
    // Default showcase if no custom services
    $default_svcs = [
      ['icon'=>'fa-building','color'=>'#4f46e5','title'=>'Gym (PT)','desc'=>'Personal Training at the gym. 1-on-1 focused workout sessions with equipment.','price_label'=>'₹'.number_format((float)$rate,0).'/hr'],
      ['icon'=>'fa-home','color'=>'#22c55e','title'=>'Home Training','desc'=>'I come to your home. No gym needed — effective training with minimal equipment.','price_label'=>'Custom'],
      ['icon'=>'fa-video','color'=>'#f97316','title'=>'Online Coaching','desc'=>'Remote training via video call. Diet plans, workout plans, and weekly check-ins.','price_label'=>'Custom'],
    ];
    $display_svcs = !empty($svc_data) ? $svc_data : $default_svcs;
    ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <?php foreach ($display_svcs as $svc): ?>
      <div class="card bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4"
             style="background:<?= $svc['color'] ?? '#4f46e5' ?>">
          <i class="fa <?= $svc['icon'] ?? 'fa-dumbbell' ?>"></i>
        </div>
        <h3 class="font-bold text-gray-800 text-lg mb-2"><?= htmlspecialchars($svc['title'] ?? '') ?></h3>
        <p class="text-gray-500 text-sm mb-4"><?= htmlspecialchars($svc['desc'] ?? '') ?></p>
        <p class="text-indigo-600 font-bold text-lg mb-4"><?= htmlspecialchars($svc['price_label'] ?? 'Contact') ?></p>
        <button onclick="bookSvc('<?= htmlspecialchars(addslashes($svc['title']??'')) ?>','<?= htmlspecialchars(addslashes($svc['price_label']??'')) ?>')"
                class="w-full py-2.5 rounded-xl font-semibold text-sm bg-indigo-600 hover:bg-indigo-700 text-white transition">
          Book This Service
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Contact CTA -->
  <div class="bg-indigo-600 rounded-2xl p-8 text-white text-center">
    <h3 class="text-2xl font-bold mb-2">Ready to Transform Your Fitness?</h3>
    <p class="text-indigo-200 mb-6">Book a free consultation today. No commitment required.</p>
    <div class="flex justify-center gap-4 flex-wrap">
      <?php if ($phone): ?>
        <a href="tel:<?= htmlspecialchars($phone) ?>" class="bg-white text-indigo-700 font-bold px-6 py-3 rounded-full text-sm hover:bg-indigo-50 transition">
          <i class="fa fa-phone mr-2"></i><?= htmlspecialchars($phone) ?>
        </a>
      <?php endif; ?>
      <?php if ($email): ?>
        <a href="mailto:<?= htmlspecialchars($email) ?>" class="border-2 border-white text-white font-bold px-6 py-3 rounded-full text-sm hover:bg-white hover:text-indigo-700 transition">
          <i class="fa fa-envelope mr-2"></i><?= htmlspecialchars($email) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-gray-400 text-xs mt-8">Powered by <strong class="text-indigo-600">GymCircle</strong></p>
</div>

<!-- Lightbox -->
<div id="lb" onclick="closeLB()" style="display:none" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
  <img id="lb_img" src="" class="max-h-screen max-w-full rounded-xl">
</div>

<!-- Booking Modal -->
<div id="bmodal" style="display:none" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
    <h3 class="text-xl font-bold text-gray-800 mb-1">Book — <span id="bsvc" class="text-indigo-600"></span></h3>
    <p class="text-gray-500 text-sm mb-1">Price: <span id="bprice" class="font-semibold text-indigo-600"></span></p>
    <p class="text-gray-400 text-xs mb-5">Fill your details and the trainer will contact you.</p>
    <div class="space-y-3">
      <input id="bn" type="text"  placeholder="Full Name *"      class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="bp" type="tel"   placeholder="Phone Number *"   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <input id="be" type="email" placeholder="Email (optional)" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
      <textarea id="bm" placeholder="Message (goals, preferred time...)" rows="2"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
    </div>
    <div id="bok" style="display:none" class="bg-green-50 text-green-700 px-4 py-3 rounded-lg mt-3 text-sm"></div>
    <div class="flex gap-3 mt-4">
      <button onclick="submitBook()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm"><i class="fa fa-paper-plane mr-1"></i>Send Booking Request</button>
      <button onclick="closeBModal()" class="border border-gray-300 px-5 py-3 rounded-xl text-sm text-gray-600">Cancel</button>
    </div>
  </div>
</div>

<script>
function openLB(s){document.getElementById('lb_img').src=s;document.getElementById('lb').style.display='flex';}
function closeLB(){document.getElementById('lb').style.display='none';}
function bookSvc(svc,price){
  document.getElementById('bsvc').textContent=svc;
  document.getElementById('bprice').textContent=price;
  document.getElementById('bmodal').style.display='flex';
  document.getElementById('bok').style.display='none';
  ['bn','bp','be','bm'].forEach(id=>document.getElementById(id).value='');
}
function closeBModal(){document.getElementById('bmodal').style.display='none';}
function submitBook(){
  const n=document.getElementById('bn').value.trim(),p=document.getElementById('bp').value.trim();
  if(!n||!p){alert('Please enter name and phone.');return;}
  const el=document.getElementById('bok');
  el.textContent='✅ Booking request sent! '+n+', the trainer will contact you at '+p+' soon.';
  el.style.display='block';
  setTimeout(closeBModal,3500);
}
</script>
</body>
</html>
