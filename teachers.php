<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Teachers';

// ── ADD ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='add') {
    $name  = post('name'); $email = post('email');
    $emp   = post('emp_code'); $qual  = post('qualification');
    $phone = post('phone'); $joined= post('joined_date');

    if (!$name||!$email||!$emp) { setFlash('error','Name, Email and Employee Code are required.'); }
    else {
        $dup=$pdo->prepare("SELECT id FROM users WHERE email=?");
        $dup->execute([$email]);
        if ($dup->fetch()) { setFlash('error','Email already exists.'); }
        else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'teacher')")
                    ->execute([$name,$email,password_hash('teacher123',PASSWORD_BCRYPT)]);
                $uid=$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO teachers(user_id,emp_code,qualification,phone,joined_date) VALUES(?,?,?,?,?)")
                    ->execute([$uid,$emp,$qual,$phone,$joined]);
                $pdo->commit();
                setFlash('success',"Teacher '$name' added. Default password: teacher123");
            } catch(Exception $e){ $pdo->rollBack(); setFlash('error','Error: '.$e->getMessage()); }
        }
    }
    redirect('teachers.php');
}

// ── EDIT ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='edit') {
    $tid   = (int)post('tid');
    $name  = post('name'); $qual=post('qualification');
    $phone = post('phone'); $joined=post('joined_date');
    $pdo->prepare("UPDATE users SET name=? WHERE id=(SELECT user_id FROM teachers WHERE id=?)")->execute([$name,$tid]);
    $pdo->prepare("UPDATE teachers SET qualification=?,phone=?,joined_date=? WHERE id=?")->execute([$qual,$phone,$joined,$tid]);
    setFlash('success','Teacher updated.');
    redirect('teachers.php');
}

// ── DELETE ─────────────────────────────────────────────────────
if (isset($_GET['del'])) {
    $tid=(int)$_GET['del'];
    $uid=$pdo->prepare("SELECT user_id FROM teachers WHERE id=?");
    $uid->execute([$tid]); $uid=$uid->fetchColumn();
    if ($uid) { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]); }
    setFlash('success','Teacher deleted.');
    redirect('teachers.php');
}

// ── LOAD EDIT ──────────────────────────────────────────────────
$editData=null;
if (isset($_GET['edit'])) {
    $es=$pdo->prepare("SELECT t.*,u.name,u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id=?");
    $es->execute([(int)$_GET['edit']]); $editData=$es->fetch();
}

// ── LIST ───────────────────────────────────────────────────────
$search=get('q'); $perPage=15; $page=max(1,(int)get('page',1)); $offset=($page-1)*$perPage;
$where=['1=1']; $params=[];
if ($search) { $where[]='(u.name LIKE ? OR t.emp_code LIKE ?)'; $params=["%$search%","%$search%"]; }
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON t.user_id=u.id WHERE $ws");
$total->execute($params); $total=$total->fetchColumn();
$rows=$pdo->prepare("SELECT t.*,u.name,u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE $ws ORDER BY u.name LIMIT $perPage OFFSET $offset");
$rows->execute($params); $rows=$rows->fetchAll();

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Teacher Management</h1>
  <div class="page-head-right">
    <span style="font-size:12px;color:var(--text2)"><?=$total?> total</span>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Teacher</button>
  </div>
</div>
<?= showFlash() ?>

<div class="filter-bar">
  <form method="GET" style="display:flex;gap:8px">
    <input class="inp" style="width:220px" name="q" value="<?=e($search)?>" placeholder="🔍 Search name or code...">
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search): ?><a href="teachers.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Emp. Code</th><th>Name</th><th>Email</th><th>Qualification</th><th>Phone</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($rows as $i=>$t): ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td><span class="badge badge-primary"><?=e($t['emp_code'])?></span></td>
          <td style="font-weight:500"><?=e($t['name'])?></td>
          <td style="font-size:12px"><?=e($t['email'])?></td>
          <td><?=e($t['qualification']??'—')?></td>
          <td><?=e($t['phone']??'—')?></td>
          <td><?=e($t['joined_date']??'—')?></td>
          <td>
            <a href="?edit=<?=$t['id']?>" class="btn btn-outline btn-xs">Edit</a>
            <button onclick="confirmDel('?del=<?=$t['id']?>','<?=e($t['name'])?>')" class="btn btn-danger btn-xs">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr class="tbl-empty"><td colspan="8">No teachers found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= paginate($total,$perPage,$page,'teachers.php?q='.urlencode($search)) ?>
</div>

<!-- ADD MODAL -->
<div class="modal-bg" id="addModal">
<div class="modal-box">
  <div class="modal-title">Add New Teacher</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Full Name *</label><input class="inp" name="name" required placeholder="Dr. Ahmed Khan"></div>
      <div class="form-group"><label class="lbl">Email (Login) *</label><input class="inp" type="email" name="email" required></div>
      <div class="form-group"><label class="lbl">Employee Code *</label><input class="inp" name="emp_code" required placeholder="EMP-004"></div>
      <div class="form-group"><label class="lbl">Qualification</label><input class="inp" name="qualification" placeholder="M.Sc Mathematics"></div>
      <div class="form-group"><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="03xx-xxxxxxx"></div>
      <div class="form-group"><label class="lbl">Joining Date</label><input class="inp" type="date" name="joined_date"></div>
    </div>
    <p style="font-size:11px;color:var(--text2);margin-top:8px">Default password: <strong>teacher123</strong></p>
    <div class="modal-footer">
      <button class="btn btn-primary">Add Teacher</button>
      <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<!-- EDIT MODAL -->
<?php if($editData): ?>
<div class="modal-bg show" id="editModal">
<div class="modal-box">
  <div class="modal-title">Edit Teacher — <?=e($editData['name'])?></div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="tid" value="<?=$editData['id']?>">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Full Name *</label><input class="inp" name="name" value="<?=e($editData['name'])?>" required></div>
      <div class="form-group"><label class="lbl">Emp. Code (read-only)</label><input class="inp" value="<?=e($editData['emp_code'])?>" readonly></div>
      <div class="form-group"><label class="lbl">Qualification</label><input class="inp" name="qualification" value="<?=e($editData['qualification']??'')?>"></div>
      <div class="form-group"><label class="lbl">Phone</label><input class="inp" name="phone" value="<?=e($editData['phone']??'')?>"></div>
      <div class="form-group"><label class="lbl">Joining Date</label><input class="inp" type="date" name="joined_date" value="<?=e($editData['joined_date']??'')?>"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Save Changes</button>
      <a href="teachers.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
