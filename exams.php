<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Exams';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=post('action');
    if ($action==='add') {
        $name   = post('name'); $type=post('type','Midterm');
        $cid    = (int)post('class_id'); $sid=(int)post('subject_id');
        $total  = (int)post('total_marks',100);
        $pass   = (int)post('pass_marks',40);
        $date   = post('exam_date');
        if (!$name||!$cid||!$sid) { setFlash('error','Name, Class and Subject required.'); }
        else {
            $pdo->prepare("INSERT INTO exams(name,type,class_id,subject_id,total_marks,pass_marks,exam_date,created_by) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([$name,$type,$cid,$sid,$total,$pass,$date ?: null,$_SESSION['user_id']]);
            setFlash('success',"Exam '$name' created.");
        }
    }
    if ($action==='edit') {
        $pdo->prepare("UPDATE exams SET name=?,type=?,class_id=?,subject_id=?,total_marks=?,pass_marks=?,exam_date=? WHERE id=?")
            ->execute([post('name'),post('type'),(int)post('class_id'),(int)post('subject_id'),(int)post('total_marks'),(int)post('pass_marks'),post('exam_date') ?: null,(int)post('eid')]);
        setFlash('success','Exam updated.');
    }
    redirect('exams.php');
}

if (isset($_GET['del'])) {
    try {
        $pdo->prepare("DELETE FROM exams WHERE id=?")->execute([(int)$_GET['del']]);
        setFlash('success','Exam deleted.');
    } catch(Exception $e){ setFlash('error','Cannot delete: exam has marks linked.'); }
    redirect('exams.php');
}

$editData=null;
if (isset($_GET['edit'])) {
    $es=$pdo->prepare("SELECT * FROM exams WHERE id=?");
    $es->execute([(int)$_GET['edit']]); $editData=$es->fetch();
}

// Filters
$fcid=(int)get('cid'); $ftype=get('type');
$where=['1=1']; $params=[];
if ($fcid)  { $where[]='e.class_id=?'; $params[]=$fcid; }
if ($ftype) { $where[]='e.type=?';     $params[]=$ftype; }
$ws=implode(' AND ',$where);
$exams=$pdo->prepare("SELECT e.*,c.name cn,c.section cs,s.name sn FROM exams e JOIN classes c ON e.class_id=c.id JOIN subjects s ON e.subject_id=s.id WHERE $ws ORDER BY e.exam_date DESC,e.id DESC");
$exams->execute($params); $exams=$exams->fetchAll();
$classes=$pdo->query("SELECT * FROM classes ORDER BY name,section")->fetchAll();

// Subjects for selected class (for modal AJAX - load all for simplicity)
$allSubjects=$pdo->query("SELECT s.*,c.name cn,c.section cs FROM subjects s JOIN classes c ON s.class_id=c.id ORDER BY c.name,s.name")->fetchAll();
$types=['Quiz','Midterm','Final','Assignment','Practical'];

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Exam Management</h1>
  <div class="page-head-right">
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Create Exam</button>
  </div>
</div>
<?= showFlash() ?>

<div class="filter-bar">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
    <select class="inp" style="width:170px" name="cid">
      <option value="">All Classes</option>
      <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$fcid==$c['id']?'selected':''?>><?=e($c['name'].' '.$c['section'])?></option><?php endforeach; ?>
    </select>
    <select class="inp" style="width:140px" name="type">
      <option value="">All Types</option>
      <?php foreach($types as $t): ?><option <?=$ftype===$t?'selected':''?>><?=$t?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm">Filter</button>
    <?php if($fcid||$ftype): ?><a href="exams.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Exam Name</th><th>Type</th><th>Class</th><th>Subject</th><th>Total</th><th>Pass</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($exams as $i=>$ex): ?>
        <tr>
          <td><?=$i+1?></td>
          <td style="font-weight:500"><?=e($ex['name'])?></td>
          <td><span class="badge <?=match($ex['type']){'Quiz'=>'badge-info','Midterm'=>'badge-warning','Final'=>'badge-danger','Assignment'=>'badge-success',default=>'badge-secondary'}?>"><?=e($ex['type'])?></span></td>
          <td><?=e($ex['cn'].' '.$ex['cs'])?></td>
          <td><?=e($ex['sn'])?></td>
          <td><?=e($ex['total_marks'])?></td>
          <td><?=e($ex['pass_marks'])?></td>
          <td><?=e($ex['exam_date']??'—')?></td>
          <td>
            <a href="?edit=<?=$ex['id']?>" class="btn btn-outline btn-xs">Edit</a>
            <button onclick="confirmDel('?del=<?=$ex['id']?>','<?=e($ex['name'])?>')" class="btn btn-danger btn-xs">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$exams): ?><tr class="tbl-empty"><td colspan="9">No exams yet. Create one to allow teachers to enter marks.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-bg" id="addModal">
