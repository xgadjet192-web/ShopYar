<?php
error_reporting(0);
ini_set('display_errors', 0);
require 'db.php';
requireLogin();

$activePage = 'products';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'json_list':
        $rows = $pdo->query("SELECT id, name, price, stock, icon FROM products ORDER BY name")->fetchAll();
        jsonResponse($rows);
        break;

    case 'create':
        $name     = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '') ?: 'متفرقه';
        $price    = (int)($_POST['price'] ?? 0);
        $stock    = (int)($_POST['stock'] ?? 0);
        $icon     = trim($_POST['icon'] ?? '') ?: '📦';
        $desc     = trim($_POST['description'] ?? '');
        if ($name === '' || $price <= 0) {
            header('Location: admin_products.php?msg='.urlencode('نام و قیمت محصول الزامی است').'&type=error'); exit;
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('description', $cols)) {
                $pdo->prepare("INSERT INTO products (name, category, price, stock, icon, description) VALUES (?,?,?,?,?,?)")->execute([$name,$category,$price,$stock,$icon,$desc]);
            } else {
                $pdo->prepare("INSERT INTO products (name, category, price, stock, icon) VALUES (?,?,?,?,?)")->execute([$name,$category,$price,$stock,$icon]);
            }
            // اعلان
            $pdo->prepare("INSERT INTO notifications (title,message,type) VALUES (?,?,?)")->execute(["محصول جدید اضافه شد","محصول «{$name}» با قیمت ".number_format($price)." تومان ثبت شد",'success']);
        } catch(Exception $e) {
            header('Location: admin_products.php?msg='.urlencode('خطا: '.$e->getMessage()).'&type=error'); exit;
        }
        header('Location: admin_products.php?msg='.urlencode('محصول با موفقیت اضافه شد').'&type=success'); exit;

    case 'update':
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '') ?: 'متفرقه';
        $price    = (int)($_POST['price'] ?? 0);
        $stock    = (int)($_POST['stock'] ?? 0);
        $icon     = trim($_POST['icon'] ?? '') ?: '📦';
        if ($id <= 0 || $name === '' || $price <= 0) {
            header('Location: admin_products.php?msg='.urlencode('اطلاعات نامعتبر است').'&type=error'); exit;
        }
        try {
            $pdo->prepare("UPDATE products SET name=?,category=?,price=?,stock=?,icon=? WHERE id=?")->execute([$name,$category,$price,$stock,$icon,$id]);
            $pdo->prepare("INSERT INTO notifications (title,message,type) VALUES (?,?,?)")->execute(["محصول ویرایش شد","محصول «{$name}» به‌روزرسانی شد",'info']);
        } catch(Exception $e) {
            header('Location: admin_products.php?msg='.urlencode('خطا: '.$e->getMessage()).'&type=error'); exit;
        }
        header('Location: admin_products.php?msg='.urlencode('تغییرات ذخیره شد').'&type=success'); exit;

    case 'delete':
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id=?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                header('Location: admin_products.php?msg='.urlencode('این محصول در سفارش استفاده شده و قابل حذف نیست').'&type=error'); exit;
            }
            // انتقال به سطل آشغال
            $item = $pdo->prepare("SELECT * FROM products WHERE id=?"); $item->execute([$id]); $p = $item->fetch();
            if ($p) {
                $pdo->prepare("INSERT INTO trash (item_type,item_id,item_data) VALUES ('product',?,?)")->execute([$id, json_encode($p, JSON_UNESCAPED_UNICODE)]);
                $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
                $pdo->prepare("INSERT INTO notifications (title,message,type) VALUES (?,?,?)")->execute(["محصول حذف شد","محصول «{$p['name']}» به سطل آشغال منتقل شد",'warning']);
            }
        }
        header('Location: admin_products.php?msg='.urlencode('محصول به سطل آشغال منتقل شد').'&type=success'); exit;

    case 'restore':
        $trash_id = (int)($_GET['trash_id'] ?? 0);
        if ($trash_id > 0) {
            $row = $pdo->prepare("SELECT * FROM trash WHERE id=? AND item_type='product'"); $row->execute([$trash_id]); $t = $row->fetch();
            if ($t) {
                $d = json_decode($t['item_data'], true);
                try {
                    $pdo->prepare("INSERT INTO products (name,category,price,stock,icon) VALUES (?,?,?,?,?)")->execute([$d['name'],$d['category']??'متفرقه',$d['price'],$d['stock'],$d['icon']??'📦']);
                    $pdo->prepare("DELETE FROM trash WHERE id=?")->execute([$trash_id]);
                    $pdo->prepare("INSERT INTO notifications (title,message,type) VALUES (?,?,?)")->execute(["محصول بازگردانی شد","محصول «{$d['name']}» از سطل آشغال بازگردانی شد",'success']);
                } catch(Exception $e) {}
            }
        }
        header('Location: admin_products.php?tab=trash&msg='.urlencode('محصول بازگردانی شد').'&type=success'); exit;

    case 'trash_delete':
        $trash_id = (int)($_GET['trash_id'] ?? 0);
        if ($trash_id > 0) $pdo->prepare("DELETE FROM trash WHERE id=? AND item_type='product'")->execute([$trash_id]);
        header('Location: admin_products.php?tab=trash&msg='.urlencode('حذف دائمی انجام شد').'&type=success'); exit;
}

