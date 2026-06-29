<?php
require 'db.php';
requireLogin();

$activePage = 'users';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'json_list':
        $rows = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name")->fetchAll();
        jsonResponse($rows);
        break;

    case 'create':
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
        if ($full_name === '' || $email === '') {
            header('Location: admin_users.php?msg='.urlencode('نام و ایمیل الزامی است').'&type=error'); exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $phone, $role]);
            $msg='کاربر با موفقیت اضافه شد'; $type='success';
        } catch (PDOException $e) {
            $msg='این ایمیل قبلاً برای کاربر دیگری ثبت شده است'; $type='error';
        }
        header('Location: admin_users.php?msg='.urlencode($msg).'&type='.$type); exit;

    case 'update':
        $id        = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
        if ($id <= 0 || $full_name === '' || $email === '') {
            header('Location: admin_users.php?msg='.urlencode('اطلاعات وارد شده نامعتبر است').'&type=error'); exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?");
            $stmt->execute([$full_name, $email, $phone, $role, $id]);
            $msg='تغییرات با موفقیت ذخیره شد'; $type='success';
        } catch (PDOException $e) {
            $msg='این ایمیل قبلاً برای کاربر دیگری ثبت شده است'; $type='error';
        }
        header('Location: admin_users.php?msg='.urlencode($msg).'&type='.$type); exit;

    case 'delete':
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
        header('Location: admin_users.php?msg='.urlencode('کاربر حذف شد').'&type=success'); exit;

    case 'list':
    default:
        break;
}

$message     = $_GET['msg'] ?? '';
$messageType = $_GET['type'] ?? '';
$users = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count
    FROM users u ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | ShopAdmin</title>
    <link rel="stylesheet" href="css/style.css">
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

    <a href="admin_orders_manage.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12"/>
        </svg>
      </span>
      سفارش‌ها
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

    <a href="admin_users.php" class="nav-item active">
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

    <a href="#" class="nav-item">
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
            <div class="topbar-title">مدیریت <span>کاربران</span></div>
            <div class="breadcrumb">ShopAdmin › کاربران</div>
        </div>
        <button class="btn btn-primary" onclick="openAddUser()">+ افزودن کاربر</button>
    </header>

    <main class="content">

        <?php if ($message): ?>
        <div style="
            padding:1rem 1.5rem;border-radius:12px;margin-bottom:1.5rem;
            background:<?= $messageType==='error'?'rgba(239,68,68,0.1)':'rgba(0,201,177,0.1)' ?>;
            border:1px solid <?= $messageType==='error'?'rgba(239,68,68,0.3)':'rgba(0,201,177,0.3)' ?>;
            color:<?= $messageType==='error'?'#dc2626':'var(--teal-700)' ?>;font-weight:600;">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" stroke-linecap="round">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                  </svg>
                  لیست کاربران
                </div>
                <span style="font-size:0.75rem;color:var(--text-light);background:rgba(0,201,177,0.08);padding:4px 12px;border-radius:20px;border:1px solid var(--border-glass)"><?= count($users) ?> کاربر</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>نام کامل</th>
                            <th>ایمیل</th>
                            <th>تلفن</th>
                            <th>نقش</th>
                            <th>تعداد سفارش</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="avatar"><?= mb_substr($u['full_name'],0,1,'UTF-8') ?></div>
                                    <div>
                                        <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <div class="user-email"><?= jdate($u['created_at']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.82rem;color:var(--text-mid)"><?= htmlspecialchars($u['email']) ?></td>
                            <td style="font-size:0.82rem"><?= htmlspecialchars($u['phone']?:'—') ?></td>
                            <td>
                                <span style="
                                    background:<?= $u['role']==='admin'?'rgba(99,102,241,0.12)':'rgba(0,201,177,0.1)' ?>;
                                    color:<?= $u['role']==='admin'?'#5b5fc7':'var(--teal-600)' ?>;
                                    padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700">
                                    <?= $u['role']==='admin'?'👑 مدیر':'👤 مشتری' ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--teal-600)"><?= (int)$u['order_count'] ?> سفارش</td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn btn-ghost btn-sm"
                                            onclick='editUser(<?= json_encode($u,JSON_UNESCAPED_UNICODE) ?>)'>
                                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                      ویرایش
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                            onclick="deleteUser(<?= (int)$u['id'] ?>,'<?= htmlspecialchars($u['full_name'],ENT_QUOTES) ?>',<?= (int)$u['order_count'] ?>)">
                                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                      حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <div style="font-size:3rem;margin-bottom:1rem">👤</div>
                                <div class="empty-title">هنوز کاربری ثبت نشده</div>
                            </div>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- مودال کاربر -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="userModalTitle">افزودن کاربر جدید</div>
            <button class="modal-close" onclick="closeModal('userModal')">✕</button>
        </div>
        <form method="POST" action="admin_users.php">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="userId">
            <div class="form-group">
                <label class="form-label">نام کامل</label>
                <input type="text" name="full_name" id="userFullName" class="form-control" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                <div class="form-group">
                    <label class="form-label">ایمیل</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">تلفن</label>
                    <input type="text" name="phone" id="userPhone" class="form-control" placeholder="09xxxxxxxxx">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">نقش کاربر</label>
                <select name="role" id="userRole" class="form-control">
                    <option value="customer">👤 مشتری</option>
                    <option value="admin">👑 مدیر</option>
                </select>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem">
                <button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">انصراف</button>
                <button type="submit" class="btn btn-primary">💾 ذخیره کاربر</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteForm" method="POST" action="admin_users.php" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<div class="toast-container" id="toast-container"></div>
<script src="js/main.js"></script>
<script>
function openModal(id)  { const el=document.getElementById(id); if(el) el.classList.add('open'); }
function closeModal(id) { const el=document.getElementById(id); if(el) el.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{ if(e.target===o) o.classList.remove('open'); });
});
document.addEventListener('keydown',e=>{
    if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open'));
});
function openAddUser() {
    document.getElementById('userModalTitle').textContent='افزودن کاربر جدید';
    document.getElementById('formAction').value='create';
    document.getElementById('userId').value='';
    document.getElementById('userFullName').value='';
    document.getElementById('userEmail').value='';
    document.getElementById('userPhone').value='';
    document.getElementById('userRole').value='customer';
    openModal('userModal');
}
function editUser(u) {
    document.getElementById('userModalTitle').textContent='ویرایش کاربر';
    document.getElementById('formAction').value='update';
    document.getElementById('userId').value=u.id;
    document.getElementById('userFullName').value=u.full_name;
    document.getElementById('userEmail').value=u.email;
    document.getElementById('userPhone').value=u.phone??'';
    document.getElementById('userRole').value=u.role;
    openModal('userModal');
}
function deleteUser(id,name,orderCount) {
    var msg='آیا از حذف کاربر «'+name+'» مطمئن هستید؟';
    if(orderCount>0) msg+='\nاین کاربر '+orderCount+' سفارش دارد که آن‌ها هم حذف خواهند شد.';
    if(confirm(msg)) {
        document.getElementById('deleteId').value=id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
</body>
</html>