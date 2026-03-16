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
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --white:#ffffff;
  --gray-25:#FCFCFD;
  --gray-50:#F9FAFB;
  --gray-100:#F2F4F7;
  --gray-200:#E4E7EC;
  --gray-300:#D0D5DD;
  --gray-400:#98A2B3;
  --gray-500:#667085;
  --gray-600:#475467;
  --gray-700:#344054;
  --gray-800:#1D2939;
  --gray-900:#101828;
  --navy-50:#EEF4FF;
  --navy-100:#E0EAFF;
  --navy-200:#C7D7FD;
  --navy-300:#A4BCFD;
  --navy-400:#8098F9;
  --navy-500:#6172F3;
  --navy-600:#444CE7;
  --navy-700:#3538CD;
  --navy-800:#2D31A6;
  --navy-900:#1F2370;
  --error-50:#FEF3F2;
  --error-300:#FDA29B;
  --error-500:#F04438;
  --error-600:#D92D20;
  --success-500:#12B76A;
  --f:'Plus Jakarta Sans',sans-serif;
  --mono:'JetBrains Mono',monospace;
}

html,body,#root{height:100%;font-family:var(--f);-webkit-font-smoothing:antialiased;}

body{
  background:var(--gray-900);
  display:flex;align-items:center;justify-content:center;
  min-height:100vh;overflow:hidden;position:relative;
}

/* ── BACKGROUND ── */
.bg{
  position:fixed;inset:0;z-index:0;
  background:var(--gray-900);
  overflow:hidden;
}
.bg-grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(97,114,243,0.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(97,114,243,0.04) 1px,transparent 1px);
  background-size:40px 40px;
}
.bg-glow-1{
  position:absolute;
  width:600px;height:600px;border-radius:50%;
  background:radial-gradient(circle,rgba(53,56,205,0.18) 0%,transparent 70%);
  top:-100px;left:-100px;
}
.bg-glow-2{
  position:absolute;
  width:500px;height:500px;border-radius:50%;
  background:radial-gradient(circle,rgba(97,114,243,0.12) 0%,transparent 70%);
  bottom:-80px;right:-80px;
}
.bg-glow-3{
  position:absolute;
  width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(124,58,237,0.08) 0%,transparent 70%);
  top:40%;left:60%;
}

/* ── SPLIT LAYOUT ── */
.shell{
  position:relative;z-index:1;
  display:flex;width:100%;max-width:1100px;
  min-height:100vh;
  align-items:center;
  padding:40px 24px;
}

