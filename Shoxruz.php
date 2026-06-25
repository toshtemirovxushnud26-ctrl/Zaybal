<?php
/**
 * Telegram bot — Stars, Premium, Gift va FixSIM nomer xizmatlari
 * To'lov: HUMO karta (avtomatik)
 * Kanal xabarnomasi: har bir muvaffaqiyatli buyurtma kanalga yoziladi
 */

// ─── .env yuklash ───────────────────────────────────────────────
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

// ─── Sozlamalar ─────────────────────────────────────────────────
define('BOT_TOKEN',        $_ENV['BOT_TOKEN']        ?? '8893001472:AAHhcXOXvciHHFJJ9OXxOnVTRZm0XVqFfhQ');
define('ADMIN_ID',   (int)($_ENV['ADMIN_ID']         ?? '8541213007'));
define('HYPERPIN_API_URL', $_ENV['HYPERPIN_API_URL'] ?? 'https://hyperpin.top/api/v1');
define('HYPERPIN_API_KEY', $_ENV['HYPERPIN_API_KEY'] ?? 'hp_859b4f4805a721c8a60e57ed81a2d975');
define('SHOP_ID',    (int)($_ENV['SHOP_ID']          ?? '24'));
define('SHOP_KEY',         $_ENV['SHOP_KEY']         ?? 'sk_f37c8fa4298ab6ec43e91c20d2400a80');
define('SHOP_API',         $_ENV['SHOP_API']         ?? 'https://694bccc3c315b.myxvest1.ru/super/api.php');
define('FIXSIM_API_KEY',   $_ENV['FIXSIM_API_KEY']   ?? 'a94390e6d0269a5');
define('FIXSIM_API_URL',   $_ENV['FIXSIM_API_URL']   ?? 'https://69eda83c94f75.myxvest2.ru/api.php');
define('DB_PATH',          $_ENV['DB_PATH']          ?? __DIR__ . '/bot_data.db');

// 📢 KANAL ID — buyurtma xabarnomasi yuboriladigan kanal
// Masalan: '@mening_kanalim' yoki '-1001234567890'
define('LOG_CHANNEL_ID',   $_ENV['LOG_CHANNEL_ID']   ?? '@spelly_orders');

// ─── Default paketlar ───────────────────────────────────────────
define('DEFAULT_STARS_PACKAGES', json_encode([
    ['stars' => 50,   'price' => 15000],
    ['stars' => 100,  'price' => 28000],
    ['stars' => 250,  'price' => 65000],
    ['stars' => 500,  'price' => 125000],
    ['stars' => 1000, 'price' => 240000],
    ['stars' => 2500, 'price' => 580000],
]));

define('DEFAULT_PREMIUM_PACKAGES', json_encode([
    ['months' => 1,  'label' => '1 oy',  'price' => 85000],
    ['months' => 3,  'label' => '3 oy',  'price' => 240000],
    ['months' => 6,  'label' => '6 oy',  'price' => 450000],
    ['months' => 12, 'label' => '12 oy', 'price' => 850000],
]));

define('DEFAULT_GIFT_PACKAGES', json_encode([
    ['gift_id' => 'gift_select:1:15',  'name' => '🧸 Teddy Bear', 'stars_cost' => 15,  'price' => 3000],
    ['gift_id' => 'gift_select:0:15',  'name' => '💖 Heart',      'stars_cost' => 15,  'price' => 3000],
    ['gift_id' => 'gift_select:0:25',  'name' => '🎁 Gift Box',   'stars_cost' => 25,  'price' => 5000],
    ['gift_id' => 'gift_select:1:25',  'name' => '🌹 Atirgul',    'stars_cost' => 25,  'price' => 5000],
    ['gift_id' => 'gift_select:0:50',  'name' => '🎂 Tort',       'stars_cost' => 50,  'price' => 10000],
    ['gift_id' => 'gift_select:1:50',  'name' => '💐 Guldasta',   'stars_cost' => 50,  'price' => 10000],
    ['gift_id' => 'gift_select:2:50',  'name' => '🚀 Raketa',     'stars_cost' => 50,  'price' => 10000],
    ['gift_id' => 'gift_select:3:50',  'name' => '🍾 Champagne',  'stars_cost' => 50,  'price' => 10000],
    ['gift_id' => 'gift_select:0:100', 'name' => '🏆 Kubok',      'stars_cost' => 100, 'price' => 20000],
    ['gift_id' => 'gift_select:1:100', 'name' => '💍 Uzuk',       'stars_cost' => 100, 'price' => 20000],
    ['gift_id' => 'gift_select:2:100', 'name' => '💎 Olmos',      'stars_cost' => 100, 'price' => 20000],
]));

// ─── SQLite ──────────────────────────────────────────────────────
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
    }
    return $pdo;
}

