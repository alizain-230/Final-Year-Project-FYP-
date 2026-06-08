<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Students';

// ── ADD ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='add') {
    $name   = post('name'); $email  = post('email');
    $roll   = post('roll_no'); $cid = (int)post('class_id');
    $dob    = post('dob'); $gender = post('gender');
    $phone  = post('phone'); $addr  = post('address');
    $fee    = post('fee_status','Pending');
    $pid    = (int)post('parent_id') ?: null;

    if (!$name||!$email||!$roll||!$cid) { setFlash('error','Name, Email, Roll No. and Class are required.'); }
    else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $dup->execute([$email]);
        if ($dup->fetch()) { setFlash('error','Email already exists.'); }
        else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'student')")
                    ->execute([$name,$email,password_hash('student123',PASSWORD_BCRYPT)]);
                $uid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO students(user_id,roll_no,class_id,parent_id,dob,gender,phone,address,fee_status) VALUES(?,?,?,?,?,?,?,?,?)")
                    ->execute([$uid,$roll,$cid,$pid,$dob,$gender,$phone,$addr,$fee]);
                $pdo->commit();
                setFlash('success',"Student '$name' added. Default password: student123");
            } catch(Exception $e) { $pdo->rollBack(); setFlash('error','Error: '.$e->getMessage()); }
        }
    }
    redirect('students.php');
}

// ── EDIT ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='edit') {
    $sid   = (int)post('sid');
    $name  = post('name'); $cid=(int)post('class_id');
    $roll  = post('roll_no'); $dob=post('dob');
    $gender= post('gender'); $phone=post('phone');
    $addr  = post('address'); $fee=post('fee_status');
    $pid   = (int)post('parent_id') ?: null;

    $pdo->prepare("UPDATE users SET name=? WHERE id=(SELECT user_id FROM students WHERE id=?)")->execute([$name,$sid]);
    $pdo->prepare("UPDATE students SET roll_no=?,class_id=?,parent_id=?,dob=?,gender=?,phone=?,address=?,fee_status=? WHERE id=?")
        ->execute([$roll,$cid,$pid,$dob,$gender,$phone,$addr,$fee,$sid]);
    setFlash('success',"Student updated.");
    redirect('students.php');
}

// ── DELETE ─────────────────────────────────────────────────────
if (isset($_GET['del'])) {
    $sid = (int)$_GET['del'];
    $uid = $pdo->prepare("SELECT user_id FROM students WHERE id=?");
    $uid->execute([$sid]); $uid=$uid->fetchColumn();
    if ($uid) { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]); }
    setFlash('success','Student deleted.');
    redirect('students.php');
}

// ── LOAD EDIT DATA ─────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit'])) {
    $es = $pdo->prepare("SELECT s.*,u.name,u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
    $es->execute([(int)$_GET['edit']]); $editData=$es->fetch();
}

// ── LIST ───────────────────────────────────────────────────────
$search = get('q'); $fcid = (int)get('cid'); $ffee = get('fee');
$perPage=15; $page=max(1,(int)get('page',1)); $offset=($page-1)*$perPage;
$where=['1=1']; $params=[];
if ($search) { $where[]='(u.name LIKE ? OR s.roll_no LIKE ?)'; $params=["%$search%","%$search%"]; }
if ($fcid)   { $where[]='s.class_id=?'; $params[]=$fcid; }
if ($ffee)   { $where[]='s.fee_status=?'; $params[]=$ffee; }
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id=u.id WHERE $ws");
$total->execute($params); $total=$total->fetchColumn();
$rows=$pdo->prepare("SELECT s.*,u.name,u.email,c.name cn,c.section cs FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id WHERE $ws ORDER BY s.id DESC LIMIT $perPage OFFSET $offset");
$rows->execute($params); $rows=$rows->fetchAll();
$classes=$pdo->query("SELECT * FROM classes ORDER BY name,section")->fetchAll();
$parents=$pdo->query("SELECT p.*,u.name FROM parents p JOIN users u ON p.user_id=u.id")->fetchAll();

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Student Management</h1>
  <div class="page-head-right">
    <span style="font-size:12px;color:var(--text2)"><?= $total ?> total</span>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Student</button>
  </div>
</div>

<?= showFlash() ?>

