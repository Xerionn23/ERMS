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
<script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
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

<div id="root"></div>

<script>
  window.__ERMS_LOGIN__ = { hasError: <?php echo $hasError ? 'true' : 'false'; ?> };
</script>

<script type="text/babel">
const {useState,useEffect}=React;

const IcShield=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>;
const IcEye=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>;
const IcEyeOff=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>;
const IcArrow=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>;
const IcAlert=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>;
const IcCheck=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>;
const IcLock=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>;
const IcUsers=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>;
const IcActivity=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>;
const IcDoc=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M7 3h10"/><path d="M7 7h10"/><path d="M7 11h10"/><path d="M7 15h7"/><path d="M6 3h-1a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/></svg>;
const IcCalendar=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>;
const IcReport=()=><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-7"/></svg>;

function LoginPage(){
  const [id,setId]=useState('');
  const [pw,setPw]=useState('');
  const [showPw,setShowPw]=useState(false);
  const [error,setError]=useState((window.__ERMS_LOGIN__&&window.__ERMS_LOGIN__.hasError)?'Invalid username or password. Please try again.':'');
  const [view,setView]=useState('login');
  const [setupId,setSetupId]=useState('');
  const [setupMode,setSetupMode]=useState('create');
  const [setupState,setSetupState]=useState({sending:false,type:'',msg:''});
  const [cooldown,setCooldown]=useState(0);

  useEffect(()=>{
    if(cooldown<=0)return;
    const t=setTimeout(()=>setCooldown(v=>Math.max(0,v-1)),1000);
    return()=>clearTimeout(t);
  },[cooldown]);

  const sendSetup=async()=>{
    if(!setupId.trim()){
      setSetupState({sending:false,type:'err',msg:'Please enter your User ID.'});
      return;
    }
    setSetupState({sending:true,type:'',msg:''});
    try{
      const fd=new FormData();
      fd.append('employee_id',setupId.trim());
      fd.append('intent',setupMode==='reset'?'reset':'create');
      const r=await fetch('../auth/request_account_setup.php',{method:'POST',body:fd,credentials:'same-origin'});
      const j=await r.json().catch(()=>null);
      if(!r.ok||!j||j.ok!==true){
        if(r&&r.status===429){
          setCooldown(15);
        }
        throw new Error((j&&j.error)?j.error:'Request failed.');
      }
      setSetupState({sending:false,type:'ok',msg:String(j.message||'Setup link sent. Please check your email.')});
      setCooldown(15);
    }catch(e){
      setSetupState({sending:false,type:'err',msg:String(e&&e.message?e.message:e)});
    }
  };

  return(
    <div className="shell">
      {/* LEFT PANEL */}
      <div className="left">
        <div className="brand">
          <div className="brand-logos">
            <div className="brand-logo">
              <img src="../assets/img/brainmaster.jpg" alt="Brain Master" />
            </div>
            <div className="brand-logo">
              <img src="../assets/img/erms-logo.svg" alt="ERMS" />
            </div>
          </div>
          <div>
            <div className="brand-name">ERMS</div>
            <div className="brand-tag">Brain Master Diagnostic Center</div>
          </div>
        </div>

        <div className="hero-label">
          <div className="hero-dot"></div>
          SECURE ACCESS
        </div>

        <h1 className="hero-title">
          Efficient resource management<br/>
          for <span>diagnostic workflows.</span>
        </h1>
        <p className="hero-sub">
          Manage neuro and drug test documents, track attendance by batch, and prepare reports with a clear, secure workflow.
        </p>

        <div className="feature-list">
          {[
            {icon:<IcDoc/>,text:<><strong>Document flow</strong> with consistent templates and validation</>},
            {icon:<IcCalendar/>,text:<><strong>Attendance batches</strong> organized by folder and date</>},
            {icon:<IcReport/>,text:<><strong>Reports ready</strong> for printing and compliance needs</>},
          ].map((f,i)=>(
            <div className="feat" key={i}>
              <div className="feat-ico">{f.icon}</div>
              <div className="feat-text">{f.text}</div>
            </div>
          ))}
        </div>

        <div className="preview">
          <div className="preview-head">
            <div className="preview-title">Operational snapshot</div>
            <div className="preview-tag">Today</div>
          </div>
          <div className="preview-grid">
            <div className="preview-item">
              <div className="preview-label">Documents</div>
              <div className="preview-value">Neuro and drug test</div>
            </div>
            <div className="preview-item">
              <div className="preview-label">Attendance</div>
              <div className="preview-value">Batch review ready</div>
            </div>
            <div className="preview-item">
              <div className="preview-label">Reports</div>
              <div className="preview-value">Export and print</div>
            </div>
            <div className="preview-item">
              <div className="preview-label">Security</div>
              <div className="preview-value">Role-based access</div>
            </div>
          </div>
        </div>
      </div>

      {/* DIVIDER */}
      <div className="divider"/>

      {/* RIGHT PANEL */}
      <div className="right">
        <div className="card">
          <div className="card-hd">
            <div className="card-logo">
              <img src="../assets/img/erms-logo.svg" alt="ERMS" />
            </div>
            <div className="card-title">
              {view==='login' ? 'Welcome back' : (setupMode==='reset' ? 'Reset password' : 'Create account')}
            </div>
            <div className="card-sub">
              {view==='login'
                ? 'Brain Master Diagnostic Center'
                : (setupMode==='reset'
                  ? 'We will send a secure reset link to your Gmail'
                  : 'We will send a secure setup link to your Gmail')
              }
            </div>
            <div className="card-pill">SECURE ACCESS</div>
          </div>

          {view==='login' ? (
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
              <label className="fl">Password</label>
              <div className="inp-wrap">
                <input
                  className={`inp${error&&!pw?' err':''}`}
                  type={showPw?'text':'password'}
                  placeholder="Your ERMS password"
                  name="password"
                  value={pw}
                  onChange={e=>{setPw(e.target.value);if(error)setError('');}}
                  autoComplete="current-password"
                  style={{paddingRight:48}}
                  required
                />
                <button
                  className="pw-toggle"
                  onClick={()=>setShowPw(v=>!v)}
                  type="button"
                  aria-label={showPw?'Hide password':'Show password'}
                >
                  {showPw?<IcEye/>:<IcEyeOff/>}
                </button>
              </div>
              <div className="helper-row">
                <a
                  className="forgot-link"
                  href="#"
                  onClick={e=>{e.preventDefault();setView('setup');setSetupMode('reset');setSetupId('');setSetupState({sending:false,type:'',msg:''});}}
                >
                  Forgot password?
                </a>
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
          ) : (
          <div className="form">
            {setupState.msg&&(
              <div className={setupState.type==='ok'?'ok-msg':'err-msg'}>
                {setupState.type==='ok'?<IcCheck/>:<IcAlert/>}
                {setupState.msg}
              </div>
            )}

            {(!setupState.msg || setupState.type!=='ok')&&(
              <div className="card-footer" style={{marginTop:0}}>
                <IcUsers/>
                {setupMode==='reset'
                  ? 'Enter your User ID. We will send a secure reset link to your Gmail on file.'
                  : 'Enter your User ID. We will send a secure setup link to your Gmail on file.'
                }
              </div>
            )}

            <div className="fgrp">
              <label className="fl">User ID</label>
              <div className="inp-wrap">
                <input
                  className="inp inp-mono"
                  placeholder="e.g. 2024-0001"
                  value={setupId}
                  onChange={e=>{setSetupId(e.target.value);}}
                  autoComplete="username"
                  spellCheck={false}
                  disabled={setupState.sending}
                  required
                />
              </div>
            </div>

            <button className="btn-signin" type="button" onClick={sendSetup} disabled={setupState.sending||cooldown>0}>
              {setupState.sending
                ?(<><span className="spinner"/> Sending…</>)
                :(cooldown>0
                  ?(<>Resend in {cooldown}s</>)
                  :(<>Send setup link <IcArrow/></>)
                )
              }
            </button>

            {cooldown>0&&(
              <div className="card-footer" style={{marginTop:8}}>
                <IcLock/>
                Please wait before requesting another email.
              </div>
            )}
          </div>
          )}

          <div className="card-footer" style={{marginTop:14}}>
            {view==='login' ? (
              <>
                <span style={{color:'var(--gray-400)'}}>New here?</span>
                <a href="#" onClick={e=>{e.preventDefault();setView('setup');setSetupMode('create');setSetupId('');setSetupState({sending:false,type:'',msg:''});}} style={{color:'var(--navy-600)',textDecoration:'none',fontWeight:700}}>Create account</a>
              </>
            ) : (
              <>
                <a href="#" onClick={e=>{e.preventDefault();setView('login');setSetupId('');setSetupState({sending:false,type:'',msg:''});}} style={{color:'var(--navy-600)',textDecoration:'none',fontWeight:700}}>Back to sign in</a>
              </>
            )}
          </div>

          {view==='login'&&(
            <div className="card-footer">
              <IcLock/>
              Encrypted login. Authorized staff only.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<LoginPage/>);
</script>
</body>
</html>
