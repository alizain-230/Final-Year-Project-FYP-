<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Timetable';

$days    = getDays();
$periods = getPeriods();

if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='add') {
    $cid  = (int)post('class_id'); $sid=(int)post('subject_id');
    $tid  = (int)post('teacher_id'); $day=post('day');
    $per  = (int)post('period'); $room=post('room');
    if (!$cid||!$sid||!$tid||!$day||!$per) { setFlash('error','All fields are required.'); }
    else {
        // Conflict: teacher already has a slot this day+period
        $cf=$pdo->prepare("SELECT tt.id,c.name cn,c.section cs FROM timetable tt JOIN classes c ON tt.class_id=c.id WHERE tt.teacher_id=? AND tt.day=? AND tt.period=?");
        $cf->execute([$tid,$day,$per]); $cf=$cf->fetch();
        if ($cf) { setFlash('error',"Conflict! Teacher already has ".e($cf['cn'].' '.$cf['cs'])." on $day Period $per."); }
        else {
            $p=$periods[$per]; $st=$p['start'].':00'; $et=$p['end'].':00';
            try {
                $pdo->prepare("INSERT INTO timetable(class_id,subject_id,teacher_id,day,period,start_time,end_time,room) VALUES(?,?,?,?,?,?,?,?)")
                    ->execute([$cid,$sid,$tid,$day,$per,$st,$et,$room]);
                setFlash('success','Slot added.');
            } catch(Exception $e){ setFlash('error','This slot already exists for that class.'); }
        }
    }
    redirect('timetable.php?cid='.(int)post('class_id'));
}

if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM timetable WHERE id=?")->execute([(int)$_GET['del']]);
    setFlash('success','Slot removed.');
    redirect('timetable.php?cid='.(int)get('cid'));
}

$classes  =$pdo->query("SELECT * FROM classes ORDER BY name,section")->fetchAll();
$teachers =$pdo->query("SELECT t.*,u.name FROM teachers t JOIN users u ON t.user_id=u.id ORDER BY u.name")->fetchAll();
$vcid     = (int)(get('cid') ?: ($classes[0]['id'] ?? 0));
$subjects =$pdo->prepare("SELECT * FROM subjects WHERE class_id=? ORDER BY name");
$subjects->execute([$vcid]); $subjects=$subjects->fetchAll();

// Build grid
$ttRows=$pdo->prepare("SELECT tt.*,s.name sn,u.name tn FROM timetable tt JOIN subjects s ON tt.subject_id=s.id JOIN teachers t ON tt.teacher_id=t.id JOIN users u ON t.user_id=u.id WHERE tt.class_id=?");
$ttRows->execute([$vcid]); $ttRows=$ttRows->fetchAll();
$grid=[];
foreach ($ttRows as $r) $grid[$r['day']][$r['period']]=$r;

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Timetable Manager</h1>
  <div class="page-head-right">
    <button class="btn btn-primary" onclick="openModal('addSlot')">+ Add Slot</button>
  </div>
</div>
<?= showFlash() ?>

<!-- Class selector -->
<form method="GET" class="filter-bar">
  <label class="lbl" style="margin:0">View Class:</label>
  <select class="inp" style="width:200px" name="cid" onchange="this.form.submit()">
    <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$vcid==$c['id']?'selected':''?>><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
  </select>
</form>

<!-- Period times legend -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
  <?php foreach($periods as $pn=>$pd): ?>
  <span style="font-size:10px;background:var(--bg3);border:1px solid var(--border);border-radius:5px;padding:3px 7px;color:var(--text2)">P<?=$pn?>: <?=$pd['start']?>–<?=$pd['end']?></span>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Weekly Schedule — <?php foreach($classes as $c) if($c['id']==$vcid) echo e($c['name'].' '.$c['section']); ?></span></div>
  <div class="card-body tt-wrap">
    <table class="tt-table">
      <thead>
        <tr>
          <th style="width:70px">Period</th>
          <?php foreach($days as $d): ?><th><?=$d?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($periods as $pn=>$pd): ?>
        <tr>
          <td style="text-align:center;color:var(--text2);font-size:11px"><strong>P<?=$pn?></strong><br><?=$pd['start']?></td>
          <?php foreach($days as $d): ?>
          <td>
            <?php if(isset($grid[$d][$pn])): $sl=$grid[$d][$pn]; ?>
            <div class="tt-cell">
              <div class="tt-subj"><?=e($sl['sn'])?></div>
              <div class="tt-info"><?=e($sl['tn'])?></div>
              <?php if($sl['room']): ?><div class="tt-info"><?=e($sl['room'])?></div><?php endif; ?>
              <a href="?del=<?=$sl['id']?>&cid=<?=$vcid?>" onclick="return confirm('Remove slot?')" style="font-size:10px;color:var(--danger)">✕ remove</a>
            </div>
            <?php else: ?>
            <span class="tt-empty">—</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD SLOT MODAL -->
<div class="modal-bg" id="addSlot">
<div class="modal-box wide">
  <div class="modal-title">Add Timetable Slot</div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group"><label class="lbl">Class *</label>
        <select class="inp" name="class_id" required>
          <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$vcid==$c['id']?'selected':''?>><?=e($c['name'].' - '.$c['section'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Subject *</label>
        <select class="inp" name="subject_id" required>
          <option value="">Select Subject</option>
          <?php foreach($subjects as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Teacher *</label>
        <select class="inp" name="teacher_id" required>
          <option value="">Select Teacher</option>
          <?php foreach($teachers as $t): ?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Day *</label>
        <select class="inp" name="day" required>
          <?php foreach($days as $d): ?><option><?=$d?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Period *</label>
        <select class="inp" name="period" required>
          <?php foreach($periods as $pn=>$pd): ?><option value="<?=$pn?>">P<?=$pn?> (<?=$pd['start']?>–<?=$pd['end']?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="lbl">Room</label><input class="inp" name="room" placeholder="e.g. R-101"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Add Slot</button>
      <button type="button" onclick="closeModal('addSlot')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<?php include '../includes/footer.php'; ?>
