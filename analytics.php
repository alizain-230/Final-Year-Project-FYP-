<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Analytics';

// 1. 30-day attendance trend
$trend=$pdo->query("SELECT date, ROUND(SUM(status='Present')/COUNT(*)*100,1) pct FROM attendance WHERE date>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY date ORDER BY date")->fetchAll();
$tL=array_map(fn($r)=>date('d M',strtotime($r['date'])),$trend);
$tD=array_column($trend,'pct');

// 2. Per-class attendance
$cAtt=$pdo->query("SELECT c.name,c.section,ROUND(SUM(a.status='Present')/COUNT(*)*100,1) pct FROM attendance a JOIN classes c ON a.class_id=c.id WHERE a.date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY c.id ORDER BY pct DESC")->fetchAll();
$cL=array_map(fn($r)=>$r['name'].'-'.$r['section'],$cAtt);
$cD=array_column($cAtt,'pct');
$cColors=array_map(fn($p)=>$p>=75?'rgba(22,163,74,0.7)':($p>=50?'rgba(217,119,6,0.7)':'rgba(220,38,38,0.7)'),$cD);

// 3. Grade distribution
$gd=$pdo->query("SELECT grade,COUNT(*) n FROM marks WHERE grade IS NOT NULL GROUP BY grade ORDER BY FIELD(grade,'A+','A','B','C','D','F')")->fetchAll();
$gL=array_column($gd,'grade'); $gD=array_column($gd,'n');

// 4. Top 10 students
$top=$pdo->query("SELECT u.name,s.roll_no,c.name cn,ROUND(AVG(m.obtained/e.total_marks*100),1) pct FROM marks m JOIN students s ON m.student_id=s.id JOIN users u ON s.user_id=u.id JOIN exams e ON m.exam_id=e.id LEFT JOIN classes c ON s.class_id=c.id GROUP BY s.id ORDER BY pct DESC LIMIT 10")->fetchAll();
$tNames=array_map(fn($r)=>$r['name'].' ('.$r['cn'].')',$top);
$tPct=array_column($top,'pct');

// 5. Fee status
$fee=$pdo->query("SELECT fee_status,COUNT(*) n FROM students GROUP BY fee_status")->fetchAll();
$fL=array_column($fee,'fee_status'); $fD=array_column($fee,'n');

// Summary stats
$tStudents=$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$tTeachers=$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$tExams   =$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$overallAtt=$pdo->query("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance")->fetchColumn();

include '../includes/header.php'; ?>
<div class="page-head"><h1>Analytics & Reports</h1><span class="badge badge-info">Data as of <?=date('d M Y')?></span></div>

<div class="stats-row">
  <div class="stat-card blue"><span class="stat-icon">🎒</span><div><span class="stat-num"><?=$tStudents?></span><div class="stat-label">Students</div></div></div>
  <div class="stat-card green"><span class="stat-icon">👨‍🏫</span><div><span class="stat-num"><?=$tTeachers?></span><div class="stat-label">Teachers</div></div></div>
  <div class="stat-card purple"><span class="stat-icon">📋</span><div><span class="stat-num"><?=$tExams?></span><div class="stat-label">Exams</div></div></div>
  <div class="stat-card orange"><span class="stat-icon">✅</span><div><span class="stat-num"><?=$overallAtt?>%</span><div class="stat-label">Overall Attendance</div></div></div>
</div>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:16px">
  <div class="card"><div class="card-header"><span class="card-title">30-Day Attendance Trend</span></div><div class="card-body chart-wrap"><canvas id="c1"></canvas></div></div>
  <div class="card"><div class="card-header"><span class="card-title">Attendance by Class</span></div><div class="card-body chart-wrap"><canvas id="c2"></canvas></div></div>
</div>
<div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:16px">
  <div class="card"><div class="card-header"><span class="card-title">Grade Distribution</span></div><div class="card-body chart-wrap"><canvas id="c3"></canvas></div></div>
  <div class="card"><div class="card-header"><span class="card-title">Top 10 Students</span></div><div class="card-body" style="position:relative;height:280px"><canvas id="c4"></canvas></div></div>
</div>
<div style="display:grid;grid-template-columns:1fr 2fr;gap:16px">
  <div class="card"><div class="card-header"><span class="card-title">Fee Status</span></div><div class="card-body chart-wrap"><canvas id="c5"></canvas></div></div>
  <div class="card">
    <div class="card-header"><span class="card-title">Top 10 Students — Details</span></div>
    <div class="table-wrap"><table class="tbl"><thead><tr><th>Rank</th><th>Name</th><th>Roll No.</th><th>Average</th><th>Grade</th></tr></thead><tbody>
      <?php foreach($top as $i=>$s): ?>
      <tr><td style="font-weight:700;color:<?=['#ffd700','#c0c0c0','#cd7f32'][$i]??'inherit'?>">#<?=$i+1?></td><td><?=e($s['name'])?></td><td><?=e($s['roll_no'])?></td>
      <td><div style="display:flex;align-items:center;gap:8px"><div class="progress" style="flex:1"><div class="progress-bar pb-blue" style="width:<?=$s['pct']?>%"></div></div><span style="font-size:12px;font-weight:600"><?=$s['pct']?>%</span></div></td>
      <td><span class="badge <?=gradeBadge(getGrade($s['pct'],100))?>"><?=getGrade($s['pct'],100)?></span></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
const co={plugins:{legend:{labels:{color:'#8b949e',font:{size:11}}}},scales:{x:{ticks:{color:'#8b949e',font:{size:10}},grid:{color:'rgba(48,54,61,0.7)'}},y:{ticks:{color:'#8b949e',font:{size:10}},grid:{color:'rgba(48,54,61,0.7)'}}}};
new Chart(document.getElementById('c1'),{type:'line',data:{labels:<?=json_encode($tL)?>,datasets:[{label:'Att %',data:<?=json_encode($tD)?>,borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,0.1)',fill:true,tension:0.4,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,...co,scales:{...co.scales,y:{...co.scales.y,min:0,max:100,ticks:{...co.scales.y.ticks,callback:v=>v+'%'}}}}});
new Chart(document.getElementById('c2'),{type:'bar',data:{labels:<?=json_encode($cL)?>,datasets:[{label:'Att %',data:<?=json_encode($cD)?>,backgroundColor:<?=json_encode($cColors)?>,borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,...co,plugins:{legend:{display:false}},scales:{...co.scales,y:{...co.scales.y,min:0,max:100,ticks:{...co.scales.y.ticks,callback:v=>v+'%'}}}}});
new Chart(document.getElementById('c3'),{type:'doughnut',data:{labels:<?=json_encode($gL)?>,datasets:[{data:<?=json_encode($gD)?>,backgroundColor:['#16a34a','#4ade80','#2563eb','#d97706','#ea580c','#dc2626'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{labels:{color:'#8b949e',font:{size:11}}}}}});
new Chart(document.getElementById('c4'),{type:'bar',data:{labels:<?=json_encode($tNames)?>,datasets:[{label:'Avg %',data:<?=json_encode($tPct)?>,backgroundColor:'rgba(37,99,235,0.7)',borderRadius:4}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,...co,plugins:{legend:{display:false}},scales:{...co.scales,x:{...co.scales.x,min:0,max:100,ticks:{...co.scales.x.ticks,callback:v=>v+'%'}}}}});
new Chart(document.getElementById('c5'),{type:'doughnut',data:{labels:<?=json_encode($fL)?>,datasets:[{data:<?=json_encode($fD)?>,backgroundColor:['#16a34a','#dc2626','#d97706'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{labels:{color:'#8b949e'}}}}});
</script>
