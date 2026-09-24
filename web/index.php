<?php
require_once __DIR__ . '/helpers.php';
// Already logged in? Redirect to dashboard
if (isset($_SESSION['access_token'])) {
    $is_sa = false;
    foreach (($_SESSION['user_roles'] ?? []) as $r) {
        if ($r['role'] === 'SUPER_ADMIN') { $is_sa = true; break; }
    }
    header('Location: ' . ($is_sa ? '/superadmin/dashboard.php' : '/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>GymCircle — Smart Gym Management Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .hero-bg{background:linear-gradient(135deg,#0f0c29,#302b63,#24243e)}
    .feature-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(99,102,241,.15)}
    .feature-card{transition:all .3s ease}
    .gradient-text{background:linear-gradient(135deg,#818cf8,#c084fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
    .step-line::after{content:'';position:absolute;top:28px;left:calc(50% + 40px);width:calc(100% - 80px);height:2px;background:linear-gradient(90deg,#6366f1,#a855f7);z-index:0}
    @media(max-width:768px){.step-line::after{display:none}}
    .floating{animation:float 6s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    html{scroll-behavior:smooth}
  </style>
</head>
<body class="bg-white">

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur-md border-b border-gray-100 shadow-sm">
  <div class="max-w-6xl mx-auto px-6 py-3 flex justify-between items-center">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
        <i class="fa fa-dumbbell text-white text-sm"></i>
      </div>
      <span class="text-xl font-extrabold text-gray-900">GymCircle</span>
    </div>
    <div class="hidden md:flex items-center gap-6 text-sm text-gray-600">
      <a href="#features" class="hover:text-indigo-600 transition">Features</a>
      <a href="#how-it-works" class="hover:text-indigo-600 transition">How It Works</a>
      <a href="#for-trainers" class="hover:text-indigo-600 transition">For Trainers</a>
      <a href="#pricing" class="hover:text-indigo-600 transition">Plans</a>
    </div>
    <div class="flex items-center gap-3">
      <a href="/login.php" class="text-sm font-semibold text-gray-700 hover:text-indigo-600 transition px-3 py-2">Login</a>
      <a href="/register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition shadow-sm">
        Get Started Free
      </a>
    </div>
  </div>
</nav>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero-bg min-h-screen flex items-center pt-16">
  <div class="max-w-6xl mx-auto px-6 py-20 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <!-- Left Text -->
    <div class="text-white">
      <div class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 rounded-full px-4 py-2 text-xs font-semibold text-indigo-300 mb-6">
        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
        India's Smart Gym Management Platform
      </div>
      <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
        Run Your Gym
        <span class="block gradient-text">Like a Pro</span>
      </h1>
      <p class="text-gray-300 text-lg leading-relaxed mb-8">
        GymCircle handles <strong class="text-white">members, fees, attendance, trainers</strong> and more —
        so you can focus on what matters: <strong class="text-white">building a great gym.</strong>
      </p>
      <div class="flex flex-wrap gap-4 mb-10">
        <a href="/register.php"
           class="bg-indigo-500 hover:bg-indigo-400 text-white font-bold px-8 py-4 rounded-2xl text-base transition shadow-lg shadow-indigo-500/30">
          <i class="fa fa-rocket mr-2"></i> Register Your Gym — Free
        </a>
        <a href="/login.php"
           class="border-2 border-white/30 hover:border-white text-white font-semibold px-8 py-4 rounded-2xl text-base transition">
          <i class="fa fa-sign-in-alt mr-2"></i> Login
        </a>
      </div>
      <!-- Trust Badges -->
      <div class="flex flex-wrap gap-5 text-sm text-gray-400">
        <span><i class="fa fa-check text-green-400 mr-1.5"></i>Free to start</span>
        <span><i class="fa fa-check text-green-400 mr-1.5"></i>No credit card needed</span>
        <span><i class="fa fa-check text-green-400 mr-1.5"></i>Setup in 2 minutes</span>
      </div>
    </div>

    <!-- Right — Dashboard Preview Card -->
    <div class="floating hidden lg:block">
      <div class="bg-white/10 backdrop-blur-lg rounded-3xl border border-white/20 p-6 shadow-2xl">
        <div class="flex items-center gap-2 mb-5">
          <div class="w-3 h-3 bg-red-400 rounded-full"></div>
          <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
          <div class="w-3 h-3 bg-green-400 rounded-full"></div>
          <span class="ml-2 text-white/50 text-xs font-mono">GymCircle Dashboard</span>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="bg-indigo-600/50 rounded-xl p-3 text-white">
            <p class="text-xs text-indigo-200">Members</p>
            <p class="text-2xl font-bold mt-1">248</p>
            <p class="text-xs text-green-300 mt-0.5">↑ 12 this week</p>
          </div>
          <div class="bg-purple-600/50 rounded-xl p-3 text-white">
            <p class="text-xs text-purple-200">Revenue</p>
            <p class="text-2xl font-bold mt-1">₹1.2L</p>
            <p class="text-xs text-green-300 mt-0.5">↑ This month</p>
          </div>
          <div class="bg-green-600/50 rounded-xl p-3 text-white">
            <p class="text-xs text-green-200">Today's Check-ins</p>
            <p class="text-2xl font-bold mt-1">34</p>
            <p class="text-xs text-green-200 mt-0.5">Active members</p>
          </div>
          <div class="bg-orange-500/50 rounded-xl p-3 text-white">
            <p class="text-xs text-orange-200">Pending Fees</p>
            <p class="text-2xl font-bold mt-1">₹8,500</p>
            <p class="text-xs text-red-300 mt-0.5">6 overdue</p>
          </div>
        </div>
        <div class="bg-white/10 rounded-xl p-3">
          <p class="text-white/60 text-xs mb-2">Quick Check-In</p>
          <div class="flex gap-2">
            <div class="flex-1 bg-white/10 rounded-lg px-3 py-2 text-white/50 text-xs">member@email.com</div>
            <div class="bg-green-500 rounded-lg px-3 py-2 text-white text-xs font-bold">✓ In</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ STATS ═══════════════ -->
<section class="bg-indigo-600 py-12">
  <div class="max-w-5xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-white">
    <div><p class="text-4xl font-extrabold">500+</p><p class="text-indigo-200 text-sm mt-1">Gyms Registered</p></div>
    <div><p class="text-4xl font-extrabold">50K+</p><p class="text-indigo-200 text-sm mt-1">Members Managed</p></div>
    <div><p class="text-4xl font-extrabold">₹2Cr+</p><p class="text-indigo-200 text-sm mt-1">Fees Collected</p></div>
    <div><p class="text-4xl font-extrabold">1000+</p><p class="text-indigo-200 text-sm mt-1">Trainers Active</p></div>
  </div>
</section>

<!-- ═══════════════ FEATURES ═══════════════ -->
<section id="features" class="py-20 bg-gray-50">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-14">
      <p class="text-indigo-600 text-sm font-bold uppercase tracking-wide mb-2">Everything You Need</p>
      <h2 class="text-4xl font-extrabold text-gray-900">One Platform, Complete Control</h2>
      <p class="text-gray-500 mt-3 text-lg">No more Excel sheets, no more confusion. GymCircle does it all.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php
      $features = [
        ['icon'=>'fa-users','color'=>'#4f46e5','bg'=>'bg-indigo-50','title'=>'Member Management',
         'desc'=>'Add members, track profiles, view history, manage active/inactive status. Full member directory with search.'],
        ['icon'=>'fa-indian-rupee-sign','color'=>'#16a34a','bg'=>'bg-green-50','title'=>'Fee Collection & Ledger',
         'desc'=>'Collect fees via Cash, UPI, Card or Bank Transfer. Track pending, overdue, and collected amounts with full ledger.'],
        ['icon'=>'fa-clipboard-check','color'=>'#f97316','bg'=>'bg-orange-50','title'=>'Attendance Tracking',
         'desc'=>'Log daily check-ins and check-outs. View attendance history per member. Quick check-in console for reception.'],
        ['icon'=>'fa-id-card','color'=>'#7c3aed','bg'=>'bg-purple-50','title'=>'Membership Plans',
         'desc'=>'Create Monthly, Quarterly, Half-Yearly, and Yearly plans. Auto-calculate effective monthly rate. Assign to members instantly.'],
        ['icon'=>'fa-dumbbell','color'=>'#0891b2','bg'=>'bg-cyan-50','title'=>'Trainer Management',
         'desc'=>'Manage trainers, their services (PT, Home, Online), photos, and availability. Each trainer gets a public profile page.'],
        ['icon'=>'fa-globe','color'=>'#db2777','bg'=>'bg-pink-50','title'=>'Public Gym Page',
         'desc'=>'Your gym gets a beautiful public page with photos, membership plans, and a "Join Now" button for clients to enquire.'],
        ['icon'=>'fa-gauge','color'=>'#d97706','bg'=>'bg-amber-50','title'=>'Live Dashboard',
         'desc'=>"Today's collection, monthly revenue, pending fees, expiring memberships — all on one screen at a glance."],
        ['icon'=>'fa-images','color'=>'#059669','bg'=>'bg-emerald-50','title'=>'Photo Gallery',
         'desc'=>'Upload gym hall, equipment, cardio area photos. Showcase your facility to attract new members online.'],
        ['icon'=>'fa-shield-halved','color'=>'#6366f1','bg'=>'bg-indigo-50','title'=>'Role-Based Access',
         'desc'=>'Owner, Manager, Receptionist, Trainer — different roles with appropriate access. Super Admin oversees all gyms.'],
      ];
      foreach ($features as $f): ?>
        <div class="feature-card bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 <?= $f['bg'] ?>">
            <i class="fa <?= $f['icon'] ?> text-xl" style="color:<?= $f['color'] ?>"></i>
          </div>
          <h3 class="font-bold text-gray-800 text-lg mb-2"><?= $f['title'] ?></h3>
          <p class="text-gray-500 text-sm leading-relaxed"><?= $f['desc'] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section id="how-it-works" class="py-20 bg-white">
  <div class="max-w-5xl mx-auto px-6">
    <div class="text-center mb-14">
      <p class="text-indigo-600 text-sm font-bold uppercase tracking-wide mb-2">Simple Setup</p>
      <h2 class="text-4xl font-extrabold text-gray-900">Get Started in 3 Steps</h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
      <?php
      $steps = [
        ['num'=>'1','icon'=>'fa-building-circle-arrow-right','color'=>'bg-indigo-600',
         'title'=>'Register Your Gym','desc'=>'Fill gym name, your email, password. Your gym page is instantly created.'],
        ['num'=>'2','icon'=>'fa-users-plus','color'=>'bg-purple-600',
         'title'=>'Add Members & Plans','desc'=>'Create membership plans (Monthly/Yearly), add members, assign plans.'],
        ['num'=>'3','icon'=>'fa-share-nodes','color'=>'bg-pink-600',
         'title'=>'Share & Grow','desc'=>'Share your public gym page link. Clients view plans and send join requests.'],
      ];
      foreach ($steps as $i => $s): ?>
        <div class="text-center relative <?= $i<2?'step-line':'' ?>">
          <div class="w-16 h-16 <?= $s['color'] ?> rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-lg relative z-10">
            <i class="fa <?= $s['icon'] ?>"></i>
          </div>
          <div class="absolute -top-2 -right-2 w-7 h-7 bg-gray-900 text-white text-xs font-bold rounded-full flex items-center justify-center z-20 md:block hidden"><?= $s['num'] ?></div>
          <h3 class="font-bold text-gray-800 text-lg mb-2"><?= $s['title'] ?></h3>
          <p class="text-gray-500 text-sm"><?= $s['desc'] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-12">
      <a href="/register.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-10 py-4 rounded-2xl text-base transition shadow-lg">
        <i class="fa fa-rocket mr-2"></i> Start Now — It's Free
      </a>
    </div>
  </div>
</section>

<!-- ═══════════════ FOR TRAINERS ═══════════════ -->
<section id="for-trainers" class="py-20 bg-gradient-to-br from-indigo-50 to-purple-50">
  <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <div>
      <p class="text-purple-600 text-sm font-bold uppercase tracking-wide mb-3">For Trainers</p>
      <h2 class="text-4xl font-extrabold text-gray-900 mb-5">Sell Your Training Services Online</h2>
      <p class="text-gray-600 text-lg mb-8 leading-relaxed">
        Every trainer gets a beautiful <strong>public profile page</strong> to showcase services and attract clients — directly through GymCircle.
      </p>
      <div class="space-y-4">
        <?php
        $tsvc = [
          ['icon'=>'fa-building','color'=>'text-indigo-600','title'=>'Gym PT Sessions','desc'=>'1-on-1 personal training at the gym'],
          ['icon'=>'fa-home','color'=>'text-green-600','title'=>'Home Training','desc'=>'Come to client\'s home — no gym needed'],
          ['icon'=>'fa-video','color'=>'text-orange-600','title'=>'Online Coaching','desc'=>'Remote coaching via video call with plans'],
          ['icon'=>'fa-apple-whole','color'=>'text-emerald-600','title'=>'Diet & Nutrition','desc'=>'Custom meal plans and weekly follow-ups'],
        ];
        foreach ($tsvc as $t): ?>
          <div class="flex items-start gap-4 bg-white rounded-xl p-4 shadow-sm">
            <div class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center flex-shrink-0">
              <i class="fa <?= $t['icon'] ?> <?= $t['color'] ?> text-lg"></i>
            </div>
            <div>
              <p class="font-semibold text-gray-800"><?= $t['title'] ?></p>
              <p class="text-gray-500 text-sm"><?= $t['desc'] ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100">
      <div class="flex items-center gap-4 mb-6 pb-5 border-b border-gray-100">
        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">R</div>
        <div>
          <h3 class="font-bold text-gray-800 text-xl">Rahul Sharma</h3>
          <p class="text-gray-500 text-sm">Certified Trainer · 5 years exp</p>
          <div class="flex gap-2 mt-1">
            <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full">Weight Training</span>
            <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full">Cardio</span>
          </div>
        </div>
      </div>
      <div class="space-y-3">
        <div class="flex justify-between items-center p-3 bg-indigo-50 rounded-xl">
          <div><i class="fa fa-building text-indigo-600 mr-2"></i><span class="font-medium text-sm">Gym PT</span></div>
          <span class="font-bold text-indigo-700">₹500/hr</span>
        </div>
        <div class="flex justify-between items-center p-3 bg-green-50 rounded-xl">
          <div><i class="fa fa-home text-green-600 mr-2"></i><span class="font-medium text-sm">Home Training</span></div>
          <span class="font-bold text-green-700">₹4,000/mo</span>
        </div>
        <div class="flex justify-between items-center p-3 bg-orange-50 rounded-xl">
          <div><i class="fa fa-video text-orange-600 mr-2"></i><span class="font-medium text-sm">Online Coaching</span></div>
          <span class="font-bold text-orange-700">₹2,500/mo</span>
        </div>
      </div>
      <button class="w-full mt-5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm transition">
        <i class="fa fa-calendar-plus mr-2"></i> Book a Session
      </button>
    </div>
  </div>
</section>

<!-- ═══════════════ PRICING ═══════════════ -->
<section id="pricing" class="py-20 bg-white">
  <div class="max-w-5xl mx-auto px-6">
    <div class="text-center mb-14">
      <p class="text-indigo-600 text-sm font-bold uppercase tracking-wide mb-2">Simple Pricing</p>
      <h2 class="text-4xl font-extrabold text-gray-900">Start Free, Grow With Us</h2>
      <p class="text-gray-500 mt-3">No hidden fees. No credit card required to start.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php
      $plans = [
        ['name'=>'Starter','price'=>'Free','period'=>'forever','color'=>'border-gray-200','btn'=>'bg-gray-800 hover:bg-gray-900',
         'features'=>['1 Gym Location','Up to 100 Members','Basic Attendance','Fee Collection','Public Gym Page'],'popular'=>false],
        ['name'=>'Growth','price'=>'₹999','period'=>'per month','color'=>'border-indigo-500','btn'=>'bg-indigo-600 hover:bg-indigo-700',
         'features'=>['3 Gym Locations','Unlimited Members','Advanced Reports','Trainer Profiles','Priority Support','Custom Branding'],'popular'=>true],
        ['name'=>'Enterprise','price'=>'Custom','period'=>'contact us','color'=>'border-purple-300','btn'=>'bg-purple-600 hover:bg-purple-700',
         'features'=>['Unlimited Gyms','All Features','API Access','Dedicated Manager','SLA Guarantee','White Label'],'popular'=>false],
      ];
      foreach ($plans as $p): ?>
        <div class="rounded-2xl border-2 <?= $p['color'] ?> p-7 relative <?= $p['popular']?'shadow-xl':'' ?>">
          <?php if ($p['popular']): ?>
            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-xs font-bold px-4 py-1 rounded-full">⭐ Most Popular</div>
          <?php endif; ?>
          <h3 class="font-bold text-gray-800 text-xl mb-1"><?= $p['name'] ?></h3>
          <div class="flex items-baseline gap-1 mb-1">
            <span class="text-4xl font-extrabold text-gray-900"><?= $p['price'] ?></span>
          </div>
          <p class="text-gray-400 text-xs mb-6"><?= $p['period'] ?></p>
          <ul class="space-y-2.5 mb-8">
            <?php foreach ($p['features'] as $f): ?>
              <li class="flex items-center gap-2 text-sm text-gray-600">
                <i class="fa fa-check-circle text-green-500 flex-shrink-0"></i><?= $f ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <a href="/register.php" class="block text-center <?= $p['btn'] ?> text-white font-bold py-3 rounded-xl text-sm transition">
            Get Started
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ CTA BANNER ═══════════════ -->
<section class="py-20 bg-gradient-to-r from-indigo-600 to-purple-700 text-white text-center">
  <div class="max-w-3xl mx-auto px-6">
    <h2 class="text-4xl font-extrabold mb-4">Ready to Transform Your Gym?</h2>
    <p class="text-indigo-200 text-lg mb-8">Join hundreds of gym owners who manage smarter with GymCircle.</p>
    <div class="flex justify-center gap-4 flex-wrap">
      <a href="/register.php" class="bg-white text-indigo-700 font-bold px-10 py-4 rounded-2xl text-base hover:bg-indigo-50 transition shadow-lg">
        <i class="fa fa-rocket mr-2"></i> Register Your Gym Free
      </a>
      <a href="/login.php" class="border-2 border-white/50 hover:border-white text-white font-semibold px-8 py-4 rounded-2xl text-base transition">
        <i class="fa fa-sign-in-alt mr-2"></i> Login
      </a>
    </div>
  </div>
</section>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="bg-gray-900 text-gray-400 py-12">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
      <div>
        <div class="flex items-center gap-2 mb-3">
          <div class="w-7 h-7 bg-indigo-600 rounded-lg flex items-center justify-center">
            <i class="fa fa-dumbbell text-white text-xs"></i>
          </div>
          <span class="text-white font-bold text-lg">GymCircle</span>
        </div>
        <p class="text-sm leading-relaxed">Smart gym management platform for modern gym owners across India.</p>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3 text-sm">Platform</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="#features" class="hover:text-white transition">Features</a></li>
          <li><a href="#how-it-works" class="hover:text-white transition">How It Works</a></li>
          <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
          <li><a href="#for-trainers" class="hover:text-white transition">For Trainers</a></li>
        </ul>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3 text-sm">Account</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="/register.php" class="hover:text-white transition">Register Gym</a></li>
          <li><a href="/login.php" class="hover:text-white transition">Owner Login</a></li>
          <li><a href="/login.php" class="hover:text-white transition">Super Admin</a></li>
        </ul>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3 text-sm">Contact</h4>
        <ul class="space-y-2 text-sm">
          <li><i class="fa fa-envelope mr-2"></i>support@gymcircle.in</li>
          <li><i class="fa fa-phone mr-2"></i>+91 98765 43210</li>
          <li><i class="fa fa-location-dot mr-2"></i>India</li>
        </ul>
      </div>
    </div>
    <div class="border-t border-gray-800 pt-6 flex flex-col md:flex-row justify-between items-center gap-3 text-sm">
      <p>© <?= date('Y') ?> GymCircle. All rights reserved.</p>
      <p>Made with <span class="text-red-500">❤</span> for Indian Gym Owners</p>
    </div>
  </div>
</footer>

<!-- Scroll-to-top button -->
<button onclick="window.scrollTo({top:0,behavior:'smooth'})"
        class="fixed bottom-6 right-6 w-12 h-12 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg flex items-center justify-center transition z-40">
  <i class="fa fa-arrow-up"></i>
</button>

</body>
</html>
