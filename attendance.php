<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('parent');
$pageTitle = 'Child Attendance';
$uid = $_SESSION['user_id'];

$parent = $pdo->prepare("SELECT id FROM parents WHERE user_id=?");
$parent->execute([$uid]);
$parent = $parent->fetch();
$pid = $parent['id'] ?? 0;

$child = $pdo->prepare("SELECT s.*,u.name,c.name cn,c.section cs FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id WHERE s.parent_id=? LIMIT 1");
$child->execute([$pid]);
$child = $child->fetch();
$csid  = $child['id'] ?? 0;

// Summary
$summ = $pdo->prepare("SELECT COUNT(*) t, SUM(status='Present') p, SUM(status='Absent') a, SUM(status='Late') l, SUM(status='Leave') lv FROM attendance WHERE student_id=?");
$summ->execute([$csid]);
$summ = $summ->fetch();
$attPct = ($summ['t']??0)>0 ? round($summ['p']/$summ['t']*100,1) : 0;

// Monthly filter
$selMonth = get('month', date('Y-m'));
$from = $selMonth.'-01';
$to   = date('Y-m-t', strtotime($from));

$records = $pdo->prepare("SELECT date,status FROM attendance WHERE student_id=? AND date BETWEEN ? AND ? ORDER BY date");
$records->execute([$csid,$from,$to]);
$records = $records->fetchAll();

// 30-day chart
$chartAtt = $pdo->prepare("SELECT date,status FROM attendance WHERE student_id=? AND date>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) ORDER BY date");
$chartAtt->execute([$csid]);
$chartAtt = $chartAtt->fetchAll();
$cL = array_map(fn($r)=>date('d M',strtotime($r['date'])), $chartAtt);
$cP = array_map(fn($r)=>$r['status']==='Present'?1:0, $chartAtt);
$cA = array_map(fn($r)=>$r['status']==='Absent'?1:0, $chartAtt);

include '../includes/header.php';
?>
<div class="page-head"><h1>Child Attendance</h1></div>

<?php if (!$child): ?>
<div class="alert alert-warning">No student linked to your account.</div>
<?php else: ?>

<!-- Child name bar -->
<div style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.2);border-radius:8px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0"><?= strtoupper(substr($child['name'],0,2)) ?></div>
    <div>
        <div style="font-weight:600"><?= e($child['name']) ?></div>
        <div style="font-size:12px;color:var(--text2)">Roll: <?= e($child['roll_no']) ?> &nbsp;|&nbsp; Class: <?= e(($child['cn']??'').' '.($child['cs']??'')) ?></div>
    </div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-card <?= $attPct>=75?'green':($attPct>=50?'orange':'red') ?>">
        <span class="stat-icon">📊</span>
        <div>
            <span class="stat-num"><?= $attPct ?>%</span>
            <div class="stat-label">Attendance Rate</div>
            <div class="progress" style="margin-top:6px"><div class="progress-bar pb-<?= $attPct>=75?'green':($attPct>=50?'yellow':'red') ?>" style="width:<?= $attPct ?>%"></div></div>
        </div>
    </div>
    <div class="stat-card green"><span class="stat-icon">✅</span><div><span class="stat-num"><?= $summ['p']??0 ?></span><div class="stat-label">Present</div></div></div>
    <div class="stat-card red"><span class="stat-icon">❌</span><div><span class="stat-num"><?= $summ['a']??0 ?></span><div class="stat-label">Absent</div></div></div>
    <div class="stat-card orange"><span class="stat-icon">⏰</span><div><span class="stat-num"><?= $summ['l']??0 ?></span><div class="stat-label">Late</div></div></div>
    <div class="stat-card blue"><span class="stat-icon">📅</span><div><span class="stat-num"><?= $summ['t']??0 ?></span><div class="stat-label">Total Days</div></div></div>
</div>

<!-- 30-day chart -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">30-Day Attendance Log</span></div>
    <div class="card-body chart-wrap"><canvas id="attChart"></canvas></div>
</div>

<!-- Warning -->
<?php if ($attPct < 75 && ($summ['t']??0) > 5): ?>
<div class="alert alert-warning">
    ⚠️ <strong>Attendance Warning:</strong> Your child's attendance is <?= $attPct ?>% which is below the minimum required 75%. Please ensure regular school attendance.
</div>
<?php endif; ?>

<!-- Monthly detail -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Monthly Records</span>
        <form method="GET" style="display:flex;gap:6px;align-items:center">
            <input class="inp" type="month" name="month" value="<?= e($selMonth) ?>" style="width:160px">
            <button class="btn btn-primary btn-sm">View</button>
        </form>
    </div>
    <?php if ($records): ?>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>#</th><th>Date</th><th>Day</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach($records as $i=>$r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= date('d M Y', strtotime($r['date'])) ?></td>
                    <td><?= date('l', strtotime($r['date'])) ?></td>
                    <td><span class="badge <?= match($r['status']){'Present'=>'badge-success','Absent'=>'badge-danger','Late'=>'badge-warning','Leave'=>'badge-info',default=>'badge-secondary'} ?>"><?= e($r['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:32px;color:var(--text2)">No records for <?= e($selMonth) ?>.</div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
<script>
const actx = document.getElementById('attChart');
if (actx) {
    new Chart(actx,{
        type:'bar',
        data:{
            labels:<?= json_encode($cL) ?>,
            datasets:[
                {label:'Present',data:<?= json_encode($cP) ?>,backgroundColor:'rgba(22,163,74,0.7)',borderRadius:3},
                {label:'Absent', data:<?= json_encode($cA)  ?>,backgroundColor:'rgba(220,38,38,0.7)', borderRadius:3}
            ]
        },
        options:{responsive:true,maintainAspectRatio:false,...chartDefaults,scales:{...chartDefaults.scales,x:{...chartDefaults.scales.x,stacked:true,ticks:{...chartDefaults.scales.x.ticks,maxRotation:45,font:{size:9}}},y:{...chartDefaults.scales.y,stacked:true,max:1,ticks:{...chartDefaults.scales.y.ticks,precision:0}}}}
    });
}
</script>
