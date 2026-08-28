<?php
require_once __DIR__ . '/helpers.php';
require_roles(['GYM_OWNER', 'GYM_MANAGER', 'RECEPTIONIST']);

$gym_id = $_SESSION['gym_id'] ?? null;
if (!$gym_id) {
    echo "<h1>No Gym context selected. Log in with a Gym Role.</h1>";
    exit;
}

// Fetch Gym Details
$gym_res = api_request('GET', '/gyms/' . $gym_id);
$gym_name = $gym_res['status'] === 200 ? $gym_res['data']['name'] : 'My Gym';

// Fetch Dashboard Metrics (Only for Owners/Managers, fall back to empty for Receptionist)
$metrics = [
    'today_collection' => 0.00,
    'monthly_collection' => 0.00,
    'pending_fees_total' => 0.00,
    'overdue_count' => 0,
    'expiring_count' => 0
];

if (has_role(['GYM_OWNER', 'GYM_MANAGER'])) {
    $metrics_res = api_request('GET', '/payments/dashboard-metrics');
    if ($metrics_res['status'] === 200) {
        $metrics = $metrics_res['data'];
    }
}

// Handle Check-In triggers
$checkin_message = null;
$checkin_status = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    $client_email = $_POST['client_email'] ?? '';
    
    // 1. Resolve client user by email (we list clients of this gym and search)
    $clients_res = api_request('GET', '/clients');
    $client_uuid = null;
    if ($clients_res['status'] === 200) {
        foreach ($clients_res['data'] as $c) {
            if (strtolower($c['email']) === strtolower($client_email)) {
                $client_uuid = $c['id'];
                break;
            }
        }
    }

    if ($client_uuid) {
        $ci_res = api_request('POST', '/attendance/check-in', [
            'client_id' => $client_uuid
        ]);

        if ($ci_res['status'] === 201) {
            $ci_data = $ci_res['data'];
            $checkin_status = 'success';
            $checkin_message = $ci_data['message'];
            if ($ci_data['has_pending_fees']) {
                $checkin_message .= " [WARNING: Client has pending fees dues!]";
            }
            if ($ci_data['membership_expired']) {
                $checkin_message .= " [ALERT: Membership plan expired!]";
            }
        } else {
            $checkin_status = 'error';
            $checkin_message = $ci_res['data']['detail'] ?? 'Failed to complete check-in.';
        }
    } else {
        $checkin_status = 'error';
        $checkin_message = 'Client email not registered in this Gym.';
    }
}

// Fetch Clients list for Receptionist quick view
$clients_list = [];
$clients_res = api_request('GET', '/clients');
if ($clients_res['status'] === 200) {
    $clients_list = $clients_res['data'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymCircle - <?php echo htmlspecialchars($gym_name); ?> Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <!-- Navbar -->
    <nav class="bg-indigo-600 p-4 shadow-md text-white flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">GymCircle</h1>
            <p class="text-xs opacity-75">Gym Portal: <?php echo htmlspecialchars($gym_name); ?></p>
        </div>
        <div class="flex items-center space-x-6">
            <span class="text-sm font-semibold">Active Gym Context ID: <?php echo htmlspecialchars(substr($gym_id, 0, 8)); ?>...</span>
            <a href="logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-4 py-2 rounded text-sm font-bold">Sign Out</a>
        </div>
    </nav>

    <!-- Main Content Grid -->
    <div class="container mx-auto px-4 py-8">
        <?php if (has_role(['GYM_OWNER', 'GYM_MANAGER'])): ?>
            <!-- Owner Statistics Row -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                    <p class="text-sm text-gray-500 font-bold uppercase">Today's Collections</p>
                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format((float)$metrics['today_collection'], 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                    <p class="text-sm text-gray-500 font-bold uppercase">Monthly Collections</p>
                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format((float)$metrics['monthly_collection'], 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500">
                    <p class="text-sm text-gray-500 font-bold uppercase">Pending Collections</p>
                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format((float)$metrics['pending_fees_total'], 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
                    <p class="text-sm text-gray-500 font-bold uppercase">Overdue Accounts</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($metrics['overdue_count']); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-indigo-500">
                    <p class="text-sm text-gray-500 font-bold uppercase">Expiring (7 days)</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($metrics['expiring_count']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Client Lookup -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Gym Members Quick Directory</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($clients_list)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No clients registered yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients_list as $client): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Col: Reception Check-In Console -->
            <div class="space-y-8">
                <!-- Check-in Widget -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Reception Check-In Console</h2>
                    
                    <?php if ($checkin_message): ?>
                        <div class="p-4 rounded-md mb-4 <?php echo $checkin_status === 'success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300'; ?>">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($checkin_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="dashboard.php" class="space-y-4">
                        <input type="hidden" name="action" value="checkin">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Scan Member Email</label>
                            <input type="email" name="client_email" placeholder="client@example.com" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit"
                                class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                            Verify & Log Attendance
                        </button>
                    </form>
                </div>

                <!-- Invite Codes Widget -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Platform Administration</h2>
                    <p class="text-sm text-gray-500 mb-4">Staff members and Trainers join GymCircle via secure invitation tokens issued by Owners.</p>
                    <a href="#" class="inline-block w-full text-center py-2 px-4 border border-indigo-600 text-indigo-600 rounded-md text-sm font-bold hover:bg-indigo-50">
                        Generate Invite Tokens
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
