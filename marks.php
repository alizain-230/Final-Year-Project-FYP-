<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('teacher');
$pageTitle = 'Enter Marks';
$uid = $_SESSION['user_id'];

// Get teacher record
$tRow = $pdo->prepare("SELECT id FROM teachers WHERE user_id=?");
$tRow->execute([$uid]);
$tRow = $tRow->fetch();
$tid  = $tRow['id'] ?? 0;

// ── SAVE MARKS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid   = (int)post('exam_id');
    $marks = $_POST['marks'] ?? [];

    $examRow = $pdo->prepare("SELECT total_marks FROM exams WHERE id=?");
    $examRow->execute([$eid]);
    $examRow = $examRow->fetch();

    if ($examRow && $marks) {
        $total = $examRow['total_marks'];
        $st = $pdo->prepare("
            INSERT INTO marks(student_id,exam_id,obtained,grade,entered_by)
            VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE obtained=VALUES(obtained), grade=VALUES(grade)
        ");
        $pdo->beginTransaction();
        try {
            $saved = 0;
            foreach ($marks as $sid => $obt) {
                $obt = trim($obt);
                if ($obt === '' || !is_numeric($obt)) continue;
                $obt = (float)$obt;
                if ($obt < 0 || $obt > $total) continue;
                $grade = getGrade($obt, $total);
                $st->execute([$sid, $eid, $obt, $grade, $uid]);
                $saved++;
            }
            $pdo->commit();
            setFlash('success', "Marks saved for $saved students.");
        } catch(Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error: ' . $e->getMessage());
        }
    }
    redirect('marks.php?eid=' . $eid);
}

