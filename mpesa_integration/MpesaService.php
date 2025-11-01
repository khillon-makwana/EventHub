<?php

class MpesaService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/config.php';
    }

    /**
     * Query STK push status by CheckoutRequestID
     */
    public function queryStkStatus(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl() . '/mpesa/stkpushquery/v1/query';

        $shortcode = $this->config['shortcode'] ?? '';
        $timestamp = date('YmdHis');
        $passkey = $this->config['passkey'] ?? '';
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$response) {
            @file_put_contents(__DIR__ . '/stk_errors.log', date('c') . " QUERY HTTP:$code ERR:$err RESP:$response\n", FILE_APPEND);
            throw new RuntimeException('STK query failed: HTTP ' . $code);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid STK query response');
        }
        return $data;
    }

    private function baseUrl(): string
    {
        return rtrim($this->config['baseUrl'] ?? 'https://sandbox.safaricom.co.ke', '/');
    }

    public function getAccessToken(): string
    {
        $authUrl = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $auth = base64_encode(($this->config['consumerKey'] ?? '') . ':' . ($this->config['consumerSecret'] ?? ''));
        $headers = ["Authorization: Basic {$auth}"];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $authUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$response) {
            throw new RuntimeException('Failed to obtain access token: HTTP ' . $code . ' ' . $err);
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new RuntimeException('Missing access token in response');
        }
        return $data['access_token'];
    }

    /**
     * Initiate an STK Push
     *
     * @return array Decoded response including CheckoutRequestID, MerchantRequestID etc.
     */
    public function stkPush(int $amount, string $msisdn, string $callbackUrl, string $accountReference = 'EventHub', string $description = 'Ticket Purchase'): array
    {
        // Basic validations and normalizations
        // Amount must be >= 1 and integer
        $amount = max(1, (int)$amount);

        // Normalize MSISDN: keep digits and ensure 254XXXXXXXXX
        $msisdn = preg_replace('/\D+/', '', $msisdn ?? '');
        if (!preg_match('/^2547\d{8}$/', $msisdn)) {
            throw new InvalidArgumentException('Phone must be in format 2547XXXXXXXX');
        }

        // Ensure HTTPS callback (Daraja requires https). If not https, fallback to config callbackUrl.
        $cbUrl = trim($callbackUrl);
        if (stripos($cbUrl, 'https://') !== 0) {
            $fallback = $this->config['callbackUrl'] ?? '';
            if (stripos($fallback, 'https://') === 0) {
                $cbUrl = $fallback;
            } else {
                throw new InvalidArgumentException('Callback URL must be HTTPS and publicly reachable');
            }
        }

        // AccountReference max length 12 alphanumeric
        $accountReference = preg_replace('/[^A-Za-z0-9]/', '', $accountReference);
        if ($accountReference === '') { $accountReference = 'EventHub'; }
        $accountReference = substr($accountReference, 0, 12);

        $token = $this->getAccessToken();
        $stkUrl = $this->baseUrl() . '/mpesa/stkpush/v1/processrequest';

        $shortcode = $this->config['shortcode'] ?? '';
        $timestamp = date('YmdHis');
        $passkey = $this->config['passkey'] ?? '';
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $msisdn,
            'PartyB' => $shortcode,
            'PhoneNumber' => $msisdn,
            'CallBackURL' => $cbUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ];

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $stkUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$response) {
            // Persist response for debugging
            @file_put_contents(__DIR__ . '/stk_errors.log', date('c') . " HTTP:$code ERR:$err RESP:$response\n", FILE_APPEND);
            $msg = 'STK push request failed: HTTP ' . $code;
            if ($response) { $msg .= ' Body: ' . $response; }
            if ($err) { $msg .= ' CurlErr: ' . $err; }
            throw new RuntimeException($msg);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid STK push response');
        }

        return $data;
    }
}

?>
