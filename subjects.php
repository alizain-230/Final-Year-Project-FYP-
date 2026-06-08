<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Subjects';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=post('action');
    if ($action==='add') {
        $name=post('name'); $code=post('code'); $cid=(int)post('class_id');
        if (!$name||!$code||!$cid) { setFlash('error','All fields required.'); }
        else {
            try {
                $pdo->prepare("INSERT INTO subjects(name,code,class_id) VALUES(?,?,?)")->execute([$name,$code,$cid]);
                setFlash('success',"Subject '$name' added.");
            } catch(Exception $e){ setFlash('error','Subject code already exists.'); }
        }
    }
    if ($action==='edit') {
        $pdo->prepare("UPDATE subjects SET name=?,code=?,class_id=? WHERE id=?")
            ->execute([post('name'),post('code'),(int)post('class_id'),(int)post('sid')]);
        setFlash('success','Subject updated.');
    }
    redirect('subjects.php');
}

if (isset($_GET['del'])) {
    try {
        $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([(int)$_GET['del']]);
        setFlash('success','Subject deleted.');
    } catch(Exception $e){ setFlash('error','Cannot delete: subject has linked exams or marks.'); }
    redirect('subjects.php');
}

$editData=null;
if (isset($_GET['edit'])) {
    $es=$pdo->prepare("SELECT * FROM subjects WHERE id=?");
    $es->execute([(int)$_GET['edit']]); $editData=$es->fetch();
}

$fcid=(int)get('cid');
$where='1=1'; $params=[];
if ($fcid) { $where='s.class_id=?'; $params=[$fcid]; }
$subjects=$pdo->prepare("SELECT s.*,c.name cn,c.section cs FROM subjects s JOIN classes c ON s.class_id=c.id WHERE $where ORDER BY c.name,s.name");
$subjects->execute($params); $subjects=$subjects->fetchAll();
$classes=$pdo->query("SELECT * FROM classes ORDER BY name,section")->fetchAll();

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Subject Management</h1>
  <div class="page-head-right">
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Subject</button>
  </div>
</div>
<?= showFlash() ?>

<div class="filter-bar">
  <form method="GET" style="display:flex;gap:8px">
    <select class="inp" style="width:180px" name="cid" onchange="this.form.submit()">
      <option value="">All Classes</option>
      <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$fcid==$c['id']?'selected':''?>><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
    </select>
    <?php if($fcid): ?><a href="subjects.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Code</th><th>Subject Name</th><th>Class</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($subjects as $i=>$s): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><span class="badge badge-info"><?=e($s['code'])?></span></td>
          <td style="font-weight:500"><?=e($s['name'])?></td>
          <td><?=e($s['cn'].' - '.$s['cs'])?></td>
          <td>
            <a href="?edit=<?=$s['id']?>" class="btn btn-outline btn-xs">Edit</a>
            <button onclick="confirmDel('?del=<?=$s['id']?>','<?=e($s['name'])?>')" class="btn btn-danger btn-xs">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$subjects): ?><tr class="tbl-empty"><td colspan="5">No subjects yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD -->
<div class="modal-bg" id="addModal">
<div class="modal-box">
  <div class="modal-title">Add New Subject</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-group"><label class="lbl">Subject Name *</label><input class="inp" name="name" required placeholder="e.g. Mathematics"></div>
    <div class="form-group"><label class="lbl">Subject Code *</label><input class="inp" name="code" required placeholder="e.g. MATH-9A" style="text-transform:uppercase"></div>
    <div class="form-group"><label class="lbl">Class *</label>
      <select class="inp" name="class_id" required><option value="">Select Class</option>
        <?php foreach($classes as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Add Subject</button>
      <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<!-- EDIT -->
<?php if($editData): ?>
<div class="modal-bg show">
<div class="modal-box">
  <div class="modal-title">Edit Subject</div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="sid" value="<?=$editData['id']?>">
    <div class="form-group"><label class="lbl">Subject Name *</label><input class="inp" name="name" value="<?=e($editData['name'])?>" required></div>
    <div class="form-group"><label class="lbl">Subject Code *</label><input class="inp" name="code" value="<?=e($editData['code'])?>" required></div>
    <div class="form-group"><label class="lbl">Class *</label>
      <select class="inp" name="class_id" required>
        <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$editData['class_id']==$c['id']?'selected':''?>><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Save</button>
      <a href="subjects.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