<div class="filter-bar">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input class="inp" style="width:200px" name="q" value="<?=e($search)?>" placeholder="🔍 Search name, roll...">
    <select class="inp" style="width:160px" name="cid">
      <option value="">All Classes</option>
      <?php foreach($classes as $c): ?>
      <option value="<?=$c['id']?>" <?=$fcid==$c['id']?'selected':''?>><?=e($c['name'].' '.$c['section'])?></option>
      <?php endforeach; ?>
    </select>
    <select class="inp" style="width:130px" name="fee">
      <option value="">All Fees</option>
      <option <?=$ffee==='Paid'?'selected':''?>>Paid</option>
      <option <?=$ffee==='Pending'?'selected':''?>>Pending</option>
      <option <?=$ffee==='Partial'?'selected':''?>>Partial</option>
    </select>
    <button class="btn btn-primary btn-sm">Filter</button>
    <?php if($search||$fcid||$ffee): ?><a href="students.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Roll No.</th><th>Name</th><th>Class</th><th>Gender</th><th>Phone</th><th>Fee</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($rows as $i=>$s): ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td><span class="badge badge-primary"><?=e($s['roll_no'])?></span></td>
          <td><?=e($s['name'])?></td>
          <td><?=e(($s['cn']??'—').' '.($s['cs']??''))?></td>
          <td><?=e($s['gender']??'—')?></td>
          <td><?=e($s['phone']??'—')?></td>
          <td><span class="badge <?=match($s['fee_status']??''){'Paid'=>'badge-success','Pending'=>'badge-danger',default=>'badge-warning'}?>"><?=e($s['fee_status']??'—')?></span></td>
          <td>
            <a href="?edit=<?=$s['id']?>" class="btn btn-outline btn-xs">Edit</a>
            <button onclick="confirmDel('?del=<?=$s['id']?>','<?=e($s['name'])?>')" class="btn btn-danger btn-xs">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr class="tbl-empty"><td colspan="8">No students found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= paginate($total,$perPage,$page,'students.php?q='.urlencode($search).'&cid='.$fcid.'&fee='.$ffee) ?>
</div>

<!-- ADD MODAL -->
<div class="modal-bg" id="addModal">
<div class="modal-box wide">
  <div class="modal-title">Register New Student</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Full Name *</label><input class="inp" name="name" required placeholder="Muhammad Ali"></div>
      <div class="form-group"><label class="lbl">Email (Login) *</label><input class="inp" type="email" name="email" required placeholder="student@email.com"></div>
      <div class="form-group"><label class="lbl">Roll Number *</label><input class="inp" name="roll_no" required placeholder="2024-001"></div>
      <div class="form-group"><label class="lbl">Class *</label>
        <select class="inp" name="class_id" required><option value="">Select Class</option>
          <?php foreach($classes as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Date of Birth</label><input class="inp" type="date" name="dob"></div>
      <div class="form-group"><label class="lbl">Gender</label>
        <select class="inp" name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select>
      </div>
      <div class="form-group"><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="03xx-xxxxxxx"></div>
      <div class="form-group"><label class="lbl">Fee Status</label>
        <select class="inp" name="fee_status"><option>Pending</option><option>Paid</option><option>Partial</option></select>
      </div>
      <div class="form-group"><label class="lbl">Parent</label>
        <select class="inp" name="parent_id"><option value="">None</option>
          <?php foreach($parents as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-full"><label class="lbl">Address</label><textarea class="inp" name="address" rows="2"></textarea></div>
    </div>
    <p style="font-size:11px;color:var(--text2);margin-top:8px">Default password: <strong>student123</strong></p>
    <div class="modal-footer">
      <button class="btn btn-primary">Register</button>
      <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<!-- EDIT MODAL -->
<?php if($editData): ?>
<div class="modal-bg show" id="editModal">
<div class="modal-box wide">
  <div class="modal-title">Edit Student</div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="sid" value="<?=$editData['id']?>">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Full Name *</label><input class="inp" name="name" value="<?=e($editData['name'])?>" required></div>
      <div class="form-group"><label class="lbl">Roll No. *</label><input class="inp" name="roll_no" value="<?=e($editData['roll_no'])?>" required></div>
      <div class="form-group"><label class="lbl">Class</label>
        <select class="inp" name="class_id">
          <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$editData['class_id']==$c['id']?'selected':''?>><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Date of Birth</label><input class="inp" type="date" name="dob" value="<?=e($editData['dob']??'')?>"></div>
      <div class="form-group"><label class="lbl">Gender</label>
        <select class="inp" name="gender"><?php foreach(['Male','Female','Other'] as $g): ?><option <?=$editData['gender']===$g?'selected':''?>><?=$g?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group"><label class="lbl">Phone</label><input class="inp" name="phone" value="<?=e($editData['phone']??'')?>"></div>
      <div class="form-group"><label class="lbl">Fee Status</label>
        <select class="inp" name="fee_status"><?php foreach(['Paid','Pending','Partial'] as $f): ?><option <?=$editData['fee_status']===$f?'selected':''?>><?=$f?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group"><label class="lbl">Parent</label>
        <select class="inp" name="parent_id"><option value="">None</option>
          <?php foreach($parents as $p): ?><option value="<?=$p['id']?>" <?=$editData['parent_id']==$p['id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-full"><label class="lbl">Address</label><textarea class="inp" name="address" rows="2"><?=e($editData['address']??'')?></textarea></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Save Changes</button>
      <a href="students.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
