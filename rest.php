<?php
declare(strict_types=1);


function dev_send_bitrix_lead(string $subject, array $payload): bool
{
    $cfg = dev_app_config();
    $crm = $cfg['crm'] ?? [];
    if (empty($crm['host']) || empty($crm['path'])) {
        dev_log_runtime('CRM skipped: missing config');
        return false;
    }

    $postData = [
        'TITLE' => $subject,
        'NAME' => (string)($payload['name'] ?? ''),
        'PHONE_WORK' => (string)($payload['phone'] ?? ''),
        'EMAIL_WORK' => (string)($payload['email'] ?? ''),
        'COMMENTS' => (string)($payload['comments'] ?? ''),
        'PRODUCT_ID' => (string)($payload['id_product'] ?? ''),
        'LOGIN' => (string)($crm['login'] ?? ''),
        'PASSWORD' => (string)($crm['password'] ?? ''),
    ];

    $fp = @fsockopen('ssl://' . $crm['host'], (int)($crm['port'] ?? 443), $errno, $errstr, 30);
    if (!$fp) {
        dev_log_runtime('CRM connection failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    $strPostData = http_build_query($postData);
    $str = "POST " . $crm['path'] . " HTTP/1.0\r\n";
    $str .= "Host: " . $crm['host'] . "\r\n";
    $str .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $str .= "Content-Length: " . strlen($strPostData) . "\r\n";
    $str .= "Connection: close\r\n\r\n";
    $str .= $strPostData;

    fwrite($fp, $str);
    $result = '';
    while (!feof($fp)) {
        $result .= fgets($fp, 128);
    }
    fclose($fp);

    return $result !== '';
}
