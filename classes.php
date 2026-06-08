<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Classes';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = post('action');

    if ($action==='add') {
        $name    = post('name'); $section=post('section');
        $room    = post('room'); $cap=(int)post('capacity',40);
        if (!$name||!$section) { setFlash('error','Class name and section are required.'); }
        else {
            try {
                $pdo->prepare("INSERT INTO classes(name,section,room,capacity) VALUES(?,?,?,?)")->execute([$name,$section,$room,$cap]);
                setFlash('success',"Class '$name - $section' added.");
            } catch(Exception $e){ setFlash('error','Class already exists or error: '.$e->getMessage()); }
        }
    }

    if ($action==='edit') {
        $cid=$pdo->prepare("UPDATE classes SET name=?,section=?,room=?,capacity=? WHERE id=?");
        $cid->execute([post('name'),post('section'),post('room'),(int)post('capacity'),(int)post('cid')]);
        setFlash('success','Class updated.');
    }
    redirect('classes.php');
}

if (isset($_GET['del'])) {
    try {
        $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([(int)$_GET['del']]);
        setFlash('success','Class deleted.');
    } catch(Exception $e){ setFlash('error','Cannot delete: class has linked students or subjects.'); }
    redirect('classes.php');
}

$editData=null;
if (isset($_GET['edit'])) {
    $es=$pdo->prepare("SELECT * FROM classes WHERE id=?");
    $es->execute([(int)$_GET['edit']]); $editData=$es->fetch();
}

// List with student count
$classes=$pdo->query("
  SELECT c.*, COUNT(DISTINCT s.id) AS student_count
  FROM classes c LEFT JOIN students s ON s.class_id=c.id
  GROUP BY c.id ORDER BY c.name,c.section
")->fetchAll();

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Class Management</h1>
  <div class="page-head-right">
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Class</button>
  </div>
</div>
<?= showFlash() ?>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Class Name</th><th>Section</th><th>Room</th><th>Capacity</th><th>Students</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($classes as $i=>$c): ?>
        <tr>
          <td><?=$i+1?></td>
          <td style="font-weight:600"><?=e($c['name'])?></td>
          <td><span class="badge badge-primary"><?=e($c['section'])?></span></td>
          <td><?=e($c['room']??'—')?></td>
          <td><?=e($c['capacity'])?></td>
          <td>
            <span class="badge <?=$c['student_count']>0?'badge-success':'badge-secondary'?>">
              <?=$c['student_count']?> students
            </span>
          </td>
          <td>
            <a href="?edit=<?=$c['id']?>" class="btn btn-outline btn-xs">Edit</a>
            <button onclick="confirmDel('?del=<?=$c['id']?>','<?=e($c['name'].' '.$c['section'])?>')" class="btn btn-danger btn-xs">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$classes): ?><tr class="tbl-empty"><td colspan="7">No classes yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD -->
<div class="modal-bg" id="addModal">
<div class="modal-box">
  <div class="modal-title">Add New Class</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Class Name *</label><input class="inp" name="name" required placeholder="e.g. Grade 9"></div>
      <div class="form-group"><label class="lbl">Section *</label><input class="inp" name="section" required placeholder="e.g. A"></div>
      <div class="form-group"><label class="lbl">Room Number</label><input class="inp" name="room" placeholder="e.g. R-101"></div>
      <div class="form-group"><label class="lbl">Capacity</label><input class="inp" type="number" name="capacity" value="40" min="1"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Add Class</button>
      <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<!-- EDIT -->
<?php if($editData): ?>
<div class="modal-bg show">
<div class="modal-box">
  <div class="modal-title">Edit Class</div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="cid" value="<?=$editData['id']?>">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Class Name *</label><input class="inp" name="name" value="<?=e($editData['name'])?>" required></div>
      <div class="form-group"><label class="lbl">Section *</label><input class="inp" name="section" value="<?=e($editData['section'])?>" required></div>
      <div class="form-group"><label class="lbl">Room</label><input class="inp" name="room" value="<?=e($editData['room']??'')?>"></div>
      <div class="form-group"><label class="lbl">Capacity</label><input class="inp" type="number" name="capacity" value="<?=e($editData['capacity'])?>"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Save</button>
      <a href="classes.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
