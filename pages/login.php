<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $role = (string)($_SESSION['user_role'] ?? '');

    if ($role === 'employee') {
        $_SESSION['company'] = 'brainmaster';
        header('Location: neuro_documents.php');
        exit;
    }

    if ($role === 'security_operation') {
        $_SESSION['company'] = 'jubecer';
        header('Location: home.php');
        exit;
    }

    if (!isset($_SESSION['company'])) {
        header('Location: choose_company.php');
        exit;
    }
    header('Location: home.php');
    exit;
}

$hasError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ERMS — Login</title>
<link rel="icon" type="image/svg+xml" href="../assets/img/erms-logo.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<!-- Removed React + in-browser Babel to avoid 'import' transform errors in browser extensions.
  The page now renders server-side HTML so it works without client-side JSX compilation. -->
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --white:#ffffff;
  --gray-25:#fcfcfd;
  --gray-50:#f9fafb;
  --gray-100:#f1f5f9;
  --gray-200:#e2e8f0;
  --gray-300:#cbd5e1;
  --gray-400:#94a3b8;
  --gray-500:#64748b;
  --gray-600:#475569;
  --gray-700:#334155;
  --gray-800:#1e293b;
  --gray-900:#0f172a;
  --navy-50:#eef2ff;
  --navy-100:#e0e7ff;
  --navy-200:#c7d2fe;
  --navy-300:#a5b4fc;
  --navy-400:#818cf8;
  --navy-500:#6366f1;
  --navy-600:#4f46e5;
  --navy-700:#1f3a8a;
  --navy-800:#1e3a8a;
  --error-50:#fef2f2;
  --error-300:#fca5a5;
  --error-500:#ef4444;
  --error-600:#dc2626;
  --success-500:#16a34a;
  --f:'Sora',sans-serif;
  --mono:'JetBrains Mono',monospace;
  --shadow-sm:0 6px 16px rgba(15,23,42,0.08);
  --shadow-md:0 18px 40px rgba(15,23,42,0.12);
}

html,body{height:100%;font-family:var(--f);-webkit-font-smoothing:antialiased;}
#root{min-height:100%;width:100%;display:flex;align-items:center;justify-content:center;}

body{
  background:var(--gray-50);
  display:flex;align-items:center;justify-content:center;
  min-height:100vh;
  overflow-x:hidden;
  overflow-y:auto;
  position:relative;color:var(--gray-900);
}

/* BACKGROUND */
.bg{position:fixed;inset:0;z-index:0;overflow:hidden;background:var(--gray-50);}
.bg-shape{position:absolute;border-radius:999px;opacity:0.65;}
.bg-s1{width:520px;height:520px;background:rgba(79,70,229,0.12);top:-140px;right:-140px;}
.bg-s2{width:480px;height:480px;background:rgba(37,99,235,0.12);bottom:-180px;left:-160px;}
.bg-s3{width:300px;height:300px;background:rgba(224,236,255,0.9);top:45%;left:60%;opacity:0.7;}

/* SPLIT LAYOUT */
.shell{
  position:relative;z-index:1;
  display:flex;width:100%;max-width:1120px;
  align-items:center;gap:36px;
  padding:40px 24px;
}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* LEFT PANEL */
.left{
  flex:1;
  display:flex;flex-direction:column;
  justify-content:center;gap:12px;
  animation:fadeUp .5s ease both;
}

.brand{display:flex;align-items:center;gap:12px;margin-bottom:8px;}
.brand-logos{display:flex;align-items:center;gap:8px;}
.brand-logo{
  width:42px;height:42px;border-radius:12px;
  background:var(--white);border:1px solid var(--gray-200);
  display:flex;align-items:center;justify-content:center;
  box-shadow:var(--shadow-sm);overflow:hidden;
}
.brand-logo img{width:100%;height:100%;object-fit:contain;}
.brand-name{font-size:16px;font-weight:700;color:var(--gray-900);letter-spacing:-0.2px;}
.brand-tag{font-size:12px;color:var(--gray-600);margin-top:2px;font-weight:500;}

.hero-label{
  display:inline-flex;align-items:center;gap:7px;
  background:var(--navy-50);
  border:1px solid var(--navy-200);
  border-radius:20px;padding:5px 12px;
  font-size:11px;font-weight:700;color:var(--navy-700);
  letter-spacing:0.4px;
  text-transform:uppercase;
  width:max-content;
}
.hero-dot{width:6px;height:6px;border-radius:50%;background:var(--navy-600);}

