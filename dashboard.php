<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Dashboard';

// Live stats
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$totalClasses  = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalParents  = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();

// Today's attendance
$today = date('Y-m-d');
$att   = $pdo->prepare("SELECT COUNT(*) t, SUM(status='Present') p FROM attendance WHERE date=?");
$att->execute([$today]);
$att = $att->fetch();
$attPct = $att['t'] > 0 ? round($att['p']/$att['t']*100) : 0;

// 30-day attendance trend
$trend = $pdo->query("
  SELECT date,
    ROUND(SUM(status='Present')/COUNT(*)*100,1) AS pct
  FROM attendance
  WHERE date >= DATE_SUB(CURDATE(),INTERVAL 29 DAY)
  GROUP BY date ORDER BY date
")->fetchAll();
$tLabels = array_map(fn($r)=>date('d M',strtotime($r['date'])), $trend);
$tData   = array_column($trend,'pct');

// Grade distribution
$grades = $pdo->query("SELECT grade, COUNT(*) n FROM marks WHERE grade IS NOT NULL GROUP BY grade ORDER BY FIELD(grade,'A+','A','B','C','D','F')")->fetchAll();
$gLabels = array_column($grades,'grade');
$gData   = array_column($grades,'n');

// Recent students
$recent = $pdo->query("SELECT s.*, c.name cn, c.section cs FROM students s LEFT JOIN classes c ON s.class_id=c.id ORDER BY s.id DESC LIMIT 6")->fetchAll();

// Unread notifications
$unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE to_id=? AND is_read=0");
$unread->execute([$_SESSION['user_id']]);
$unread = $unread->fetchColumn();

include '../includes/header.php';
?>
<div class="page-head">
  <h1>Admin Dashboard</h1>
  <div class="page-head-right">
    <span class="badge badge-info">📅 <?= date('d M Y') ?></span>
    <a href="students.php" class="btn btn-primary btn-sm">+ Add Student</a>
  </div>
</div>

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card blue">  <span class="stat-icon">🎒</span><div><span class="stat-num"><?= $totalStudents ?></span><div class="stat-label">Total Students</div></div></div>
  <div class="stat-card green"> <span class="stat-icon">👨‍🏫</span><div><span class="stat-num"><?= $totalTeachers ?></span><div class="stat-label">Teachers</div></div></div>
  <div class="stat-card purple"><span class="stat-icon">🏫</span><div><span class="stat-num"><?= $totalClasses ?></span><div class="stat-label">Classes</div></div></div>
  <div class="stat-card cyan">  <span class="stat-icon">👨‍👩‍👦</span><div><span class="stat-num"><?= $totalParents ?></span><div class="stat-label">Parents</div></div></div>
  <div class="stat-card <?= $attPct>=75?'green':($attPct>=50?'orange':'red') ?>">
    <span class="stat-icon">✅</span>
    <div>
      <span class="stat-num"><?= $attPct ?>%</span>
      <div class="stat-label">Today's Attendance</div>
      <div class="progress" style="margin-top:6px"><div class="progress-bar pb-<?= $attPct>=75?'green':($attPct>=50?'yellow':'red') ?>" style="width:<?= $attPct ?>%"></div></div>
    </div>
  </div>
  <div class="stat-card orange"><span class="stat-icon">📋</span><div><span class="stat-num"><?= $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn() ?></span><div class="stat-label">Total Exams</div></div></div>
</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:18px">
  <div class="card">
    <div class="card-header"><span class="card-title">30-Day Attendance Trend</span></div>
    <div class="card-body chart-wrap"><canvas id="trendChart"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Grade Distribution</span></div>
    <div class="card-body chart-wrap"><canvas id="gradeChart"></canvas></div>
  </div>
</div>

<!-- Recent Students -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Registrations</span>
    <a href="students.php" class="btn btn-outline btn-sm">View All →</a>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Roll No.</th><th>Name</th><th>Class</th><th>Fee Status</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $i => $s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge badge-primary"><?= e($s['roll_no']) ?></span></td>
          <td><?= e($s['name'] ?? '—') ?></td>
          <td><?= e(($s['cn']??'—').' '.($s['cs']??'')) ?></td>
          <td><span class="badge <?= match($s['fee_status']??''){
            'Paid'=>'badge-success','Pending'=>'badge-danger',default=>'badge-warning'} ?>">
            <?= e($s['fee_status']??'—') ?></span></td>
          <td><?= e($s['joined']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$recent): ?><tr class="tbl-empty"><td colspan="6">No students yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
new Chart(document.getElementById('trendChart'),{
  type:'line',
  data:{labels:<?=json_encode($tLabels)?>,datasets:[{label:'Attendance %',data:<?=json_encode($tData)?>,borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,0.1)',fill:true,tension:0.4,pointRadius:3}]},
  options:{responsive:true,maintainAspectRatio:false,...chartDefaults,scales:{...chartDefaults.scales,y:{...chartDefaults.scales.y,min:0,max:100,ticks:{...chartDefaults.scales.y.ticks,callback:v=>v+'%'}}}}
});
new Chart(document.getElementById('gradeChart'),{
  type:'doughnut',
  data:{labels:<?=json_encode($gLabels)?>,datasets:[{data:<?=json_encode($gData)?>,backgroundColor:['#16a34a','#4ade80','#2563eb','#d97706','#ea580c','#dc2626'],borderWidth:0}]},
  options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{labels:{color:'#8b949e',font:{size:11}}}}}
});
</script>