$message     = $_GET['msg']  ?? '';
$messageType = $_GET['type'] ?? '';
$activeTab   = $_GET['tab']  ?? 'list';

// فیلترها
$filterCat    = $_GET['cat']    ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterStock  = $_GET['stock']  ?? '';

$where = "WHERE 1=1"; $params = [];
if ($filterCat)    { $where .= " AND category=?"; $params[] = $filterCat; }
if ($filterSearch) { $where .= " AND (name LIKE ? OR category LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }
if ($filterStock === 'low')  { $where .= " AND stock <= 5"; }
if ($filterStock === 'zero') { $where .= " AND stock = 0"; }

$stmt = $pdo->prepare("SELECT * FROM products $where ORDER BY id DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

// دسته‌بندی‌ها
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// آمار
$stats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(price*stock),0) AS total_value, SUM(stock<=5) AS low_stock, SUM(stock=0) AS out_of_stock FROM products")->fetch();

// سطل آشغال
$trash = $pdo->query("SELECT * FROM trash WHERE item_type='product' ORDER BY deleted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>مدیریت محصولات | ShopAdmin</title>
<link rel="stylesheet" href="css/style.css">
<style>
.tab-bar { display:flex; gap:0; border-bottom:2px solid rgba(0,201,177,0.12); margin-bottom:1.5rem; }
.tab-btn { padding:0.7rem 1.5rem; font-family:inherit; font-size:0.84rem; font-weight:600; border:none; background:none; color:var(--text-light); cursor:pointer; position:relative; transition:all .25s; display:flex; align-items:center; gap:0.4rem; }
.tab-btn:hover { color:var(--teal-600); }
.tab-btn.active { color:var(--teal-600); }
.tab-btn.active::after { content:''; position:absolute; bottom:-2px; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--teal-500),var(--cyan-400)); border-radius:2px 2px 0 0; }
.tab-badge-r { background:rgba(239,68,68,0.1); color:#dc2626; font-size:0.62rem; font-weight:700; padding:1px 6px; border-radius:10px; }
.tab-badge-t { background:rgba(0,201,177,0.1); color:var(--teal-600); font-size:0.62rem; font-weight:700; padding:1px 6px; border-radius:10px; }

.kpi-mini { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
.kpi-mini-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:var(--radius-md); padding:1rem 1.25rem; display:flex; align-items:center; gap:0.85rem; transition:var(--transition); }
.kpi-mini-card:hover { border-color:rgba(0,201,177,0.3); transform:translateY(-2px); }
.kpi-mini-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.kpi-mini-icon svg { width:20px; height:20px; }
.kpi-mini-val { font-size:1.3rem; font-weight:900; color:var(--text-dark); line-height:1; }
.kpi-mini-label { font-size:0.7rem; color:var(--text-light); font-weight:500; margin-top:2px; }

.filter-row { display:flex; gap:0.65rem; align-items:center; flex-wrap:wrap; padding:0.9rem 1.5rem; border-bottom:1px solid var(--border-glass); background:rgba(240,253,252,0.5); }
.filter-chip { padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:600; border:1.5px solid var(--border-glass); background:transparent; color:var(--text-light); cursor:pointer; transition:var(--transition); font-family:inherit; }
.filter-chip:hover,.filter-chip.active { background:linear-gradient(135deg,var(--teal-500),var(--cyan-400)); border-color:transparent; color:#fff; }

.trash-item { display:flex; align-items:center; gap:1rem; padding:0.9rem 1.5rem; border-bottom:1px solid rgba(0,201,177,0.07); transition:var(--transition); }
.trash-item:hover { background:rgba(239,68,68,0.03); }
.trash-icon { font-size:1.6rem; }
.trash-name { flex:1; font-weight:600; font-size:0.85rem; }
.trash-date { font-size:0.72rem; color:var(--text-muted); }

.notif-panel-wrap { position:relative; }
.notif-dropdown { position:absolute; top:calc(100% + 8px); left:0; width:320px; background:rgba(255,255,255,0.98); backdrop-filter:blur(20px); border:1px solid var(--border-glass); border-radius:var(--radius-lg); box-shadow:0 16px 48px rgba(0,0,0,0.12); z-index:300; display:none; overflow:hidden; }
.notif-dropdown.open { display:block; animation:slideUp .25s ease; }
.notif-head { padding:0.85rem 1rem; border-bottom:1px solid var(--border-glass); display:flex; align-items:center; justify-content:space-between; }
.notif-head-title { font-size:0.85rem; font-weight:800; color:var(--text-dark); }
.notif-item { display:flex; gap:0.75rem; align-items:flex-start; padding:0.8rem 1rem; border-bottom:1px solid rgba(0,201,177,0.07); cursor:pointer; transition:var(--transition); }
.notif-item:hover { background:rgba(0,201,177,0.04); }
.notif-item:last-child { border-bottom:none; }
.notif-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
.notif-dot.success { background:#22c55e; }
.notif-dot.warning { background:#f59e0b; }
.notif-dot.info    { background:#3b82f6; }
.notif-dot.danger  { background:#ef4444; }
.notif-body { flex:1; }
.notif-title { font-size:0.78rem; font-weight:700; color:var(--text-dark); }
.notif-msg   { font-size:0.71rem; color:var(--text-light); margin-top:1px; }
.notif-time  { font-size:0.67rem; color:var(--text-muted); margin-top:2px; }
.notif-unread { background:rgba(0,201,177,0.04); }
</style>
</head>
<body>

<!-- SIDEBAR -->
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
    <a href="admin_orders_manage.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12"/></svg></span>
      سفارش‌ها
    </a>
    <a href="admin_products.php" class="nav-item active">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
      محصولات
    </a>
    <a href="admin_users.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span>
      کاربران
    </a>
    <div class="nav-label" style="margin-top:1rem">سیستم</div>
    <a href="admin_orders_manage.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
      تیکت‌ها
    </a>
    <a href="admin_products.php?tab=trash" class="nav-item <?= $activeTab==='trash'?'active':'' ?>">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg></span>
      سطل آشغال
      <?php if(count($trash)>0): ?><span class="nav-badge"><?=count($trash)?></span><?php endif; ?>
    </a>
    <a href="logout.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></span>
      خروج
    </a>
  </nav>
</aside>

<!-- MAIN -->
<div class="main-wrapper">
  <header class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">مدیریت <span>محصولات</span></div>
      <div class="breadcrumb">ShopAdmin › محصولات</div>
    </div>
    <div class="topbar-right">
      <div class="topbar-clock" id="clock">--:--</div>
      <!-- زنگوله اعلانات -->
      <div class="notif-panel-wrap">
        <div class="topbar-notif" id="notifBtn" onclick="toggleNotif()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
          <?php $unread=$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn(); ?>
          <?php if($unread>0): ?><div class="notif-dot"></div><?php endif; ?>
        </div>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-head">
            <span class="notif-head-title">اعلانات <?php if($unread>0): ?><span class="tab-badge-r"><?=$unread?> جدید</span><?php endif; ?></span>
            <button onclick="markAllRead()" style="font-size:0.72rem;color:var(--teal-600);background:none;border:none;cursor:pointer;font-family:inherit">همه خوانده شد</button>
          </div>
          <?php $notifs=$pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 8")->fetchAll(); ?>
          <?php foreach($notifs as $n): ?>
          <div class="notif-item <?= !$n['is_read']?'notif-unread':'' ?>" onclick="readNotif(<?=$n['id']?>)">
            <div class="notif-dot <?=$n['type']?>"></div>
            <div class="notif-body">
              <div class="notif-title"><?=htmlspecialchars($n['title'])?></div>
              <div class="notif-msg"><?=htmlspecialchars($n['message']??'')?></div>
              <div class="notif-time"><?=date('Y/m/d H:i',strtotime($n['created_at']))?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(!$notifs): ?><div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:0.82rem">اعلانی وجود ندارد</div><?php endif; ?>
        </div>
      </div>
      <button class="btn btn-primary" onclick="openAddProduct()">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" width="15" height="15" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        افزودن محصول
      </button>
    </div>
  </header>

  <main class="content">

    <?php if($message): ?>
    <div class="alert alert-<?=$messageType==='error'?'error':'success'?>" style="margin-bottom:1.25rem">
      <?=$messageType==='error'?'❌':'✅'?> <?=htmlspecialchars($message)?>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-mini">
      <div class="kpi-mini-card">
        <div class="kpi-mini-icon stat-icon-wrap teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
        <div><div class="kpi-mini-val"><?=$stats['total']?></div><div class="kpi-mini-label">کل محصولات</div></div>
      </div>
      <div class="kpi-mini-card">
        <div class="kpi-mini-icon stat-icon-wrap purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <div><div class="kpi-mini-val" style="font-size:1rem"><?=number_format($stats['total_value']/1000000,1)?>M</div><div class="kpi-mini-label">ارزش انبار (تومان)</div></div>
      </div>
      <div class="kpi-mini-card">
        <div class="kpi-mini-icon stat-icon-wrap yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div><div class="kpi-mini-val" style="color:#d97706"><?=$stats['low_stock']?></div><div class="kpi-mini-label">موجودی کم</div></div>
      </div>
      <div class="kpi-mini-card">
        <div class="kpi-mini-icon stat-icon-wrap red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div><div class="kpi-mini-val" style="color:#dc2626"><?=$stats['out_of_stock']?></div><div class="kpi-mini-label">ناموجود</div></div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
      <button class="tab-btn <?=$activeTab==='list'?'active':''?>" onclick="switchTab('list')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>
        لیست محصولات
        <span class="tab-badge-t"><?=count($products)?></span>
      </button>
      <button class="tab-btn <?=$activeTab==='trash'?'active':''?>" onclick="switchTab('trash')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        سطل آشغال
        <?php if(count($trash)>0): ?><span class="tab-badge-r"><?=count($trash)?></span><?php endif; ?>
      </button>
    </div>

    <!-- TAB: LIST -->
    <div id="tab-list" style="<?=$activeTab!=='list'?'display:none':''?>">
      <div class="glass-card">
        <div class="card-header">
          <div class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            لیست محصولات
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center">
            <span style="font-size:0.75rem;color:var(--text-light);background:rgba(0,201,177,0.08);padding:4px 12px;border-radius:20px;border:1px solid var(--border-glass)"><?=count($products)?> محصول</span>
          </div>
        </div>

        <!-- Filters -->
        <div class="filter-row">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="prod-search" class="filter-input" placeholder="جستجو نام یا دسته‌بندی..." value="<?=htmlspecialchars($filterSearch)?>" style="min-width:200px" oninput="filterProducts()">
          </div>
          <select id="prod-cat" class="filter-select" onchange="filterProducts()">
            <option value="">همه دسته‌ها</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?=htmlspecialchars($cat)?>" <?=$filterCat===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option>
            <?php endforeach; ?>
          </select>
          <button class="filter-chip <?=$filterStock===''?'active':''?>" onclick="setStockFilter('')">همه</button>
          <button class="filter-chip <?=$filterStock==='low'?'active':''?>" onclick="setStockFilter('low')">موجودی کم</button>
          <button class="filter-chip <?=$filterStock==='zero'?'active':''?>" onclick="setStockFilter('zero')">ناموجود</button>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>آیکون</th>
                <th>نام محصول</th>
                <th>دسته‌بندی</th>
                <th>قیمت</th>
                <th>موجودی</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody id="products-tbody">
              <?php foreach($products as $i=>$p): ?>
              <tr style="animation-delay:<?=$i*0.04?>s">
                <td style="text-align:center;font-size:1.4rem"><?=htmlspecialchars($p['icon']??'📦')?></td>
                <td style="font-weight:600"><?=htmlspecialchars($p['name'])?></td>
                <td><span class="tag"><?=htmlspecialchars($p['category']??'متفرقه')?></span></td>
                <td class="price-cell"><?=number_format($p['price'])?> <span class="price-unit">تومان</span></td>
                <td>
                  <span class="stock-badge <?=$p['stock']<=5?'stock-low':'stock-ok'?>">
                    <?=(int)$p['stock']?> عدد
                  </span>
                </td>
                <td>
                  <div class="actions-cell">
                    <button class="btn btn-ghost btn-sm" onclick='editProduct(<?=json_encode($p,JSON_UNESCAPED_UNICODE)?>)'>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      ویرایش
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?=(int)$p['id']?>,'<?=htmlspecialchars($p['name'],ENT_QUOTES)?>')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$products): ?>
              <tr><td colspan="6">
                <div class="empty-state">
                  <div style="font-size:3rem;margin-bottom:1rem">📭</div>
                  <div class="empty-title">محصولی یافت نشد</div>
                  <div class="empty-sub">فیلترها را تغییر دهید یا محصول جدید اضافه کنید</div>
                </div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB: TRASH -->
    <div id="tab-trash" style="<?=$activeTab!=='trash'?'display:none':''?>">
      <div class="glass-card">
        <div class="card-header">
          <div class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            سطل آشغال محصولات
          </div>
          <span style="font-size:0.72rem;color:var(--text-muted)">محصولات حذف شده — قابل بازگردانی</span>
        </div>
        <?php if(!$trash): ?>
        <div class="empty-state"><div style="font-size:3rem;margin-bottom:1rem">🗑️</div><div class="empty-title">سطل آشغال خالی است</div></div>
        <?php else: ?>
        <?php foreach($trash as $t): $d=json_decode($t['item_data'],true); ?>
        <div class="trash-item">
          <div class="trash-icon"><?=htmlspecialchars($d['icon']??'📦')?></div>
          <div style="flex:1">
            <div class="trash-name"><?=htmlspecialchars($d['name']??'—')?></div>
            <div class="trash-date">حذف شده در <?=date('Y/m/d H:i',strtotime($t['deleted_at']))?></div>
          </div>
          <div style="display:flex;gap:0.5rem">
            <a href="admin_products.php?action=restore&trash_id=<?=$t['id']?>" class="btn btn-ghost btn-sm">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
              بازگردانی
            </a>
            <a href="admin_products.php?action=trash_delete&trash_id=<?=$t['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف دائمی؟')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
              حذف دائمی
            </a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

<!-- MODAL محصول -->
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="productModalTitle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="20" height="20"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        افزودن محصول جدید
      </div>
      <button class="modal-close" onclick="closeModal('productModal')">✕</button>
    </div>
    <form method="POST" action="admin_products.php">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="productId">
      <div class="form-group">
        <label class="form-label">نام محصول <span style="color:#ef4444">*</span></label>
        <input type="text" name="name" id="productName" class="form-control" required placeholder="نام محصول را وارد کنید">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">دسته‌بندی</label>
          <input type="text" name="category" id="productCategory" class="form-control" placeholder="مثلاً الکترونیک" list="cat-list">
          <datalist id="cat-list">
            <?php foreach($categories as $cat): ?><option value="<?=htmlspecialchars($cat)?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-group">
          <label class="form-label">آیکون (اموجی)</label>
          <input type="text" name="icon" id="productIcon" class="form-control" maxlength="4" placeholder="📦">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">قیمت (تومان) <span style="color:#ef4444">*</span></label>
          <input type="number" name="price" id="productPrice" class="form-control" min="1" required placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">موجودی انبار</label>
          <input type="number" name="stock" id="productStock" class="form-control" min="0" value="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('productModal')">انصراف</button>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="14" height="14" stroke-linecap="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
          ذخیره محصول
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL تایید حذف -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="max-width:400px">
    <div class="confirm-box">
      <div class="confirm-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" width="56" height="56"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
      </div>
      <div class="confirm-msg">انتقال به سطل آشغال</div>
      <div class="confirm-sub">محصول «<span id="del-name" style="color:var(--teal-600)"></span>» به سطل آشغال منتقل می‌شود و قابل بازگردانی است.</div>
      <div class="confirm-actions">
        <button class="btn btn-ghost" onclick="closeModal('deleteModal')">انصراف</button>
        <button class="btn btn-danger" id="btn-del-confirm">انتقال به سطل آشغال</button>
      </div>
    </div>
  </div>
</div>

<form id="deleteForm" method="GET" action="admin_products.php" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<div class="toast-container" id="toast-container"></div>
<script src="js/main.js"></script>
<script>
// Clock
(function(){ function t(){ const el=document.getElementById('clock'); if(el) el.textContent=new Date().toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); } t(); setInterval(t,1000); })();

// Tabs
function switchTab(name) {
  document.querySelectorAll('[id^="tab-"]').forEach(el=>el.style.display='none');
  document.getElementById('tab-'+name).style.display='';
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  event.currentTarget.classList.add('active');
  history.replaceState(null,'','?tab='+name);
}

// Product modal
function openAddProduct() {
  document.getElementById('productModalTitle').innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="20" height="20"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> افزودن محصول جدید';
  document.getElementById('formAction').value='create';
  ['productId','productName','productCategory','productIcon','productPrice'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  const s=document.getElementById('productStock'); if(s) s.value='0';
  openModal('productModal');
}
function editProduct(p) {
  document.getElementById('productModalTitle').innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="20" height="20"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> ویرایش محصول';
  document.getElementById('formAction').value='update';
  document.getElementById('productId').value=p.id;
  document.getElementById('productName').value=p.name;
  document.getElementById('productCategory').value=p.category||'';
  document.getElementById('productIcon').value=p.icon||'';
  document.getElementById('productPrice').value=p.price;
  document.getElementById('productStock').value=p.stock;
  openModal('productModal');
}

// Delete with confirm modal
let _delId=0;
function deleteProduct(id,name) {
  _delId=id;
  document.getElementById('del-name').textContent=name;
  openModal('deleteModal');
}
document.getElementById('btn-del-confirm').addEventListener('click',function(){
  document.getElementById('deleteId').value=_delId;
  document.getElementById('deleteForm').submit();
});

// Filter
function filterProducts() {
  const s=document.getElementById('prod-search').value.toLowerCase();
  const c=document.getElementById('prod-cat').value.toLowerCase();
  document.querySelectorAll('#products-tbody tr').forEach(tr=>{
    const name=tr.cells[1]?.textContent.toLowerCase()||'';
    const cat=tr.cells[2]?.textContent.toLowerCase()||'';
    tr.style.display=(!s||name.includes(s))&&(!c||cat.includes(c))?'':'none';
  });
}
function setStockFilter(val) {
  const url=new URL(window.location.href);
  val?url.searchParams.set('stock',val):url.searchParams.delete('stock');
  url.searchParams.set('tab','list');
  window.location.href=url.toString();
}

// Notifications
function toggleNotif() {
  const d=document.getElementById('notifDropdown');
  d.classList.toggle('open');
}
document.addEventListener('click',function(e){
  if(!e.target.closest('.notif-panel-wrap')) document.getElementById('notifDropdown')?.classList.remove('open');
});
function readNotif(id) {
  fetch('admin_products.php?action=read_notif&id='+id);
  // mark visually
  event.currentTarget.classList.remove('notif-unread');
}
function markAllRead() {
  fetch('admin_products.php?action=mark_all_read');
  document.querySelectorAll('.notif-unread').forEach(el=>el.classList.remove('notif-unread'));
  document.querySelector('.notif-dot')?.remove();
  showToast('همه اعلانات خوانده شد','info');
}
</script>
</body>
</html>