.hero-title{
  font-size:30px;font-weight:800;color:var(--gray-900);
  letter-spacing:-0.6px;line-height:1.15;
}
.hero-title span{
  background:linear-gradient(135deg,var(--navy-600),var(--navy-400));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.hero-sub{
  font-size:14px;color:var(--gray-600);line-height:1.55;
  max-width:420px;font-weight:400;
}

.feature-list{display:grid;gap:8px;margin-top:2px;}
.feat{
  display:flex;align-items:center;gap:12px;
  padding:8px 10px;border-radius:12px;background:var(--white);
  border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);
}
.feat-ico{
  width:32px;height:32px;border-radius:10px;flex-shrink:0;
  background:var(--navy-50);border:1px solid var(--navy-100);
  display:flex;align-items:center;justify-content:center;
  color:var(--navy-700);
}
.feat-ico svg{width:15px;height:15px;}
.feat-text{font-size:12.5px;color:var(--gray-600);font-weight:500;min-width:0;line-height:1.35;}
.feat-text strong{color:var(--gray-900);font-weight:700;}

.preview{
  margin-top:4px;padding:10px;border-radius:16px;
  background:var(--white);border:1px solid var(--gray-200);
  box-shadow:var(--shadow-md);display:grid;gap:8px;
}
.preview-head{display:flex;align-items:center;justify-content:space-between;}
.preview-title{font-size:12.5px;font-weight:700;color:var(--gray-900);}
.preview-tag{font-size:10px;font-weight:700;color:var(--navy-700);background:var(--navy-50);border:1px solid var(--navy-100);padding:3px 7px;border-radius:999px;}
.preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;}
.preview-item{padding:8px;border-radius:12px;background:var(--gray-50);border:1px solid var(--gray-200);}
.preview-label{font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:var(--gray-500);}
.preview-value{margin-top:3px;font-size:12.5px;font-weight:700;color:var(--gray-900);line-height:1.2;}

/* DIVIDER */
.divider{width:1px;background:var(--gray-200);align-self:stretch;margin:0 10px;flex-shrink:0;}

/* RIGHT PANEL (CARD) */
.right{
  width:420px;flex-shrink:0;
  animation:fadeUp .5s .08s ease both;
}
.card{
  background:rgba(255,255,255,0.92);
  border-radius:20px;
  padding:34px 34px 30px;
  border:1px solid var(--gray-200);
  box-shadow:var(--shadow-md);
  backdrop-filter:blur(6px);
}

/* CARD HEADER */
.card-hd{text-align:center;margin-bottom:24px;}
.card-logo{
  width:56px;height:56px;border-radius:16px;
  background:var(--white);margin:0 auto 14px;
  display:flex;align-items:center;justify-content:center;
  border:1px solid var(--gray-200);
  box-shadow:var(--shadow-sm);
}
.card-logo img{width:100%;height:100%;object-fit:contain;}
.card-title{font-size:20px;font-weight:800;color:var(--gray-900);letter-spacing:-0.4px;margin-bottom:4px;}
.card-sub{font-size:13px;color:var(--gray-600);}
.card-pill{
  display:inline-flex;align-items:center;gap:5px;
  background:var(--navy-50);border:1px solid var(--navy-100);
  border-radius:20px;padding:4px 10px;
  font-size:10px;font-weight:700;color:var(--navy-700);
  letter-spacing:0.12em;margin-top:10px;text-transform:uppercase;
}
.card-pill::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--navy-600);}

/* FORM */
.form{display:flex;flex-direction:column;gap:16px;}
.fgrp{display:flex;flex-direction:column;gap:6px;}
.fl{font-size:12px;font-weight:600;color:var(--gray-700);display:flex;align-items:center;justify-content:space-between;}
.helper-row{display:flex;justify-content:flex-end;margin-top:6px;}
.forgot-link{font-size:12px;font-weight:600;color:var(--navy-700);text-decoration:none;transition:color .15s;}
.forgot-link:hover{color:var(--navy-800);}

.inp-wrap{position:relative;}
.inp{
  width:100%;padding:10px 14px;
  background:var(--gray-50);border:1.5px solid var(--gray-200);
  border-radius:12px;color:var(--gray-900);
  font-size:14px;font-family:var(--f);
  outline:none;transition:all .15s;
}
.inp::placeholder{color:var(--gray-400);}
.inp:focus{border-color:var(--navy-600);box-shadow:0 0 0 4px rgba(37,99,235,0.16);background:var(--white);}
.inp.err{border-color:var(--error-500);box-shadow:0 0 0 4px rgba(239,68,68,0.12);}
.inp-mono{font-family:var(--mono);}