/* ── LEFT PANEL ── */
.left{
  flex:1;padding:40px 60px 40px 20px;
  display:flex;flex-direction:column;justify-content:center;
  animation:fadeUp .5s ease both;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

.brand{display:flex;align-items:center;gap:12px;margin-bottom:48px;}
.brand-logo{
  width:40px;height:40px;border-radius:10px;
  background:var(--navy-700);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:17px;color:#fff;
  box-shadow:0 0 0 1px rgba(255,255,255,0.12),0 4px 12px rgba(53,56,205,0.5);
}
.brand-name{font-size:17px;font-weight:700;color:#fff;letter-spacing:-0.3px;}
.brand-tag{font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;font-weight:500;}

.hero-label{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(97,114,243,0.12);
  border:1px solid rgba(97,114,243,0.25);
  border-radius:20px;padding:5px 12px;
  font-size:11px;font-weight:600;color:var(--navy-400);
  letter-spacing:0.5px;margin-bottom:22px;
  font-family:var(--mono);
}
.hero-dot{width:6px;height:6px;border-radius:50%;background:var(--navy-500);}

.hero-title{
  font-size:38px;font-weight:800;color:#fff;
  letter-spacing:-1.5px;line-height:1.15;margin-bottom:16px;
}
.hero-title span{
  background:linear-gradient(135deg,var(--navy-400),var(--navy-300));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.hero-sub{
  font-size:15px;color:rgba(255,255,255,0.4);line-height:1.6;
  max-width:360px;margin-bottom:40px;font-weight:400;
}

.feature-list{display:flex;flex-direction:column;gap:12px;}
.feat{
  display:flex;align-items:center;gap:12px;
}
.feat-ico{
  width:32px;height:32px;border-radius:8px;flex-shrink:0;
  background:rgba(97,114,243,0.1);border:1px solid rgba(97,114,243,0.15);
  display:flex;align-items:center;justify-content:center;
}
.feat-ico svg{width:14px;height:14px;color:var(--navy-400);}
.feat-text{font-size:13px;color:rgba(255,255,255,0.5);font-weight:500;}
.feat-text strong{color:rgba(255,255,255,0.8);font-weight:600;}

/* ── DIVIDER ── */
.divider{
  width:1px;background:rgba(255,255,255,0.06);
  align-self:stretch;margin:0 20px;
  flex-shrink:0;
}

/* ── RIGHT PANEL (CARD) ── */
.right{
  width:420px;flex-shrink:0;
  animation:fadeUp .5s .1s ease both;
}
.card{
  background:var(--white);
  border-radius:20px;
  padding:36px 36px 32px;
  box-shadow:0 25px 50px rgba(0,0,0,0.4),0 0 0 1px rgba(255,255,255,0.05);
}

/* CARD HEADER */
.card-hd{text-align:center;margin-bottom:28px;}
.card-logo{
  width:52px;height:52px;border-radius:14px;
  background:var(--navy-700);margin:0 auto 16px;
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:22px;color:#fff;
  box-shadow:0 4px 16px rgba(53,56,205,0.4);
}
.card-title{font-size:20px;font-weight:800;color:var(--gray-900);letter-spacing:-0.5px;margin-bottom:4px;}
.card-sub{font-size:13px;color:var(--gray-500);}
.card-pill{
  display:inline-flex;align-items:center;gap:5px;
  background:var(--navy-50);border:1px solid var(--navy-200);
  border-radius:20px;padding:3px 10px;
  font-size:10px;font-weight:700;color:var(--navy-700);
  letter-spacing:1px;margin-top:10px;font-family:var(--mono);
}
.card-pill::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--navy-600);}

/* FORM */
.form{display:flex;flex-direction:column;gap:16px;}
.fgrp{display:flex;flex-direction:column;gap:6px;}
.fl{font-size:12px;font-weight:600;color:var(--gray-700);display:flex;align-items:center;justify-content:space-between;}
.fl a{font-size:12px;font-weight:600;color:var(--navy-600);text-decoration:none;transition:color .15s;}
.fl a:hover{color:var(--navy-700);}

.inp-wrap{position:relative;}
.inp{
  width:100%;padding:10px 14px;
  background:var(--white);border:1.5px solid var(--gray-300);
  border-radius:10px;color:var(--gray-900);
  font-size:14px;font-family:var(--f);
  outline:none;transition:all .15s;
}
.inp::placeholder{color:var(--gray-400);}
.inp:focus{border-color:var(--navy-500);box-shadow:0 0 0 4px rgba(97,114,243,0.1);}
.inp.err{border-color:var(--error-500);box-shadow:0 0 0 4px rgba(240,68,56,0.1);}
.inp-mono{font-family:var(--mono);}

.pw-toggle{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  font-size:12px;font-weight:600;color:var(--navy-600);
  padding:4px 8px;border-radius:6px;transition:all .15s;
}
.pw-toggle:hover{background:var(--navy-50);color:var(--navy-700);}

.err-msg{
  display:flex;align-items:center;gap:5px;
  font-size:12px;font-weight:500;color:var(--error-600);
  background:var(--error-50);border:1px solid var(--error-300);
  border-radius:8px;padding:8px 12px;
  animation:fadeUp .2s ease;
}
.err-msg svg{width:14px;height:14px;flex-shrink:0;}

/* REMEMBER ROW */
.remember-row{display:flex;align-items:center;justify-content:space-between;}
.cb-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;}
.cb-wrap input[type=checkbox]{
  width:16px;height:16px;border-radius:4px;
  accent-color:var(--navy-600);cursor:pointer;
}
.cb-lbl{font-size:13px;font-weight:500;color:var(--gray-600);}

/* SIGN IN BUTTON */
.btn-signin{
  width:100%;padding:12px;border-radius:10px;
  background:var(--navy-700);color:#fff;
  font-size:14px;font-weight:700;font-family:var(--f);
  border:none;cursor:pointer;
  transition:all .15s;
  box-shadow:0 1px 3px rgba(53,56,205,0.3),0 4px 12px rgba(53,56,205,0.2);
  display:flex;align-items:center;justify-content:center;gap:8px;
  position:relative;overflow:hidden;
}
.btn-signin:hover{background:var(--navy-800);transform:translateY(-1px);box-shadow:0 2px 6px rgba(53,56,205,0.35),0 8px 20px rgba(53,56,205,0.25);}
.btn-signin:active{transform:translateY(0);}
.btn-signin:disabled{opacity:0.65;cursor:default;transform:none;}
.btn-signin svg{width:15px;height:15px;}

/* SPINNER */
.spinner{
  width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);
  border-top-color:#fff;border-radius:50%;
  animation:spin .6s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* FOOTER NOTE */