<div class="modal-box wide">
  <div class="modal-title">Create New Exam</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group form-full"><label class="lbl">Exam Name *</label><input class="inp" name="name" required placeholder="e.g. Mid Term 2026 Mathematics"></div>
      <div class="form-group"><label class="lbl">Exam Type</label>
        <select class="inp" name="type"><?php foreach($types as $t): ?><option><?=$t?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group"><label class="lbl">Class *</label>
        <select class="inp" name="class_id" id="addClassId" required onchange="filterSubjects(this.value,'addSubjectId')">
          <option value="">Select Class</option>
          <?php foreach($classes as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Subject *</label>
        <select class="inp" name="subject_id" id="addSubjectId" required>
          <option value="">Select Subject</option>
          <?php foreach($allSubjects as $s): ?><option value="<?=$s['id']?>" data-class="<?=$s['class_id']?>"><?=e($s['name'].' ('.$s['cn'].' '.$s['cs'].')')?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Total Marks</label><input class="inp" type="number" name="total_marks" value="100" min="1"></div>
      <div class="form-group"><label class="lbl">Passing Marks</label><input class="inp" type="number" name="pass_marks" value="40" min="1"></div>
      <div class="form-group"><label class="lbl">Exam Date</label><input class="inp" type="date" name="exam_date"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Create Exam</button>
      <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<!-- EDIT MODAL -->
<?php if($editData): ?>
<div class="modal-bg show">
<div class="modal-box wide">
  <div class="modal-title">Edit Exam</div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="eid" value="<?=$editData['id']?>">
    <div class="form-row">
      <div class="form-group form-full"><label class="lbl">Exam Name *</label><input class="inp" name="name" value="<?=e($editData['name'])?>" required></div>
      <div class="form-group"><label class="lbl">Type</label>
        <select class="inp" name="type"><?php foreach($types as $t): ?><option <?=$editData['type']===$t?'selected':''?>><?=$t?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group"><label class="lbl">Class</label>
        <select class="inp" name="class_id" onchange="filterSubjects(this.value,'editSubId')">
          <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$editData['class_id']==$c['id']?'selected':''?>><?=e($c['name'].' '.$c['section'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Subject</label>
        <select class="inp" name="subject_id" id="editSubId">
          <?php foreach($allSubjects as $s): ?><option value="<?=$s['id']?>" data-class="<?=$s['class_id']?>" <?=$editData['subject_id']==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Total Marks</label><input class="inp" type="number" name="total_marks" value="<?=e($editData['total_marks'])?>"></div>
      <div class="form-group"><label class="lbl">Pass Marks</label><input class="inp" type="number" name="pass_marks" value="<?=e($editData['pass_marks'])?>"></div>
      <div class="form-group"><label class="lbl">Exam Date</label><input class="inp" type="date" name="exam_date" value="<?=e($editData['exam_date']??'')?>"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Save</button>
      <a href="exams.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<script>
// Filter subjects by selected class in dropdown
function filterSubjects(classId, targetId) {
    const sel = document.getElementById(targetId);
    if (!sel) return;
    const opts = sel.querySelectorAll('option');
    opts.forEach(o => {
        if (!o.value) return;
        o.style.display = (!classId || o.dataset.class == classId) ? '' : 'none';
    });
    // Reset selection
    sel.value = '';
}
</script>

<?php include '../includes/footer.php'; ?>