.pw-toggle{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  width:34px;height:34px;border-radius:50%;
  background:var(--navy-50);border:1px solid var(--navy-100);
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  color:var(--navy-700);transition:all .15s;
}
.pw-toggle svg{width:16px;height:16px;}
.pw-toggle:hover{background:var(--navy-100);border-color:var(--navy-200);}

.err-msg{
  display:flex;align-items:center;gap:6px;
  font-size:12px;font-weight:600;color:var(--error-600);
  background:var(--error-50);border:1px solid var(--error-300);
  border-radius:10px;padding:8px 12px;
  animation:fadeUp .2s ease;
}
.err-msg svg{width:14px;height:14px;flex-shrink:0;}
.ok-msg{
  display:flex;align-items:center;gap:6px;
  font-size:12px;font-weight:600;color:var(--success-500);
  background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.25);
  border-radius:10px;padding:8px 12px;
  animation:fadeUp .2s ease;
}
.ok-msg svg{width:14px;height:14px;flex-shrink:0;}

/* REMEMBER ROW */
.remember-row{display:flex;align-items:center;justify-content:space-between;}
.cb-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;}
.cb-wrap input[type=checkbox]{width:16px;height:16px;border-radius:4px;accent-color:var(--navy-600);cursor:pointer;}
.cb-lbl{font-size:13px;font-weight:500;color:var(--gray-600);}

