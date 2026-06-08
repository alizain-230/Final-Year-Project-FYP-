<?php
// index.php - Login Page with 3D Galaxy
require_once 'config/functions.php';
require_once 'config/db.php';

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    redirect('/sms/'.$_SESSION['role'].'/dashboard.php');
}

$err = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role']     ?? '');

    $valid_roles = ['admin','teacher','student','parent'];

    if (!$email || !$password || !in_array($role, $valid_roles)) {
        $err = 'Please fill all fields and select a role.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role=? AND is_active=1");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Update last login
            $pdo->prepare("UPDATE users SET created_at=created_at WHERE id=?")->execute([$user['id']]);

            redirect('/sms/'.$user['role'].'/dashboard.php');
        } else {
            $err = 'Invalid email, password, or role. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — School Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0d1117;font-family:'Inter',sans-serif;overflow:hidden;height:100vh}
canvas{position:fixed;inset:0;z-index:0}
.page{position:fixed;inset:0;z-index:1;display:flex;align-items:center;justify-content:space-between;padding:0 8vw;gap:40px}

/* Left branding */
.brand{flex:1;max-width:420px;animation:slideIn 0.9s ease both}
@keyframes slideIn{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:none}}
.brand-ring{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:28px;font-weight:700;color:#fff;margin-bottom:20px;position:relative;box-shadow:0 0 30px rgba(37,99,235,0.5)}
.brand-ring::after{content:'';position:absolute;inset:-5px;border-radius:50%;border:1.5px solid rgba(96,165,250,0.4);animation:spin 6s linear infinite}
.brand-ring::before{content:'';position:absolute;inset:-11px;border-radius:50%;border:1px dashed rgba(96,165,250,0.18);animation:spin 14s linear infinite reverse}
@keyframes spin{to{transform:rotate(360deg)}}
.brand h3{font-size:11px;letter-spacing:3px;color:rgba(139,148,158,0.7);text-transform:uppercase;margin-bottom:6px}
.brand h1{font-family:'Orbitron',sans-serif;font-size:26px;font-weight:700;color:#e6edf3;margin-bottom:8px;line-height:1.2}
.brand p{font-size:13px;color:rgba(139,148,158,0.75);line-height:1.65;margin-bottom:24px}
.brand ul{list-style:none}
.brand ul li{font-size:12.5px;color:rgba(139,148,158,0.65);padding:4px 0;padding-left:14px;position:relative}
.brand ul li::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:5px;height:5px;border-radius:50%;background:#3b82f6;box-shadow:0 0 6px #3b82f6}
.sdg-row{display:flex;gap:7px;margin-top:22px;flex-wrap:wrap}
.sdg{padding:4px 11px;border-radius:20px;font-size:9px;font-weight:600;letter-spacing:0.8px;border:1px solid}
.sdg-4{background:rgba(37,99,235,0.12);border-color:rgba(37,99,235,0.3);color:#60a5fa}
.sdg-9{background:rgba(124,58,237,0.12);border-color:rgba(124,58,237,0.3);color:#a78bfa}
.sdg-12{background:rgba(5,150,105,0.12);border-color:rgba(5,150,105,0.3);color:#34d399}

/* Login card */
.card{width:400px;flex-shrink:0;background:rgba(22,27,34,0.85);border:1px solid rgba(48,54,61,0.8);border-radius:16px;padding:36px 32px;backdrop-filter:blur(18px);box-shadow:0 20px 60px rgba(0,0,0,0.5);animation:cardUp 0.9s ease 0.15s both}
@keyframes cardUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:none}}
.card-head{text-align:center;margin-bottom:22px}
.card-head h2{font-family:'Orbitron',sans-serif;font-size:15px;color:#60a5fa;letter-spacing:1.5px;text-shadow:0 0 16px rgba(96,165,250,0.35)}
.roles{display:flex;gap:4px;background:rgba(255,255,255,0.04);border-radius:8px;padding:4px;margin-bottom:20px}
.role-btn{flex:1;padding:7px 0;border:none;background:transparent;color:rgba(139,148,158,0.6);font-size:11px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;border-radius:5px;cursor:pointer;transition:0.2s;font-family:'Inter',sans-serif}
.role-btn.active{background:rgba(37,99,235,0.25);color:#93c5fd}
.role-btn:hover:not(.active){color:rgba(230,237,243,0.8)}
.field{margin-bottom:14px}
.field label{display:block;font-size:10px;font-weight:600;color:rgba(139,148,158,0.7);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px}
.field input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(48,54,61,0.9);border-radius:8px;padding:10px 13px;color:#e6edf3;font-size:13px;outline:none;transition:0.2s;font-family:'Inter',sans-serif}
.field input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.15)}
.field input::placeholder{color:rgba(72,79,88,0.8)}
.submit-btn{width:100%;padding:12px;border:none;border-radius:8px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;font-family:'Orbitron',sans-serif;font-size:11px;font-weight:700;letter-spacing:2px;cursor:pointer;box-shadow:0 4px 18px rgba(37,99,235,0.35);transition:0.2s;margin-top:4px}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(37,99,235,0.5)}
.err-box{background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.3);border-radius:7px;padding:9px 13px;color:#f87171;font-size:12px;margin-bottom:14px;text-align:center}
.divider{border:none;border-top:1px solid rgba(48,54,61,0.7);margin:18px 0}
.mini-stats{display:flex;gap:8px}
.mini-stat{flex:1;background:rgba(255,255,255,0.03);border:1px solid rgba(48,54,61,0.6);border-radius:8px;padding:9px;text-align:center}
.mini-num{font-family:'Orbitron',sans-serif;font-size:16px;font-weight:700;color:#60a5fa}
.mini-lbl{font-size:9px;color:rgba(139,148,158,0.5);text-transform:uppercase;letter-spacing:0.5px;margin-top:2px}
.status-line{text-align:center;margin-top:14px;font-size:10px;color:rgba(72,79,88,0.7);letter-spacing:0.8px;text-transform:uppercase}
.dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:#22c55e;box-shadow:0 0 6px #22c55e;margin-right:4px;animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
@media(max-width:820px){.brand{display:none}.page{justify-content:center}}
</style>
</head>
<body>
<canvas id="c"></canvas>

<div class="page">
  <!-- Left: Branding -->
  <div class="brand">
    <div class="brand-ring">S</div>
    <h3>University of Agriculture, Faisalabad — TTS</h3>
    <h1>School Management System</h1>
    <p>A complete web-based platform for automating student records, faculty management, attendance tracking, and academic reporting.</p>
    <ul>
      <li>4 User Roles: Admin, Teacher, Student, Parent</li>
      <li>Real-time Attendance Tracking</li>
      <li>Auto-generated Broadsheet & Result Cards</li>
      <li>Weekly Timetable with Conflict Detection</li>
      <li>Chart.js Analytics Dashboards</li>
      <li>Parent Notification System</li>
    </ul>
    <div class="sdg-row">
      <span class="sdg sdg-4">SDG 4 — Quality Education</span>
      <span class="sdg sdg-9">SDG 9 — Innovation</span>
      <span class="sdg sdg-12">SDG 12 — Sustainability</span>
    </div>
  </div>

  <!-- Right: Login Card -->
  <div class="card">
    <div class="card-head">
      <h2>ACCESS PORTAL</h2>
    </div>

    <?php if ($err): ?>
    <div class="err-box"><?= e($err) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
    <div class="err-box">Access denied. Please login with correct credentials.</div>
    <?php endif; ?>

    <!-- Role tabs -->
    <div class="roles">
      <button class="role-btn active" onclick="setRole('admin',this)">Admin</button>
      <button class="role-btn" onclick="setRole('teacher',this)">Teacher</button>
      <button class="role-btn" onclick="setRole('student',this)">Student</button>
      <button class="role-btn" onclick="setRole('parent',this)">Parent</button>
    </div>

    <form method="POST">
      <input type="hidden" name="role" id="roleField" value="admin">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="Enter your email" autocomplete="email">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
      <button type="submit" class="submit-btn">ACCESS SYSTEM →</button>
    </form>

    <hr class="divider">
    <div class="mini-stats">
      <div class="mini-stat"><div class="mini-num">4</div><div class="mini-lbl">Students</div></div>
      <div class="mini-stat"><div class="mini-num">3</div><div class="mini-lbl">Teachers</div></div>
      <div class="mini-stat"><div class="mini-num">5</div><div class="mini-lbl">Classes</div></div>
    </div>
    <div class="status-line"><span class="dot"></span>System Online &nbsp;|&nbsp; FYP 2026 &nbsp;|&nbsp; Ali Zain</div>
  </div>
</div>

<script>
// Role tab switcher
function setRole(r, el) {
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('roleField').value = r;
}

// 3D Particle Galaxy
const canvas = document.getElementById('c');
const ctx = canvas.getContext('2d');
let W, H, stars = [];

function resize() { W = canvas.width = innerWidth; H = canvas.height = innerHeight; }
resize();
window.addEventListener('resize', resize);

let mx = W/2, my = H/2;
window.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });

class Star {
  constructor() { this.init(); }
  init() {
    this.r  = 30 + Math.pow(Math.random(), 0.45) * (Math.min(W,H) * 0.56);
    this.a  = Math.random() * Math.PI * 2;
    this.sp = (0.00006 + Math.random() * 0.0002) * (Math.random() > 0.5 ? 1 : -1);
    this.sz = Math.random() * 1.8 + 0.3;
    this.tw = Math.random() * Math.PI * 2;
    this.ts = 0.015 + Math.random() * 0.025;
    this.al = Math.random() * 0.7 + 0.2;
    const h = [200,215,230,250,190][Math.floor(Math.random()*5)];
    this.col = `hsl(${h},75%,${60+Math.random()*30}%)`;
  }
  update() {
    this.a  += this.sp * (1 + this.r * 0.001);
    this.tw += this.ts;
    const ox = (mx - W/2) * 0.01 * (this.r/300);
    const oy = (my - H/2) * 0.01 * (this.r/300);
    this.x = W/2 + ox + Math.cos(this.a) * this.r;
    this.y = H/2 + oy + Math.sin(this.a) * this.r;
    this.ca = this.al * (0.5 + 0.5 * Math.sin(this.tw));
  }
  draw() {
    ctx.globalAlpha = this.ca;
    ctx.fillStyle   = this.col;
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.sz, 0, Math.PI*2);
    ctx.fill();
    if (this.sz > 1.2) {
      const g = ctx.createRadialGradient(this.x,this.y,0,this.x,this.y,this.sz*3);
      g.addColorStop(0, this.col.replace('hsl','hsla').replace(')',',0.2)'));
      g.addColorStop(1,'transparent');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(this.x,this.y,this.sz*3,0,Math.PI*2); ctx.fill();
    }
    ctx.globalAlpha = 1;
  }
}

