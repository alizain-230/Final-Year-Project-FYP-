<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('student');
$pageTitle = 'Assignments';
$uid = $_SESSION['user_id'];

$student = $pdo->prepare("SELECT id,class_id FROM students WHERE user_id=?");
$student->execute([$uid]);
$student = $student->fetch();
$sid = $student['id'] ?? 0;
$cid = $student['class_id'] ?? 0;

// Submit assignment
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $aid   = (int)post('assignment_id');
    $fname = null;

    if (!empty($_FILES['sfile']['name'])) {
        $uploadDir = '../uploads/assignments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['sfile']['name'], PATHINFO_EXTENSION));
        $allow = ['pdf','doc','docx','ppt','pptx','jpg','png','zip'];
        if (!in_array($ext, $allow)) {
            setFlash('error','File type not allowed.');
            redirect('assignments.php');
        }
        $fname = uniqid('sub_').'.'.$ext;
        move_uploaded_file($_FILES['sfile']['tmp_name'], $uploadDir.$fname);
    }

    // Check if late
    $asgn = $pdo->prepare("SELECT due_date FROM assignments WHERE id=?");
    $asgn->execute([$aid]); $asgn=$asgn->fetch();
    $status = ($asgn && $asgn['due_date'] && strtotime($asgn['due_date']) < time()) ? 'Late' : 'Submitted';

    try {
        $pdo->prepare("INSERT INTO submissions(assignment_id,student_id,file_name,status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE file_name=VALUES(file_name),status=VALUES(status),submitted_at=NOW()")
            ->execute([$aid,$sid,$fname,$status]);
        setFlash('success', $status==='Late' ? 'Assignment submitted (marked as Late).' : 'Assignment submitted successfully.');
    } catch(Exception $e) {
        setFlash('error','Submission failed: '.$e->getMessage());
    }
    redirect('assignments.php');
}

// Load all assignments for this class with submission status
$assignments = $pdo->prepare("
    SELECT a.*,
           s.name sn,
           u.name tname,
           sub.id sub_id, sub.status sub_status, sub.obtained sub_marks, sub.submitted_at sub_time
    FROM assignments a
    JOIN subjects s ON a.subject_id=s.id
    JOIN teachers t ON a.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN submissions sub ON sub.assignment_id=a.id AND sub.student_id=?
    WHERE a.class_id=?
    ORDER BY a.due_date ASC, a.created_at DESC
");
$assignments->execute([$sid,$cid]);
$assignments = $assignments->fetchAll();

// Split pending vs submitted
$pending   = array_filter($assignments, fn($a) => !$a['sub_id']);
$submitted = array_filter($assignments, fn($a) => $a['sub_id']);

include '../includes/header.php';
?>
<div class="page-head">
    <h1>Assignments</h1>
    <div class="page-head-right">
        <span class="badge badge-danger"><?= count($pending) ?> pending</span>
        <span class="badge badge-success"><?= count($submitted) ?> submitted</span>
    </div>
</div>
<?= showFlash() ?>

<!-- Pending assignments -->
<?php if ($pending): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">⚠️ Pending Submissions</span></div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>#</th><th>Title</th><th>Subject</th><th>Teacher</th><th>Marks</th><th>Due Date</th><th>Submit</th></tr></thead>
            <tbody>
                <?php foreach($pending as $i=>$a): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:500"><?= e($a['title']) ?></div>
                        <?php if ($a['description']): ?><div style="font-size:11px;color:var(--text2)"><?= e(substr($a['description'],0,60)) ?></div><?php endif; ?>
                        <?php if ($a['file_name']): ?><a href="../uploads/assignments/<?= e($a['file_name']) ?>" class="btn btn-outline btn-xs" target="_blank" style="margin-top:4px">📎 Download</a><?php endif; ?>
                    </td>
                    <td><?= e($a['sn']) ?></td>
                    <td><?= e($a['tname']) ?></td>
                    <td><?= e($a['total_marks']) ?></td>
                    <td>
                        <?php if ($a['due_date']): ?>
                        <span class="badge <?= strtotime($a['due_date'])<time()?'badge-danger':'badge-warning' ?>"><?= e($a['due_date']) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" enctype="multipart/form-data" style="display:flex;gap:5px;align-items:center">
                            <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                            <input type="file" name="sfile" class="inp" style="width:180px;font-size:11px;padding:5px" accept=".pdf,.doc,.docx,.ppt,.jpg,.png,.zip">
                            <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Submitted assignments -->
<div class="card">
    <div class="card-header"><span class="card-title">✅ Submitted Assignments</span></div>
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>#</th><th>Title</th><th>Subject</th><th>Submitted</th><th>Status</th><th>Marks Received</th><th>File</th></tr></thead>
            <tbody>
                <?php foreach($submitted as $i=>$a): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td style="font-weight:500"><?= e($a['title']) ?></td>
                    <td><?= e($a['sn']) ?></td>
                    <td style="font-size:12px"><?= $a['sub_time'] ? date('d M Y', strtotime($a['sub_time'])) : '—' ?></td>
                    <td><span class="badge <?= match($a['sub_status']){'Submitted'=>'badge-info','Graded'=>'badge-success','Late'=>'badge-warning',default=>'badge-secondary'} ?>"><?= e($a['sub_status']) ?></span></td>
                    <td>
                        <?php if ($a['sub_marks']!==null): ?>
                        <strong><?= e($a['sub_marks']) ?> / <?= e($a['total_marks']) ?></strong>
                        <?php else: ?>
                        <span style="color:var(--text2)">Not graded yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($a['sub_id']): ?>
                        <span class="badge badge-success">Submitted</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$submitted): ?><tr class="tbl-empty"><td colspan="7">No submitted assignments yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