.card-footer{
  margin-top:20px;text-align:center;
  font-size:11px;color:var(--gray-400);
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.card-footer svg{width:12px;height:12px;color:var(--gray-400);}

/* BOTTOM NOTE */
.bottom-note{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
  font-size:11px;color:rgba(255,255,255,0.2);
  font-family:var(--mono);z-index:10;white-space:nowrap;
}

/* RESPONSIVE */
@media(max-width:860px){
  .left{display:none;}
  .divider{display:none;}
  .shell{justify-content:center;}
  .right{width:100%;max-width:400px;}
}
</style>
</head>
<body>
<div class="bg">
  <div class="bg-grid"></div>
  <div class="bg-glow-1"></div>
  <div class="bg-glow-2"></div>
  <div class="bg-glow-3"></div>
</div>

<div id="root"></div>

<div class="bottom-note">ERMS v2.0 · Jubecer Security · Activity is logged</div>

<script>
  window.__ERMS_LOGIN__ = { hasError: <?php echo $hasError ? 'true' : 'false'; ?> };
</script>

<script type="text/babel">
const {useState}=React;

const IcShield=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>;
const IcEye=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>;
const IcEyeOff=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>;
const IcArrow=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>;
const IcAlert=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>;
const IcCheck=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>;
const IcLock=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>;
const IcUsers=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>;
const IcActivity=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>;

function LoginPage(){
  const [id,setId]=useState('');
  const [pw,setPw]=useState('');
  const [showPw,setShowPw]=useState(false);
  const [error,setError]=useState((window.__ERMS_LOGIN__&&window.__ERMS_LOGIN__.hasError)?'Invalid username or password. Please try again.':'');

  return(
    <div className="shell">
      {/* LEFT PANEL */}
      <div className="left">
        <div className="brand">
          <div className="brand-logo">E</div>
          <div>
            <div className="brand-name">ERMS</div>
            <div className="brand-tag">Employee Requirements Management System</div>
          </div>
        </div>

        <div className="hero-label">
          <div className="hero-dot"></div>
          SECURE ACCESS PORTAL
        </div>

        <h1 className="hero-title">
          Guard compliance,<br/>
          <span>simplified.</span>
        </h1>
        <p className="hero-sub">
          A centralized platform for managing security guard requirements, license monitoring, and compliance tracking across all agencies.
        </p>

        <div className="feature-list">
          {[
            {icon:<IcUsers/>,text:<><strong>151 Guards</strong> across 4 agencies monitored</>},
            {icon:<IcActivity/>,text:<><strong>Real-time</strong> license expiry tracking &amp; alerts</>},
            {icon:<IcLock/>,text:<><strong>Role-based access</strong> with full audit logging</>},
          ].map((f,i)=>(
            <div className="feat" key={i}>
              <div className="feat-ico">{f.icon}</div>
              <div className="feat-text">{f.text}</div>
            </div>
          ))}
        </div>
      </div>

      {/* DIVIDER */}
      <div className="divider"/>

      {/* RIGHT PANEL */}
      <div className="right">
        <div className="card">
          <div className="card-hd">
            <div className="card-logo">E</div>
            <div className="card-title">Welcome back</div>
            <div className="card-sub">Brain Master · Jubecer</div>
            <div className="card-pill">SECURE ACCESS</div>
          </div>

          <form className="form" method="post" action="../auth/authenticate.php">
            {error&&(
              <div className="err-msg">
                <IcAlert/>
                {error}
              </div>
            )}

            <div className="fgrp">
              <label className="fl">Employee ID</label>
              <div className="inp-wrap">
                <input
                  className={`inp inp-mono${error&&!id?' err':''}`}
                  placeholder="e.g. 2024-0001"
                  name="username"
                  value={id}
                  onChange={e=>{setId(e.target.value);if(error)setError('');}}
                  autoComplete="username"
                  spellCheck={false}
                  required
                />
              </div>
            </div>

            <div className="fgrp">
              <label className="fl">
                Password
                <a href="#">Forgot password?</a>
              </label>
              <div className="inp-wrap">
                <input
                  className={`inp${error&&!pw?' err':''}`}
                  type={showPw?'text':'password'}
                  placeholder="Your ERMS password"
                  name="password"
                  value={pw}
                  onChange={e=>{setPw(e.target.value);if(error)setError('');}}
                  autoComplete="current-password"
                  style={{paddingRight:70}}
                  required
                />
                <button className="pw-toggle" onClick={()=>setShowPw(v=>!v)} type="button">
                  {showPw?'Hide':'Show'}
                </button>
              </div>
            </div>

            <div className="remember-row">
              <label className="cb-wrap">
                <input type="checkbox" name="remember" />
                <span className="cb-lbl">Remember me</span>
              </label>
            </div>

            <button className="btn-signin" type="submit">
              Sign in <IcArrow/>
            </button>
          </form>

          <div className="card-footer">
            <IcLock/>
            Authorized staff only. Activity is logged.
          </div>
        </div>
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<LoginPage/>);
</script>
</body>
</html>
