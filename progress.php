<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('parent');
$pageTitle = 'Child Progress';
$uid = $_SESSION['user_id'];

$parent = $pdo->prepare("SELECT id FROM parents WHERE user_id=?");
$parent->execute([$uid]);
$parent = $parent->fetch();
$pid = $parent['id'] ?? 0;

$child = $pdo->prepare("SELECT s.*,u.name,c.name cn,c.section cs FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id WHERE s.parent_id=? LIMIT 1");
$child->execute([$pid]);
$child = $child->fetch();
$csid = $child['id']       ?? 0;
$ccid = $child['class_id'] ?? 0;

// All marks grouped by type
$marks = $pdo->prepare("
    SELECT m.obtained, m.grade, e.name en, e.type, e.total_marks, e.pass_marks, e.exam_date, sub.name sn
    FROM marks m
    JOIN exams e ON m.exam_id=e.id
    JOIN subjects sub ON e.subject_id=sub.id
    WHERE m.student_id=?
    ORDER BY e.type, e.exam_date, sub.name
");
$marks->execute([$csid]);
$marks = $marks->fetchAll();

$grouped = [];
foreach ($marks as $m) $grouped[$m['type']][] = $m;

// Overall
$overall = count($marks) ? round(array_sum(array_map(fn($m)=>$m['obtained']/$m['total_marks']*100,$marks))/count($marks),1) : 0;

// Class rank
$rankRows = $pdo->prepare("SELECT st.id, ROUND(AVG(m.obtained/e.total_marks*100),1) pct FROM marks m JOIN students st ON m.student_id=st.id JOIN exams e ON m.exam_id=e.id WHERE st.class_id=? GROUP BY st.id ORDER BY pct DESC");
$rankRows->execute([$ccid]);
$rankRows = $rankRows->fetchAll();
$myRank   = array_search($csid, array_column($rankRows,'id')) + 1;

// Attendance
$att = $pdo->prepare("SELECT COUNT(*) t, SUM(status='Present') p, SUM(status='Absent') a FROM attendance WHERE student_id=?");
$att->execute([$csid]);
$att = $att->fetch();
$attPct = ($att['t']??0)>0 ? round($att['p']/$att['t']*100,1) : 0;

// Subject averages for chart
$subAvg = $pdo->prepare("SELECT sub.name sn, ROUND(AVG(m.obtained/e.total_marks*100),1) pct FROM marks m JOIN exams e ON m.exam_id=e.id JOIN subjects sub ON e.subject_id=sub.id WHERE m.student_id=? GROUP BY sub.id ORDER BY sub.name");
$subAvg->execute([$csid]);
$subAvg = $subAvg->fetchAll();

include '../includes/header.php';
?>
<div class="page-head">
    <h1>Child Academic Progress</h1>
    <button class="btn btn-success no-print" onclick="window.print()">🖨️ Print Report</button>
</div>

<?php if (!$child): ?>
<div class="alert alert-warning">No student linked to your account.</div>
<?php else: ?>

<!-- Print header -->
<div id="printHead" style="display:none;text-align:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #000">
    <h2>SCHOOL MANAGEMENT SYSTEM</h2>
    <div>University of Agriculture Faisalabad — Sub Campus TTS</div>
    <h3 style="margin-top:6px">STUDENT PROGRESS REPORT — <?= date('Y') ?></h3>
</div>

<!-- Student info -->
<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0"><?= strtoupper(substr($child['name'],0,2)) ?></div>
        <div style="flex:1">
            <div style="font-size:18px;font-weight:700"><?= e($child['name']) ?></div>
            <div style="font-size:13px;color:var(--text2);margin-top:2px">Roll: <?= e($child['roll_no']) ?> &nbsp;|&nbsp; Class: <?= e(($child['cn']??'').' '.($child['cs']??'')) ?> &nbsp;|&nbsp; <?= e($child['gender']??'—') ?></div>
        </div>
    </div>
</div>

<!-- Summary stats -->
<div class="stats-row">
    <div class="stat-card blue"><span class="stat-icon">📊</span><div><span class="stat-num"><?= $overall ?>%</span><div class="stat-label">Overall Average</div></div></div>
    <div class="stat-card green"><span class="stat-icon">🏅</span><div><span class="stat-num"><span class="badge <?= gradeBadge(getGrade($overall,100)) ?>" style="font-size:18px"><?= getGrade($overall,100) ?></span></span><div class="stat-label">Overall Grade</div></div></div>
    <div class="stat-card purple"><span class="stat-icon">🏆</span><div><span class="stat-num">#<?= $myRank?:'-' ?></span><div class="stat-label">Class Rank of <?= count($rankRows) ?></div></div></div>
    <div class="stat-card <?= $attPct>=75?'green':'red' ?>"><span class="stat-icon">✅</span><div><span class="stat-num"><?= $attPct ?>%</span><div class="stat-label">Attendance</div></div></div>
</div>

<!-- Subject performance chart -->
<?php if ($subAvg): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">Subject-wise Performance</span></div>
    <div class="card-body chart-wrap"><canvas id="subChart"></canvas></div>
</div>
<?php endif; ?>

<!-- Results by exam type -->
<?php if (!$marks): ?>
<div class="alert alert-info">No exam results available yet.</div>
<?php else: ?>
<?php foreach($grouped as $type => $typeMarks): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title"><?= e($type) ?> Results</span>
        <span class="badge badge-primary"><?= count($typeMarks) ?> subjects</span>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>Subject</th><th>Exam</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th><th>Result</th></tr></thead>
            <tbody>
                <?php foreach($typeMarks as $m):
                    $pct = round($m['obtained']/$m['total_marks']*100,1);
                    $pass = $pct >= ($m['pass_marks']/$m['total_marks']*100);
                ?>
                <tr>
                    <td style="font-weight:500"><?= e($m['sn']) ?></td>
                    <td><?= e($m['en']) ?></td>
                    <td style="font-size:12px"><?= e($m['exam_date']??'—') ?></td>
                    <td style="font-weight:600"><?= e($m['obtained']) ?> / <?= e($m['total_marks']) ?></td>
                    <td style="font-weight:700;color:<?= $pct>=75?'#4ade80':($pct>=50?'#fbbf24':'#f87171') ?>"><?= $pct ?>%</td>
                    <td><span class="badge <?= gradeBadge($m['grade']??'') ?>"><?= e($m['grade']??'—') ?></span></td>
                    <td><span class="badge <?= $pass?'badge-success':'badge-danger' ?>"><?= $pass?'Pass':'Fail' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Print signature -->
<div id="printSig" style="display:none;margin-top:30px">
    <div style="display:flex;justify-content:space-between">
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Class Teacher</div>
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Principal</div>
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Date</div>
    </div>
</div>

<?php endif; ?>

<style>
@media print {
    .no-print,.topbar,.sidebar,.page-head { display:none!important; }
    .app { display:block; } .content { padding:0; }
    body,td,th { color:#000!important; background:#fff!important; }
    #printHead,#printSig { display:block!important; }
    .card { border:1px solid #ccc!important; margin-bottom:10px!important; }
    .badge { border:1px solid #999!important; background:transparent!important; color:#000!important; }
}
</style>

<?php include '../includes/footer.php'; ?>
<script>
<?php if ($subAvg): ?>
new Chart(document.getElementById('subChart'),{
    type:'bar',
    data:{
        labels:<?= json_encode(array_column($subAvg,'sn')) ?>,
        datasets:[{label:'Average %',data:<?= json_encode(array_column($subAvg,'pct')) ?>,backgroundColor:['#2563eb','#16a34a','#d97706','#7c3aed','#dc2626','#0891b2'],borderRadius:5}]
    },
    options:{responsive:true,maintainAspectRatio:false,...chartDefaults,plugins:{legend:{display:false}},scales:{...chartDefaults.scales,y:{...chartDefaults.scales.y,min:0,max:100,ticks:{...chartDefaults.scales.y.ticks,callback:v=>v+'%'}}}}
});
<?php endif; ?>
</script>