/* SIGN IN BUTTON */
.btn-signin{
  width:100%;padding:12px;border-radius:12px;
  background:var(--navy-700);color:#fff;
  font-size:14px;font-weight:700;font-family:var(--f);
  border:none;cursor:pointer;
  transition:all .15s;
  box-shadow:0 10px 20px rgba(31,58,138,0.2);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-signin:hover{background:var(--navy-800);transform:translateY(-1px);box-shadow:0 14px 26px rgba(31,58,138,0.24);}
.btn-signin:active{transform:translateY(0);}
.btn-signin:disabled{opacity:0.65;cursor:default;transform:none;}
.btn-signin svg{width:15px;height:15px;}

/* SPINNER */
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* FOOTER NOTE */
.card-footer{
  margin-top:18px;text-align:center;
  font-size:11px;color:var(--gray-500);
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.card-footer svg{width:12px;height:12px;color:var(--gray-400);}

/* RESPONSIVE */
@media(max-width:1100px){
  .shell{gap:24px;}
  .hero-title{font-size:30px;}
  .right{width:400px;}
}

@media(max-width:980px){
  body{align-items:flex-start;}
  .shell{
    flex-direction:column;
    align-items:stretch;
    justify-content:flex-start;
    gap:18px;
    padding:28px 16px 34px;
  }
  .left,.right{width:100%;}
  .right{order:1;}
  .divider{display:block;width:100%;height:1px;background:var(--gray-200);margin:2px 0;}
  .left{order:2;flex:none;}
  .hero-sub{max-width:none;}
  .right{max-width:520px;margin:0 auto;}
  .left{max-width:760px;margin:0 auto;}
  .card{padding:28px 22px 22px;}
}

@media(max-width:640px){
  .bg-s1{width:420px;height:420px;top:-160px;right:-220px;}
  .bg-s2{width:420px;height:420px;bottom:-220px;left:-240px;}
  .bg-s3{width:240px;height:240px;top:48%;left:52%;}

  .brand{margin-bottom:6px;}
  .brand-logo{width:40px;height:40px;border-radius:12px;}
  .hero-title{font-size:28px;}
  .hero-sub{font-size:14px;}
  .feat{padding:10px;}
  .feat-text{font-size:12.5px;}
  .preview{padding:12px;}
  .preview-grid{grid-template-columns:1fr;}
}

@media(max-width:420px){
  .shell{padding:22px 12px 28px;}
  .hero-title{font-size:26px;}
  .card{border-radius:18px;}
  .card-logo{width:52px;height:52px;border-radius:14px;}
  .card-title{font-size:18px;}
  .inp{padding:10px 12px;}
  .btn-signin{padding:12px;}
}
</style>
</head>
<body>
<div class="bg">
  <div class="bg-shape bg-s1"></div>
  <div class="bg-shape bg-s2"></div>
  <div class="bg-shape bg-s3"></div>
</div>

<!-- Server-rendered login UI (replaces client-side React rendering). -->
<div class="shell">
  <div class="left"> 
    <div class="brand">
      <div class="brand-logos">
        <div class="brand-logo">
          <img src="../assets/img/brainmaster.jpg" alt="Brain Master" />
        </div>
        <div class="brand-logo">
          <img src="../assets/img/erms-logo.svg" alt="ERMS" />
        </div>
      </div>
      <div>
        <div class="brand-name">ERMS</div>
        <div class="brand-tag">Brain Master Diagnostic Center</div>
      </div>
    </div>

    <div class="hero-label">
      <div class="hero-dot"></div>
      SECURE ACCESS
    </div>

    <h1 class="hero-title">
      Efficient resource management<br/>
      for <span>diagnostic workflows.</span>
    </h1>
    <p class="hero-sub">
      Manage neuro and drug test documents, track attendance by batch, and prepare reports with a clear, secure workflow.
    </p>

    <div class="feature-list">
      <div class="feat">
        <div class="feat-ico"></div>
        <div class="feat-text"><strong>Document flow</strong> with consistent templates and validation</div>
      </div>
      <div class="feat">
        <div class="feat-ico"></div>
        <div class="feat-text"><strong>Attendance batches</strong> organized by folder and date</div>
      </div>
      <div class="feat">
        <div class="feat-ico"></div>
        <div class="feat-text"><strong>Reports ready</strong> for printing and compliance needs</div>
      </div>
    </div>

    <div class="preview">
      <div class="preview-head">
        <div class="preview-title">Operational snapshot</div>
        <div class="preview-tag">Today</div>
      </div>
      <div class="preview-grid">
        <div class="preview-item">
          <div class="preview-label">Documents</div>
          <div class="preview-value">Neuro and drug test</div>
        </div>
        <div class="preview-item">
          <div class="preview-label">Attendance</div>
          <div class="preview-value">Batch review ready</div>
        </div>
        <div class="preview-item">
          <div class="preview-label">Reports</div>
          <div class="preview-value">Export and print</div>
        </div>
        <div class="preview-item">
          <div class="preview-label">Security</div>
          <div class="preview-value">Role-based access</div>
        </div>
      </div>
    </div>
  </div>

  <div class="divider"></div>

  <div class="right">
    <div class="card">
      <div class="card-hd">
        <div class="card-logo">
          <img src="../assets/img/erms-logo.svg" alt="ERMS" />
        </div>
        <div class="card-title">Welcome back</div>
        <div class="card-sub">Brain Master Diagnostic Center</div>
        <div class="card-pill">SECURE ACCESS</div>
      </div>

      <form class="form" method="post" action="../auth/authenticate.php">
        <?php if ($hasError): ?>
          <div class="err-msg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Invalid username or password. Please try again.
          </div>
        <?php endif; ?>

        <div class="fgrp">
          <label class="fl">Employee ID</label>
          <div class="inp-wrap">
            <input class="inp inp-mono" placeholder="e.g. 2024-0001" name="username" autocomplete="username" spellcheck="false" required />
          </div>
        </div>

        <div class="fgrp">
          <label class="fl">Password</label>
          <div class="inp-wrap">
            <input class="inp" type="password" placeholder="Your ERMS password" name="password" autocomplete="current-password" style="padding-right:48px" required />
            <button class="pw-toggle" type="button" aria-label="Show password">Show</button>
          </div>
          <div class="helper-row">
            <a class="forgot-link" href="../auth/request_account_setup.php">Forgot password?</a>
          </div>
        </div>

        <div class="remember-row">
          <label class="cb-wrap">
            <input type="checkbox" name="remember" />
            <span class="cb-lbl">Remember me</span>
          </label>
        </div>

        <button class="btn-signin" type="submit">Sign in</button>
      </form>

      <div class="card-footer" style="margin-top:14px">
        <span style="color:var(--gray-400)">New here?</span>
        <a href="#" onclick="document.querySelector('form').action='../auth/request_account_setup.php';return false;" style="color:var(--navy-600);text-decoration:none;font-weight:700;margin-left:8px">Create account</a>
      </div>

      <div class="card-footer" style="margin-top:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:12px;height:12px;margin-right:6px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Encrypted login. Authorized staff only.
      </div>
    </div>
  </div>
</div>

<script>
// Minimal password toggle behavior
document.addEventListener('DOMContentLoaded', function(){
  var pwToggle = document.querySelector('.pw-toggle');
  var pwInput = document.querySelector('input[name="password"]');
  if(pwToggle && pwInput){
    pwToggle.addEventListener('click', function(e){
      e.preventDefault();
      if(pwInput.type === 'password'){
        pwInput.type = 'text';
        pwToggle.textContent = 'Hide';
        pwToggle.setAttribute('aria-label','Hide password');
      } else {
        pwInput.type = 'password';
        pwToggle.textContent = 'Show';
        pwToggle.setAttribute('aria-label','Show password');
      }
    });
  }
});
</script>
</body>
</html>
</body>
</html>