// Nebula clouds
function drawNebula(rx,ry,r,h) {
  const x=rx*W, y=ry*H, rad=r*Math.min(W,H);
  const g = ctx.createRadialGradient(x,y,0,x,y,rad);
  g.addColorStop(0,   `hsla(${h},65%,35%,0.07)`);
  g.addColorStop(0.5, `hsla(${h},55%,25%,0.03)`);
  g.addColorStop(1,   'transparent');
  ctx.fillStyle = g;
  ctx.beginPath(); ctx.arc(x,y,rad,0,Math.PI*2); ctx.fill();
}

for (let i = 0; i < 700; i++) stars.push(new Star());

function animate() {
  requestAnimationFrame(animate);
  ctx.fillStyle = 'rgba(13,17,23,0.18)';
  ctx.fillRect(0,0,W,H);
  drawNebula(0.3,0.4,0.26,220);
  drawNebula(0.72,0.58,0.2,260);
  drawNebula(0.5,0.5,0.36,200);
  drawNebula(0.15,0.75,0.18,180);
  stars.forEach(s => { s.update(); s.draw(); });
  // Core glow
  const cg = ctx.createRadialGradient(W/2,H/2,0,W/2,H/2,120);
  cg.addColorStop(0,'rgba(30,90,255,0.1)');
  cg.addColorStop(1,'transparent');
  ctx.beginPath(); ctx.arc(W/2,H/2,120,0,Math.PI*2);
  ctx.fillStyle = cg; ctx.fill();
}
animate();
</script>
</body>
</html>
