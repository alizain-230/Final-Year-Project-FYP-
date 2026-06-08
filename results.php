<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('student');
$pageTitle = 'My Results';
$uid = $_SESSION['user_id'];

$student = $pdo->prepare("SELECT s.*,u.name,c.name cn,c.section cs FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id WHERE s.user_id=?");
$student->execute([$uid]);
$student = $student->fetch();
$sid = $student['id'] ?? 0;
$cid = $student['class_id'] ?? 0;

// All marks grouped by exam type
$marks = $pdo->prepare("
    SELECT m.obtained, m.grade, e.name en, e.type, e.total_marks, e.pass_marks, e.exam_date,
           sub.name sn
    FROM marks m
    JOIN exams e ON m.exam_id=e.id
    JOIN subjects sub ON e.subject_id=sub.id
    WHERE m.student_id=?
    ORDER BY e.type, e.exam_date, sub.name
");
$marks->execute([$sid]);
$marks = $marks->fetchAll();

// Group by type
$grouped = [];
foreach ($marks as $m) $grouped[$m['type']][] = $m;

// Overall stats
$overall = count($marks) ? round(array_sum(array_map(fn($m)=>$m['obtained']/$m['total_marks']*100,$marks))/count($marks),1) : 0;

// Class rank
$rankRows = $pdo->prepare("SELECT st.id, ROUND(AVG(m.obtained/e.total_marks*100),1) pct FROM marks m JOIN students st ON m.student_id=st.id JOIN exams e ON m.exam_id=e.id WHERE st.class_id=? GROUP BY st.id ORDER BY pct DESC");
$rankRows->execute([$cid]);
$rankRows = $rankRows->fetchAll();
$myRank   = array_search($sid, array_column($rankRows,'id')) + 1;

include '../includes/header.php';
?>
<div class="page-head">
    <h1>My Results</h1>
    <button class="btn btn-success no-print" onclick="window.print()">🖨️ Print Result Card</button>
</div>

<!-- Print header (hidden on screen) -->
<div id="printHead" style="display:none;text-align:center;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #000">
    <h2 style="font-size:18px">SCHOOL MANAGEMENT SYSTEM</h2>
    <div>University of Agriculture Faisalabad — Sub Campus Toba Tek Singh</div>
    <h3 style="margin-top:6px">STUDENT RESULT CARD — Academic Year <?= date('Y') ?></h3>
</div>

<!-- Summary -->
<div class="stats-row no-print">
    <div class="stat-card blue"><span class="stat-icon">📊</span><div><span class="stat-num"><?= $overall ?>%</span><div class="stat-label">Overall Avg</div></div></div>
    <div class="stat-card green"><span class="stat-icon">🏅</span><div><span class="stat-num"><span class="badge <?= gradeBadge(getGrade($overall,100)) ?>" style="font-size:20px"><?= getGrade($overall,100) ?></span></span><div class="stat-label">Overall Grade</div></div></div>
    <div class="stat-card purple"><span class="stat-icon">🏆</span><div><span class="stat-num">#<?= $myRank?:'-' ?></span><div class="stat-label">Class Rank of <?= count($rankRows) ?></div></div></div>
    <div class="stat-card orange"><span class="stat-icon">📝</span><div><span class="stat-num"><?= count($marks) ?></span><div class="stat-label">Exams Taken</div></div></div>
</div>

<!-- Student info for print -->
<div style="display:none" id="stuInfo">
    <table style="width:100%;font-size:13px;border-collapse:collapse;margin-bottom:12px">
        <tr>
            <td style="padding:4px 8px;border:1px solid #000"><strong>Name:</strong> <?= e($student['name']) ?></td>
            <td style="padding:4px 8px;border:1px solid #000"><strong>Roll No.:</strong> <?= e($student['roll_no']) ?></td>
            <td style="padding:4px 8px;border:1px solid #000"><strong>Class:</strong> <?= e(($student['cn']??'').' '.($student['cs']??'')) ?></td>
            <td style="padding:4px 8px;border:1px solid #000"><strong>Class Rank:</strong> #<?= $myRank ?> of <?= count($rankRows) ?></td>
        </tr>
    </table>
</div>

<?php if (!$marks): ?>
<div class="alert alert-info">No results available yet.</div>
<?php else: ?>
<?php foreach($grouped as $type => $typeMarks): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title"><?= e($type) ?> Results</span>
        <span class="badge badge-primary"><?= count($typeMarks) ?> subjects</span>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>Subject</th><th>Exam Name</th><th>Date</th><th>Marks</th><th>Percentage</th><th>Grade</th><th>Result</th></tr></thead>
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

<!-- Print signatures -->
<div id="printSig" style="display:none;margin-top:30px">
    <div style="display:flex;justify-content:space-between;margin-top:40px">
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Class Teacher</div>
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Principal</div>
        <div style="text-align:center;border-top:1px solid #000;padding-top:5px;width:130px;font-size:12px">Date</div>
    </div>
</div>

<style>
@media print {
    .no-print,.topbar,.sidebar,.page-head { display:none!important; }
    .app { display:block; } .content { padding:0; }
    body,td,th { color:#000!important; background:#fff!important; }
    #printHead,#stuInfo,#printSig { display:block!important; }
    .card { border:1px solid #ccc!important; margin-bottom:12px!important; }
    .badge { border:1px solid #999!important; background:transparent!important; color:#000!important; }
}
</style>

<?php include '../includes/footer.php'; ?>
