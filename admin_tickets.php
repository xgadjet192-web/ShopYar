<?php
error_reporting(0);
ini_set('display_errors',0);
require_once 'db.php';
requireLogin();

$stats = $pdo->query("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='delivered') AS delivered, COALESCE(SUM(total_price),0) AS revenue FROM orders")->fetch();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>تیکت‌ها | ShopAdmin</title>
<link rel="stylesheet" href="css/style.css">
<style>
.ticket-page-wrap { max-width: 760px; margin: 0 auto; }
.ticket-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
.ticket-page-title { font-size:1.2rem; font-weight:800; color:var(--text-dark); display:flex; align-items:center; gap:0.5rem; }
.ticket-add-form { background:#fff; border-radius:20px; border:1px solid var(--border-glass); padding:1.5rem; margin-bottom:1.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.ticket-add-form .form-row { display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; }
.ticket-add-form .form-group { margin:0; }
.filter-tabs { display:flex; gap:0.4rem; background:rgba(0,201,177,0.07); border-radius:30px; padding:4px; border:1px solid rgba(0,201,177,0.12); }
.filter-tab { padding:6px 18px; border-radius:24px; border:none; cursor:pointer; font-size:0.78rem; font-weight:700; font-family:inherit; color:var(--text-light); background:transparent; transition:all .2s; }
.filter-tab.active { background:linear-gradient(135deg,#00c9b1,#00a3ff); color:#fff; box-shadow:0 3px 10px rgba(0,201,177,0.3); }
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
    <a href="admin_orders_manage.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12"/></svg></span>
      سفارش‌ها
      <span class="nav-badge"><?= $stats['total'] ?></span>
    </a>
    <a href="admin_products.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
      محصولات
    </a>
    <a href="admin_users.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span>
      کاربران
    </a>
    <div class="nav-label" style="margin-top:1rem">سیستم</div>
    <a href="admin_tickets.php" class="nav-item active">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
      تیکت‌ها
    </a>
    <a href="logout.php" class="nav-item">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></span>
      خروج
    </a>
  </nav>
</aside>

<div class="main-wrapper">
  <header class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">سیستم <span>تیکتینگ</span></div>
      <div class="breadcrumb">ShopAdmin › تیکت‌ها</div>
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

  <main class="content">
    <div class="ticket-page-wrap">

      <div class="ticket-page-header">
        <div class="ticket-page-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="url(#tg)" stroke-width="2.5" stroke-linecap="round" width="22" height="22">
            <defs><linearGradient id="tg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00c9b1"/><stop offset="100%" stop-color="#a855f7"/></linearGradient></defs>
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
          </svg>
          مدیریت تیکت‌ها
        </div>
        <div class="filter-tabs">
          <button class="filter-tab active" data-filter="all" onclick="setFilter('all',this)">همه</button>
          <button class="filter-tab" data-filter="open" onclick="setFilter('open',this)">باز</button>
          <button class="filter-tab" data-filter="done" onclick="setFilter('done',this)">انجام شده</button>
        </div>
      </div>

      <!-- فرم افزودن -->
      <div class="ticket-add-form">
        <div class="form-row">
          <div class="form-group" style="flex:1;min-width:200px">
            <label class="form-label">موضوع تیکت</label>
            <input id="tk-page-subject" class="form-control" placeholder="مثال: پیگیری سفارش معوق..." onkeydown="if(event.key==='Enter')addPageTicket()">
          </div>
          <div class="form-group" style="min-width:140px">
            <label class="form-label">اولویت</label>
            <select id="tk-page-priority" class="form-control">
              <option value="high">🔴 فوری</option>
              <option value="medium" selected>🟡 متوسط</option>
              <option value="low">🟢 عادی</option>
            </select>
          </div>
          <button class="btn btn-primary" id="tk-page-add" onclick="addPageTicket()" style="padding:0.6rem 1.4rem;align-self:flex-end">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            افزودن تیکت
          </button>
        </div>
      </div>

      <!-- لیست تیکت‌ها -->
      <div id="tickets-page-list"></div>

    </div>
  </main>
</div>

<div class="toast-container" id="toast-container"></div>

<script src="js/main.js"></script>
<script>
(function(){
  function tick(){
    var el=document.getElementById('clock');
    if(el) el.textContent=new Date().toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  tick(); setInterval(tick,1000);
})();

var _currentFilter = 'all';

function setFilter(f, btn) {
  _currentFilter = f;
  document.querySelectorAll('.filter-tab').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  renderPageTickets();
}

function renderPageTickets() {
  var wrap = document.getElementById('tickets-page-list');
  if (!wrap) return;

  var TICKET_KEY = 'shopAdmin_tickets';
  var tickets = [];
  try { tickets = JSON.parse(localStorage.getItem(TICKET_KEY)) || []; } catch(e) {}

  var filtered = tickets.filter(function(t) {
    if (_currentFilter === 'open') return !t.done;
    if (_currentFilter === 'done') return t.done;
    return true;
  });

  if (!filtered.length) {
    wrap.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--text-light);font-size:0.9rem">📋 تیکتی در این دسته وجود ندارد</div>';
    return;
  }

  var pMap = { high:['🔴','فوری','#ef4444'], medium:['🟡','متوسط','#f59e0b'], low:['🟢','عادی','#22c55e'] };

  wrap.innerHTML = filtered.map(function(t) {
    var realIdx = tickets.indexOf(t);
    var p = pMap[t.priority] || pMap.medium;
    var date = t.ts ? new Date(t.ts).toLocaleDateString('fa-IR') : '';
    return '<div style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:#fff;border-radius:16px;margin-bottom:10px;border:1px solid var(--border-glass);box-shadow:0 2px 12px rgba(0,0,0,0.04);transition:box-shadow .2s" onmouseover="this.style.boxShadow=\'0 6px 24px rgba(0,201,177,0.1)\'" onmouseout="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,0.04)\'">'
      + '<div onclick="toggleAndRender(' + realIdx + ')" style="width:24px;height:24px;border-radius:8px;border:2px solid ' + (t.done ? '#00c9b1' : '#ddd') + ';background:' + (t.done ? 'linear-gradient(135deg,#00c9b1,#00a3ff)' : '#fff') + ';cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s">'
      + (t.done ? '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>' : '') + '</div>'
      + '<span style="font-size:0.78rem;font-weight:800;padding:3px 10px;border-radius:20px;background:' + p[2] + '18;color:' + p[2] + '">' + p[0] + ' ' + p[1] + '</span>'
      + '<span style="flex:1;font-weight:' + (t.done ? '400' : '700') + ';font-size:0.88rem;' + (t.done ? 'text-decoration:line-through;color:var(--text-light)' : 'color:var(--text-dark)') + '">' + escHtml(t.subject) + '</span>'
      + '<span style="font-size:0.75rem;color:var(--text-light)">' + date + '</span>'
      + '<button onclick="deleteAndRender(' + realIdx + ')" style="background:#fff0f0;border:1px solid #fecaca;cursor:pointer;color:#ef4444;padding:5px 10px;border-radius:8px;font-size:0.78rem;font-weight:700;transition:all .2s" onmouseover="this.style.background=\'#ef4444\';this.style.color=\'#fff\'" onmouseout="this.style.background=\'#fff0f0\';this.style.color=\'#ef4444\'">🗑 حذف</button>'
      + '</div>';
  }).join('');
}

function toggleAndRender(i) {
  var TICKET_KEY = 'shopAdmin_tickets';
  var t = [];
  try { t = JSON.parse(localStorage.getItem(TICKET_KEY)) || []; } catch(e) {}
  if (t[i]) {
    t[i].done = !t[i].done;
    try { localStorage.setItem(TICKET_KEY, JSON.stringify(t)); } catch(e) {}
    if(typeof showToast==='function') showToast(t[i].done ? 'تیکت انجام شد ✅' : 'تیکت بازگشت به باز', 'info');
  }
  renderPageTickets();
  if(typeof renderTickets==='function') renderTickets();
}

function deleteAndRender(i) {
  var TICKET_KEY = 'shopAdmin_tickets';
  var t = [];
  try { t = JSON.parse(localStorage.getItem(TICKET_KEY)) || []; } catch(e) {}
  t.splice(i, 1);
  try { localStorage.setItem(TICKET_KEY, JSON.stringify(t)); } catch(e) {}
  if(typeof showToast==='function') showToast('تیکت حذف شد', 'info');
  renderPageTickets();
}

function addPageTicket() {
  var subj = (document.getElementById('tk-page-subject') || {}).value || '';
  var prio = (document.getElementById('tk-page-priority') || {}).value || 'medium';
  subj = subj.trim();
  if (!subj) { if(typeof showToast==='function') showToast('موضوع را وارد کنید', 'error'); return; }
  var TICKET_KEY = 'shopAdmin_tickets';
  var list = [];
  try { list = JSON.parse(localStorage.getItem(TICKET_KEY)) || []; } catch(e) {}
  list.unshift({ subject: subj, priority: prio, done: false, ts: Date.now() });
  try { localStorage.setItem(TICKET_KEY, JSON.stringify(list)); } catch(e) {}
  var si = document.getElementById('tk-page-subject');
  if (si) si.value = '';
  renderPageTickets();
  if(typeof showToast==='function') showToast('تیکت ثبت شد ✅');
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
  renderPageTickets();
});
</script>
</body>
</html>