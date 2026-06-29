<?php
// =============================================
// login.php - صفحه ورود به پنل مدیریت
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// اگه قبلاً لاگین کرده، مستقیم بره داخل
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_orders_manage.php');
    exit;
}

require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'لطفاً تمام فیلدها را پر کنید';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $admin['username'];
            $_SESSION['admin_id']        = $admin['id'];
            header('Location: admin_orders_manage.php');
            exit;
        } else {
            $error = 'نام کاربری یا رمز عبور اشتباه است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به پنل مدیریت | ShopAdmin</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal-1: #0d9488;
    --teal-2: #14b8a6;
    --teal-3: #2dd4bf;
    --teal-light: #ccfbf1;
    --bg: #f0fdfa;
    --glass: rgba(255,255,255,0.72);
    --shadow: 0 32px 80px rgba(13,148,136,0.18);
  }

  body {
    font-family: 'Vazirmatn', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg);
    overflow: hidden;
    position: relative;
  }

  /* ── Animated background blobs ── */
  .blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.35;
    animation: float 8s ease-in-out infinite;
    pointer-events: none;
  }
  .blob-1 {
    width: 500px; height: 500px;
    background: #2dd4bf;
    top: -150px; right: -100px;
    animation-delay: 0s;
  }
  .blob-2 {
    width: 400px; height: 400px;
    background: #818cf8;
    bottom: -100px; left: -100px;
    animation-delay: 3s;
  }
  .blob-3 {
    width: 300px; height: 300px;
    background: #34d399;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: 1.5s;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0) scale(1); }
    50%       { transform: translateY(-30px) scale(1.05); }
  }

  /* ── Grid background ── */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(13,148,136,0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(13,148,136,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }

  /* ── 3D Scene & Card ── */
  .login-scene {
    perspective: 1200px;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    padding: 1rem;
  }

  .login-card {
    background: var(--glass);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1.5px solid rgba(255,255,255,0.8);
    border-radius: 28px;
    padding: 2.8rem 2.5rem;
    box-shadow: var(--shadow), 0 0 0 1px rgba(255,255,255,0.5) inset;
    transform-style: preserve-3d;
    transition: transform 0.1s ease;
    animation: cardIn 0.7s cubic-bezier(0.34,1.56,0.64,1) both;
  }

  @keyframes cardIn {
    from { opacity: 0; transform: translateY(40px) rotateX(8deg) scale(0.95); }
    to   { opacity: 1; transform: translateY(0) rotateX(0) scale(1); }
  }

  /* ── Logo area ── */
  .logo-wrap {
    text-align: center;
    margin-bottom: 2rem;
  }

  .logo-orb {
    width: 76px; height: 76px;
    background: linear-gradient(135deg, var(--teal-1), var(--teal-3));
    border-radius: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
    box-shadow: 0 12px 32px rgba(13,148,136,0.35), 0 0 0 8px rgba(45,212,191,0.12);
    transform: translateZ(30px);
    animation: orbPulse 3s ease-in-out infinite;
  }

  @keyframes orbPulse {
    0%, 100% {
      box-shadow: 0 12px 32px rgba(13,148,136,0.35), 0 0 0 8px rgba(45,212,191,0.12);
    }
    50% {
      box-shadow: 0 12px 40px rgba(13,148,136,0.5), 0 0 0 16px rgba(45,212,191,0.08);
    }
  }

  .logo-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
  }

  .logo-sub {
    font-size: 0.82rem;
    color: #64748b;
    margin-top: 0.25rem;
  }

  /* ── Divider ── */
  .divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1.5rem 0;
    color: #94a3b8;
    font-size: 0.75rem;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,201,177,0.2), transparent);
  }

  /* ── Form elements ── */
  .form-group {
    margin-bottom: 1.2rem;
    position: relative;
  }

  .form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
  }

  .input-wrap {
    position: relative;
  }

  .input-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    pointer-events: none;
    opacity: 0.5;
    transition: opacity 0.2s;
  }

  .form-input {
    width: 100%;
    padding: 0.85rem 2.8rem 0.85rem 1rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    background: rgba(255,255,255,0.8);
    font-family: 'Vazirmatn', sans-serif;
    font-size: 0.95rem;
    color: #1e293b;
    transition: all 0.25s;
    outline: none;
    direction: rtl;
  }

  .form-input:focus {
    border-color: var(--teal-2);
    background: white;
    box-shadow: 0 0 0 4px rgba(20,184,166,0.12);
  }

  .form-input:focus + .input-icon,
  .input-wrap:focus-within .input-icon {
    opacity: 0.9;
  }

  /* ── Toggle password visibility ── */
  .toggle-pass {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.4;
    transition: opacity 0.2s;
    padding: 0;
    line-height: 1;
  }
  .toggle-pass:hover { opacity: 0.8; }

  /* ── Remember me ── */
  .remember-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.2rem;
    font-size: 0.8rem;
  }

  .remember-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #64748b;
    cursor: pointer;
    user-select: none;
  }

  .remember-label input[type="checkbox"] {
    accent-color: var(--teal-2);
    width: 14px;
    height: 14px;
    cursor: pointer;
  }

  .forgot-link {
    color: var(--teal-1);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
  }
  .forgot-link:hover { color: var(--teal-2); }

  /* ── Error message ── */
  .error-msg {
    background: linear-gradient(135deg, #fef2f2, #fff1f2);
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    color: #dc2626;
    font-size: 0.82rem;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: shake 0.4s ease;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25%       { transform: translateX(-6px); }
    75%       { transform: translateX(6px); }
  }

  /* ── Submit button ── */
  .btn-login {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--teal-1), var(--teal-2));
    border: none;
    border-radius: 14px;
    color: white;
    font-family: 'Vazirmatn', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s;
    position: relative;
    overflow: hidden;
    margin-top: 0.5rem;
    letter-spacing: 0.02em;
  }

  .btn-login::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--teal-2), var(--teal-3));
    opacity: 0;
    transition: opacity 0.25s;
  }

  .btn-login:hover::before { opacity: 1; }
  .btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(13,148,136,0.4);
  }
  .btn-login:active { transform: translateY(0); }

  .btn-login span {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }

  /* ── Loading state ── */
  .btn-login.loading .btn-text { display: none; }
  .btn-login .spinner {
    display: none;
    width: 20px; height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }
  .btn-login.loading .spinner { display: block; }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* ── Hint box ── */
  .login-hint {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.8;
  }

  .login-hint code {
    background: rgba(20,184,166,0.1);
    color: var(--teal-1);
    padding: 2px 8px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.8rem;
  }

  /* ── Features strip ── */
  .features-strip {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-top: 1.75rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(0,201,177,0.15);
  }

  .feature-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    color: #94a3b8;
    text-align: center;
  }

  .feature-icon {
    font-size: 1.1rem;
    filter: saturate(0.8);
  }

  /* ── Particles ── */
  .particles {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 1;
  }

  .particle {
    position: absolute;
    border-radius: 50%;
    background: var(--teal-2);
    opacity: 0;
    animation: particle 6s ease-in-out infinite;
  }

  @keyframes particle {
    0%   { opacity: 0; transform: translateY(100vh) scale(0); }
    20%  { opacity: 0.6; }
    80%  { opacity: 0.3; }
    100% { opacity: 0; transform: translateY(-20vh) scale(1.5); }
  }

  /* ── Version badge ── */
  .version-badge {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    font-size: 0.65rem;
    color: rgba(0,0,0,0.25);
    z-index: 20;
  }