function init_db(): void {
    $db = get_db();
    $db->exec("CREATE TABLE IF NOT EXISTS settings
        (key TEXT PRIMARY KEY, value TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS orders
        (id INTEGER PRIMARY KEY AUTOINCREMENT,
         user_id INTEGER, order_id INTEGER, type TEXT,
         amount INTEGER, price REAL,
         recipient TEXT DEFAULT '',
         extra_data TEXT DEFAULT '',
         status TEXT DEFAULT 'pending',
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS forced_channels
        (id INTEGER PRIMARY KEY AUTOINCREMENT,
         channel_id TEXT, channel_title TEXT, channel_link TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS users
        (user_id INTEGER PRIMARY KEY, username TEXT, full_name TEXT,
         balance INTEGER DEFAULT 0,
         joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS sms_flood
        (user_id INTEGER, hash_key TEXT, last_checked INTEGER,
         PRIMARY KEY (user_id, hash_key))");

    // ALTER TABLE (agar ustun yo'q bo'lsa)
    foreach (['recipient', 'extra_data'] as $col) {
        try { $db->exec("ALTER TABLE orders ADD COLUMN $col TEXT DEFAULT ''"); } catch (Exception $e) {}
    }

    // Default sozlamalar
    $defaults = [
        'stars_packages'        => DEFAULT_STARS_PACKAGES,
        'premium_packages'      => DEFAULT_PREMIUM_PACKAGES,
        'gift_packages'         => DEFAULT_GIFT_PACKAGES,
        'stars_enabled'         => '1',
        'premium_enabled'       => '1',
        'gift_enabled'          => '1',
        'nomer_enabled'         => '1',
        'nomer_price_overrides' => '{}',
        'welcome_text'          => '🌟 Xush kelibsiz!\n\n💫 Telegram xizmatlarini tez va qulay sotib oling:\n\n⭐ Stars · 💎 Premium · 🎁 Gift · 📞 Nomer\n\n👇 Quyidagi bo\'limni tanlang:',
    ];
    $st = $db->prepare("INSERT OR IGNORE INTO settings (key,value) VALUES (?,?)");
    foreach ($defaults as $k => $v) $st->execute([$k, $v]);
}

// ─── Settings ────────────────────────────────────────────────────
function get_setting(string $key, ?string $default = null): ?string {
    $st = get_db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $row = $st->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : $default;
}

function set_setting(string $key, string $value): void {
    $st = get_db()->prepare("INSERT OR REPLACE INTO settings (key,value) VALUES (?,?)");
    $st->execute([$key, $value]);
}

// ─── Paketlar ────────────────────────────────────────────────────
function get_packages(string $type): array {
    $defaults = [
        'stars'   => DEFAULT_STARS_PACKAGES,
        'premium' => DEFAULT_PREMIUM_PACKAGES,
        'gift'    => DEFAULT_GIFT_PACKAGES,
    ];
    $raw = get_setting($type . '_packages');
    return json_decode($raw ?? $defaults[$type], true) ?? [];
}

function set_packages(string $type, array $pkgs): void {
    set_setting($type . '_packages', json_encode($pkgs, JSON_UNESCAPED_UNICODE));
}

// ─── Nomer override ──────────────────────────────────────────────
function get_nomer_overrides(): array {
    $raw = get_setting('nomer_price_overrides', '{}');
    return json_decode($raw, true) ?? [];
}

function set_nomer_override(string $code, int $price): void {
    $ov = get_nomer_overrides();
    $ov[$code] = $price;
    set_setting('nomer_price_overrides', json_encode($ov));
}

function remove_nomer_override(string $code): void {
    $ov = get_nomer_overrides();
    unset($ov[$code]);
    set_setting('nomer_price_overrides', json_encode($ov));
}

// ─── Majburiy obunalar ───────────────────────────────────────────
function get_forced_channels(): array {
    $st = get_db()->query("SELECT id,channel_id,channel_title,channel_link FROM forced_channels");
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function add_forced_channel(string $cid, string $title, string $link): void {
    $st = get_db()->prepare("INSERT INTO forced_channels (channel_id,channel_title,channel_link) VALUES (?,?,?)");
    $st->execute([$cid, $title, $link]);
}

function remove_forced_channel(int $id): void {
    get_db()->prepare("DELETE FROM forced_channels WHERE id=?")->execute([$id]);
}

// ─── Buyurtmalar ────────────────────────────────────────────────
function create_order(int $uid, int $order_id, string $type, int $amount, float $price, string $recipient = '', string $extra_data = ''): void {
    $st = get_db()->prepare("INSERT INTO orders (user_id,order_id,type,amount,price,recipient,extra_data) VALUES (?,?,?,?,?,?,?)");
    $st->execute([$uid, $order_id, $type, $amount, $price, $recipient, $extra_data]);
}

function get_order(int $order_id): ?array {
    $st = get_db()->prepare("SELECT id,user_id,order_id,type,amount,price,status,recipient,extra_data FROM orders WHERE order_id=?");
    $st->execute([$order_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function update_order_status(int $order_id, string $status): void {
    get_db()->prepare("UPDATE orders SET status=? WHERE order_id=?")->execute([$status, $order_id]);
}

function try_claim_order(int $order_id): bool {
    $st = get_db()->prepare("UPDATE orders SET status='processing' WHERE order_id=? AND status IN ('pending','paid')");
    $st->execute([$order_id]);
    return $st->rowCount() > 0;
}

function get_user_orders(int $uid, int $limit = 10): array {
    $st = get_db()->prepare("SELECT order_id,type,amount,price,status,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
    $st->execute([$uid, $limit]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function get_stats(): array {
    $db = get_db();
    $paid    = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','completed')")->fetchColumn();
    $pending = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $revenue = $db->query("SELECT SUM(price) FROM orders WHERE status IN ('paid','completed')")->fetchColumn() ?: 0;
    $users   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $today   = $db->query("SELECT COUNT(*) FROM users WHERE joined_at >= date('now','-1 day')")->fetchColumn();
    return compact('paid', 'pending', 'revenue', 'users') + ['new_today' => $today];
}

// ─── Foydalanuvchilar ────────────────────────────────────────────
function register_user(int $uid, string $username, string $full_name): bool {
    $db = get_db();
    $st = $db->prepare("SELECT user_id FROM users WHERE user_id=?");
    $st->execute([$uid]);
    $exists = $st->fetch();
    if ($exists) {
        $db->prepare("UPDATE users SET username=?,full_name=? WHERE user_id=?")->execute([$username, $full_name, $uid]);
        return false;
    }
    $db->prepare("INSERT INTO users (user_id,username,full_name) VALUES (?,?,?)")->execute([$uid, $username, $full_name]);
    return true;
}

function get_user(int $uid): ?array {
    $st = get_db()->prepare("SELECT user_id,username,full_name,balance,joined_at FROM users WHERE user_id=?");
    $st->execute([$uid]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_all_user_ids(): array {
    return get_db()->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
}

// ─── SMS flood tekshiruv ─────────────────────────────────────────
function fixsim_flood_check(int $uid, string $hash, int $cooldown = 30): bool {
    $now = time();
    $db = get_db();
    $st = $db->prepare("SELECT last_checked FROM sms_flood WHERE user_id=? AND hash_key=?");
    $st->execute([$uid, $hash]);
    $row = $st->fetch(PDO::FETCH_NUM);
    if ($row && ($now - $row[0]) < $cooldown) return false;
    $db->prepare("INSERT OR REPLACE INTO sms_flood (user_id,hash_key,last_checked) VALUES (?,?,?)")->execute([$uid, $hash, $now]);
    return true;
}

// ─── Yordamchilar ────────────────────────────────────────────────
function fmt($n): string {
    return number_format((int)$n, 0, '.', ' ');
}

function order_status_emoji(string $status): string {
    return ['completed' => '✅', 'paid' => '✅', 'pending' => '⏳', 'expired' => '⏰', 'cancelled' => '❌'][$status] ?? '❓';
}

// ─── Telegram API ────────────────────────────────────────────────
function tg(string $method, array $params = []): ?array {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function send_message(int|string $chat_id, string $text, array $extra = []): ?array {
    return tg('sendMessage', array_merge(['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'], $extra));
}

function edit_message_text(int|string $chat_id, int $message_id, string $text, array $extra = []): ?array {
    return tg('editMessageText', array_merge(['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML'], $extra));
}

function answer_callback(string $callback_id, string $text = '', bool $alert = false): void {
    tg('answerCallbackQuery', ['callback_query_id' => $callback_id, 'text' => $text, 'show_alert' => $alert]);
}

function delete_message(int|string $chat_id, int $message_id): void {
    tg('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function get_chat_member(string $channel_id, int $user_id): ?array {
    $res = tg('getChatMember', ['chat_id' => $channel_id, 'user_id' => $user_id]);
    return $res['ok'] ? $res['result'] : null;
}

// ─── 📢 KANAL XABARNOMASI ────────────────────────────────────────
/**
 * Muvaffaqiyatli buyurtma haqida kanalga xabar yuboradi.
 * Kanal IDsini .env da LOG_CHANNEL_ID sifatida belgilang.
 * Botni kanalga admin qilib qo'shishni unutmang!
 *
 * Misol: @xushnudbek 50 stars sotib oldi ✅ Muvaffaqiyatli yetkazib berildi.
 */
function notify_channel(string $text): void {
    $channel = LOG_CHANNEL_ID;
    if (empty($channel)) return; // Kanal belgilanmagan bo'lsa chiqib ketadi
    tg('sendMessage', [
        'chat_id'    => $channel,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]);
}

function notify_channel_order(array $order, array $from_user): void {
    $channel = LOG_CHANNEL_ID;
    if (empty($channel)) return;

    $username  = $from_user['username'] ? '@' . $from_user['username'] : $from_user['full_name'];
    $recipient = $order['recipient'] ?? '';
    $price     = fmt($order['price']);
    $oid       = $order['order_id'];

    switch ($order['type']) {
        case 'stars':
            $amount = $order['amount'] . ' Stars';
            $icon   = '⭐';
            $label  = 'Stars';
            break;
        case 'premium':
            $amount = $order['amount'] . ' oy Premium';
            $icon   = '💎';
            $label  = 'Premium';
            break;
        case 'gift':
            $extra = json_decode($order['extra_data'] ?? '{}', true);
            $amount = ($extra['gift_name'] ?? $order['amount'] . ' stars') . ' Gift';
            $icon   = '🎁';
            $label  = 'Gift';
            break;
        case 'nomer':
            $extra  = json_decode($order['extra_data'] ?? '{}', true);
            $amount = ($extra['country_name'] ?? '') . ' raqam';
            $icon   = '📞';
            $label  = 'Nomer';
            break;
        default:
            $amount = (string)$order['amount'];
            $icon   = '✅';
            $label  = $order['type'];
    }

    $to = $recipient ? " ➡️ <b>{$recipient}</b>" : '';

    $text = "{$icon} <b>{$username}</b>{$to}\n"
          . "<b>{$amount}</b> sotib oldi\n"
          . "💰 <b>{$price} so'm</b> | 🧾 #{$oid}\n"
          . "✅ <b>Muvaffaqiyatli yetkazib berildi!</b>";

    notify_channel($text);
}

// Admin xabarnomasi
function notify_admin(string $title, string $details): void {
    send_message(ADMIN_ID, "🚨 <b>{$title}</b>\n\n{$details}");
}

// ─── Hyperpin API ────────────────────────────────────────────────
function hyperpin_call(string $method, string $path, array $data = [], array $params = []): array {
    $url = HYPERPIN_API_URL . $path;
    if ($params) $url .= '?' . http_build_query($params);
    $headers = ['X-API-Key: ' . HYPERPIN_API_KEY];
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return ['success' => false, 'error' => 'Javob yo\'q'];
    $d = json_decode($res, true);
    if (!is_array($d)) return ['success' => false, 'error' => 'Noto\'g\'ri javob'];
    if (isset($d['success']) && $d['success']) return array_merge(['success' => true], $d);
    if (isset($d['id']) || isset($d['balance'])) return array_merge(['success' => true], $d);
    return array_merge(['success' => false, 'error' => $d['error'] ?? 'Noma\'lum xato'], $d);
}

function hyperpin_buy_stars(string $username, int $qty): array {
    return hyperpin_call('POST', '/stars/buy', ['username' => ltrim($username, '@'), 'quantity' => $qty]);
}

function hyperpin_buy_premium(string $username, int $months): array {
    return hyperpin_call('POST', '/premium/buy', ['username' => ltrim($username, '@'), 'months' => $months]);
}

function hyperpin_buy_gift(string $username, string $gift_id, int $stars_cost): array {
    return hyperpin_call('POST', '/gift/buy', ['username' => ltrim($username, '@'), 'gift_id' => $gift_id, 'stars_cost' => $stars_cost]);
}

function hyperpin_balance(): array    { return hyperpin_call('GET', '/balance'); }
function hyperpin_deposit(): array    { return hyperpin_call('GET', '/account/deposit'); }
function hyperpin_stats(): array      { return hyperpin_call('GET', '/account/stats'); }

// ─── FixSIM API ──────────────────────────────────────────────────
function fixsim_request(array $params): array {
    $url = FIXSIM_API_URL . '?' . http_build_query(array_merge(['key' => FIXSIM_API_KEY], $params));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error) return [];
    $data = json_decode($response, true);
    if (!is_array($data)) return [];
    return $data;
}

function fixsim_get_countries(): array {
    $data = fixsim_request(['action' => 'countries']);
    // Turli API javob strukturalarini qo'llab-quvvatlash
    if (isset($data['countries']) && is_array($data['countries'])) {
        return $data['countries'];
    }
    // Ba'zi API'lar to'g'ridan-to'g'ri massiv qaytaradi
    if (isset($data[0]) && is_array($data[0])) {
        return $data;
    }
    // data ichida bo'lishi mumkin
    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }
    return [];
}

function get_countries_with_overrides(): array {
    $countries = fixsim_get_countries();
    $overrides = get_nomer_overrides();
    foreach ($countries as &$c) {
        $code = (string)($c['country_code'] ?? '');
        if (isset($overrides[$code])) {
            $c['price_uzs']       = $overrides[$code];
            $c['price_overridden'] = true;
        } else {
            $c['price_overridden'] = false;
        }
    }
    return $countries;
}

function fixsim_buy_number(string $code): array {
    $res = fixsim_request(['action' => 'getnum', 'code' => $code]);
    if (isset($res['error'])) return ['phone' => null, 'hash' => null, 'error' => $res['error']];
    $phone = $res['phone'] ?? null;
    $hash  = $res['hash']  ?? null;
    if (!$phone || !$hash) return ['phone' => null, 'hash' => null, 'error' => 'no_number'];
    return ['phone' => $phone, 'hash' => $hash, 'error' => null];
}

function fixsim_get_sms(string $hash): array {
    $res = fixsim_request(['action' => 'getsms', 'hash' => $hash]);
    if (isset($res['error'])) return ['sms' => null, 'password' => null, 'error' => $res['error']];
    $sms = $res['sms'] ?? null;
    if (!$sms) return ['sms' => null, 'password' => null, 'error' => 'no_sms'];
    return ['sms' => $sms, 'password' => $res['password'] ?? '', 'error' => null];
}

// ─── HUMO to'lov ─────────────────────────────────────────────────
function humo_create(int $uid, float $amount): array {
    $ch = curl_init(SHOP_API . '?action=create_order');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['shop_id' => SHOP_ID, 'shop_key' => SHOP_KEY, 'amount' => $amount, 'user_id' => (string)$uid]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return ($res ? json_decode($res, true) : null) ?? ['ok' => false, 'error' => 'Javob yo\'q'];
}

function humo_check(int $order_id): ?array {
    $url = SHOP_API . '?' . http_build_query(['action' => 'check', 'order_id' => $order_id, 'shop_id' => SHOP_ID, 'shop_key' => SHOP_KEY]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $res = curl_exec($ch);
    curl_close($ch);
    $d = $res ? json_decode($res, true) : null;
    return $d['data'] ?? null;
}

// ─── Session (FSM o'rniga fayl asosida) ─────────────────────────
function session_file(int $uid): string {
    return sys_get_temp_dir() . "/tgbot_session_{$uid}.json";
}

function get_session(int $uid): array {
    $f = session_file($uid);
    if (!file_exists($f)) return [];
    return json_decode(file_get_contents($f), true) ?? [];
}

function set_session(int $uid, array $data): void {
    file_put_contents(session_file($uid), json_encode($data));
}

function clear_session(int $uid): void {
    $f = session_file($uid);
    if (file_exists($f)) unlink($f);
}

function update_session(int $uid, array $merge): void {
    $s = get_session($uid);
    set_session($uid, array_merge($s, $merge));
}

// ─── Klaviaturalar ───────────────────────────────────────────────
function kb_main(bool $stars = true, bool $premium = true, bool $gift = true, bool $nomer = true): array {
    $rows = [];
    if ($stars)   $rows[] = [['text' => '⭐ Telegram Stars sotib olish']];
    if ($premium) $rows[] = [['text' => '💎 Telegram Premium sotib olish']];
    if ($gift)    $rows[] = [['text' => '🎁 Telegram Gift yuborish']];
    if ($nomer)   $rows[] = [['text' => '📞 Telegram nomer olish']];
    $rows[] = [['text' => '📦 Buyurtmalarim'], ['text' => '👤 Profilim']];
    $rows[] = [['text' => 'ℹ️ Yordam']];
    return ['keyboard' => $rows, 'resize_keyboard' => true];
}

function kb_admin(): array {
    return ['keyboard' => [
        [['text' => '📊 Statistika']],
        [['text' => '⭐ Stars narxlari'], ['text' => '💎 Premium narxlari']],
        [['text' => '🎁 Gift narxlari'],  ['text' => '📞 Nomer narxlari']],
        [['text' => '📢 Majburiy obuna'], ['text' => '⚙️ Bot sozlamalari']],
        [['text' => '📣 Xabar yuborish'], ['text' => '💰 Hyperpin']],
        [['text' => '🔙 Asosiy menyu']],
    ], 'resize_keyboard' => true];
}

function kb_inline(array $rows): array {
    return ['inline_keyboard' => $rows];
}

function kb_stars(array $pkgs): array {
    $rows = [];
    foreach ($pkgs as $i => $p)
        $rows[] = [['text' => $p['stars'] . ' Stars — ' . fmt($p['price']) . " so'm", 'callback_data' => "stars_buy:{$i}"]];
    $rows[] = [['text' => "✏️ O'zim yozaman", 'callback_data' => 'stars_custom']];
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']];
    return kb_inline($rows);
}

function kb_premium(array $pkgs): array {
    $rows = [];
    foreach ($pkgs as $i => $p)
        $rows[] = [['text' => $p['label'] . ' — ' . fmt($p['price']) . " so'm", 'callback_data' => "premium_buy:{$i}"]];
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']];
    return kb_inline($rows);
}

function kb_gift(array $pkgs): array {
    $rows = [];
    foreach ($pkgs as $i => $p)
        $rows[] = [['text' => $p['name'] . ' — ' . fmt($p['price']) . " so'm", 'callback_data' => "gift_buy:{$i}"]];
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']];
    return kb_inline($rows);
}

function kb_payment(int $oid, string $card, int $amount): array {
    $card_clean = str_replace(' ', '', $card);
    return kb_inline([
        [['text' => "📋 {$card}", 'callback_data' => "copy_card:{$card_clean}"]],
        [['text' => '💰 ' . fmt($amount) . " so'm", 'callback_data' => "copy_sum:{$amount}"]],
        [['text' => "✅ To'lov qildim", 'callback_data' => "check_pay:{$oid}"]],
        [['text' => '❌ Bekor qilish',  'callback_data' => "cancel_pay:{$oid}"]],
    ]);
}

function kb_channels(array $channels): array {
    $rows = array_map(fn($ch) => [['text' => "📢 {$ch['channel_title']}", 'url' => $ch['channel_link']]], $channels);
    $rows[] = [['text' => "✅ Obuna bo'ldim", 'callback_data' => 'check_sub']];
    return kb_inline($rows);
}

function kb_fixsim_countries(array $countries, int $page): array {
    $per = 10; $total = count($countries);
    $pages = max(1, (int)ceil($total / $per));
    $page  = max(0, min($page, $pages - 1));
    $chunk = array_slice($countries, $page * $per, $per);
    $rows  = [];
    foreach ($chunk as $c) {
        $code = $c['country_code'] ?? '';
        $name = $c['country_name'] ?? $code;
        $price = (int)($c['price_uzs'] ?? 0);
        if (!$code || $price <= 0) continue;
        $rows[] = [['text' => "{$name} — " . fmt($price) . " so'm", 'callback_data' => "fs_buy:{$code}:" . substr($name, 0, 30)]];
    }
    $nav = [];
    if ($page > 0) $nav[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'fs_page:' . ($page - 1)];
    $nav[] = ['text' => ($page + 1) . "/{$pages}", 'callback_data' => 'none'];
    if (($page + 1) * $per < $total) $nav[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'fs_page:' . ($page + 1)];
    if ($nav) $rows[] = $nav;
    $rows[] = [['text' => '📊 TOP 10', 'callback_data' => 'fs_top10'], ['text' => '💰 Arzonlar', 'callback_data' => 'fs_cheap']];
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']];
    return kb_inline($rows);
}

function kb_fixsim_list(array $countries, string $back = 'fs_start'): array {
    $rows = [];
    foreach ($countries as $c) {
        $code = $c['country_code'] ?? ''; $name = $c['country_name'] ?? $code; $price = (int)($c['price_uzs'] ?? 0);
        if (!$code || $price <= 0) continue;
        $rows[] = [['text' => "{$name} — " . fmt($price) . " so'm", 'callback_data' => "fs_buy:{$code}:" . substr($name, 0, 30)]];
    }
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => $back]];
    return kb_inline($rows);
}

// ─── Admin klaviaturalar ─────────────────────────────────────────
function kb_admin_pkg(array $pkgs, string $prefix, string $name_key, string $val_key, string $add_cb, string $del_cb): array {
    $rows = [];
    foreach ($pkgs as $i => $p)
        $rows[] = [['text' => $p[$name_key] . ' → ' . fmt($p[$val_key]) . " so'm", 'callback_data' => "edit_{$prefix}:{$i}"]];
    $rows[] = [['text' => '➕ Yangi paket', 'callback_data' => $add_cb], ['text' => "🗑 O'chirish", 'callback_data' => $del_cb]];
    $rows[] = [['text' => '🔙 Admin panel', 'callback_data' => 'admin_back']];
    return kb_inline($rows);
}

function kb_del_pkg(array $pkgs, string $prefix, string $label_key): array {
    $rows = array_map(fn($i, $p) => [['text' => "❌ {$p[$label_key]}", 'callback_data' => "del_{$prefix}:{$i}"]], array_keys($pkgs), $pkgs);
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']];
    return kb_inline($rows);
}

function kb_admin_channels(array $channels): array {
    $rows = array_map(fn($ch) => [['text' => "❌ {$ch['channel_title']}", 'callback_data' => "del_channel:{$ch['id']}"]], $channels);
    $rows[] = [['text' => "➕ Kanal qo'shish", 'callback_data' => 'add_channel']];
    $rows[] = [['text' => '🔙 Admin panel', 'callback_data' => 'admin_back']];
    return kb_inline($rows);
}

function kb_admin_settings(array $s, string $welcome): array {
    $on = fn($v) => $v === '1' ? '✅ Yoqiq' : "❌ O'chiq";
    return kb_inline([
        [['text' => '⭐ Stars: ' . $on($s['stars_enabled']),   'callback_data' => 'toggle_stars']],
        [['text' => '💎 Premium: ' . $on($s['premium_enabled']), 'callback_data' => 'toggle_premium']],
        [['text' => '🎁 Gift: ' . $on($s['gift_enabled']),     'callback_data' => 'toggle_gift']],
        [['text' => '📞 Nomer: ' . $on($s['nomer_enabled']),   'callback_data' => 'toggle_nomer']],
        [['text' => "✏️ Xush kelish xabarini o'zgartirish",   'callback_data' => 'edit_welcome']],
    ]);
}

function kb_nomer_overrides(array $overrides, array $countries): array {
    $name_map = [];
    foreach ($countries as $c) $name_map[(string)($c['country_code'] ?? '')] = $c['country_name'] ?? '';
    $rows = [];
    foreach ($overrides as $code => $price) {
        $name = $name_map[$code] ?? $code;
        $rows[] = [
            ['text' => "✏️ {$name} — " . fmt($price) . " so'm", 'callback_data' => "nomer_edit:{$code}"],
            ['text' => '🗑', 'callback_data' => "nomer_del:{$code}"],
        ];
    }
    $rows[] = [['text' => '➕ Yangi narx belgilash', 'callback_data' => 'nomer_add']];
    $rows[] = [['text' => '🔙 Admin panel', 'callback_data' => 'admin_back']];
    return kb_inline($rows);
}

// ─── Obuna tekshiruv ─────────────────────────────────────────────
function check_subscriptions(int $uid): array {
    $not_sub = [];
    foreach (get_forced_channels() as $ch) {
        $m = get_chat_member($ch['channel_id'], $uid);
        if (!$m || in_array($m['status'], ['left', 'kicked', 'banned'])) $not_sub[] = $ch;
    }
    return $not_sub;
}

// ─── To'lov yaratish ─────────────────────────────────────────────
function create_payment(int $uid, float $price, string $type, int $amount, int $msg_id, int $chat_id, array $extra = []): ?int {
    $wait = send_message($chat_id, '⏳ To\'lov yaratilmoqda...');
    $res = humo_create($uid, $price);
    if (!$res || !($res['ok'] ?? false)) {
        $err = $res['error'] ?? 'Server xatosi';
        edit_message_text($chat_id, $wait['result']['message_id'], "❌ <b>Xato:</b> {$err}\n\nQaytadan /start bosing.");
        clear_session($uid);
        return null;
    }
    $d    = $res['data'];
    $oid  = (int)$d['order_id'];
    $fp   = (int)$d['amount'];
    $card = $d['card_number'];
    $recipient  = $extra['recipient'] ?? '';
    $extra_data = json_encode(array_diff_key($extra, ['recipient' => '']));
    create_order($uid, $oid, $type, $amount, $fp, $recipient, $extra_data);
    update_session($uid, array_merge(['order_id' => $oid, 'recipient' => $recipient], $extra));

    $plus = (($d['extra_sum'] ?? 0) > 0) ? "\n\n⚠️ Farqlash uchun <b>+{$d['extra_sum']} so'm</b> qo'shildi." : '';
    edit_message_text($chat_id, $wait['result']['message_id'],
        "💰 <b>To'lov</b>\n\n"
        . "🏦 Karta: <code>{$card}</code>\n"
        . "💰 Summa: <b>" . fmt($fp) . " so'm</b>\n\n"
        . "📋 1️⃣ Karta raqamini nusxa oling\n"
        . "2️⃣ Aniq summani o'tkazing\n"
        . "3️⃣ «✅ To'lov qildim» tugmasini bosing\n\n"
        . "⏳ Muddat: <b>10 daqiqa</b>{$plus}",
        ['reply_markup' => kb_payment($oid, $card, $fp)]
    );
    return $oid;
}

// ─── Webhook handler ─────────────────────────────────────────────
function handle_update(array $upd): void {
    if (isset($upd['message'])) {
        handle_message($upd['message']);
    } elseif (isset($upd['callback_query'])) {
        handle_callback($upd['callback_query']);
    }
}

function handle_message(array $msg): void {
    $uid      = $msg['from']['id'];
    $chat_id  = $msg['chat']['id'];
    $text     = $msg['text'] ?? '';
    $username = $msg['from']['username'] ?? '';
    $fullname = ($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? '');
    $fullname = trim($fullname);
    $mid      = $msg['message_id'];

    register_user($uid, $username, $fullname);

    $sess  = get_session($uid);
    $state = $sess['state'] ?? '';

    // /cancel
    if ($text === '/cancel' || mb_strtolower($text) === 'bekor') {
        clear_session($uid);
        send_message($chat_id, "❌ Bekor qilindi. /start bosing.");
        return;
    }

    // FSM holatlari
    if ($state) {
        handle_state($uid, $chat_id, $text, $state, $sess, $msg);
        return;
    }

    // /start
    if ($text === '/start') {
        clear_session($uid);
        if ($uid === ADMIN_ID) {
            send_message($chat_id, "👋 Xush kelibsiz, Admin!\n\nAdmin panelga: /admin", ['reply_markup' => kb_main()]);
            return;
        }
        $not_sub = check_subscriptions($uid);
        if ($not_sub) {
            send_message($chat_id, "⚠️ <b>Botdan foydalanish uchun kanallarga obuna bo'ling:</b>", ['reply_markup' => kb_channels($not_sub)]);
            return;
        }
        $welcome = get_setting('welcome_text', 'Xush kelibsiz!');
        $s = get_enabled();
        send_message($chat_id, "👋 {$welcome}", ['reply_markup' => kb_main($s[0], $s[1], $s[2], $s[3])]);
        return;
    }

    // /admin
    if ($text === '/admin' && $uid === ADMIN_ID) {
        send_message($chat_id, '🔐 <b>Admin Panel</b>', ['reply_markup' => kb_admin()]);
        return;
    }

    // Admin tugmalari
    if ($uid === ADMIN_ID) {
        handle_admin_text($uid, $chat_id, $text, $sess);
        return;
    }

    // Foydalanuvchi tugmalari
    handle_user_text($uid, $chat_id, $text, $sess, $fullname, $username);
}

function get_enabled(): array {
    return [
        get_setting('stars_enabled',   '1') === '1',
        get_setting('premium_enabled', '1') === '1',
        get_setting('gift_enabled',    '1') === '1',
        get_setting('nomer_enabled',   '1') === '1',
    ];
}

function handle_user_text(int $uid, int $chat_id, string $text, array $sess, string $fullname, string $username): void {
    $not_sub = check_subscriptions($uid);
    if ($not_sub) {
        send_message($chat_id, "⚠️ Botdan foydalanish uchun kanallarga obuna bo'ling:", ['reply_markup' => kb_channels($not_sub)]);
        return;
    }
    switch ($text) {
        case '⭐ Telegram Stars sotib olish':
            if (get_setting('stars_enabled', '1') !== '1') { send_message($chat_id, '⭐ Stars xizmati vaqtincha to\'xtatilgan.'); return; }
            update_session($uid, ['state' => 'stars_recipient']);
            send_message($chat_id, "⭐ <b>Telegram Stars</b>\n\n👤 Qabul qiluvchining <b>\@username</b> ini kiriting:\n\n<i>Masalan: \@durav</i>\n\nBekor qilish: /cancel");
            break;
        case '💎 Telegram Premium sotib olish':
            if (get_setting('premium_enabled', '1') !== '1') { send_message($chat_id, '💎 Premium xizmati vaqtincha to\'xtatilgan.'); return; }
            update_session($uid, ['state' => 'premium_recipient']);
            send_message($chat_id, "💎 <b>Telegram Premium</b>\n\n👤 Premium kimga faollashtirilsin?\n<b>\@username</b> sini kiriting:\n\nBekor qilish: /cancel");
            break;
        case '🎁 Telegram Gift yuborish':
            if (get_setting('gift_enabled', '1') !== '1') { send_message($chat_id, '🎁 Gift xizmati vaqtincha to\'xtatilgan.'); return; }
            update_session($uid, ['state' => 'gift_recipient']);
            send_message($chat_id, "🎁 <b>Telegram Gift</b>\n\n👤 Qabul qiluvchining <b>\@username</b> sini kiriting:\n\nBekor qilish: /cancel");
            break;
        case '📞 Telegram nomer olish':
            if (get_setting('nomer_enabled', '1') !== '1') { send_message($chat_id, '📞 Nomer xizmati vaqtincha to\'xtatilgan.'); return; }
            send_message($chat_id,
                "<b>📶 Tayyor Telegram akkauntlar</b>\n\n<b>📌 Ishlash tartibi:</b>\n\n<blockquote>"
                . "1️⃣ Bot sizga raqam beradi.\n"
                . "2️⃣ Shu raqam bilan Telegramga kiring — rasmiy ko'k ilovadan foydalanmang!\n"
                . "3️⃣ Telegram kod so'raganda <b>SMS kodni olish</b> tugmasini bosing.\n"
                . "4️⃣ 1–3 daqiqada kirish kodi va 2 bosqichli parol beriladi.\n\n"
                . "⚠️ Raqamni olganingizdan so'ng uni bekor qilish imkonsiz!\n"
                . "🚫 Spam yoki noqonuniy maqsadlarda ishlatmang!"
                . "</blockquote>\n\n✅ O'qib chiqdingizmi? <b>Tushundim</b> tugmasini bosing.",
                ['reply_markup' => kb_inline([[['text' => '✅ Tushundim', 'callback_data' => 'fs_start']], [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']]])]
            );
            break;
        case '📦 Buyurtmalarim':
            $orders = get_user_orders($uid);
            if (!$orders) { send_message($chat_id, '📦 Sizda hali buyurtma yo\'q.'); return; }
            $labels = ['stars' => '⭐ Stars', 'premium' => '💎 Premium', 'gift' => '🎁 Gift', 'nomer' => '📞 Nomer'];
            $lines  = ["🛒 <b>So'nggi buyurtmalaringiz:</b>\n━━━━━━━━━━━━━━━━\n"];
            foreach ($orders as $o) {
                $e = order_status_emoji($o['status']);
                $t = $labels[$o['type']] ?? $o['type'];
                $a = match($o['type']) {
                    'stars'   => $o['amount'] . ' Stars',
                    'premium' => $o['amount'] . ' oy',
                    'gift'    => $o['amount'] . ' stars',
                    default   => (string)$o['amount'],
                };
                $d = substr($o['created_at'], 0, 10);
                $lines[] = "{$e} #{$o['order_id']} | {$t} | {$a}\n   💰 " . fmt($o['price']) . " so'm | {$d}\n";
            }
            send_message($chat_id, implode("\n", $lines));
            break;
        case '👤 Profilim':
            $user   = get_user($uid);
            $joined = $user ? substr($user['joined_at'], 0, 10) : '—';
            send_message($chat_id,
                "👤 <b>Mening profilim</b>\n\n━━━━━━━━━━━━━━━━\n🆔 ID: <code>{$uid}</code>\n📛 Ism: <b>{$fullname}</b>\n📅 Ro'yxatdan o'tgan: {$joined}\n━━━━━━━━━━━━━━━━\n💰 Balans: <b>" . fmt($user['balance'] ?? 0) . " so'm</b>"
            );
            break;
        case 'ℹ️ Yordam':
            send_message($chat_id,
                "ℹ️ <b>Yordam</b>\n\nBu bot orqali Telegram xizmatlarini qulay sotib olasiz:\n\n"
                . "⭐ <b>Stars</b> — istalgan akkauntga yuboriladi\n"
                . "💎 <b>Premium</b> — username orqali avtomatik faollashadi\n"
                . "🎁 <b>Gift</b> — do'stingizga sovg'a yuboring\n"
                . "📞 <b>Nomer</b> — oldindan ro'yxatdan o'tkazilgan akkauntlar\n\n"
                . "💳 <b>To'lov:</b> HUMO karta orqali avtomatik\n\n❓ Muammo bo'lsa admin bilan bog'laning."
            );
            break;
        default:
            $s = get_enabled();
            send_message($chat_id, get_setting('welcome_text', 'Xush kelibsiz!'), ['reply_markup' => kb_main(...$s)]);
    }
}

function handle_state(int $uid, int $chat_id, string $text, string $state, array $sess, array $msg): void {
    switch ($state) {
        // ── Stars ──
        case 'stars_recipient':
            if (!str_starts_with($text, '@')) {
                send_message($chat_id, "⚠️ Faqat <b>\@username</b> ko'rinishida kiriting.\nBekor qilish: /cancel");
                return;
            }
            update_session($uid, ['state' => 'stars_choose', 'recipient' => $text]);
            send_message($chat_id, "✅ Qabul qiluvchi: <b>{$text}</b>\n\n⭐ <b>Paket tanlang:</b>", ['reply_markup' => kb_stars(get_packages('stars'))]);
            break;

        case 'stars_custom':
            $n = (int)$text;
            if (!ctype_digit(str_replace(' ', '', $text)) || $n < 50 || $n > 1000000) {
                send_message($chat_id, "⚠️ 50 dan 1 000 000 gacha raqam kiriting.");
                return;
            }
            $pkgs = get_packages('stars');
            $pps  = $pkgs ? round($pkgs[0]['price'] / $pkgs[0]['stars']) : 280;
            $price = $n * $pps;
            $recipient = $sess['recipient'] ?? (string)$uid;
            update_session($uid, ['state' => 'stars_pay', 'stars' => $n]);
            create_payment($uid, $price, 'stars', $n, $msg['message_id'], $chat_id, ['recipient' => $recipient, 'stars' => $n]);
            break;

        // ── Premium ──
        case 'premium_recipient':
            if (!str_starts_with($text, '@')) {
                send_message($chat_id, "⚠️ Noto'g'ri format!\n\@username ko'rinishida kiriting.");
                return;
            }
            update_session($uid, ['state' => 'premium_choose', 'recipient' => $text]);
            send_message($chat_id, "✅ Qabul qiluvchi: <b>{$text}</b>\n\n💎 <b>Muddatni tanlang:</b>", ['reply_markup' => kb_premium(get_packages('premium'))]);
            break;

        // ── Gift ──
        case 'gift_recipient':
            if (!str_starts_with($text, '@')) {
                send_message($chat_id, "⚠️ Noto'g'ri format!\n\@username ko'rinishida kiriting.");
                return;
            }
            update_session($uid, ['state' => 'gift_choose', 'recipient' => $text]);
            send_message($chat_id, "✅ Qabul qiluvchi: <b>{$text}</b>\n\n🎁 <b>Gift tanlang:</b>", ['reply_markup' => kb_gift(get_packages('gift'))]);
            break;

        // ── Admin: Broadcast ──
        case 'admin_broadcast':
            if ($uid !== ADMIN_ID) { clear_session($uid); return; }
            clear_session($uid);
            $ids = get_all_user_ids();
            $sent = $fail = 0;
            foreach ($ids as $id) {
                $r = send_message((int)$id, $text);
                if ($r && $r['ok']) $sent++; else $fail++;
                usleep(50000);
            }
            send_message($chat_id, "✅ <b>Broadcast yakunlandi!</b>\n\n📤 Yuborildi: <b>{$sent}</b>\n❌ Muvaffaqiyatsiz: <b>{$fail}</b>\n📊 Jami: <b>" . count($ids) . "</b>");
            break;

        // ── Admin: Welcome ──
        case 'admin_edit_welcome':
            if ($uid !== ADMIN_ID) { clear_session($uid); return; }
            set_setting('welcome_text', trim($text));
            clear_session($uid);
            send_message($chat_id, "✅ Xush kelish matni yangilandi!");
            break;

        // ── Admin: Stars paket ──
        case 'admin_add_stars_value':
            if (!ctype_digit($text)) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            update_session($uid, ['state' => 'admin_add_stars_price', 'new_value' => (int)$text]);
            send_message($chat_id, "💰 {$text} Stars uchun narx (so'm)?");
            break;
        case 'admin_add_stars_price':
            if (!ctype_digit(str_replace(' ', '', $text))) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            $pkgs = get_packages('stars');
            $pkgs[] = ['stars' => $sess['new_value'], 'price' => (int)str_replace(' ', '', $text)];
            usort($pkgs, fn($a, $b) => $a['stars'] <=> $b['stars']);
            set_packages('stars', $pkgs);
            clear_session($uid);
            send_message($chat_id, "✅ Yangi Stars paketi qo'shildi!", ['reply_markup' => kb_admin_pkg($pkgs, 'stars', 'stars', 'price', 'add_stars_pkg', 'del_stars_menu')]);
            break;

        // ── Admin: Premium paket ──
        case 'admin_add_premium_value':
            if (!ctype_digit($text)) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            update_session($uid, ['state' => 'admin_add_premium_label', 'new_value' => (int)$text]);
            send_message($chat_id, "📝 {$text} oy uchun yorliq kiriting (masalan: '2 oy'):");
            break;
        case 'admin_add_premium_label':
            update_session($uid, ['state' => 'admin_add_premium_price', 'new_label' => trim($text)]);
            send_message($chat_id, "💰 Narxi (so'm)?");
            break;
        case 'admin_add_premium_price':
            if (!ctype_digit(str_replace(' ', '', $text))) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            $pkgs = get_packages('premium');
            $pkgs[] = ['months' => $sess['new_value'], 'label' => $sess['new_label'], 'price' => (int)str_replace(' ', '', $text)];
            usort($pkgs, fn($a, $b) => $a['months'] <=> $b['months']);
            set_packages('premium', $pkgs);
            clear_session($uid);
            send_message($chat_id, "✅ Yangi Premium paketi qo'shildi!", ['reply_markup' => kb_admin_pkg($pkgs, 'premium', 'label', 'price', 'add_premium_pkg', 'del_premium_menu')]);
            break;

        // ── Admin: Gift paket ──
        case 'admin_add_gift_id':
            update_session($uid, ['state' => 'admin_add_gift_name', 'new_gift_id' => trim($text)]);
            send_message($chat_id, "🎁 Gift nomini kiriting:");
            break;
        case 'admin_add_gift_name':
            update_session($uid, ['state' => 'admin_add_gift_stars', 'new_gift_name' => trim($text)]);
            send_message($chat_id, "⭐ Gift stars_cost (1–100):");
            break;
        case 'admin_add_gift_stars':
            $n = (int)$text;
            if (!ctype_digit($text) || $n < 1 || $n > 100) { send_message($chat_id, '⚠️ 1 dan 100 gacha raqam kiriting.'); return; }
            update_session($uid, ['state' => 'admin_add_gift_price', 'new_stars_cost' => $n]);
            send_message($chat_id, "💰 Narxi (so'm)?");
            break;
        case 'admin_add_gift_price':
            if (!ctype_digit(str_replace(' ', '', $text))) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            $pkgs = get_packages('gift');
            $pkgs[] = ['gift_id' => $sess['new_gift_id'], 'name' => $sess['new_gift_name'], 'stars_cost' => $sess['new_stars_cost'], 'price' => (int)str_replace(' ', '', $text)];
            set_packages('gift', $pkgs);
            clear_session($uid);
            send_message($chat_id, "✅ Yangi Gift qo'shildi!", ['reply_markup' => kb_admin_pkg($pkgs, 'gift', 'name', 'price', 'add_gift_pkg', 'del_gift_menu')]);
            break;

        // ── Admin: narx tahrirlash ──
        case 'admin_edit_price':
            if (!ctype_digit(str_replace(' ', '', $text))) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            $type = $sess['pkg_type'] ?? 'stars';
            $idx  = (int)($sess['idx'] ?? 0);
            $pkgs = get_packages($type);
            $pkgs[$idx]['price'] = (int)str_replace(' ', '', $text);
            set_packages($type, $pkgs);
            clear_session($uid);
            $kbs = [
                'stars'   => fn($p) => kb_admin_pkg($p, 'stars',   'stars', 'price', 'add_stars_pkg',   'del_stars_menu'),
                'premium' => fn($p) => kb_admin_pkg($p, 'premium', 'label', 'price', 'add_premium_pkg', 'del_premium_menu'),
                'gift'    => fn($p) => kb_admin_pkg($p, 'gift',    'name',  'price', 'add_gift_pkg',    'del_gift_menu'),
            ];
            send_message($chat_id, "✅ Narx yangilandi!", ['reply_markup' => ($kbs[$type] ?? fn($p) => [])($pkgs)]);
            break;

        // ── Admin: kanal qo'shish ──
        case 'admin_add_ch_id':
            update_session($uid, ['state' => 'admin_add_ch_title', 'new_ch_id' => trim($text)]);
            send_message($chat_id, "📝 Kanal nomini kiriting:");
            break;
        case 'admin_add_ch_title':
            update_session($uid, ['state' => 'admin_add_ch_link', 'new_ch_title' => trim($text)]);
            send_message($chat_id, "🔗 Kanal linkini kiriting (masalan: https://t.me/kanal):");
            break;
        case 'admin_add_ch_link':
            add_forced_channel($sess['new_ch_id'], $sess['new_ch_title'], trim($text));
            clear_session($uid);
            $channels = get_forced_channels();
            send_message($chat_id, "✅ Kanal qo'shildi!", ['reply_markup' => kb_admin_channels($channels)]);
            break;

        // ── Admin: Nomer narx ──
        case 'admin_nomer_set_price':
            if (!ctype_digit(str_replace(' ', '', $text))) { send_message($chat_id, 'Faqat raqam kiriting!'); return; }
            $code = $sess['nomer_code'] ?? '';
            if ($code) set_nomer_override($code, (int)str_replace(' ', '', $text));
            clear_session($uid);
            send_message($chat_id, "✅ Narx yangilandi!");
            break;

        default:
            clear_session($uid);
    }
}

// ─── Admin matn tugmalari ─────────────────────────────────────────
function handle_admin_text(int $uid, int $chat_id, string $text, array $sess): void {
    switch ($text) {
        case '🔙 Asosiy menyu':
            clear_session($uid);
            $s = get_enabled();
            send_message($chat_id, "Asosiy menyu:", ['reply_markup' => kb_main(...$s)]);
            break;
        case '📊 Statistika':
            $s = get_stats();
            send_message($chat_id,
                "📊 <b>Statistika</b>

"
                . "━━━━━━━━━━━━━━━━
"
                . "👥 Foydalanuvchilar: <b>{$s['users']}</b>
"
                . "🆕 Bugun: <b>{$s['new_today']}</b>
"
                . "━━━━━━━━━━━━━━━━
"
                . "✅ Muvaffaqiyatli: <b>{$s['paid']}</b>
"
                . "⏳ Kutilayotgan: <b>{$s['pending']}</b>
"
                . "━━━━━━━━━━━━━━━━
"
                . "💰 Jami daromad: <b>" . fmt($s['revenue']) . " so'm</b>"
            );
            break;
        case '⭐ Stars narxlari':
            $pkgs = get_packages('stars');
            send_message($chat_id, "⭐ <b>Stars paketlari:</b>", ['reply_markup' => kb_admin_pkg($pkgs, 'stars', 'stars', 'price', 'add_stars_pkg', 'del_stars_menu')]);
            break;
        case '💎 Premium narxlari':
            $pkgs = get_packages('premium');
            send_message($chat_id, "💎 <b>Premium paketlari:</b>", ['reply_markup' => kb_admin_pkg($pkgs, 'premium', 'label', 'price', 'add_premium_pkg', 'del_premium_menu')]);
            break;
        case '🎁 Gift narxlari':
            $pkgs = get_packages('gift');
            send_message($chat_id, "🎁 <b>Gift paketlari:</b>", ['reply_markup' => kb_admin_pkg($pkgs, 'gift', 'name', 'price', 'add_gift_pkg', 'del_gift_menu')]);
            break;
        case '📞 Nomer narxlari':
            $ov = get_nomer_overrides();
            $countries = fixsim_get_countries();
            send_message($chat_id,
                "📞 <b>Nomer narxlari</b>\n\nFixSIM API narxlari asosida ko'rsatiladi.\n✏️ O'zgartirilgan davlatlar: <b>" . count($ov) . "</b> ta",
                ['reply_markup' => kb_nomer_overrides($ov, $countries)]
            );
            break;
        case '📢 Majburiy obuna':
            $channels = get_forced_channels();
            send_message($chat_id, "📢 <b>Majburiy obuna kanallari:</b>", ['reply_markup' => kb_admin_channels($channels)]);
            break;
        case '⚙️ Bot sozlamalari':
            $s = ['stars_enabled' => get_setting('stars_enabled','1'), 'premium_enabled' => get_setting('premium_enabled','1'), 'gift_enabled' => get_setting('gift_enabled','1'), 'nomer_enabled' => get_setting('nomer_enabled','1')];
            $welcome = get_setting('welcome_text', 'Xush kelibsiz!');
            $on = fn($v) => $v === '1' ? '✅ Yoqiq' : "❌ O'chiq";
            send_message($chat_id,
                "⚙️ <b>Bot sozlamalari</b>

━━━━━━━━━━━━━━━━
⭐ Stars: {$on($s['stars_enabled'])}\n💎 Premium: {$on($s['premium_enabled'])}\n🎁 Gift: {$on($s['gift_enabled'])}\n📞 Nomer: {$on($s['nomer_enabled'])}\n\n━━━━━━━━━━━━━━━━

📝 <b>Xush kelish matni:</b>
<i>{$welcome}</i>",
                ['reply_markup' => kb_admin_settings($s, $welcome)]
            );
            break;
        case '📣 Xabar yuborish':
            update_session($uid, ['state' => 'admin_broadcast']);
            send_message($chat_id, "📣 <b>Broadcast</b>\n\nBarcha foydalanuvchilarga yuboriladigan xabarni kiriting.\nHTML formatlash qo'llab-quvvatlanadi.\n\nBekor qilish: /cancel");
            break;
        case '💰 Hyperpin':
            $bal  = hyperpin_balance();
            $dep  = hyperpin_deposit();
            $text = '';
            if ($bal['success'] ?? false) {
                $text .= "💰 <b>Balans:</b> <code>{$bal['balance']} TON</code>\n👤 <b>Foydalanuvchi:</b> @{$bal['username']}\n";
            } else {
                $text .= "❌ Balans xatosi: " . ($bal['error'] ?? 'Noma\'lum') . "\n";
            }
            if ($dep['address'] ?? '') $text .= "\n📥 <b>Depozit manzil:</b> <code>{$dep['address']}</code>";
            send_message($chat_id, $text ?: "Ma'lumot yo'q.");
            break;
        default:
            handle_user_text($uid, $chat_id, $text, $sess, '', '');
    }
}

// ─── Callback handler ─────────────────────────────────────────────
function handle_callback(array $cb): void {
    $uid     = $cb['from']['id'];
    $chat_id = $cb['message']['chat']['id'];
    $mid     = $cb['message']['message_id'];
    $cbid    = $cb['id'];
    $data    = $cb['data'];
    $from    = $cb['from'];
    $sess    = get_session($uid);

    // Obuna tekshiruv
    if ($data === 'check_sub') {
        $not_sub = check_subscriptions($uid);
        if ($not_sub) { answer_callback($cbid, "⚠️ Hali barcha kanallarga obuna bo'lmadingiz!", true); return; }
        delete_message($chat_id, $mid);
        $s = get_enabled();
        $welcome = get_setting('welcome_text', 'Xush kelibsiz!');
        send_message($chat_id, "✅ Rahmat!\n\n👋 {$welcome}", ['reply_markup' => kb_main(...$s)]);
        answer_callback($cbid);
        return;
    }

    if ($data === 'back_main') {
        clear_session($uid);
        try { delete_message($chat_id, $mid); } catch (Exception $e) {}
        answer_callback($cbid);
        return;
    }

    if ($data === 'none') { answer_callback($cbid); return; }

    // Stars
    if (str_starts_with($data, 'stars_buy:')) {
        $idx  = (int)explode(':', $data)[1];
        $pkgs = get_packages('stars');
        if (!isset($pkgs[$idx])) { answer_callback($cbid, 'Paket topilmadi!', true); return; }
        $p         = $pkgs[$idx];
        $recipient = $sess['recipient'] ?? (string)$uid;
        update_session($uid, ['state' => 'stars_pay', 'stars' => $p['stars']]);
        create_payment($uid, $p['price'], 'stars', $p['stars'], $mid, $chat_id, ['recipient' => $recipient, 'stars' => $p['stars']]);
        answer_callback($cbid);
        return;
    }

    if ($data === 'stars_custom') {
        update_session($uid, ['state' => 'stars_custom']);
        send_message($chat_id, "✏️ Nechta Stars xohlaysiz?\n\n<i>Minimal: 50 | Maksimal: 1 000 000</i>\n\nBekor qilish: /cancel");
        answer_callback($cbid);
        return;
    }

    // Premium
    if (str_starts_with($data, 'premium_buy:')) {
        $idx  = (int)explode(':', $data)[1];
        $pkgs = get_packages('premium');
        if (!isset($pkgs[$idx])) { answer_callback($cbid, 'Paket topilmadi!', true); return; }
        $p         = $pkgs[$idx];
        $recipient = $sess['recipient'] ?? '';
        if (!$recipient) { answer_callback($cbid, '❌ Avval @username kiriting!', true); return; }
        update_session($uid, ['state' => 'premium_pay', 'months' => $p['months']]);
        create_payment($uid, $p['price'], 'premium', $p['months'], $mid, $chat_id, ['recipient' => $recipient, 'months' => $p['months']]);
        answer_callback($cbid);
        return;
    }

    // Gift
    if (str_starts_with($data, 'gift_buy:')) {
        $idx  = (int)explode(':', $data)[1];
        $pkgs = get_packages('gift');
        if (!isset($pkgs[$idx])) { answer_callback($cbid, 'Gift topilmadi!', true); return; }
        $p         = $pkgs[$idx];
        $recipient = $sess['recipient'] ?? (string)$uid;
        update_session($uid, ['state' => 'gift_pay', 'gift_idx' => $idx]);
        create_payment($uid, $p['price'], 'gift', $p['stars_cost'], $mid, $chat_id, ['recipient' => $recipient, 'gift_idx' => $idx, 'gift_name' => $p['name']]);
        answer_callback($cbid);
        return;
    }

    // FixSIM
    if ($data === 'fs_start') {
        edit_message_text($chat_id, $mid, "<b>⏳ Davlatlar yuklanmoqda...</b>");
        $countries = get_countries_with_overrides();
        if (!$countries) {
            edit_message_text($chat_id, $mid, "<b>❌ Davlatlar yuklanmadi.</b>",
                ['reply_markup' => kb_inline([[['text' => '🔄 Qayta urinish', 'callback_data' => 'fs_start']], [['text' => '🔙 Orqaga', 'callback_data' => 'back_main']]])]);
            answer_callback($cbid); return;
        }
        edit_message_text($chat_id, $mid, "<b>🌍 Mavjud davlatlar ro'yxati:</b>\n\nKerakli davlatni tanlang:", ['reply_markup' => kb_fixsim_countries($countries, 0)]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'fs_page:')) {
        $page      = (int)explode(':', $data)[1];
        $countries = get_countries_with_overrides();
        edit_message_text($chat_id, $mid, "<b>🌍 Mavjud davlatlar ro'yxati:</b>\n\nKerakli davlatni tanlang:", ['reply_markup' => kb_fixsim_countries($countries, $page)]);
        answer_callback($cbid);
        return;
    }

    if ($data === 'fs_top10') {
        $countries = get_countries_with_overrides();
        edit_message_text($chat_id, $mid, "<b>📊 TOP 10 mashhur davlatlar:</b>", ['reply_markup' => kb_fixsim_list(array_slice($countries, 0, 10), 'fs_start')]);
        answer_callback($cbid);
        return;
    }

    if ($data === 'fs_cheap') {
        $countries = get_countries_with_overrides();
        usort($countries, fn($a, $b) => (float)($a['price_uzs'] ?? 0) <=> (float)($b['price_uzs'] ?? 0));
        edit_message_text($chat_id, $mid, "<b>💰 Eng arzon 10 ta davlat:</b>", ['reply_markup' => kb_fixsim_list(array_slice($countries, 0, 10), 'fs_start')]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'fs_buy:')) {
        $parts  = explode(':', $data, 3);
        $c_code = $parts[1] ?? '';
        $c_name = $parts[2] ?? $c_code;
        $price  = 0;
        foreach (get_countries_with_overrides() as $c) {
            if ((string)($c['country_code'] ?? '') === $c_code) { $price = (int)($c['price_uzs'] ?? 0); break; }
        }
        if ($price <= 0) { answer_callback($cbid, "❌ Narx aniqlanmadi.", true); return; }
        edit_message_text($chat_id, $mid,
            "<b>🛒 Buyurtmangizni tasdiqlang</b>\n\n<blockquote>🌍 Davlat: <b>{$c_name}</b>\n💵 Narxi: <code>" . fmt($price) . "</code> so'm</blockquote>\n\n"
            . "<blockquote>⚠️ Raqamni Telegramning rasmiy ilovasidan faollashtirmang!\n✅ Sinovdan o'tgan, 2 bosqichli parolli raqam beriladi.</blockquote>\n\n"
            . "Tasdiqlash uchun <b>✅ Sotib olish</b> tugmasini bosing.",
            ['reply_markup' => kb_inline([[['text' => '✅ Sotib olish', 'callback_data' => "fs_confirm:{$c_code}:" . substr($c_name, 0, 30)]], [['text' => '❌ Bekor qilish', 'callback_data' => 'fs_start']]])]
        );
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'fs_confirm:')) {
        $parts  = explode(':', $data, 3);
        $c_code = $parts[1] ?? '';
        $c_name = $parts[2] ?? $c_code;
        $price  = 0;
        foreach (get_countries_with_overrides() as $c) {
            if ((string)($c['country_code'] ?? '') === $c_code) { $price = (int)($c['price_uzs'] ?? 0); break; }
        }
        if ($price <= 0) { answer_callback($cbid, "❌ Narx aniqlanmadi", true); return; }
        update_session($uid, ['state' => 'nomer_pay']);
        create_payment($uid, $price, 'nomer', $price, $mid, $chat_id, ['country_code' => $c_code, 'country_name' => $c_name]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'fs_sms:')) {
        $hash = explode(':', $data, 2)[1] ?? '';
        if (!$hash) { answer_callback($cbid, '❌ Xato ma\'lumot', true); return; }
        if (!fixsim_flood_check($uid, $hash)) { answer_callback($cbid, '⏳ Iltimos, 30 soniya kuting', true); return; }
        $res = fixsim_get_sms($hash);
        if ($res['sms']) {
            $t = "<b>🎉 SMS Kod keldi!</b>\n\n<blockquote>🔢 Kirish kodi: <code>{$res['sms']}</code>";
            if ($res['password']) $t .= "\n🔐 2 Bosqichli parol: <code>{$res['password']}</code>";
            $t .= "</blockquote>";
            send_message($chat_id, $t);
        } else {
            answer_callback($cbid, '⏳ SMS hali kelmadi. 30 soniyadan so\'ng qayta bosing.', true);
            edit_message_text($chat_id, $mid,
                "<b>⏳ SMS hali kelmadi.</b>\n\n<blockquote>Biroz kuting va qayta tekshiring. Odatda 1–3 daqiqa ichida keladi.</blockquote>",
                ['reply_markup' => kb_inline([[['text' => '🔄 Qayta tekshirish', 'callback_data' => "fs_sms:{$hash}"]]])]
            );
        }
        answer_callback($cbid);
        return;
    }

    // ── To'lovni tekshirish ──
    if (str_starts_with($data, 'check_pay:')) {
        $oid = (int)explode(':', $data)[1];
        $d   = humo_check($oid);
        if (!$d) { answer_callback($cbid, "⚠️ Server javob bermadi. Qaytadan bosing.", true); return; }
        $status = $d['status'] ?? '';
        $secs   = (int)($d['seconds_left'] ?? 0);

        if ($status === 'paid') {
            if (!try_claim_order($oid)) { answer_callback($cbid, "⚠️ Bu buyurtma allaqachon qayta ishlanmoqda.", true); return; }
            $order = get_order($oid);
            if (!$order) { answer_callback($cbid, 'Buyurtma topilmadi!', true); return; }
            edit_message_text($chat_id, $mid, '⏳ To\'lov tasdiqlandi! Mahsulot yuborilmoqda...');

            $recipient  = $order['recipient'] ?? '';
            $extra      = json_decode($order['extra_data'] ?? '{}', true) ?? [];

            if ($order['type'] === 'stars') {
                $stars    = (int)$order['amount'];
                $username = ltrim($recipient ?: (string)$uid, '@');
                $res      = hyperpin_buy_stars($username, $stars);
                if ($res['success'] ?? false) {
                    update_order_status($oid, 'completed');
                    edit_message_text($chat_id, $mid,
                        "🎉 <b>Muvaffaqiyatli yetkazildi!</b>\n\n━━━━━━━━━━━━━━━━\n👤 Qabul: <b>@{$username}</b>\n⭐ <b>{$stars} Telegram Stars</b> yuborildi!\n🧾 Buyurtma: <code>#{$oid}</code>\n━━━━━━━━━━━━━━━━"
                    );
                    // 📢 Kanalga xabar
                    notify_channel_order(array_merge($order, ['order_id' => $oid]), $from);
                } else {
                    $err = $res['error'] ?? 'Noma\'lum xato';
                    edit_message_text($chat_id, $mid, "✅ To'lov qabul qilindi!\n⭐ Stars tez orada yuboriladi.\n🧾 Buyurtma: <code>#{$oid}</code>");
                    notify_admin("Stars yuborilmadi!", "💳 Xaridor: <code>{$uid}</code>\n👤 Qabul: <b>@{$username}</b>\n⭐ Miqdor: <b>{$stars}</b>\n🧾 Order: <code>#{$oid}</code>\n⚠️ Xato: <code>{$err}</code>");
                }

            } elseif ($order['type'] === 'premium') {
                $months   = (int)$order['amount'];
                $username = ltrim($recipient, '@');
                if (!$username) { edit_message_text($chat_id, $mid, "❌ Premium uchun \@username topilmadi.\nAdmin bilan bog'laning."); return; }
                $res = hyperpin_buy_premium($username, $months);
                if ($res['success'] ?? false) {
                    update_order_status($oid, 'completed');
                    edit_message_text($chat_id, $mid,
                        "🎉 <b>Muvaffaqiyatli!</b>\n\n━━━━━━━━━━━━━━━━\n👤 Qabul: <b>@{$username}</b>\n💎 <b>{$months} oy Telegram Premium</b> faollashtirildi!\n🧾 Buyurtma: <code>#{$oid}</code>\n━━━━━━━━━━━━━━━━"
                    );
                    notify_channel_order(array_merge($order, ['order_id' => $oid]), $from);
                } else {
                    $err = $res['error'] ?? 'Noma\'lum xato';
                    edit_message_text($chat_id, $mid, "✅ To'lov qabul qilindi!\n💎 Premium tez orada faollashadi.\n🧾 Buyurtma: <code>#{$oid}</code>");
                    notify_admin("Premium faollashmadi!", "💳 Xaridor: <code>{$uid}</code>\n👤 Qabul: <b>@{$username}</b>\n💎 Muddat: <b>{$months} oy</b>\n🧾 Order: <code>#{$oid}</code>\n⚠️ Xato: <code>{$err}</code>");
                }

            } elseif ($order['type'] === 'gift') {
                $gift_idx  = (int)($extra['gift_idx'] ?? 0);
                $pkgs      = get_packages('gift');
                $username  = ltrim($recipient ?: (string)$uid, '@');
                if (isset($pkgs[$gift_idx])) {
                    $p   = $pkgs[$gift_idx];
                    $res = hyperpin_buy_gift($username, $p['gift_id'], $p['stars_cost']);
                    if ($res['success'] ?? false) {
                        update_order_status($oid, 'completed');
                        edit_message_text($chat_id, $mid,
                            "🎉 <b>Muvaffaqiyatli!</b>\n\n━━━━━━━━━━━━━━━━\n👤 Qabul: <b>@{$username}</b>\n🎁 <b>{$p['name']}</b> yuborildi!\n🧾 Buyurtma: <code>#{$oid}</code>\n━━━━━━━━━━━━━━━━"
                        );
                        notify_channel_order(array_merge($order, ['order_id' => $oid]), $from);
                    } else {
                        $err = $res['error'] ?? 'Noma\'lum xato';
                        edit_message_text($chat_id, $mid, "✅ To'lov qabul qilindi!\n🎁 Gift tez orada yuboriladi.\n🧾 Buyurtma: <code>#{$oid}</code>");
                        notify_admin("Gift yuborilmadi!", "💳 Xaridor: <code>{$uid}</code>\n👤 Qabul: <b>@{$username}</b>\n🎁 {$p['name']}\n🧾 Order: <code>#{$oid}</code>\n⚠️ Xato: <code>{$err}</code>");
                    }
                } else {
                    edit_message_text($chat_id, $mid, "✅ To'lov qabul qilindi!\n🎁 Gift tez orada yuboriladi.\n🧾 Buyurtma: <code>#{$oid}</code>");
                }

            } elseif ($order['type'] === 'nomer') {
                $c_code = $extra['country_code'] ?? '';
                $c_name = $extra['country_name'] ?? $c_code;
                if (!$c_code) {
                    edit_message_text($chat_id, $mid, "❌ Davlat ma'lumotlari topilmadi. Admin bilan bog'laning.");
                    notify_admin("Nomer: country_code yo'q!", "👤 User: <code>{$uid}</code>\n🧾 Order: <code>#{$oid}</code>");
                    return;
                }
                $result = fixsim_buy_number($c_code);
                if ($result['error']) {
                    edit_message_text($chat_id, $mid, "<b>⛔️ To'lov qabul qilindi, lekin raqam qolmagan.</b>\n\nAdmin bilan bog'laning — puliz qaytariladi.");
                    notify_admin("Nomer ajratilmadi (to'lov o'tdi)!", "👤 User: <code>{$uid}</code>\n🌍 Davlat: <b>{$c_name}</b>\n💰 To'lov: <b>" . fmt($order['price']) . " so'm</b>\n🧾 Order: <code>#{$oid}</code>\n⚠️ Xato: <code>{$result['error']}</code>");
                    return;
                }
                $phone = $result['phone'];
                $hash  = $result['hash'];
                update_order_status($oid, 'completed');
                edit_message_text($chat_id, $mid, "✅ <b>To'lov tasdiqlandi, raqam ajratildi!</b>");
                send_message($chat_id,
                    "<b>✅ Raqam muvaffaqiyatli olindi!</b>\n\n<blockquote>"
                    . "📞 Raqamingiz: <code>{$phone}</code>\n"
                    . "🌍 Davlat: {$c_name}\n"
                    . "💵 Narxi: <code>" . fmt($order['price']) . "</code> so'm"
                    . "</blockquote>\n\n<blockquote>"
                    . "⚠️ Bu raqam bilan Telegramning rasmiy ilovasidan kirmang!\n"
                    . "📱 Nicegram, Plus Messenger kabi norasmiy ilovalardan foydalaning.\n"
                    . "💡 Kodni olish uchun quyidagi tugmani bosing."
                    . "</blockquote>",
                    ['reply_markup' => kb_inline([[['text' => '🔢 SMS kodni olish', 'callback_data' => "fs_sms:{$hash}"]]])]
                );
                // 📢 Kanalga nomer xabarnomasi
                notify_channel_order(array_merge($order, ['order_id' => $oid]), $from);
            }
            clear_session($uid);

        } elseif (in_array($status, ['expired', 'cancelled']) || $secs <= 0) {
            update_order_status($oid, 'expired');
            answer_callback($cbid, "⏰ Muddat tugadi. Qaytadan urinib ko'ring.", true);
            edit_message_text($chat_id, $mid, "⏰ <b>To'lov muddati tugadi.</b>\n\nQaytadan /start bosing.", ['reply_markup' => null]);
            clear_session($uid);
        } else {
            $m = intdiv($secs, 60); $s = $secs % 60;
            $t = $m ? "⏳ Hali tasdiqlanmadi. ~{$m} daqiqa {$s} soniya qoldi." : "⏳ Hali tasdiqlanmadi. ~{$s} soniya qoldi.";
            answer_callback($cbid, $t, true);
        }
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'cancel_pay:')) {
        $oid = (int)explode(':', $data)[1];
        update_order_status($oid, 'cancelled');
        clear_session($uid);
        edit_message_text($chat_id, $mid, "❌ To'lov bekor qilindi.");
        answer_callback($cbid);
        return;
    }

    // ── Admin callback-lar ──
    if ($uid !== ADMIN_ID) { answer_callback($cbid); return; }

    // Toggle
    foreach (['toggle_stars' => ['stars_enabled', 'Stars'], 'toggle_premium' => ['premium_enabled', 'Premium'], 'toggle_gift' => ['gift_enabled', 'Gift'], 'toggle_nomer' => ['nomer_enabled', 'Nomer xizmati']] as $cb_key => [$setting, $label]) {
        if ($data === $cb_key) {
            $cur = get_setting($setting, '1');
            $new = $cur === '1' ? '0' : '1';
            set_setting($setting, $new);
            $state_text = $new === '1' ? 'yoqildi ✅' : "o'chirildi ❌";
            answer_callback($cbid, "{$label} {$state_text}", true);
            try { delete_message($chat_id, $mid); } catch (Exception $e) {}
            return;
        }
    }

    if ($data === 'edit_welcome') {
        update_session($uid, ['state' => 'admin_edit_welcome']);
        send_message($chat_id, "✏️ Yangi xush kelish xabarini kiriting:");
        answer_callback($cbid);
        return;
    }

    if ($data === 'admin_back') {
        clear_session($uid);
        try { delete_message($chat_id, $mid); } catch (Exception $e) {}
        answer_callback($cbid);
        return;
    }

    // Majburiy obuna
    if ($data === 'add_channel') {
        update_session($uid, ['state' => 'admin_add_ch_id']);
        send_message($chat_id, "📢 Kanal ID sini kiriting (masalan: @mening_kanal yoki -1001234567890):");
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'del_channel:')) {
        $id = (int)explode(':', $data)[1];
        remove_forced_channel($id);
        $channels = get_forced_channels();
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_admin_channels($channels)]);
        answer_callback($cbid, "✅ O'chirildi.");
        return;
    }

    // Stars paket
    if (str_starts_with($data, 'edit_stars:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('stars');
        update_session($uid, ['state' => 'admin_edit_price', 'idx' => $idx, 'pkg_type' => 'stars']);
        send_message($chat_id, "⭐ <b>{$pkgs[$idx]['stars']} Stars</b> — hozirgi: <b>" . fmt($pkgs[$idx]['price']) . " so'm</b>\n\nYangi narx (so'm):");
        answer_callback($cbid);
        return;
    }

    if ($data === 'add_stars_pkg') {
        update_session($uid, ['state' => 'admin_add_stars_value']);
        send_message($chat_id, "⭐ Yangi paket uchun nechta Stars? (masalan: 300)");
        answer_callback($cbid);
        return;
    }

    if ($data === 'del_stars_menu') {
        $pkgs = get_packages('stars');
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_del_pkg($pkgs, 'stars', 'stars')]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'del_stars:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('stars');
        if (count($pkgs) <= 1) { answer_callback($cbid, 'Kamida 1 ta paket qolishi kerak!', true); return; }
        $removed = array_splice($pkgs, $idx, 1)[0];
        set_packages('stars', $pkgs);
        answer_callback($cbid, "✅ {$removed['stars']} Stars o'chirildi.");
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_admin_pkg($pkgs, 'stars', 'stars', 'price', 'add_stars_pkg', 'del_stars_menu')]);
        return;
    }

    // Premium paket
    if (str_starts_with($data, 'edit_premium:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('premium');
        update_session($uid, ['state' => 'admin_edit_price', 'idx' => $idx, 'pkg_type' => 'premium']);
        send_message($chat_id, "💎 <b>{$pkgs[$idx]['label']}</b> — hozirgi: <b>" . fmt($pkgs[$idx]['price']) . " so'm</b>\n\nYangi narx (so'm):");
        answer_callback($cbid);
        return;
    }

    if ($data === 'add_premium_pkg') {
        update_session($uid, ['state' => 'admin_add_premium_value']);
        send_message($chat_id, "💎 Necha oy uchun paket? (masalan: 2)");
        answer_callback($cbid);
        return;
    }

    if ($data === 'del_premium_menu') {
        $pkgs = get_packages('premium');
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_del_pkg($pkgs, 'premium', 'label')]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'del_premium:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('premium');
        if (count($pkgs) <= 1) { answer_callback($cbid, 'Kamida 1 ta paket qolishi kerak!', true); return; }
        $removed = array_splice($pkgs, $idx, 1)[0];
        set_packages('premium', $pkgs);
        answer_callback($cbid, "✅ {$removed['label']} o'chirildi.");
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_admin_pkg($pkgs, 'premium', 'label', 'price', 'add_premium_pkg', 'del_premium_menu')]);
        return;
    }

    // Gift paket
    if (str_starts_with($data, 'edit_gift:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('gift');
        update_session($uid, ['state' => 'admin_edit_price', 'idx' => $idx, 'pkg_type' => 'gift']);
        send_message($chat_id, "🎁 <b>{$pkgs[$idx]['name']}</b> — hozirgi: <b>" . fmt($pkgs[$idx]['price']) . " so'm</b>\n\nYangi narx (so'm):");
        answer_callback($cbid);
        return;
    }

    if ($data === 'add_gift_pkg') {
        update_session($uid, ['state' => 'admin_add_gift_id']);
        send_message($chat_id, "🎁 Gift ID sini kiriting (masalan: gift_select:0:15):");
        answer_callback($cbid);
        return;
    }

    if ($data === 'del_gift_menu') {
        $pkgs = get_packages('gift');
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_del_pkg($pkgs, 'gift', 'name')]);
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'del_gift:')) {
        $idx = (int)explode(':', $data)[1];
        $pkgs = get_packages('gift');
        if (count($pkgs) <= 1) { answer_callback($cbid, 'Kamida 1 ta paket qolishi kerak!', true); return; }
        $removed = array_splice($pkgs, $idx, 1)[0];
        set_packages('gift', $pkgs);
        answer_callback($cbid, "✅ {$removed['name']} o'chirildi.");
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_admin_pkg($pkgs, 'gift', 'name', 'price', 'add_gift_pkg', 'del_gift_menu')]);
        return;
    }

    // Nomer narx override
    if (str_starts_with($data, 'nomer_edit:')) {
        $code = explode(':', $data)[1];
        update_session($uid, ['state' => 'admin_nomer_set_price', 'nomer_code' => $code]);
        send_message($chat_id, "✏️ <b>{$code}</b> uchun yangi narx (so'm):");
        answer_callback($cbid);
        return;
    }

    if (str_starts_with($data, 'nomer_del:')) {
        $code = explode(':', $data)[1];
        remove_nomer_override($code);
        $ov  = get_nomer_overrides();
        $c   = fixsim_get_countries();
        tg('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $mid, 'reply_markup' => kb_nomer_overrides($ov, $c)]);
        answer_callback($cbid, "✅ O'chirildi.");
        return;
    }

    if ($data === 'nomer_add') {
        $countries = get_countries_with_overrides();
        edit_message_text($chat_id, $mid, "<b>🌍 Davlat tanlang:</b>", ['reply_markup' => kb_fixsim_countries($countries, 0)]);
        answer_callback($cbid);
        return;
    }

    if ($data === 'admin_nomer_back') {
        $ov  = get_nomer_overrides();
        $c   = fixsim_get_countries();
        edit_message_text($chat_id, $mid, "📞 <b>Nomer narxlari</b>", ['reply_markup' => kb_nomer_overrides($ov, $c)]);
        answer_callback($cbid);
        return;
    }

    answer_callback($cbid);
}

// ─── Kirish nuqtasi ──────────────────────────────────────────────
init_db();
$input = file_get_contents('php://input');
$update = json_decode($input, true);
if ($update) handle_update($update);