// ── LOAD EXAMS for this teacher's classes ─────────────────────
$exams = $pdo->prepare("
    SELECT e.*, s.name sn, c.name cn, c.section cs
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes  c ON e.class_id   = c.id
    JOIN teacher_subjects ts ON ts.subject_id = e.subject_id
                             AND ts.class_id   = e.class_id
    WHERE ts.teacher_id = ?
    ORDER BY e.exam_date DESC, s.name
");
$exams->execute([$tid]);
$exams = $exams->fetchAll();

$selEid    = (int)(get('eid') ?: ($exams[0]['id'] ?? 0));
$examDetail = null;
$students   = [];

if ($selEid) {
    $examDetail = $pdo->prepare("
        SELECT e.*, s.name sn, c.name cn, c.section cs, c.id cid
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        JOIN classes  c ON e.class_id   = c.id
        WHERE e.id = ?
    ");
    $examDetail->execute([$selEid]);
    $examDetail = $examDetail->fetch();

    if ($examDetail) {
        $students = $pdo->prepare("
            SELECT s.id, s.roll_no, u.name,
                   m.obtained, m.grade
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN marks m ON m.student_id = s.id AND m.exam_id = ?
            WHERE s.class_id = ?
            ORDER BY s.roll_no
        ");
        $students->execute([$selEid, $examDetail['cid']]);
        $students = $students->fetchAll();
    }
}

// Class stats if marks exist
$classStats = null;
if ($students) {
    $withMarks = array_filter($students, fn($s) => $s['obtained'] !== null);
    if ($withMarks) {
        $pcts = array_map(fn($s) => ($s['obtained'] / $examDetail['total_marks']) * 100, $withMarks);
        $classStats = [
            'avg'     => round(array_sum($pcts) / count($pcts), 1),
            'highest' => round(max($pcts), 1),
            'pass'    => count(array_filter($pcts, fn($p) => $p >= $examDetail['pass_marks'] / $examDetail['total_marks'] * 100)),
            'total'   => count($withMarks),
        ];
    }
}

include '../includes/header.php';
?>
<div class="page-head">
    <h1>Enter Exam Marks</h1>
</div>
<?= showFlash() ?>

<!-- Exam selector -->
<form method="GET" class="filter-bar">
    <select class="inp" style="min-width:360px" name="eid" onchange="this.form.submit()">
        <option value="">-- Select Exam --</option>
        <?php foreach($exams as $ex): ?>
        <option value="<?= $ex['id'] ?>" <?= $selEid==$ex['id']?'selected':'' ?>>
            <?= e($ex['name'].' | '.$ex['type'].' | '.$ex['sn'].' | '.$ex['cn'].'-'.$ex['cs'].' (Total: '.$ex['total_marks'].')') ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$exams): ?>
<div class="alert alert-warning">No exams assigned to your classes. Ask admin to create exams first.</div>
<?php elseif ($examDetail && $students): ?>

<!-- Exam info cards -->
<div class="stats-row" style="margin-bottom:16px">
    <div class="stat-card blue" style="padding:14px">
        <span class="stat-icon">📋</span>
        <div><span class="stat-num" style="font-size:18px"><?= e($examDetail['total_marks']) ?></span><div class="stat-label">Total Marks</div></div>
    </div>
    <div class="stat-card orange" style="padding:14px">
        <span class="stat-icon">✅</span>
        <div><span class="stat-num" style="font-size:18px"><?= e($examDetail['pass_marks']) ?></span><div class="stat-label">Pass Marks</div></div>
    </div>
    <?php if ($classStats): ?>
    <div class="stat-card green" style="padding:14px">
        <span class="stat-icon">📊</span>
        <div><span class="stat-num" style="font-size:18px"><?= $classStats['avg'] ?>%</span><div class="stat-label">Class Avg</div></div>
    </div>
    <div class="stat-card purple" style="padding:14px">
        <span class="stat-icon">🏆</span>
        <div><span class="stat-num" style="font-size:18px"><?= $classStats['pass'] ?>/<?= $classStats['total'] ?></span><div class="stat-label">Passed</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Marks form -->
<form method="POST">
    <input type="hidden" name="exam_id" value="<?= $selEid ?>">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <?= e($examDetail['name']) ?> —
                <?= e($examDetail['cn'].'-'.$examDetail['cs']) ?> |
                <?= e($examDetail['sn']) ?>
            </span>
            <span style="font-size:12px;color:var(--text2)"><?= count($students) ?> students</span>
        </div>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Roll No.</th>
                        <th>Student Name</th>
                        <th>Obtained Marks <span style="font-weight:400;font-size:10px">(max <?= $examDetail['total_marks'] ?>)</span></th>
                        <th>Grade</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><span class="badge badge-primary"><?= e($s['roll_no']) ?></span></td>
                        <td style="font-weight:500"><?= e($s['name']) ?></td>
                        <td>
                            <input
                                type="number"
                                class="inp"
                                style="width:110px;text-align:center"
                                name="marks[<?= $s['id'] ?>]"
                                value="<?= $s['obtained'] !== null ? e($s['obtained']) : '' ?>"
                                min="0"
                                max="<?= $examDetail['total_marks'] ?>"
                                step="0.5"
                                placeholder="0–<?= $examDetail['total_marks'] ?>"
                                oninput="calcGrade(this,<?= $examDetail['total_marks'] ?>,'g<?= $s['id'] ?>')"
                            >
                        </td>
                        <td>
                            <span id="g<?= $s['id'] ?>" class="badge <?= gradeBadge($s['grade']??'') ?>">
                                <?= e($s['grade'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--text2)">
                            <?= $s['obtained']!==null ? round($s['obtained']/$examDetail['total_marks']*100,1).'%' : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:14px 18px;display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn btn-primary">💾 Save All Marks</button>
            <span style="font-size:12px;color:var(--text2)">Grades auto-calculate as you type</span>
        </div>
    </div>
</form>

<?php elseif ($selEid): ?>
<div class="alert alert-warning">No students found for this exam's class.</div>
<?php else: ?>
<div class="alert alert-info">Select an exam from the dropdown above to enter marks.</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
