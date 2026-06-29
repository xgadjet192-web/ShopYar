<?php
error_reporting(0);
ini_set('display_errors', 0);

// =============================================
// db.php - اتصال به دیتابیس

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shop_db');

// ── اتصال PDO (استفاده شده در admin_products.php و admin_users.php) ──
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#ff4d6d;direction:rtl">خطا در اتصال به دیتابیس: ' . $e->getMessage() . '</div>');
}

// ── توابع کمکی ──

/**
 * فرمت‌بندی قیمت با جداکننده هزارگان
 */
function formatPrice($price) {
    return number_format($price) . ' تومان';
}

/**
 * برگرداندن label و class وضعیت سفارش
 */
function statusLabel($status) {
    $labels = [
        'pending'    => ['label' => 'در انتظار',        'class' => 'status-pending'],
        'processing' => ['label' => 'در حال پردازش',    'class' => 'status-processing'],
        'shipped'    => ['label' => 'ارسال شده',         'class' => 'status-shipped'],
        'delivered'  => ['label' => 'تحویل داده شده',   'class' => 'status-delivered'],
        'cancelled'  => ['label' => 'لغو شده',           'class' => 'status-cancelled'],
    ];
    return $labels[$status] ?? ['label' => $status, 'class' => ''];
}

/**
 * خروجی JSON و پایان اجرا
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * بررسی لاگین ادمین - اگه لاگین نباشه به login.php ریدایرکت می‌کنه
 */
function requireLogin() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * پاک‌سازی و escape ورودی کاربر
 * (برای نمایش در HTML؛ برای query از PDO prepared statements استفاده کن)
 */
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * تولید پیام flash و ذخیره در session
 */
function setFlash($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
}

/**
 * دریافت و پاک کردن پیام flash از session
 */
function getFlash() {
    if (!empty($_SESSION['flash_message'])) {
        $msg  = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $msg, 'type' => $type];
    }
    return null;
}

// ── شروع session (اگه قبلاً شروع نشده) ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + (int)($days / 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
    return array($jy, $jm, $jd);
}
function jdate($datetime, $format = 'Y/m/d') {
    $ts = strtotime($datetime);
    if (!$ts) return '—';
    $gy=(int)date('Y',$ts); $gm=(int)date('n',$ts); $gd=(int)date('j',$ts);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy,$gm,$gd);
    $time = date('H:i',$ts);
    return str_replace(['Y','m','d','H:i'], [$jy, sprintf('%02d',$jm), sprintf('%02d',$jd), $time], $format);
}