</style>
</head>
<body>

<!-- Animated blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Particle container -->
<div class="particles" id="particles"></div>

<!-- Login scene -->
<div class="login-scene" id="loginScene">
  <div class="login-card" id="loginCard">

    <!-- Logo -->
    <div class="logo-wrap">
      <div class="logo-orb">🛒</div>
      <div class="logo-title">ShopAdmin</div>
      <div class="logo-sub">پنل مدیریت فروشگاه آنلاین</div>
    </div>

    <!-- Error message -->
    <?php if ($error): ?>
    <div class="error-msg">
      <span>⚠️</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="login.php" autocomplete="off" id="loginForm">

      <div class="form-group">
        <label class="form-label" for="username">نام کاربری</label>
        <div class="input-wrap">
          <input
            type="text"
            name="username"
            id="username"
            class="form-input"
            placeholder="نام کاربری را وارد کنید"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            required
          >
          <span class="input-icon">👤</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">رمز عبور</label>
        <div class="input-wrap">
          <input
            type="password"
            name="password"
            id="password"
            class="form-input"
            placeholder="رمز عبور را وارد کنید"
            autocomplete="current-password"
            required
            style="padding-left: 2.5rem"
          >
          <span class="input-icon">🔒</span>
          <button type="button" class="toggle-pass" onclick="togglePassword()" title="نمایش/پنهان رمز">
            👁
          </button>
        </div>
      </div>

      <div class="remember-row">
        <label class="remember-label">
          <input type="checkbox" name="remember" value="1">
          مرا به خاطر بسپار
        </label>
        <a href="#" class="forgot-link">فراموشی رمز؟</a>
      </div>

      <button type="submit" class="btn-login" id="submitBtn">
        <span>
          <span class="btn-text">ورود به پنل مدیریت ←</span>
          <div class="spinner"></div>
        </span>
      </button>

    </form>

    <!-- Divider -->
    <div class="divider">اطلاعات ورود پیش‌فرض</div>

    <!-- Hint -->
    <div class="login-hint">
      نام کاربری: <code>admin</code>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      رمز عبور: <code>admin123</code>
    </div>

    <!-- Features strip -->
    <div class="features-strip">
      <div class="feature-item">
        <span class="feature-icon">📦</span>
        مدیریت سفارش
      </div>
      <div class="feature-item">
        <span class="feature-icon">🛍️</span>
        مدیریت محصول
      </div>
      <div class="feature-item">
        <span class="feature-icon">👥</span>
        مدیریت کاربر
      </div>
      <div class="feature-item">
        <span class="feature-icon">📊</span>
        گزارش‌گیری
      </div>
    </div>

  </div>
