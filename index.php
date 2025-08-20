<?php
error_reporting(0);

// --- Configuration ---
define('7937408258:AAG0SwVVa3JYEGKGOabuL8xE0v2uDtv-DT4', '7937408258:AAG0SwVVa3JYEGKGOabuL8xE0v2uDtv-DT4'); // !!! আপনার বট টোকেন এখানে দিন (BotFather থেকে পাওয়া) !!!
define('ADMIN_CHAT_ID', 'YOUR_ADMIN_CHAT_ID'); // !!! আপনার নিজের টেলিগ্রাম আইডি দিন (@userinfobot থেকে পাওয়া) !!!

// --- 1. Database Connection & Auto Schema Setup ---
include 'db.php'; 

function setupDatabase($conn) {
    // User table with referral tracking
    $conn->query("CREATE TABLE IF NOT EXISTS `users` (`id` INT AUTO_INCREMENT PRIMARY KEY, `telegram_id` BIGINT NOT NULL UNIQUE, `first_name` VARCHAR(255) NOT NULL, `username` VARCHAR(255), `balance` DECIMAL(20, 4) DEFAULT 0.00, `total_earned` DECIMAL(20, 4) DEFAULT 0.00, `tasks_done` INT DEFAULT 0, `referrals_count` INT DEFAULT 0, `referred_by` BIGINT NULL, `daily_tasks_completed` INT DEFAULT 0, `hourly_tasks_completed` INT DEFAULT 0, `last_daily_task_date` DATE NULL, `last_hourly_task_time` DATETIME NULL, `photo` VARCHAR(255) NULL, `last_claimed_at` DATE NULL, `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Add referred_by column if it doesn't exist (for backward compatibility)
    $result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'referred_by'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `referred_by` BIGINT NULL AFTER `referrals_count`");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `tg_tasks` (`id` INT AUTO_INCREMENT PRIMARY KEY, `channel_username` VARCHAR(255) NOT NULL, `reward_amount` DECIMAL(10, 2) NOT NULL, `join_link` VARCHAR(255) NOT NULL, `is_active` BOOLEAN DEFAULT TRUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $conn->query("CREATE TABLE IF NOT EXISTS `user_completed_tg_tasks` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `task_id` INT NOT NULL, `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `user_task_unique` (`user_id`, `task_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $conn->query("CREATE TABLE IF NOT EXISTS `withdrawals` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `amount` DECIMAL(20, 4) NOT NULL, `wallet_address` VARCHAR(255) NOT NULL, `method` VARCHAR(50) NOT NULL, `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending', `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    if($conn->query("SELECT id FROM tg_tasks LIMIT 1")->num_rows == 0) { 
        $conn->query("INSERT INTO `tg_tasks` (`channel_username`, `reward_amount`, `join_link`) VALUES ('@vip_earnings_force', 10.00, 'https://t.me/vip_earnings_force'), ('@EarningOfficialBD1', 10.00, 'https://t.me/EarningOfficialBD1'), ('@Allearningsbd', 10.00, 'https://t.me/Allearningsbd'), ('@ultimateshibpayout', 40.00, 'https://t.me/ultimateshibpayout')"); 
    }
}
setupDatabase($conn);

// --- 2. Notification & Helper Functions ---
function sendTelegramMessage($chatId, $message) {
    if (defined('BOT_TOKEN') && BOT_TOKEN !== 'YOUR_BOT_TOKEN') {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        $data = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        $options = ['http' => ['method'  => 'POST', 'header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data), 'ignore_errors' => true]];
        @file_get_contents($url, false, stream_context_create($options));
    }
}

function sendAdminNotification($message) { 
    if(defined('ADMIN_CHAT_ID') && ADMIN_CHAT_ID !== 'YOUR_ADMIN_CHAT_ID') sendTelegramMessage(ADMIN_CHAT_ID, $message); 
}
function sendUserNotification($userId, $message) { sendTelegramMessage($userId, $message); }

// *** NEW FUNCTION: To check if user is a member of a channel ***
function checkTelegramMembership($channelUsername, $userId) {
    if (defined('BOT_TOKEN') && BOT_TOKEN !== 'YOUR_BOT_TOKEN') {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getChatMember?chat_id=" . urlencode($channelUsername) . "&user_id=" . $userId;
        $response = @file_get_contents($url);
        if ($response === FALSE) return false;

        $data = json_decode($response, true);
        if ($data && $data['ok']) {
            $status = $data['result']['status'];
            // User is a member if status is 'member', 'administrator', or 'creator'
            return in_array($status, ['member', 'administrator', 'creator', 'restricted']);
        }
    }
    return false; // Assume not a member if API fails
}


// --- 3. AJAX Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $telegram_id = intval($_POST['telegram_id'] ?? 0);
    $action = $_POST['action'];

    if ($telegram_id <= 0) { echo json_encode(['success' => false, 'message' => 'অবৈধ ইউজার আইডি।']); exit; }
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE telegram_id = ?"); $user_stmt->bind_param("i", $telegram_id); $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc(); $user_stmt->close();
    if (!$user) { echo json_encode(['success' => false, 'message' => 'ইউজার খুঁজে পাওয়া যায়নি।']); exit; }

    $userInfoForAdmin = "<b>User:</b> " . htmlspecialchars($user['first_name']) . " (ID: <code>" . $user['telegram_id'] . "</code>)";

    switch ($action) {
        case 'claim_bonus':
            // ... (এই অংশ অপরিবর্তিত) ...
            $today = date('Y-m-d');
            if ($user['last_claimed_at'] == $today) { echo json_encode(['success' => false, 'message' => 'আপনি আজ ইতিমধ্যেই বোনাস সংগ্রহ করেছেন।']); exit; }
            $bonus_amount = 15;
            $new_balance = $user['balance'] + $bonus_amount;
            $new_total_earned = $user['total_earned'] + $bonus_amount;
            $update_stmt = $conn->prepare("UPDATE users SET balance = ?, total_earned = ?, last_claimed_at = ? WHERE id = ?");
            $update_stmt->bind_param("ddsi", $new_balance, $new_total_earned, $today, $user['id']);
            if($update_stmt->execute()) {
                sendAdminNotification("🎁 <b>Daily Bonus Claimed</b>\n\n" . $userInfoForAdmin . "\n<b>Amount:</b> " . $bonus_amount . " SHIB");
                sendUserNotification($user['telegram_id'], "🎁 অভিনন্দন! আপনি সফলভাবে আপনার দৈনিক " . bn($bonus_amount) . " SHIB বোনাস সংগ্রহ করেছেন।");
                echo json_encode(['success' => true, 'message' => 'অভিনন্দন! আপনি ' . bn($bonus_amount) . ' SHIB পেয়েছেন।', 'new_balance' => $new_balance]);
            } else { echo json_encode(['success' => false, 'message' => 'একটি সমস্যা হয়েছে।']); }
            $update_stmt->close();
            break;

        case 'verify_tg_task':
            // *** UPDATED: Real Verification Logic ***
            $task_id = intval($_POST['task_id'] ?? 0);
            $task_stmt = $conn->prepare("SELECT * FROM tg_tasks WHERE id = ? AND is_active = 1"); $task_stmt->bind_param("i", $task_id); $task_stmt->execute();
            $task = $task_stmt->get_result()->fetch_assoc(); $task_stmt->close();
            if(!$task) { echo json_encode(['success' => false, 'message' => 'টাস্কটি আর সক্রিয় নেই।']); exit; }
            
            // Check if already completed
            $check_stmt = $conn->prepare("SELECT id FROM user_completed_tg_tasks WHERE user_id = ? AND task_id = ?"); $check_stmt->bind_param("ii", $user['id'], $task_id); $check_stmt->execute();
            if($check_stmt->get_result()->num_rows > 0) { echo json_encode(['success' => false, 'message' => 'আপনি এই টাস্কটি ইতিমধ্যেই সম্পন্ন করেছেন।']); $check_stmt->close(); exit; } $check_stmt->close();

            // *** REAL MEMBERSHIP CHECK ***
            if (!checkTelegramMembership($task['channel_username'], $user['telegram_id'])) {
                echo json_encode(['success' => false, 'message' => 'ভেরিফিকেশন ব্যর্থ হয়েছে। অনুগ্রহ করে চ্যানেলে জয়েন করুন এবং আপনার বটকে চ্যানেলের অ্যাডমিন হিসেবে যোগ করুন।']);
                exit;
            }

            // If verification is successful, proceed to give reward
            $new_balance = $user['balance'] + $task['reward_amount']; $new_total_earned = $user['total_earned'] + $task['reward_amount']; $new_tasks_done = $user['tasks_done'] + 1;
            $conn->begin_transaction();
            try {
                $update_user = $conn->prepare("UPDATE users SET balance = ?, total_earned = ?, tasks_done = ? WHERE id = ?"); $update_user->bind_param("ddii", $new_balance, $new_total_earned, $new_tasks_done, $user['id']); $update_user->execute();
                $insert_completion = $conn->prepare("INSERT INTO user_completed_tg_tasks (user_id, task_id) VALUES (?, ?)"); $insert_completion->bind_param("ii", $user['id'], $task_id); $insert_completion->execute();
                $conn->commit();
                
                sendAdminNotification("✅ <b>TG Task Completed</b>\n\n" . $userInfoForAdmin . "\n<b>Task:</b> " . htmlspecialchars($task['channel_username']) . "\n<b>Reward:</b> " . $task['reward_amount'] . " SHIB");
                sendUserNotification($user['telegram_id'], "✅ টাস্ক সম্পন্ন!\n\nআপনি '" . htmlspecialchars($task['channel_username']) . "' চ্যানেলে জয়েন করার জন্য " . bn($task['reward_amount'], 2) . " SHIB পেয়েছেন।");
                echo json_encode(['success' => true, 'message' => 'টাস্ক সম্পন্ন! '.bn($task['reward_amount'], 2).' SHIB যোগ করা হয়েছে।', 'new_balance' => $new_balance]);
            } catch (Exception $e) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।']); }
            break;

        case 'request_withdrawal':
            // ... (এই অংশ অপরিবর্তিত) ...
            $amount = floatval($_POST['amount'] ?? 0); $address = trim($_POST['address'] ?? ''); $method = trim($_POST['method'] ?? '');
            if ($amount < 100) { echo json_encode(['success' => false, 'message' => 'সর্বনিম্ন ১০০ SHIB উত্তোলন করা যাবে।']); exit; }
            if ($amount > $user['balance']) { echo json_encode(['success' => false, 'message' => 'আপনার অ্যাকাউন্টে পর্যাপ্ত ব্যালেন্স নেই।']); exit; }
            if (empty($address) || empty($method)) { echo json_encode(['success' => false, 'message' => 'অনুগ্রহ করে সকল তথ্য পূরণ করুন।']); exit; }
            $new_balance = $user['balance'] - $amount;
            $conn->begin_transaction();
            try {
                $update_user = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?"); $update_user->bind_param("di", $new_balance, $user['id']); $update_user->execute();
                $insert_req = $conn->prepare("INSERT INTO withdrawals (user_id, amount, wallet_address, method) VALUES (?, ?, ?, ?)"); $insert_req->bind_param("idss", $user['id'], $amount, $address, $method); $insert_req->execute();
                $conn->commit();
                sendAdminNotification("💰 <b>New Withdrawal Request</b>\n\n" . $userInfoForAdmin . "\n<b>Amount:</b> " . $amount . " SHIB\n<b>Method:</b> " . htmlspecialchars($method) . "\n<b>Address:</b> <code>" . htmlspecialchars($address) . "</code>");
                sendUserNotification($user['telegram_id'], "💰 আপনার " . bn($amount) . " SHIB উত্তোলনের অনুরোধটি গ্রহণ করা হয়েছে। এটি পর্যালোচনার অধীনে আছে।");
                echo json_encode(['success' => true, 'message' => 'আপনার উত্তোলন অনুরোধটি গ্রহণ করা হয়েছে।', 'new_balance' => $new_balance]);
            } catch (Exception $e) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।']); }
            break;
    }
    exit;
}

// --- 4. Main Page Load ---
$telegram_id = intval($_GET['telegram_id'] ?? 0);
$referrer_id = intval($_GET['ref'] ?? 0); // *** Get referrer ID from URL

if ($telegram_id <= 0) { die("❌ সঠিক Telegram ID প্রদান করুন।"); }

$stmt = $conn->prepare("SELECT * FROM users WHERE telegram_id = ?"); $stmt->bind_param("i", $telegram_id); $stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) { 
    // New user registration
    $first_name = "User " . $telegram_id; // A default name
    $referrer_to_save = null;

    if ($referrer_id > 0 && $referrer_id != $telegram_id) {
        // Check if referrer exists
        $ref_check_stmt = $conn->prepare("SELECT id, first_name FROM users WHERE telegram_id = ?");
        $ref_check_stmt->bind_param("i", $referrer_id);
        $ref_check_stmt->execute();
        $referrer_user = $ref_check_stmt->get_result()->fetch_assoc();
        if ($referrer_user) {
            $referrer_to_save = $referrer_id;
        }
        $ref_check_stmt->close();
    }

    $insert_stmt = $conn->prepare("INSERT INTO users (telegram_id, first_name, referred_by) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("isi", $telegram_id, $first_name, $referrer_to_save);
    $insert_stmt->execute();
    $new_user_id = $insert_stmt->insert_id;
    $insert_stmt->close();

    // Now, fetch the newly created user's data
    $stmt->execute();
    $user_result = $stmt->get_result();
    sendAdminNotification("🎉 <b>New User Registered!</b>\n\n<b>Name:</b> ".htmlspecialchars($first_name)."\n<b>ID:</b> <code>".$telegram_id."</code>" . ($referrer_to_save ? "\n<b>Referred by:</b> <code>".$referrer_to_save."</code>" : ""));

    // *** Notify the referrer and update their count ***
    if ($referrer_to_save) {
        $update_ref_count = $conn->prepare("UPDATE users SET referrals_count = referrals_count + 1 WHERE telegram_id = ?");
        $update_ref_count->bind_param("i", $referrer_to_save);
        $update_ref_count->execute();
        $update_ref_count->close();
        
        sendUserNotification($referrer_to_save, "🎉 অভিনন্দন! ".htmlspecialchars($first_name)." আপনার রেফারেল লিঙ্কের মাধ্যমে জয়েন করেছেন।");
    }
}
$user = $user_result->fetch_assoc(); $stmt->close();

function bn($number, $decimals = 0) { return str_replace(range(0, 9), ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], number_format(floatval($number), $decimals)); }
$photo = !empty($user['photo']) ? $user['photo'] : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2NlY2VjZSI+PHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyczQuNDggMTAgMTAgMTAgMTAtNC40OCAxMC0xMFMxNy41MiAyIDEyIDJ6bTAgM2MxLjY2IDAgMyAxLjM0IDMgMy4zMy0xLjMzIDAtMy0xLjM0LTMtM3ptMCAxN2MtMi41IDAtNC41My0xLjM0LTUuNjgtMy4zNGMuMDMtLjAyIDIuMzEtMS4xNiA1LjY4LTEuMTYgMy4zNyAwIDUuNjUgMS4xNCA1LjY4IDEuMTZDNjYuNTMgMTcuNjYgMTQuNSAyMCAxMiAyMHoiLz48L3N2Zz4=';

// !!! আপনার বটের ইউজারনেম এখানে দিন (@ ছাড়া) !!!
$bot_username = "testskgbot_bot"; 
$referral_link = "https://t.me/{$bot_username}?start=ref_{$user['telegram_id']}";
$is_claimed_today = (isset($user['last_claimed_at']) && $user['last_claimed_at'] == date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* আপনার দেওয়া CSS অপরিবর্তিত রাখা হয়েছে */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap');
        :root {
            --gradient-start: #a855f7; --gradient-end: #ec4899; --yellow-color: #facc15;
            --primary-color: #5d5fef; --bg-color: #ffffff; --body-bg: #f8f9fa; --text-dark: #212529; --text-light: #6c757d; --card-bg: #f8f9fa;
        }
        body { margin: 0; font-family: 'Roboto', 'Hind Siliguri', sans-serif; background-color: var(--body-bg); color: var(--text-dark); -webkit-tap-highlight-color: transparent; }
        .app-container { max-width: 450px; margin: 0 auto; background-color: var(--bg-color); min-height: 100vh; display: flex; flex-direction: column; }
        .app-header { display: flex; align-items: center; gap: 12px; padding: 15px 16px; background-color: var(--body-bg); }
        .app-header .img-container { position: relative; }
        .app-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .app-header .img-container::after { content: ''; position: absolute; bottom: 1px; right: 1px; width: 9px; height: 9px; background-color: #10b981; border-radius: 50%; border: 1.5px solid white; }
        .app-header .details .name { font-family: 'Hind Siliguri', sans-serif; font-size: 17px; font-weight: 600; margin: 0; }
        .app-header .details .balance { font-size: 13px; margin: 2px 0 0 0; }
        main { flex-grow: 1; padding: 16px; overflow-y: auto; padding-bottom: 90px; }
        .view { display: none; }
        .view.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        h2.view-title { font-size: 22px; font-weight: 700; margin: 0 0 20px 0; }
        .btn-primary { background-color: var(--primary-color); color: white; border: none; padding: 14px 20px; border-radius: 14px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; text-align: center; transition: background-color 0.2s, transform 0.2s; }
        .btn-primary:not(:disabled):active { transform: scale(0.98); }
        .btn-primary:disabled { background-color: #a5a6f6; cursor: not-allowed; }
        .info-card { background: var(--card-bg); padding: 16px; border-radius: 12px; margin-bottom: 12px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .stat-box { background: var(--card-bg); padding: 20px; border-radius: 12px; text-align: center; }
        .stat-box .value { font-size: 28px; font-weight: 700; margin: 0 0 4px 0; }
        .stat-box .label { font-size: 14px; color: var(--text-light); }
        .stat-box .limit { font-size: 12px; color: #adb5bd; margin-top: 8px; }
        .task-item { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--card-bg); border-radius: 12px; margin-bottom: 12px; }
        .task-item .left-content { flex-grow: 1; }
        .task-item .info .username { font-size: 16px; font-weight: 600; }
        .task-item .info .desc { font-size: 13px; color: var(--text-light); margin: 4px 0 10px 0; }
        .task-item .reward { background: #fffbe6; color: #ca8a04; padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; margin-left: 10px; }
        .task-item .actions { display: flex; gap: 8px; margin-top: 10px; }
        .task-item .btn-join, .task-item .btn-verify { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
        .task-item .btn-join { background-color: var(--primary-color); color: white; }
        .task-item .btn-verify { background-color: #e9ecef; color: var(--text-dark); }
        .task-item .btn-verify:disabled { background-color: #ced4da; color: #6c757d; }
        .refer-steps { list-style: none; padding: 0; margin: 20px 0; }
        .refer-steps li { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
        .refer-steps .icon-bg { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .refer-steps li:nth-child(1) .icon-bg { background: #e0e7ff; color: #4338ca; }
        .refer-steps li:nth-child(2) .icon-bg { background: #dcfce7; color: #166534; }
        .refer-steps li:nth-child(3) .icon-bg { background: #ffedd5; color: #9a3412; }
        .refer-steps .text h3 { margin: 0 0 4px 0; font-size: 16px; }
        .refer-steps .text p { margin: 0; font-size: 14px; color: var(--text-light); }
        .referral-link-box { display: flex; align-items: center; background: var(--card-bg); padding: 8px; border-radius: 10px; margin-bottom: 16px; }
        .referral-link-box input { flex-grow: 1; border: none; background: transparent; font-size: 14px; outline: none; }
        .referral-link-box button { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-light); }
        .share-buttons { display: flex; gap: 12px; }
        .share-buttons .share-btn { flex-grow: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; border-radius: 10px; text-decoration: none; color: white; font-weight: 500; }
        .btn-telegram { background: #2AABEE; } .btn-whatsapp { background: #25D366; } .btn-twitter { background: #1DA1F2; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 15px; }
        .input-wrapper { position: relative; }
        .input-wrapper input { width: 100%; background: var(--card-bg); border: 1px solid #dee2e6; border-radius: 10px; padding: 14px; font-size: 16px; box-sizing: border-box; }
        .input-wrapper .unit { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .form-group .note { font-size: 12px; color: var(--text-light); margin-top: 6px; }
        .payment-methods { display: flex; gap: 12px; }
        .payment-method { flex-grow: 1; padding: 12px; border: 2px solid #dee2e6; border-radius: 10px; cursor: pointer; text-align: center; }
        .payment-method.active { border-color: var(--primary-color); background: #f3f4ff; }
        .payment-method img { height: 24px; margin-bottom: 6px; }
        .payment-method .name { font-weight: 500; }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-header img { width: 80px; height: 80px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-header .name { font-size: 20px; font-weight: 700; margin: 10px 0 4px 0; }
        .profile-header .joined { font-size: 14px; color: var(--text-light); }
        .profile-links .link-item { display: flex; align-items: center; gap: 12px; padding: 14px; background: var(--card-bg); border-radius: 10px; margin-top: 10px; text-decoration: none; color: var(--text-dark); font-weight: 500; }
        .profile-links .link-item i { color: var(--text-light); }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; max-width: 450px; margin: 0 auto; background-color: #ffffff; display: flex; justify-content: space-around; padding-top: 8px; box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08); border-top: 1px solid #f3f4f6; padding-bottom: calc(env(safe-area-inset-bottom, 0) + 8px); }
        .nav-item { cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--text-light); text-decoration: none; font-size: 11px; font-weight: 500; flex: 1; }
        .nav-item .icon { font-size: 18px; width: 24px; text-align: center;}
        .nav-item .ad-icon { background-color: #6c757d; color: white; font-size: 10px; font-weight: bold; border-radius: 4px; padding: 2px 4px; line-height: 1.2; width: auto; display: inline-block; }
        .nav-item.active .ad-icon { background-color: var(--primary-color); }
        .nav-item.active { color: var(--primary-color); }
        #toast { visibility: hidden; min-width: 250px; background-color: #212529; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 10; left: 50%; transform: translateX(-50%); bottom: 90px; font-size: 15px; opacity: 0; transition: all 0.4s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        #toast.show { visibility: visible; opacity: 1; }
        .home-card { background: linear-gradient(120deg, var(--gradient-start), var(--gradient-end)); border-radius: 20px; padding: 20px; color: white; margin-top: 0; margin-bottom: 20px; box-shadow: 0 8px 25px -5px rgba(168, 85, 247, 0.3), 0 4px 15px -6px rgba(168, 85, 247, 0.2); }
        .home-card h2 { margin-top: 0; font-size: 20px; font-weight: 700; }
        .home-card p { margin: 4px 0 20px 0; opacity: 0.95; font-size: 14px; }
        .streak-days { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 15px; }
        .day-box { background: rgba(255, 255, 255, 0.1); border-radius: 12px; text-align: center; padding: 10px 5px; flex-grow: 1; border: 2px solid rgba(255, 255, 255, 0.2); }
        .day-box.active { border-color: var(--yellow-color); background: rgba(255, 255, 255, 0.2); }
        .day-box .icon { font-size: 18px; margin-bottom: 6px; }
        .day-box .day-label { font-size: 13px; font-weight: 500; }
        .day-box .day-reward { font-size: 14px; font-weight: 700; }
        .progress-bar-container { height: 6px; background-color: rgba(255, 255, 255, 0.3); border-radius: 3px; margin-bottom: 20px; overflow: hidden; }
        .progress-bar { width: 12.5%; height: 100%; background: linear-gradient(90deg, #f59e0b, #facc15); border-radius: 3px; }
        .claim-btn { width: 100%; padding: 14px; background-color: var(--yellow-color); border: none; border-radius: 14px; font-size: 17px; font-weight: 700; color: #422006; cursor: pointer; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 10px -2px rgba(250, 204, 21, 0.4); }
        .claim-btn:disabled { background-color: #eab308; color: #78350f; cursor: not-allowed; opacity: 0.8; }
        .claim-btn:not(:disabled):active { transform: scale(0.98); box-shadow: 0 2px 5px -1px rgba(250, 204, 21, 0.4); }
        .start-referring-btn { background-color: white; color: #6d28d9; border: none; border-radius: 12px; padding: 12px 20px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; text-align: center; text-decoration: none; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header">
             <div class="img-container"><img src="<?= htmlspecialchars($photo) ?>" alt="Profile"></div>
            <div class="details"> <p class="name"><?= htmlspecialchars($user['first_name']) ?> ভাই</p> <p class="balance">Balance: <span id="balance-display"><?= bn($user['balance'], 2) ?></span> SHIB</p> </div>
        </header>

        <main>
            <!-- Home View -->
            <div id="home-view" class="view active">
                 <div class="home-card">
                    <h2>Daily Check-in Bonus</h2><p>Claim your daily reward and keep your streak!</p>
                    <div class="streak-days">
                        <div class="day-box active"><div class="icon"><i class="fa-solid fa-star" style="color: #FFD43B;"></i></div><div class="day-label">Day 1</div><div class="day-reward">15</div></div>
                        <div class="day-box"><div class="icon"><i class="fa-solid fa-lock"></i></div><div class="day-label">Day 2</div><div class="day-reward">20</div></div>
                        <div class="day-box"><div class="icon"><i class="fa-solid fa-lock"></i></div><div class="day-label">Day 3</div><div class="day-reward">25</div></div>
                        <div class="day-box"><div class="icon"><i class="fa-solid fa-lock"></i></div><div class="day-label">Day 4</div><div class="day-reward">100</div></div>
                    </div>
                    <div class="progress-bar-container"><div class="progress-bar"></div></div>
                    <button id="claim-button" class="claim-btn" <?= $is_claimed_today ? 'disabled' : '' ?>><i class="fa-solid fa-gift"></i><span><?= $is_claimed_today ? 'Claimed Today' : 'Claim 15 SHIB' ?></span></button>
                </div>
                <div class="home-card">
                    <h2>Invite Friends & Earn</h2><p>Get rewards when a friend joins!</p>
                    <button class="start-referring-btn" onclick="document.querySelector('[data-view=refer]').click()">Start Referring</button>
                </div>
            </div>
            <!-- Other Views (Ads, TG, Refer, etc.) -->
            <div id="ads-task-view" class="view"> <!-- Ads Task Content will be injected by JS --> </div>
            <div id="tg-tasks-view" class="view"> <!-- TG Tasks Content will be injected by JS --> </div>
            <div id="refer-view" class="view"> <!-- Refer Content will be injected by JS --> </div>
            <div id="withdraw-view" class="view"> <!-- Withdraw Content will be injected by JS --> </div>
            <div id="profile-view" class="view"> <!-- Profile Content will be injected by JS --> </div>
        </main>
    </div>
    
    <nav class="bottom-nav">
        <a class="nav-item active" data-view="home"><div class="icon"><i class="fa-solid fa-house"></i></div><span>Home</span></a>
        <a class="nav-item" data-view="ads-task"><div class="icon"><span class="ad-icon">Ad</span></div><span>Ads Task</span></a>
        <a class="nav-item" data-view="tg-tasks"><div class="icon"><i class="fa-solid fa-list-check"></i></div><span>TG Tasks</span></a>
        <a class="nav-item" data-view="refer"><div class="icon"><i class="fa-solid fa-user-group"></i></div><span>Refer</span></a>
        <a class="nav-item" data-view="withdraw"><div class="icon"><i class="fa-solid fa-wallet"></i></div><span>Withdraw</span></a>
        <a class="nav-item" data-view="profile"><div class="icon"><i class="fa-solid fa-user"></i></div><span>Profile</span></a>
    </nav>
    
    <div id="toast"></div>

    <script>
        const telegramId = '<?= $telegram_id ?>';
        
        function showToast(message) { const toast = document.getElementById("toast"); toast.textContent = message; toast.className = "show"; setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000); }
        function toBengali(number, decimals = 0) { const num = parseFloat(number).toFixed(decimals); return String(num).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]); }
        
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            const views = document.querySelectorAll('.view');
            
            // Inject content into views using JavaScript to keep the HTML clean
            injectViewContent();
            
            navItems.forEach(item => { 
                item.addEventListener('click', function(e) { 
                    e.preventDefault(); 
                    const viewId = this.dataset.view + '-view'; 
                    navItems.forEach(nav => nav.classList.remove('active')); 
                    this.classList.add('active'); 
                    views.forEach(view => view.classList.remove('active')); 
                    document.getElementById(viewId)?.classList.add('active'); 
                }); 
            });
        
            // Bind events to dynamically loaded content
            bindDynamicEvents();
        });

        function injectViewContent() {
            const adsViewContent = ` <h2 class="view-title">Ads Task Overview</h2> <div class="stats-grid"> <div class="stat-box"> <p class="value"><?= bn($user['daily_tasks_completed']) ?></p> <p class="label">Daily Tasks Completed</p> <p class="limit">Limit: 40 tasks/day</p> </div> <div class="stat-box"> <p class="value"><?= bn($user['hourly_tasks_completed']) ?></p> <p class="label">Hourly Tasks Completed</p> <p class="limit">Limit: 10 tasks/hour</p> </div> <div class="stat-box"> <p class="value"><?= bn($user['tasks_done']) ?></p> <p class="label">Lifetime Completed Tasks</p> </div> <div class="stat-box"> <p class="value"><?= bn($user['total_earned'], 2) ?></p> <p class="label">Total Earnings</p> </div> </div> <button class="btn-primary">Start Task (Coming Soon)</button> `;
            document.getElementById('ads-task-view').innerHTML = adsViewContent;

            const tgTaskViewContent = ` <h2 class="view-title">Telegram Tasks</h2> <div id="tg-task-list"> <?php $completed_tasks_res = $conn->query("SELECT task_id FROM user_completed_tg_tasks WHERE user_id = {$user['id']}"); $completed_task_ids = []; while($row = $completed_tasks_res->fetch_assoc()) { $completed_task_ids[] = $row['task_id']; } $tasks_res = $conn->query("SELECT * FROM tg_tasks WHERE is_active = 1"); if($tasks_res->num_rows > 0): while($task = $tasks_res->fetch_assoc()): $is_completed = in_array($task['id'], $completed_task_ids); ?> <div class="task-item"> <div class="left-content"> <div class="info"> <p class="username"><?= htmlspecialchars($task['channel_username']) ?></p> <p class="desc">Join channel to earn reward</p> </div> <div class="actions"> <a href="<?= htmlspecialchars($task['join_link']) ?>" target="_blank" class="btn-join">Join</a> <button class="btn-verify" data-task-id="<?= $task['id'] ?>" <?= $is_completed ? 'disabled' : '' ?>><?= $is_completed ? 'Verified' : 'Verify' ?></button> </div> </div> <div class="reward"><i class="fa-solid fa-coins"></i> <?= bn($task['reward_amount'], 2) ?> SHIB</div> </div> <?php endwhile; else: echo '<p style="text-align:center; color: var(--text-light);">No tasks available right now. Please check back later.</p>'; endif; ?> </div> `;
            document.getElementById('tg-tasks-view').innerHTML = tgTaskViewContent;

            const referViewContent = `<h2 class="view-title">Refer & Earn <span style="background:#e0e7ff; color:#4338ca; font-size:12px; padding:4px 8px; border-radius:6px; vertical-align:middle;">Bonus</span></h2><p style="color:var(--text-light); margin-top:-15px; margin-bottom:20px;">Get a bonus when your friends join!</p><ul class="refer-steps"><li><div class="icon-bg"><i class="fa-solid fa-link"></i></div><div class="text"><h3>1. Copy Your Link</h3><p>Grab your unique referral link below.</p></div></li><li><div class="icon-bg"><i class="fa-solid fa-share-nodes"></i></div><div class="text"><h3>2. Share with Friends</h3><p>Use Telegram, WhatsApp, or other platforms.</p></div></li><li><div class="icon-bg"><i class="fa-solid fa-coins"></i></div><div class="text"><h3>3. Get Notified & Earn</h3><p>You'll get a notification for each successful referral.</p></div></li></ul><label style="font-weight:600; margin-bottom:8px; display:block;">Your Referral Link</label><div class="referral-link-box"><input type="text" id="referral-link-input" value="<?= htmlspecialchars($referral_link) ?>" readonly><button onclick="copyReferralLink()"><i class="fa-regular fa-copy"></i></button></div><div class="share-buttons"><a href="https://t.me/share/url?url=<?= urlencode($referral_link) ?>" target="_blank" class="share-btn btn-telegram"><i class="fab fa-telegram"></i> Telegram</a><a href="https://api.whatsapp.com/send?text=<?= urlencode('Check out this app! ' . $referral_link) ?>" target="_blank" class="share-btn btn-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a><a href="https://twitter.com/intent/tweet?text=Check+out+this+app&url=<?= urlencode($referral_link) ?>" target="_blank" class="share-btn btn-twitter"><i class="fab fa-twitter"></i> Twitter/X</a></div>`;
            document.getElementById('refer-view').innerHTML = referViewContent;

            const withdrawViewContent = `<form id="withdraw-form"><div class="form-group"><label for="withdraw-amount">Enter amount</label><div class="input-wrapper"><input type="number" id="withdraw-amount" name="amount" placeholder="Minimum: 100 SHIB" required><span class="unit">SHIB</span></div></div><div class="form-group"><label for="withdraw-address">Withdrawal Address</label><div class="input-wrapper"><input type="text" id="withdraw-address" name="address" placeholder="Enter your wallet address" required></div><p class="note">e.g., your Binance wallet address</p></div><div class="form-group"><label>Payment Method</label><div class="payment-methods"><div class="payment-method active" data-method="Binance"><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDBMMCAxbDEyIDEyIDEyLTEyTDEyIDB6bTAgMjRsMTItMTEtMy4yNzMgMy4xNDFMMTIgMjAuMzE0bDYuNzI3LTYuMTczTDYgMS42ODYgMS4yNzMgNiwxMiAxNS44NTlsMTAuNzI3LTkuODU5TDE4IDFsLTUtNC42ODZMNyAxbDEwLjczMiA5Ljg1ड्याEuNTQxLTEuNDg2TDEyIDYuMTQxIDQuNzI3IDEyLjU2NWwtMzItMS44NTlMOCAyMy42NTkgMTMuMjczIDE5IDEyIDE3Ljg1OWwtNy4yNzMgNi42ODZMMTIgMjR6IiBmaWxsPSIjRjBCOTAzIi8+PC9zdmc+" alt="Binance"><div class="name">Binance</div></div><div class="payment-method" data-method="Address"><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBkPSJNMjU2IDU2QzExOSA1NiA4IDE2MyA4IDI5N2M3IDEyMSA4MiAxOTMgMTgyIDIwN2wxNiA1YzAgMC00IDItMTEgMi0yNCAwLTQxLTEzLTQxLTQ0IDAtMzIgMTYtNTQgMzktNTUgMjEtMSAyNyAyIDI3IDQxczAtNTggMC03MGMwLTE4LTExLTQzLTMzLTY0LTIyLTIxLTI4LTE5LTU5LTE5LTMxIDAtNjMgNS02MyA0NCAwIDM3IDQzIDQxIDQzIDQxcy01MiAzNy01MiA4M2MwIDQzIDQyIDY2IDkwIDY2IDQxIDAgODMtMTEgMTE0LTI5IDEzLTcgMzYtMjkgNTEtNjEgMTQtMzIgMjAtODAgNS0xMjctMTUtNDctNDQtMTAxLTExNy0xMDNDMTY5IDE0MyAxMjYgMTY1IDk5IDIwOGMtMjYgNDMtMzUgOTQtMTQgMTM1IDE5IDM5IDczIDU3IDExOCA0MyAxMy00IDEzIDEzIDEgMTcgLTEzIDUtNzMgMTgtOTItNDYtMTgtNjYtOS0xMjYgNDAtMTcwIDQ5LTQ0IDEzNy00NCAxMzctNDRoMmM3MyAwIDEyMyA0MCAxMjMgMTExIDAgNDgtMjkgMTA2LTg5IDEwNi0zMSAwLTYxLTEyLTczLTI5LTEzLTE4LTE5LTQwLTEzLTYxIDctMjMgMzktMjcgNDYtMjcgNyAwIDcgNSA4IDI0IDEgMjIgMjAgMjIgMjIgMjJzMzktNDEgMzktNzljMC0zMi0yMS02OS02My04Mi00My0xMy0xMTYgMy0xMTYgNzcgMCA0MyAyMiA3OCA3OCA4NCA1yA2IDc3LTI4IDgxLTQzIDMtMTItNC0yOC0xOS0yOC0xNSAwLTIzIDE2LTIzIDMxIDAgMTUgMTEgMjcgMjkgMjcgMTggMCAzMy0xMSAzMy00MXoiIGZpbGw9IiM1ZDVmZWYiLz48L3N2Zz4=" alt="Address"><div class="name">Other</div></div></div><input type="hidden" id="payment-method-input" name="method" value="Binance"></div><button type="submit" class="btn-primary">Request Withdrawal</button></form>`;
            document.getElementById('withdraw-view').innerHTML = withdrawViewContent;

            const profileViewContent = `<div class="profile-header"><img src="<?= htmlspecialchars($photo) ?>" alt="Profile"><h2 class="name"><?= htmlspecialchars($user['first_name']) ?> ভাই</h2><p class="joined">Joined <?= date("F j, Y", strtotime($user['joined_at'])) ?></p></div><h3 style="font-size:18px; margin-bottom:15px;">Your Statistics</h3><div class="stats-grid"><div class="stat-box"><p class="value"><?= bn($user['total_earned'], 2) ?></p><p class="label">Total Earned (SHIB)</p></div><div class="stat-box"><p class="value"><?= bn($user['tasks_done']) ?></p><p class="label">Tasks Done</p></div><div class="stat-box"><p class="value"><?= bn($user['referrals_count']) ?></p><p class="label">Referrals</p></div><div class="stat-box"><p class="value"><?= bn($user['balance'], 2) ?></p><p class="label">Current Balance</p></div></div><div class="profile-links"><a href="#" class="link-item"><i class="fa-solid fa-circle-question"></i> Help Center</a><a href="https://t.me/your_support_contact" target="_blank" class="link-item"><i class="fa-solid fa-paper-plane"></i> Developer Contact</a></div>`;
            document.getElementById('profile-view').innerHTML = profileViewContent;
        }

        function bindDynamicEvents() {
            // Claim Button
            document.getElementById('claim-button')?.addEventListener('click', function() {
                this.disabled = true; this.querySelector('span').textContent = 'Claiming...';
                const formData = new FormData();
                formData.append('action', 'claim_bonus'); formData.append('telegram_id', telegramId);
                fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    showToast(data.message);
                    if (data.success) { this.querySelector('span').textContent = 'Claimed Today'; document.getElementById('balance-display').textContent = toBengali(data.new_balance, 2); } 
                    else { this.disabled = false; this.querySelector('span').textContent = 'Claim 15 SHIB';}
                }).catch(() => { this.disabled = false; this.querySelector('span').textContent = 'Claim 15 SHIB'; showToast('An error occurred.'); });
            });

            // TG Tasks Verify
            document.getElementById('tg-task-list')?.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('btn-verify')) {
                    const button = e.target; button.disabled = true; button.textContent = 'Verifying...';
                    const formData = new FormData(); formData.append('action', 'verify_tg_task'); formData.append('telegram_id', telegramId); formData.append('task_id', button.dataset.taskId);
                    fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(data => { 
                        showToast(data.message); 
                        if (data.success) { button.textContent = 'Verified'; document.getElementById('balance-display').textContent = toBengali(data.new_balance, 2); } 
                        else { button.disabled = false; button.textContent = 'Verify'; } 
                    }).catch(() => { button.disabled = false; button.textContent = 'Verify'; showToast('An error occurred.'); });
                }
            });
            
            // Withdraw Form
            document.querySelectorAll('.payment-method').forEach(item => { item.addEventListener('click', function() { document.querySelectorAll('.payment-method').forEach(i => i.classList.remove('active')); this.classList.add('active'); document.getElementById('payment-method-input').value = this.dataset.method; }); });
            document.getElementById('withdraw-form')?.addEventListener('submit', function(event) {
                event.preventDefault(); const form = event.target; const submitBtn = form.querySelector('button[type="submit"]'); submitBtn.disabled = true; submitBtn.textContent = 'Requesting...';
                const formData = new FormData(form); formData.append('action', 'request_withdrawal'); formData.append('telegram_id', telegramId);
                fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(data => { 
                    showToast(data.message); 
                    if (data.success) { form.reset(); document.querySelector('.payment-method[data-method="Other"]')?.classList.remove('active'); document.querySelector('.payment-method[data-method="Binance"]')?.classList.add('active'); document.getElementById('balance-display').textContent = toBengali(data.new_balance, 2); } 
                    submitBtn.disabled = false; submitBtn.textContent = 'Request Withdrawal'; 
                }).catch(() => { submitBtn.disabled = false; submitBtn.textContent = 'Request Withdrawal'; showToast('An error occurred.'); });
            });
        }
        function copyReferralLink() { const input = document.getElementById('referral-link-input'); input.select(); input.setSelectionRange(0, 99999); try { document.execCommand('copy'); showToast('Referral link copied!'); } catch (err) { showToast('Could not copy link.'); } }
    </script>
</body>
</html>