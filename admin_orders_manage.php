<?php
error_reporting(0);
ini_set('display_errors',0);
require_once 'db.php';
requireLogin();

$action  = $_REQUEST['action'] ?? '';
$message = '';
$msgType = '';

switch ($action) {
    case 'delete':
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt->execute([$order_id])) { $message="سفارش #{$order_id} با موفقیت حذف شد."; $msgType='success'; }
            else { $message="خطا در حذف سفارش."; $msgType='error'; }
        }
        header("Location: admin_orders_manage.php?msg=".urlencode($message)."&type=".$msgType); exit;

    case 'edit':
        $order_id=(int)($_POST['order_id']??0);
        $status=$_POST['status']??''; $address=$_POST['address']??''; $note=$_POST['note']??'';
        $allowed=['pending','processing','shipped','delivered','cancelled'];
        if ($order_id>0 && in_array($status,$allowed)) {
            $stmt=$pdo->prepare("UPDATE orders SET status=?,address=?,note=? WHERE id=?");
            if ($stmt->execute([$status,$address,$note,$order_id])) { $message="سفارش #{$order_id} ویرایش شد."; $msgType='success'; }
            else { $message="خطا در ویرایش."; $msgType='error'; }
        }
        header("Location: admin_orders_manage.php?msg=".urlencode($message)."&type=".$msgType); exit;

    case 'change_status':
        $order_id=(int)($_POST['order_id']??0);
        $status=$_POST['status']??'';
        $allowed=['pending','processing','shipped','delivered','cancelled'];
        if ($order_id>0 && in_array($status,$allowed)) {
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$order_id]);
            echo json_encode(['success'=>true]);
        } else { echo json_encode(['success'=>false]); }
        exit;

    case 'detail':
        $order_id=(int)($_GET['order_id']??0);
        if ($order_id>0) {
            $stmt=$pdo->prepare("SELECT o.*, u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=?");
            $stmt->execute([$order_id]);
            $order=$stmt->fetch();
            if ($order) { $order['items']=[]; }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>(bool)$order,'order'=>$order],JSON_UNESCAPED_UNICODE);
        } else { echo json_encode(['success'=>false]); }
        exit;

    case 'create_order':
        header('Content-Type: application/json; charset=utf-8');
        $user_id   = (int)($_POST['user_id'] ?? 0);
        $address   = trim($_POST['address'] ?? '');
        $city      = trim($_POST['city'] ?? '');
        $province  = trim($_POST['province'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $payment   = trim($_POST['payment_method'] ?? '');
        $discount  = (float)($_POST['discount_percent'] ?? 0);
        $items     = $_POST['items'] ?? [];

        if ($user_id <= 0 || empty($items)) {
            echo json_encode(['success' => false, 'msg' => 'اطلاعات ناقص است']);
            exit;
        }

        $total = 0;
        $validItems = [];
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $p = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
            $p->execute([$pid]);
            $prod = $p->fetch();
            if (!$prod || $prod['stock'] < $qty) continue;
            $unitPrice = $prod['price'];
            $total += $unitPrice * $qty;
            $validItems[] = ['product_id' => $pid, 'qty' => $qty, 'unit_price' => $unitPrice, 'name' => $prod['name']];
        }

        if (empty($validItems)) {
            echo json_encode(['success' => false, 'msg' => 'محصول معتبری انتخاب نشده یا موجودی کافی نیست']);
            exit;
        }

        if ($discount > 0 && $discount <= 100) {
            $total = $total * (1 - $discount / 100);
        }

        // ترکیب استان + شهر + آدرس دقیق در یک رشته‌ی نهایی
        $location     = trim(($province ?: '') . ($province && $city ? ' / ' : '') . ($city ?: ''));
        $full_address = $location ? $location . ' — ' . $address : $address;
        $full_note    = $note . ($payment ? ' | روش پرداخت: ' . $payment : '');

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO orders (user_id, total_price, status, address, note, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())");
            $ins->execute([$user_id, (int)$total, $full_address, $full_note]);
            $order_id = $pdo->lastInsertId();

            foreach ($validItems as $vi) {
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$vi['qty'], $vi['product_id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'order_id' => $order_id, 'total' => $total]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'msg' => 'خطای سرور: ' . $e->getMessage()]);
        }
        exit;

    case 'get_products':
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($_GET['q'] ?? '');
        $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE name LIKE ? AND stock > 0 LIMIT 20");
        $stmt->execute(["%$q%"]);
        echo json_encode(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;

    case 'get_users':
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($_GET['q'] ?? '');
        $stmt = $pdo->prepare("SELECT id, full_name, email, phone FROM users WHERE full_name LIKE ? OR email LIKE ? LIMIT 15");
        $stmt->execute(["%$q%", "%$q%"]);
        echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
}

if (!$message && isset($_GET['msg'])) { $message=$_GET['msg']; $msgType=$_GET['type']??'info'; }

$filterStatus = $_GET['status']  ?? '';
$filterSearch = $_GET['search']  ?? '';
$page         = max(1,(int)($_GET['page']??1));
$perPage      = 8;
$offset       = ($page-1)*$perPage;

$where='WHERE 1=1'; $params=[];
if ($filterStatus) { $where.=" AND o.status=?"; $params[]=$filterStatus; }
if ($filterSearch) {
    $where.=" AND (u.full_name LIKE ? OR u.email LIKE ? OR o.id=?)";
    $like="%{$filterSearch}%"; $params[]=$like; $params[]=$like; $params[]=(int)$filterSearch;
}

$countStmt=$pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id=u.id $where");
$countStmt->execute($params);
$totalRows=$countStmt->fetchColumn();
$totalPages=max(1,ceil($totalRows/$perPage));

$listParams=array_merge($params,[$perPage,$offset]);
$stmt=$pdo->prepare("SELECT o.id,o.total_price,o.status,o.address,o.note,o.created_at, u.full_name AS user_name,u.email AS user_email FROM orders o JOIN users u ON o.user_id=u.id $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute($listParams);
$orders=$stmt->fetchAll();

$stats=$pdo->query("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='delivered') AS delivered, COALESCE(SUM(total_price),0) AS revenue FROM orders")->fetch();

// ── داده نمودار ماهانه (۶ ماه اخیر) ──
$monthlyRows = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at,'%Y-%m') AS ym,
        COUNT(*) AS cnt,
        COALESCE(SUM(total_price),0) AS rev
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
")->fetchAll();

$monthNamesFA = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
$chartMonthlyLabels  = [];
$chartMonthlyOrders  = [];
$chartMonthlyRevenue = [];

$mLookup = [];
foreach ($monthlyRows as $r) { $mLookup[$r['ym']] = $r; }

for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("-$i month");
    $ym  = date('Y-m', $ts);
    $mon = (int)date('n', $ts);
    $chartMonthlyLabels[]  = $monthNamesFA[$mon - 1];
    $chartMonthlyOrders[]  = isset($mLookup[$ym]) ? (int)$mLookup[$ym]['cnt'] : 0;
    $chartMonthlyRevenue[] = isset($mLookup[$ym]) ? round((float)$mLookup[$ym]['rev'] / 1000000, 1) : 0;
}

// ── داده نمودار هفتگی (۷ روز اخیر) ──
$weeklyRows = $pdo->query("
    SELECT 
        DATE(created_at) AS day,
        COUNT(*) AS cnt,
        COALESCE(SUM(total_price),0) AS rev
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY day
    ORDER BY day ASC
")->fetchAll();

$faMap = [0=>'یکشنبه',1=>'دوشنبه',2=>'سه‌شنبه',3=>'چهارشنبه',4=>'پنجشنبه',5=>'جمعه',6=>'شنبه'];
$chartWeeklyLabels  = [];
$chartWeeklyOrders  = [];
$chartWeeklyRevenue = [];

$wLookup = [];
foreach ($weeklyRows as $r) { $wLookup[$r['day']] = $r; }

for ($i = 6; $i >= 0; $i--) {
    $dt  = date('Y-m-d', strtotime("-$i day"));
    $dow = (int)date('w', strtotime($dt));
    $chartWeeklyLabels[]  = $faMap[$dow];
    $chartWeeklyOrders[]  = isset($wLookup[$dt]) ? (int)$wLookup[$dt]['cnt'] : 0;
    $chartWeeklyRevenue[] = isset($wLookup[$dt]) ? round((float)$wLookup[$dt]['rev'] / 1000000, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>مدیریت سفارش‌ها | ShopAdmin</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.chart-card {
  background: linear-gradient(145deg, #ffffff, #f8fffd);
  border-radius: 24px;
  border: 1px solid rgba(0,201,177,0.15);
  padding: 1.75rem;
  box-shadow: 0 8px 40px rgba(0,201,177,0.08), 0 2px 8px rgba(0,0,0,0.04);
  position: relative;
  overflow: hidden;
}
.chart-card::before {
  content:'';
  position:absolute;
  top:-60px; right:-60px;
  width:200px; height:200px;
  background: radial-gradient(circle, rgba(0,201,177,0.08) 0%, transparent 70%);
  pointer-events:none;
}
.chart-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.chart-card-title { display:flex; align-items:center; gap:0.5rem; font-weight:800; font-size:1rem; color:var(--text-dark); }
.chart-tabs { display:flex; gap:0.35rem; background:rgba(0,201,177,0.08); border-radius:30px; padding:4px; border:1px solid rgba(0,201,177,0.12); }
.chart-tab { padding:6px 18px; border-radius:24px; border:none; cursor:pointer; font-size:0.78rem; font-weight:700; font-family:inherit; color:var(--text-light); background:transparent; transition:all .25s; }
.chart-tab.active { background:linear-gradient(135deg,#00c9b1,#00a3ff); color:#fff; box-shadow:0 4px 12px rgba(0,201,177,0.35); }
.chart-wrap { position:relative; height:280px; }
.chart-stats-row { display:flex; gap:1.5rem; margin-bottom:1rem; }
.chart-mini-stat { display:flex; align-items:center; gap:0.5rem; }
.chart-mini-dot { width:12px; height:12px; border-radius:4px; }
.chart-mini-label { font-size:0.72rem; color:var(--text-light); font-weight:600; }
.chart-mini-val { font-size:0.82rem; font-weight:800; color:var(--text-dark); }
/* ── New Order Modal ── */
.no-pay-opt input:checked + .no-pay-box {
  background: linear-gradient(135deg,#00c9b1,#00a3ff);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(0,201,177,0.3);
}
.no-pay-box {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border-glass);
  text-align: center;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  color: var(--text-dark);
  user-select: none;
}
.no-pay-box:hover { border-color: #00c9b1; color: #00c9b1; }
#new-order-steps .no-step { transition: background .25s, color .25s; }
/* ── Province / City selects (added) ── */
.no-loc-row { display:flex; gap:0.75rem; }
.no-loc-row .form-group { flex:1; margin:0; }
#no-city:disabled { background:rgba(0,0,0,0.03); cursor:not-allowed; color:var(--text-light); }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round">
        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <path d="M16 10a4 4 0 01-8 0"/>
      </svg>
    </div>
    <div class="logo-text">ShopAdmin <span>پنل مدیریت</span></div>
  </div>

  <div class="sidebar-admin">
    <div class="sidebar-admin-avatar">م</div>
    <div class="sidebar-admin-info">
      <div class="sidebar-admin-name">مدیر سیستم</div>
      <span class="sidebar-admin-role">ادمین</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">منوی اصلی</div>

    <a href="admin_orders_manage.php" class="nav-item active">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12"/>
        </svg>
      </span>
      سفارش‌ها
      <span class="nav-badge"><?= $stats['total'] ?></span>
    </a>

    <a href="admin_products.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="2" y="3" width="20" height="14" rx="2"/>
          <path d="M8 21h8M12 17v4"/>
        </svg>
      </span>
      محصولات
    </a>

    <a href="admin_users.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </span>
      کاربران
    </a>

    <div class="nav-label" style="margin-top:1rem">سیستم</div>

    <a href="admin_tickets.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
        </svg>
      </span>
      تیکت‌ها
    </a>

    <a href="logout.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
      </span>
      خروج
    </a>
  </nav>
</aside>

<div class="main-wrapper">

  <header class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">مدیریت <span>سفارش‌ها</span></div>
      <div class="breadcrumb">ShopAdmin › سفارش‌ها › لیست کامل</div>
    </div>
    <div class="topbar-right">
      <div class="topbar-clock" id="clock">--:--</div>
      <div class="topbar-notif">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </header>

  <div class="welcome-banner">
    <div class="banner-blob1"></div>
    <div class="banner-blob2"></div>
    <div class="banner-content">
      <div class="banner-greeting">سلام، مدیر سیستم 👋</div>
      <div class="banner-title">پنل مدیریت سفارش‌ها</div>
      <div class="banner-subtitle">کنترل کامل سفارش‌ها، ویرایش وضعیت و مشاهده آمار لحظه‌ای</div>
    </div>
    <div class="banner-stats">
      <div class="banner-stat">
        <span class="banner-stat-val"><?= number_format($stats['total']) ?></span>
        <span class="banner-stat-label">کل سفارش</span>
      </div>
      <div class="banner-divider"></div>
      <div class="banner-stat">
        <span class="banner-stat-val"><?= number_format($stats['pending']) ?></span>
        <span class="banner-stat-label">در انتظار</span>
      </div>
      <div class="banner-divider"></div>
      <div class="banner-stat">
        <span class="banner-stat-val"><?= number_format(($stats['revenue']??0)/1000000,1) ?>M</span>
        <span class="banner-stat-label">درآمد (تومان)</span>
      </div>
    </div>
    <div class="banner-illus">
      <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 35h60M25 35L20 20h60l-5 15M25 35l5 40h40l5-40" stroke="white" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="38" cy="82" r="5" fill="white"/>
        <circle cx="62" cy="82" r="5" fill="white"/>
      </svg>
    </div>
  </div>

  <main class="content">

    <div class="stats-grid">
      <div class="stat-card" style="animation-delay:0s">
        <div class="stat-card-top">
          <div class="stat-icon-wrap teal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12"/>
            </svg>
          </div>
          <span class="stat-change up">↑ ۱۲٪</span>
        </div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">کل سفارش‌ها</div>
        <div class="stat-bar-wrap">
          <div class="stat-bar-label"><span>پیشرفت ماه</span><span>۷۸٪</span></div>
          <div class="stat-bar"><div class="stat-bar-fill" style="width:78%"></div></div>
        </div>
      </div>

      <div class="stat-card" style="animation-delay:0.08s">
        <div class="stat-card-top">
          <div class="stat-icon-wrap yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <span class="stat-change down">↓ ۳٪</span>
        </div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label">در انتظار بررسی</div>
        <div class="stat-bar-wrap">
          <div class="stat-bar-label"><span>از کل</span><span><?= $stats['total']>0 ? round($stats['pending']/$stats['total']*100) : 0 ?>٪</span></div>
          <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $stats['total']>0 ? round($stats['pending']/$stats['total']*100) : 0 ?>%;background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div></div>
        </div>
      </div>

      <div class="stat-card" style="animation-delay:0.16s">
        <div class="stat-card-top">
          <div class="stat-icon-wrap green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>
          <span class="stat-change up">↑ ۸٪</span>
        </div>
        <div class="stat-value"><?= $stats['delivered'] ?></div>
        <div class="stat-label">تحویل داده شده</div>
        <div class="stat-bar-wrap">
          <div class="stat-bar-label"><span>موفقیت</span><span><?= $stats['total']>0 ? round($stats['delivered']/$stats['total']*100) : 0 ?>٪</span></div>
          <div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $stats['total']>0 ? round($stats['delivered']/$stats['total']*100) : 0 ?>%;background:linear-gradient(90deg,#22c55e,#4ade80)"></div></div>
        </div>
      </div>

      <div class="stat-card" style="animation-delay:0.24s">
        <div class="stat-card-top">
          <div class="stat-icon-wrap purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
          </div>
          <span class="stat-change up">↑ ۲۱٪</span>
        </div>
        <div class="stat-value" style="font-size:1.4rem"><?= number_format(($stats['revenue']??0)/1000000,1) ?>M</div>
        <div class="stat-label">درآمد کل (تومان)</div>
        <div class="stat-bar-wrap">
          <div class="stat-bar-label"><span>هدف ماهانه</span><span>۶۵٪</span></div>
          <div class="stat-bar"><div class="stat-bar-fill" style="width:65%;background:linear-gradient(90deg,#a855f7,#c084fc)"></div></div>
        </div>
      </div>
    </div>

    <!-- ══ CHART ══ -->
    <div class="two-col">
      <div class="chart-card">
        <div class="chart-card-header">
          <div>
            <div class="chart-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="url(#chartGrad)" stroke-width="2.5" stroke-linecap="round" width="20" height="20">
                <defs><linearGradient id="chartGrad" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#00c9b1"/><stop offset="100%" stop-color="#a855f7"/></linearGradient></defs>
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              نمودار سفارش‌ها
            </div>
            <div style="font-size:0.72rem;color:var(--text-light);margin-top:2px">آمار واقعی از دیتابیس</div>
          </div>
          <div class="chart-tabs">
            <button class="chart-tab active" onclick="switchChart('monthly',this)">ماهانه</button>
            <button class="chart-tab" onclick="switchChart('weekly',this)">هفتگی</button>
          </div>
        </div>
        <div class="chart-stats-row" id="chart-stats-row"></div>
        <div class="chart-wrap">
          <canvas id="ordersChart"></canvas>
        </div>
      </div>

      <div class="activity-card">
        <div class="activity-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" stroke-linecap="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          آخرین فعالیت‌ها
        </div>
        <div class="activity-list">
          <?php foreach(array_slice($orders,0,5) as $i=>$o):
            $colors=['teal','yellow','blue','teal','red'];
            $acts=['سفارش جدید ثبت شد','وضعیت تغییر کرد','پرداخت تأیید شد','ارسال شد','لغو شد'];
          ?>
          <div class="activity-item" style="animation-delay:<?=$i*0.06?>s">
            <div class="activity-dot-wrap">
              <div class="activity-dot <?=$colors[$i%5]?>"></div>
              <?php if($i<4): ?><div class="activity-line" style="min-height:24px"></div><?php endif; ?>
            </div>
            <div class="activity-body">
              <div class="activity-text"><?=$acts[$i%5]?> — <strong>#<?=$o['id']?></strong></div>
              <div class="activity-time"><?= jdate($o['created_at'],'Y/m/d H:i') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Ticketing -->
    <div class="ticket-card">
      <div class="ticket-header">
        <div class="ticket-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
          </svg>
          سیستم تیکتینگ
        </div>
        <span style="font-size:0.72rem;color:var(--text-light)">یادداشت‌ها و وظایف ادمین</span>
      </div>
      <div class="ticket-form">
        <div class="ticket-form-row">
          <div class="form-group" style="margin:0">
            <label class="form-label">موضوع تیکت</label>
            <input id="tk-subject" class="form-control" placeholder="مثال: بررسی سفارش معوق..." onkeydown="if(event.key==='Enter')addTicket()">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">اولویت</label>
            <select id="tk-priority" class="form-control">
              <option value="high">🔴 فوری</option>
              <option value="medium" selected>🟡 متوسط</option>
              <option value="low">🟢 عادی</option>
            </select>
          </div>
          <button class="btn btn-primary" onclick="addTicket()" style="height:fit-content;align-self:end;padding:0.6rem 1.25rem">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            افزودن
          </button>
        </div>
      </div>
      <div class="ticket-list" id="ticket-list"></div>
    </div>

    <!-- Orders Table -->
    <div class="glass-card">
      <div class="card-header">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
          لیست سفارش‌ها
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem">
          <span style="font-size:0.75rem;color:var(--text-light);background:rgba(0,201,177,0.08);padding:4px 12px;border-radius:20px;border:1px solid var(--border-glass)"><?= $totalRows ?> سفارش</span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-order');noReset()" style="display:flex;align-items:center;gap:5px;padding:6px 14px;font-size:0.8rem">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" width="13" height="13"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            سفارش جدید
          </button>
        </div>
      </div>

      <div class="filters-bar">
        <div class="search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="filter-search" class="filter-input" placeholder="جستجو نام، ایمیل یا شماره..." value="<?= htmlspecialchars($filterSearch) ?>" style="min-width:220px">
        </div>
        <select id="filter-status" class="filter-select">
          <option value="" <?= !$filterStatus?'selected':'' ?>>همه وضعیت‌ها</option>
          <option value="pending"    <?= $filterStatus==='pending'?'selected':'' ?>>در انتظار</option>
          <option value="processing" <?= $filterStatus==='processing'?'selected':'' ?>>در حال پردازش</option>
          <option value="shipped"    <?= $filterStatus==='shipped'?'selected':'' ?>>ارسال شده</option>
          <option value="delivered"  <?= $filterStatus==='delivered'?'selected':'' ?>>تحویل داده شده</option>
          <option value="cancelled"  <?= $filterStatus==='cancelled'?'selected':'' ?>>لغو شده</option>
        </select>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>مشتری</th>
              <th>مبلغ</th>
              <th>وضعیت</th>
              <th>تاریخ ثبت</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="6">
              <div class="empty-state">
                <div style="font-size:3rem;margin-bottom:1rem">📭</div>
                <div class="empty-title">سفارشی یافت نشد</div>
                <div class="empty-sub">فیلترها را تغییر دهید</div>
              </div>
            </td></tr>
          <?php else: foreach($orders as $i=>$order):
            $sl=['pending'=>['در انتظار','status-pending'],'processing'=>['در حال پردازش','status-processing'],'shipped'=>['ارسال شده','status-shipped'],'delivered'=>['تحویل داده شده','status-delivered'],'cancelled'=>['لغو شده','status-cancelled']];
            $s=$sl[$order['status']]??[$order['status'],''];
            $init=mb_substr($order['user_name']??'؟',0,1,'UTF-8');
          ?>
            <tr style="animation-delay:<?=$i*0.04?>s">
              <td><span class="order-id">#<?=$order['id']?></span></td>
              <td>
                <div class="user-cell">
                  <div class="avatar"><?=$init?></div>
                  <div>
                    <div class="user-name"><?=htmlspecialchars($order['user_name']??'')?></div>
                    <div class="user-email"><?=htmlspecialchars($order['user_email']??'')?></div>
                  </div>
                </div>
              </td>
              <td><span class="price-cell"><?=number_format($order['total_price'])?> <span class="price-unit">تومان</span></span></td>
              <td><span class="status-badge <?=$s[1]?>"><?=$s[0]?></span></td>
              <td><?= jdate($order['created_at']) ?></td>
              <td>
                <div class="actions-cell">
                  <button class="btn btn-ghost btn-sm" onclick="openDetail(<?=$order['id']?>)" title="جزئیات">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                  <button class="btn btn-ghost btn-sm" onclick="openEdit({id:<?=$order['id']?>,status:'<?=$order['status']?>',address:`<?=addslashes($order['address']??'')?>`,note:`<?=addslashes($order['note']??'')?>`})" title="ویرایش">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?=$order['id']?>)" title="حذف">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages>1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="?page=<?=$page-1?>&status=<?=$filterStatus?>&search=<?=urlencode($filterSearch)?>" class="page-btn">‹</a><?php endif; ?>
        <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
        <a href="?page=<?=$p?>&status=<?=$filterStatus?>&search=<?=urlencode($filterSearch)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&status=<?=$filterStatus?>&search=<?=urlencode($filterSearch)?>" class="page-btn">›</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">ویرایش سفارش <span id="edit-order-num" style="color:var(--teal-500)"></span></div>
      <button class="modal-close" onclick="closeModal('modal-edit')">✕</button>
    </div>
    <form method="POST" action="admin_orders_manage.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="order_id" id="edit-order-id">
      <div class="form-group">
        <label class="form-label">وضعیت سفارش</label>
        <select name="status" id="edit-status" class="form-control">
          <option value="pending">در انتظار</option>
          <option value="processing">در حال پردازش</option>
          <option value="shipped">ارسال شده</option>
          <option value="delivered">تحویل داده شده</option>
          <option value="cancelled">لغو شده</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">آدرس تحویل</label>
        <textarea name="address" id="edit-address" class="form-control" rows="3" placeholder="آدرس کامل..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">یادداشت</label>
        <textarea name="note" id="edit-note" class="form-control" rows="2" placeholder="یادداشت اختیاری..."></textarea>
      </div>
      <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit')">انصراف</button>
        <button type="submit" class="btn btn-primary">💾 ذخیره تغییرات</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-confirm-delete">
  <div class="modal" style="max-width:400px">
    <div class="confirm-box">
      <div class="confirm-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
      </div>
      <div class="confirm-msg">حذف سفارش <span id="delete-order-num" style="color:var(--teal-500)"></span></div>
      <div class="confirm-sub">این عملیات برگشت‌پذیر نیست. آیا مطمئن هستید؟</div>
      <div class="confirm-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-confirm-delete')">انصراف</button>
        <button class="btn btn-danger" id="btn-confirm-delete">بله، حذف کن</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-detail">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">جزئیات سفارش</div>
      <button class="modal-close" onclick="closeModal('modal-detail')">✕</button>
    </div>
    <div id="detail-body"><div style="text-align:center;padding:2rem;color:var(--text-light)">در حال بارگذاری...</div></div>
  </div>
</div>

<!-- MODAL: NEW ORDER -->
<div class="modal-overlay" id="modal-new-order">
  <div class="modal" style="max-width:680px;max-height:90vh;overflow-y:auto">
    <div class="modal-header">
      <div class="modal-title">➕ ثبت سفارش جدید</div>
      <button class="modal-close" onclick="closeModal('modal-new-order')">✕</button>
    </div>

    <!-- Step indicator -->
    <div id="new-order-steps" style="display:flex;gap:0;margin-bottom:1.5rem;border-radius:12px;overflow:hidden;border:1px solid var(--border-glass)">
      <div class="no-step" data-step="1" style="flex:1;padding:10px;text-align:center;font-size:0.78rem;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#00c9b1,#00a3ff);color:#fff">۱ · مشتری</div>
      <div class="no-step" data-step="2" style="flex:1;padding:10px;text-align:center;font-size:0.78rem;font-weight:700;cursor:pointer;color:var(--text-light)">۲ · محصولات</div>
      <div class="no-step" data-step="3" style="flex:1;padding:10px;text-align:center;font-size:0.78rem;font-weight:700;cursor:pointer;color:var(--text-light)">۳ · پرداخت و تأیید</div>
    </div>

    <!-- STEP 1: Customer -->
    <div id="no-step-1" class="no-step-content">
      <div class="form-group">
        <label class="form-label">جستجوی مشتری</label>
        <input id="no-user-search" class="form-control" placeholder="نام یا ایمیل..." autocomplete="off" oninput="searchUsers(this.value)">
        <div id="no-user-results" style="display:none;border:1px solid var(--border-glass);border-radius:12px;margin-top:6px;background:#fff;max-height:180px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.08);position:relative;z-index:10"></div>
      </div>
      <div id="no-user-selected" style="display:none;padding:14px;background:rgba(0,201,177,0.07);border-radius:12px;border:1px solid rgba(0,201,177,0.2);margin-bottom:1rem">
        <div style="font-weight:700;color:var(--text-dark)" id="no-user-name"></div>
        <div style="font-size:0.78rem;color:var(--text-light)" id="no-user-email"></div>
      </div>
      <div class="form-group">
        <label class="form-label">آدرس دقیق (خیابان، پلاک، واحد...)</label>
        <input id="no-address" class="form-control" placeholder="آدرس کامل...">
      </div>

      <!-- ▼▼ بخش جدید: انتخاب استان و شهر به سبک ووکامرس ▼▼ -->
      <div class="no-loc-row">
        <div class="form-group">
          <label class="form-label">استان</label>
          <select id="no-province" class="form-control" onchange="noPopulateCities()">
            <option value="">انتخاب استان...</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">شهر</label>
          <select id="no-city" class="form-control" disabled>
            <option value="">ابتدا استان را انتخاب کنید</option>
          </select>
        </div>
      </div>
      <!-- ▲▲ پایان بخش جدید ▲▲ -->

      <div style="display:flex;justify-content:flex-end;margin-top:1rem">
        <button class="btn btn-primary" onclick="noGoStep(2)">بعدی ◄</button>
      </div>
    </div>

    <!-- STEP 2: Products -->
    <div id="no-step-2" class="no-step-content" style="display:none">
      <div class="form-group">
        <label class="form-label">جستجوی محصول</label>
        <input id="no-prod-search" class="form-control" placeholder="نام محصول..." autocomplete="off" oninput="searchProducts(this.value)">
        <div id="no-prod-results" style="display:none;border:1px solid var(--border-glass);border-radius:12px;margin-top:6px;background:#fff;max-height:200px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.08);position:relative;z-index:10"></div>
      </div>

      <div id="no-cart" style="margin-top:1rem"></div>

      <div id="no-cart-total" style="display:none;padding:12px 16px;background:linear-gradient(135deg,rgba(0,201,177,0.08),rgba(0,163,255,0.05));border-radius:12px;border:1px solid rgba(0,201,177,0.15);justify-content:space-between;align-items:center;margin-top:1rem">
        <span style="font-weight:700;color:var(--text-dark)">جمع کل:</span>
        <span id="no-cart-total-val" style="font-weight:800;font-size:1.1rem;color:#00c9b1"></span>
      </div>

      <div style="display:flex;justify-content:space-between;margin-top:1rem">
        <button class="btn btn-ghost" onclick="noGoStep(1)">◄ قبلی</button>
        <button class="btn btn-primary" onclick="noGoStep(3)">بعدی ◄</button>
      </div>
    </div>

    <!-- STEP 3: Payment & Confirm -->
    <div id="no-step-3" class="no-step-content" style="display:none">
      <div class="form-group">
        <label class="form-label">روش پرداخت</label>
        <div id="no-payment-opts" style="display:flex;gap:0.6rem;flex-wrap:wrap">
          <label class="no-pay-opt" style="flex:1;min-width:130px">
            <input type="radio" name="no_payment" value="کارت به کارت" style="display:none">
            <div class="no-pay-box">💳 کارت به کارت</div>
          </label>
          <label class="no-pay-opt" style="flex:1;min-width:130px">
            <input type="radio" name="no_payment" value="درگاه آنلاین" style="display:none">
            <div class="no-pay-box">🌐 درگاه آنلاین</div>
          </label>
          <label class="no-pay-opt" style="flex:1;min-width:130px">
            <input type="radio" name="no_payment" value="پرداخت اقساطی" style="display:none">
            <div class="no-pay-box">📅 اقساطی</div>
          </label>
          <label class="no-pay-opt" style="flex:1;min-width:130px">
            <input type="radio" name="no_payment" value="پرداخت در محل" style="display:none">
            <div class="no-pay-box">🏠 در محل</div>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">کد تخفیف (درصد) — اختیاری</label>
        <input id="no-discount" class="form-control" type="number" min="0" max="100" placeholder="مثال: 10" oninput="noRenderSummary()">
      </div>

      <div class="form-group">
        <label class="form-label">یادداشت</label>
        <textarea id="no-note" class="form-control" rows="2" placeholder="یادداشت اختیاری..."></textarea>
      </div>

      <!-- Summary -->
      <div id="no-summary" style="background:rgba(0,201,177,0.05);border:1px solid rgba(0,201,177,0.15);border-radius:14px;padding:16px;margin-bottom:1rem;font-size:0.85rem;line-height:2"></div>

      <div style="display:flex;justify-content:space-between;margin-top:1rem">
        <button class="btn btn-ghost" onclick="noGoStep(2)">◄ قبلی</button>
        <button class="btn btn-primary" onclick="noSubmitOrder()" id="btn-no-submit">✅ ثبت سفارش</button>
      </div>
    </div>

  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<!-- داده‌های PHP برای Chart.js -->
<script>
var chartData = {
  monthly: {
    labels: <?= json_encode($chartMonthlyLabels, JSON_UNESCAPED_UNICODE) ?>,
    orders:  <?= json_encode($chartMonthlyOrders) ?>,
    revenue: <?= json_encode($chartMonthlyRevenue) ?>
  },
  weekly: {
    labels: <?= json_encode($chartWeeklyLabels, JSON_UNESCAPED_UNICODE) ?>,
    orders:  <?= json_encode($chartWeeklyOrders) ?>,
    revenue: <?= json_encode($chartWeeklyRevenue) ?>
  }
};
</script>

<!-- ▼▼ داده‌ی کامل استان‌ها و شهرهای ایران (بخش جدید) ▼▼ -->
<script>
const IRAN_LOCATIONS = {
  "آذربایجان شرقی": ["تبریز","مرند","میانه","اهر","بناب","سراب","شبستر","ملکان","هریس","چاراویماق","آذرشهر","جلفا","خداآفرین","ورزقان","عجب‌شیر","اسکو","بستان‌آباد","کلیبر"],
  "آذربایجان غربی": ["ارومیه","خوی","بوکان","مهاباد","میاندوآب","سلماس","پیرانشهر","نقده","سردشت","تکاب","ماکو","شاهین‌دژ","چالدران","اشنویه","پلدشت","چایپاره"],
  "اردبیل": ["اردبیل","پارس‌آباد","مشگین‌شهر","خلخال","گرمی","بیله‌سوار","نمین","نیر","کوثر","سرعین"],
  "اصفهان": ["اصفهان","کاشان","نجف‌آباد","خمینی‌شهر","شاهین‌شهر","نطنز","گلپایگان","فلاورجان","مبارکه","زرین‌شهر","نائین","اردستان","خوانسار","تیران","چادگان","دهاقان","سمیرم","شهرضا","فریدن","فریدون‌شهر","آران و بیدگل","برخوار"],
  "البرز": ["کرج","نظرآباد","اشتهارد","هشتگرد","طالقان","فردیس","اسارا"],
  "ایلام": ["ایلام","دهلران","مهران","ایوان","آبدانان","دره‌شهر","چرداول","ملکشاهی","بدره","سیروان"],
  "بوشهر": ["بوشهر","برازجان","گناوه","کنگان","دیر","دیلم","جم","تنگستان","دشتی"],
  "تهران": ["تهران","اسلامشهر","شهریار","ملارد","ورامین","پاکدشت","پردیس","دماوند","رباط‌کریم","ری","شمیرانات","فیروزکوه","قدس","پیشوا","بهارستان","قرچک","چهاردانگه"],
  "چهارمحال و بختیاری": ["شهرکرد","بروجن","فارسان","لردگان","اردل","کیار","کوهرنگ","سامان","بن"],
  "خراسان جنوبی": ["بیرجند","قائنات","فردوس","نهبندان","سربیشه","درمیان","بشرویه","زیرکوه","خوسف"],
  "خراسان رضوی": ["مشهد","نیشابور","سبزوار","تربت‌حیدریه","کاشمر","قوچان","تربت‌جام","چناران","درگز","گناباد","فریمان","سرخس","بجستان","خواف","زبرخان","تایباد","مه‌ولات","جوین","جغتای","فیض‌آباد","رشتخوار","خلیل‌آباد","بردسکن","بینالود"],
  "خراسان شمالی": ["بجنورد","شیروان","اسفراین","گرمه","جاجرم","مانه و سملقان","رازوجرگلان"],
  "خوزستان": ["اهواز","آبادان","خرمشهر","دزفول","شوشتر","اندیمشک","بهبهان","ماهشهر","شوش","ایذه","باغ‌ملک","رامهرمز","هندیجان","امیدیه","لالی","مسجدسلیمان","گتوند","کارون","حمیدیه","اندیکا","شادگان","رامشیر","هویزه"],
  "زنجان": ["زنجان","ابهر","خدابنده","ماهنشان","طارم","ایجرود","خرمدره"],
  "سمنان": ["سمنان","شاهرود","دامغان","گرمسار","مهدی‌شهر","میامی","آرادان"],
  "سیستان و بلوچستان": ["زاهدان","زابل","ایرانشهر","چابهار","خاش","سراوان","نیک‌شهر","کنارک","میرجاوه","سرباز","زهک","هیرمند","قصرقند","مهرستان","فنوج"],
  "فارس": ["شیراز","مرودشت","جهرم","کازرون","فسا","لارستان","آباده","نی‌ریز","داراب","اقلید","لامرد","زرین‌دشت","ممسنی","خرمبید","سپیدان","فیروزآباد","استهبان","پاسارگاد","رستم","ارسنجان","خنج","مهر","گراش","قیروکارزین"],
  "قزوین": ["قزوین","البرز","تاکستان","آبیک","بوئین‌زهرا","آوج"],
  "قم": ["قم"],
  "کردستان": ["سنندج","سقز","مریوان","بانه","قروه","کامیاران","دیواندره","بیجار","دهگلان","سرواباد"],
  "کرمان": ["کرمان","رفسنجان","سیرجان","بم","جیرفت","کهنوج","زرند","شهربابک","بردسیر","راور","عنبرآباد","رودبار جنوب","منوجان","قلعه‌گنج","ریگان","فهرج","ارزوئیه","نرماشیر","انار"],
  "کرمانشاه": ["کرمانشاه","اسلام‌آباد غرب","هرسین","کنگاور","سنقر","پاوه","جوانرود","سرپل‌ذهاب","قصرشیرین","گیلانغرب","صحنه","دالاهو","ثلاث‌باباجانی"],
  "کهگیلویه و بویراحمد": ["یاسوج","گچساران","دهدشت","لیکک","چرام","باشت","بویراحمد","کهگیلویه"],
  "گلستان": ["گرگان","گنبدکاووس","علی‌آباد کتول","آق‌قلا","کردکوی","بندرترکمن","مینودشت","کلاله","آزادشهر","رامیان","مراوه‌تپه","گالیکش"],
  "گیلان": ["رشت","بندرانزلی","لاهیجان","لنگرود","آستارا","تالش","رودسر","آستانه‌اشرفیه","صومعه‌سرا","فومن","شفت","رودبار","رضوانشهر","ماسال","سیاهکل","خمام","املش"],
  "لرستان": ["خرم‌آباد","بروجرد","دورود","کوهدشت","الیگودرز","ازنا","الشتر","پلدختر","رومشکان","چگنی","دلفان"],
  "مازندران": ["ساری","بابل","آمل","قائمشهر","بهشهر","نوشهر","چالوس","تنکابن","رامسر","نکا","جویبار","بابلسر","محمودآباد","نور","فریدونکنار","سوادکوه","کلاردشت","عباس‌آباد","گلوگاه","میاندورود"],
  "مرکزی": ["اراک","ساوه","خمین","محلات","دلیجان","شازند","تفرش","آشتیان","کمیجان","زرندیه","فرمهین"],
  "هرمزگان": ["بندرعباس","قشم","میناب","بندرلنگه","کیش","جاسک","رودان","حاجی‌آباد","بستک","پارسیان","خمیر","بشاگرد","ابوموسی"],
  "همدان": ["همدان","ملایر","نهاوند","تویسرکان","کبودرآهنگ","اسدآباد","بهار","رزن","فامنین"],
  "یزد": ["یزد","میبد","اردکان","ابرکوه","بافق","تفت","خاتم","مهریز","زارچ","بهاباد"]
};
</script>
<!-- ▲▲ پایان داده استان‌ها و شهرها ▲▲ -->

<script src="js/main.js"></script>
<script>
// ── Clock ──
(function(){
  function tick(){
    const now=new Date();
    const el=document.getElementById('clock');
    if(el) el.textContent=now.toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  tick(); setInterval(tick,1000);
})();

// ── Chart.js ──
var ordersChart;
var currentMode = 'monthly';

function updateMiniStats(d) {
  const totalOrders = d.orders.reduce((a,b)=>a+b, 0);
  const totalRevenue = d.revenue.reduce((a,b)=>a+b, 0).toFixed(1);
  const maxOrders = Math.max(...d.orders);
  const row = document.getElementById('chart-stats-row');
  if (!row) return;
  row.innerHTML = `
    <div class="chart-mini-stat">
      <div class="chart-mini-dot" style="background:linear-gradient(135deg,#00c9b1,#00a3ff)"></div>
      <div>
        <div class="chart-mini-label">کل سفارش</div>
        <div class="chart-mini-val">${totalOrders}</div>
      </div>
    </div>
    <div class="chart-mini-stat">
      <div class="chart-mini-dot" style="background:linear-gradient(135deg,#a855f7,#ec4899)"></div>
      <div>
        <div class="chart-mini-label">کل درآمد</div>
        <div class="chart-mini-val">${totalRevenue}M تومان</div>
      </div>
    </div>
    <div class="chart-mini-stat">
      <div class="chart-mini-dot" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"></div>
      <div>
        <div class="chart-mini-label">بیشترین سفارش</div>
        <div class="chart-mini-val">${maxOrders}</div>
      </div>
    </div>
  `;
}

function buildChart(mode) {
  currentMode = mode;
  const d = chartData[mode];
  const canvas = document.getElementById('ordersChart');
  const ctx = canvas.getContext('2d');
  const h = canvas.offsetHeight || 280;
  const gBar = ctx.createLinearGradient(0, 0, 0, h);
  gBar.addColorStop(0, 'rgba(0,201,177,0.9)');
  gBar.addColorStop(1, 'rgba(0,163,255,0.5)');
  const gBarHover = ctx.createLinearGradient(0, 0, 0, h);
  gBarHover.addColorStop(0, 'rgba(0,201,177,1)');
  gBarHover.addColorStop(1, 'rgba(0,163,255,0.8)');
  const gLine = ctx.createLinearGradient(0, 0, 0, h);
  gLine.addColorStop(0, 'rgba(168,85,247,0.4)');
  gLine.addColorStop(0.6, 'rgba(236,72,153,0.15)');
  gLine.addColorStop(1, 'rgba(168,85,247,0)');
  if (ordersChart) ordersChart.destroy();
  ordersChart = new Chart(ctx, {
    data: {
      labels: d.labels,
      datasets: [
        {
          type: 'bar',
          label: 'تعداد سفارش',
          data: d.orders,
          backgroundColor: gBar,
          hoverBackgroundColor: gBarHover,
          borderRadius: 10,
          borderSkipped: false,
          barPercentage: 0.55,
          categoryPercentage: 0.7,
          yAxisID: 'yOrders',
          order: 2
        },
        {
          type: 'line',
          label: 'درآمد (M تومان)',
          data: d.revenue,
          borderColor: '#a855f7',
          backgroundColor: gLine,
          borderWidth: 3,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#a855f7',
          pointBorderWidth: 2.5,
          pointRadius: 5,
          pointHoverRadius: 8,
          pointHoverBackgroundColor: '#a855f7',
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2,
          tension: 0.45,
          fill: true,
          yAxisID: 'yRevenue',
          order: 1
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeInOutQuart' },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          rtl: true,
          displayColors: true,
          bodyFont: { family: 'inherit', size: 12 },
          titleFont: { family: 'inherit', size: 13, weight: '700' },
          backgroundColor: 'rgba(15,23,42,0.92)',
          titleColor: '#e2e8f0',
          bodyColor: '#94a3b8',
          borderColor: 'rgba(0,201,177,0.3)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 12,
          callbacks: {
            title: function(items) { return ' ' + items[0].label; },
            label: function(item) {
              if (item.datasetIndex === 0) return '  سفارش: ' + item.raw + ' عدد';
              return '  درآمد: ' + item.raw + ' میلیون تومان';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(0,0,0,0.03)', drawTicks: false },
          border: { display: false },
          ticks: { font: { family: 'inherit', size: 11 }, color: '#94a3b8', padding: 8 }
        },
        yOrders: {
          type: 'linear',
          position: 'right',
          grid: { color: 'rgba(0,201,177,0.06)', drawTicks: false },
          border: { display: false, dash: [4,4] },
          ticks: {
            font: { family: 'inherit', size: 11 },
            color: '#00c9b1',
            padding: 8,
            stepSize: 1,
            callback: function(v) { return v % 1 === 0 ? v + ' سفارش' : ''; }
          }
        },
        yRevenue: {
          type: 'linear',
          position: 'left',
          grid: { display: false },
          border: { display: false },
          ticks: {
            font: { family: 'inherit', size: 11 },
            color: '#a855f7',
            padding: 8,
            callback: function(v) { return v + 'M'; }
          }
        }
      }
    }
  });
  updateMiniStats(d);
}

function switchChart(mode, btn) {
  document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const wrap = document.querySelector('.chart-wrap');
  wrap.style.opacity = '0';
  wrap.style.transition = 'opacity 0.2s';
  setTimeout(function() {
    buildChart(mode);
    wrap.style.opacity = '1';
  }, 200);
}

window.addEventListener('DOMContentLoaded', function() {
  buildChart('monthly');
  noPopulateProvinces(); // ◄ افزوده‌شده: پر کردن لیست استان‌ها هنگام بارگذاری صفحه
});

// ══════════════════════════════════════
// ── NEW ORDER MODAL ──
// ══════════════════════════════════════
var noState = { user: null, cart: [], step: 1 };
var noUserTimer = null;
var noProdTimer = null;

// ▼▼ توابع جدید: مدیریت کشویی استان/شهر ▼▼
function noPopulateProvinces() {
  var sel = document.getElementById('no-province');
  if (!sel) return;
  if (sel.options.length > 1) return; // قبلاً پر شده، دوباره اضافه نکن
  Object.keys(IRAN_LOCATIONS).forEach(function(p) {
    var opt = document.createElement('option');
    opt.value = p;
    opt.textContent = p;
    sel.appendChild(opt);
  });
}

function noPopulateCities() {
  var provSel = document.getElementById('no-province');
  var citySel = document.getElementById('no-city');
  if (!provSel || !citySel) return;
  var province = provSel.value;
  citySel.innerHTML = '';
  if (!province || !IRAN_LOCATIONS[province]) {
    citySel.innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
    citySel.disabled = true;
    return;
  }
  citySel.disabled = false;
  var placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = 'انتخاب شهر...';
  citySel.appendChild(placeholder);
  IRAN_LOCATIONS[province].forEach(function(c) {
    var opt = document.createElement('option');
    opt.value = c;
    opt.textContent = c;
    citySel.appendChild(opt);
  });
}
// ▲▲ پایان توابع جدید ▲▲

function noReset() {
  noState = { user: null, cart: [], step: 1 };
  document.getElementById('no-user-search').value = '';
  document.getElementById('no-user-results').style.display = 'none';
  document.getElementById('no-user-selected').style.display = 'none';
  document.getElementById('no-prod-search').value = '';
  document.getElementById('no-prod-results').style.display = 'none';
  document.getElementById('no-address').value = '';
  document.getElementById('no-city').value = '';
  document.getElementById('no-note').value = '';
  document.getElementById('no-discount').value = '';
  // ▼ افزوده‌شده: ریست استان و بازگرداندن شهر به حالت غیرفعال ▼
  var provSel = document.getElementById('no-province');
  if (provSel) provSel.value = '';
  noPopulateCities();
  // ▲ پایان افزوده ▲
  document.querySelectorAll('input[name="no_payment"]').forEach(function(r) {
    r.checked = false;
    r.nextElementSibling.style.background = '';
    r.nextElementSibling.style.color = '';
    r.nextElementSibling.style.borderColor = '';
    r.nextElementSibling.style.boxShadow = '';
  });
  noRenderCart();
  noGoStep(1, true);
}

function noGoStep(n, force) {
  if (!force) {
    if (n > 1 && !noState.user) { if(typeof showToast==='function') showToast('ابتدا مشتری انتخاب کنید','error'); return; }
    if (n > 2 && noState.cart.length === 0) { if(typeof showToast==='function') showToast('حداقل یک محصول اضافه کنید','error'); return; }
    if (n === 3) noRenderSummary();
  }
  noState.step = n;
  document.querySelectorAll('.no-step-content').forEach(function(el) { el.style.display = 'none'; });
  var sc = document.getElementById('no-step-' + n);
  if (sc) sc.style.display = 'block';
  document.querySelectorAll('#new-order-steps .no-step').forEach(function(el) {
    var active = parseInt(el.dataset.step) === n;
    el.style.background = active ? 'linear-gradient(135deg,#00c9b1,#00a3ff)' : '';
    el.style.color = active ? '#fff' : 'var(--text-light)';
  });
}

function searchUsers(q) {
  clearTimeout(noUserTimer);
  if (q.length < 1) { document.getElementById('no-user-results').style.display = 'none'; return; }
  noUserTimer = setTimeout(function() {
    fetch('admin_orders_manage.php?action=get_users&q=' + encodeURIComponent(q))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var box = document.getElementById('no-user-results');
        if (!data.users || !data.users.length) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        box.innerHTML = data.users.map(function(u) {
          var name = u.full_name || '';
          var email = u.email || '';
          var phone = u.phone || '';
          var init = name.substring(0, 1);
          return '<div onclick="noSelectUser(' + u.id + ',\'' + name.replace(/'/g, "\\'") + '\',\'' + email.replace(/'/g, "\\'") + '\',\'' + phone.replace(/'/g, "\\'") + '\')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border-glass);display:flex;align-items:center;gap:10px;transition:background .15s" onmouseover="this.style.background=\'rgba(0,201,177,0.06)\'" onmouseout="this.style.background=\'\'">'
            + '<div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#00c9b1,#00a3ff);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0">' + init + '</div>'
            + '<div><div style="font-weight:700;font-size:0.85rem">' + name + '</div><div style="font-size:0.72rem;color:var(--text-light)">' + email + '</div></div>'
            + '</div>';
        }).join('');
      }).catch(function() {});
  }, 300);
}

function noSelectUser(id, name, email, phone) {
  noState.user = { id: id, name: name, email: email, phone: phone };
  document.getElementById('no-user-results').style.display = 'none';
  document.getElementById('no-user-search').value = name;
  document.getElementById('no-user-name').textContent = name + (phone ? ' · ' + phone : '');
  document.getElementById('no-user-email').textContent = email;
  document.getElementById('no-user-selected').style.display = 'block';
}

function searchProducts(q) {
  clearTimeout(noProdTimer);
  if (q.length < 1) { document.getElementById('no-prod-results').style.display = 'none'; return; }
  noProdTimer = setTimeout(function() {
    fetch('admin_orders_manage.php?action=get_products&q=' + encodeURIComponent(q))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var box = document.getElementById('no-prod-results');
        if (!data.products || !data.products.length) {
          box.style.display = 'block';
          box.innerHTML = '<div style="padding:12px 14px;color:var(--text-light);font-size:0.85rem;text-align:center">محصولی یافت نشد</div>';
          return;
        }
        box.style.display = 'block';
        box.innerHTML = data.products.map(function(p) {
          var price = (p.discount_price && p.discount_price > 0) ? p.discount_price : p.price;
          var discBadge = (p.discount_price && p.discount_price > 0)
            ? '<span style="text-decoration:line-through;color:#aaa;font-size:0.72rem;margin-left:6px">' + Number(p.price).toLocaleString('fa') + '</span>'
            : '';
          return '<div onclick="noAddToCart(' + p.id + ',\'' + (p.name||'').replace(/'/g, "\\'") + '\',' + price + ',' + p.stock + ')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border-glass);display:flex;align-items:center;justify-content:space-between;transition:background .15s" onmouseover="this.style.background=\'rgba(0,201,177,0.06)\'" onmouseout="this.style.background=\'\'">'
            + '<div style="font-weight:700;font-size:0.85rem">' + (p.name||'') + '</div>'
            + '<div style="display:flex;align-items:center;gap:6px">' + discBadge + '<span style="font-weight:800;color:#00c9b1;font-size:0.85rem">' + Number(price).toLocaleString('fa') + ' ت</span><span style="font-size:0.72rem;color:var(--text-light);margin-right:6px">موجودی: ' + p.stock + '</span></div>'
            + '</div>';
        }).join('');
      }).catch(function() {});
  }, 300);
}

function noAddToCart(id, name, price, stock) {
  document.getElementById('no-prod-results').style.display = 'none';
  document.getElementById('no-prod-search').value = '';
  var ex = noState.cart.find(function(x) { return x.id === id; });
  if (ex) {
    if (ex.qty < stock) { ex.qty++; }
    else { if(typeof showToast==='function') showToast('موجودی کافی نیست','error'); return; }
  } else {
    noState.cart.push({ id: id, name: name, price: price, stock: stock, qty: 1 });
  }
  noRenderCart();
}

function noRenderCart() {
  var box = document.getElementById('no-cart');
  var totalBox = document.getElementById('no-cart-total');
  var totalVal = document.getElementById('no-cart-total-val');
  if (!noState.cart.length) {
    box.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--text-light);font-size:0.85rem">سبد خالی است — محصول جستجو کنید</div>';
    totalBox.style.display = 'none';
    return;
  }
  var total = 0;
  box.innerHTML = noState.cart.map(function(item, i) {
    total += item.price * item.qty;
    return '<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-glass)">'
      + '<div style="flex:1;font-weight:700;font-size:0.85rem">' + item.name + '</div>'
      + '<div style="display:flex;align-items:center;gap:6px">'
      + '<button onclick="noCartQty(' + i + ',-1)" style="width:26px;height:26px;border-radius:8px;border:1px solid var(--border-glass);background:none;cursor:pointer;font-size:1rem;line-height:1;display:flex;align-items:center;justify-content:center">−</button>'
      + '<span style="min-width:24px;text-align:center;font-weight:800">' + item.qty + '</span>'
      + '<button onclick="noCartQty(' + i + ',1)" style="width:26px;height:26px;border-radius:8px;border:1px solid var(--border-glass);background:none;cursor:pointer;font-size:1rem;line-height:1;display:flex;align-items:center;justify-content:center">+</button>'
      + '</div>'
      + '<div style="min-width:110px;text-align:left;font-weight:700;color:#00c9b1;font-size:0.85rem">' + Number(item.price * item.qty).toLocaleString('fa') + ' ت</div>'
      + '<button onclick="noRemoveCart(' + i + ')" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.1rem;padding:0 4px">🗑</button>'
      + '</div>';
  }).join('');
  totalBox.style.display = 'flex';
  totalVal.textContent = Number(total).toLocaleString('fa') + ' تومان';
}

function noCartQty(i, d) {
  noState.cart[i].qty += d;
  if (noState.cart[i].qty <= 0) {
    noState.cart.splice(i, 1);
  } else if (noState.cart[i].qty > noState.cart[i].stock) {
    noState.cart[i].qty = noState.cart[i].stock;
    if(typeof showToast==='function') showToast('موجودی کافی نیست','error');
  }
  noRenderCart();
}

function noRemoveCart(i) {
  noState.cart.splice(i, 1);
  noRenderCart();
}

function noRenderSummary() {
  var disc = parseFloat(document.getElementById('no-discount').value) || 0;
  var total = noState.cart.reduce(function(s, x) { return s + x.price * x.qty; }, 0);
  var discAmt = (disc > 0 && disc <= 100) ? total * disc / 100 : 0;
  var finalTotal = total - discAmt;
  var payEl = document.querySelector('input[name="no_payment"]:checked');
  var summaryEl = document.getElementById('no-summary');
  if (!summaryEl) return;
  // ▼ افزوده‌شده: نمایش استان در کنار شهر و آدرس در خلاصه‌ی سفارش ▼
  var provinceVal = document.getElementById('no-province') ? document.getElementById('no-province').value : '';
  var cityVal = document.getElementById('no-city') ? document.getElementById('no-city').value : '';
  var locParts = [provinceVal, cityVal].filter(Boolean).join(' / ');
  // ▲ پایان افزوده ▲
  summaryEl.innerHTML =
    '<div><b>مشتری:</b> ' + (noState.user ? noState.user.name : '—') + ' (' + (noState.user ? noState.user.email : '') + ')</div>'
    + '<div><b>آدرس:</b> ' + locParts + (locParts ? ' — ' : '') + (document.getElementById('no-address').value || '') + '</div>'
    + '<div><b>محصولات:</b> ' + noState.cart.map(function(x) { return x.name + ' × ' + x.qty; }).join(' | ') + '</div>'
    + '<div><b>جمع:</b> ' + Number(total).toLocaleString('fa') + ' تومان</div>'
    + (disc > 0 ? '<div><b>تخفیف (' + disc + '٪):</b> <span style="color:#ef4444">− ' + Number(discAmt).toLocaleString('fa') + ' تومان</span></div>' : '')
    + '<div style="font-weight:800;color:#00c9b1;font-size:1rem"><b>مبلغ نهایی:</b> ' + Number(finalTotal).toLocaleString('fa') + ' تومان</div>'
    + '<div><b>روش پرداخت:</b> ' + (payEl ? payEl.value : '—') + '</div>';
}

function noSubmitOrder() {
  var payEl = document.querySelector('input[name="no_payment"]:checked');
  if (!payEl) { if(typeof showToast==='function') showToast('روش پرداخت را انتخاب کنید', 'error'); return; }
  if (!noState.user) { if(typeof showToast==='function') showToast('مشتری انتخاب نشده', 'error'); return; }
  if (!noState.cart.length) { if(typeof showToast==='function') showToast('سبد خالی است', 'error'); return; }

  var btn = document.getElementById('btn-no-submit');
  btn.disabled = true;
  btn.textContent = '...در حال ثبت';

  var fd = new FormData();
  fd.append('action', 'create_order');
  fd.append('user_id', noState.user.id);
  fd.append('address', document.getElementById('no-address').value);
  fd.append('city', document.getElementById('no-city').value);
  fd.append('province', document.getElementById('no-province').value); // ◄ افزوده‌شده
  fd.append('note', document.getElementById('no-note').value);
  fd.append('payment_method', payEl.value);
  fd.append('discount_percent', document.getElementById('no-discount').value || 0);
  noState.cart.forEach(function(item, i) {
    fd.append('items[' + i + '][product_id]', item.id);
    fd.append('items[' + i + '][qty]', item.qty);
  });

  fetch('admin_orders_manage.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.textContent = '✅ ثبت سفارش';
      if (data.success) {
        if(typeof closeModal==='function') closeModal('modal-new-order');
        if(typeof showToast==='function') showToast('سفارش #' + data.order_id + ' با موفقیت ثبت شد', 'success');
        setTimeout(function() { location.reload(); }, 1200);
      } else {
        if(typeof showToast==='function') showToast(data.msg || 'خطا در ثبت سفارش', 'error');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.textContent = '✅ ثبت سفارش';
      if(typeof showToast==='function') showToast('خطای اتصال به سرور', 'error');
    });
}

// ── Payment radio style handler ──
document.addEventListener('change', function(e) {
  if (e.target.name === 'no_payment') {
    document.querySelectorAll('input[name="no_payment"]').forEach(function(r) {
      var box = r.nextElementSibling;
      if (r.checked) {
        box.style.background = 'linear-gradient(135deg,#00c9b1,#00a3ff)';
        box.style.color = '#fff';
        box.style.borderColor = 'transparent';
        box.style.boxShadow = '0 4px 12px rgba(0,201,177,0.3)';
      } else {
        box.style.background = '';
        box.style.color = '';
        box.style.borderColor = '';
        box.style.boxShadow = '';
      }
    });
    noRenderSummary();
  }
});

// ── Close dropdowns on outside click ──
document.addEventListener('click', function(e) {
  if (!e.target.closest('#no-user-search') && !e.target.closest('#no-user-results')) {
    var ur = document.getElementById('no-user-results');
    if (ur) ur.style.display = 'none';
  }
  if (!e.target.closest('#no-prod-search') && !e.target.closest('#no-prod-results')) {
    var pr = document.getElementById('no-prod-results');
    if (pr) pr.style.display = 'none';
  }
});
</script>
<?php if($message): ?>
<script>window.addEventListener('DOMContentLoaded',()=>{ if(typeof showToast==='function') showToast(<?=json_encode($message,JSON_UNESCAPED_UNICODE)?>,'<?=$msgType?>'); });</script>
<?php endif; ?>
</body>
</html>