</div>

<!-- Version badge -->
<div class="version-badge">v2.0 · 2026</div>

<script>
// ── Generate particles ──
const container = document.getElementById('particles');
for (let i = 0; i < 25; i++) {
  const p = document.createElement('div');
  p.className = 'particle';
  p.style.left              = Math.random() * 100 + 'vw';
  p.style.animationDelay    = Math.random() * 6 + 's';
  p.style.animationDuration = (4 + Math.random() * 5) + 's';
  const size = 2 + Math.random() * 5;
  p.style.width  = size + 'px';
  p.style.height = size + 'px';
  p.style.opacity = (0.3 + Math.random() * 0.4).toString();
  container.appendChild(p);
}

// ── 3D tilt effect on card ──
const scene = document.getElementById('loginScene');
const card  = document.getElementById('loginCard');

scene.addEventListener('mousemove', e => {
  const rect = scene.getBoundingClientRect();
  const cx = rect.left + rect.width  / 2;
  const cy = rect.top  + rect.height / 2;
  const rx = (e.clientY - cy) / 20;
  const ry = -(e.clientX - cx) / 20;
  card.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
});

scene.addEventListener('mouseleave', () => {
  card.style.transition = 'transform 0.6s cubic-bezier(0.34,1.56,0.64,1)';
  card.style.transform  = 'rotateX(0) rotateY(0)';
  setTimeout(() => { card.style.transition = 'transform 0.1s ease'; }, 600);
});

// ── Toggle password visibility ──
function togglePassword() {
  const input = document.getElementById('password');
  const btn   = document.querySelector('.toggle-pass');
  if (input.type === 'password') {
    input.type  = 'text';
    btn.textContent = '🙈';
  } else {
    input.type  = 'password';
    btn.textContent = '👁';
  }
}

// ── Loading state on submit ──
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.classList.add('loading');
  btn.disabled = true;
});

// ── Auto-focus username field ──
document.addEventListener('DOMContentLoaded', () => {
  const usernameField = document.getElementById('username');
  if (usernameField && !usernameField.value) {
    usernameField.focus();
  }
});

// ── Keyboard shortcut: Enter submits ──
document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
    document.getElementById('loginForm').requestSubmit();
  }
});
</script>
</body>
</html>
