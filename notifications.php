<?php
// Shared notifications page for ALL roles
// Works for: admin, teacher, student, parent
require_once '../../config/functions.php';
require_once '../../config/db.php';
requireLogin();
$pageTitle = 'Notifications';
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE to_id=?")->execute([$uid]);

// Send (admin only)
if ($role==='admin' && $_SERVER['REQUEST_METHOD']==='POST') {
    $target  = post('target');
    $title   = post('title');
    $message = post('message');
    $type    = post('type','info');
    if ($title && $message) {
        if ($target==='all')      $recs=$pdo->query("SELECT id FROM users WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        elseif($target==='parents')   $recs=$pdo->query("SELECT id FROM users WHERE role='parent'")->fetchAll(PDO::FETCH_COLUMN);
        elseif($target==='students')  $recs=$pdo->query("SELECT id FROM users WHERE role='student'")->fetchAll(PDO::FETCH_COLUMN);
        elseif($target==='teachers')  $recs=$pdo->query("SELECT id FROM users WHERE role='teacher'")->fetchAll(PDO::FETCH_COLUMN);
        elseif(is_numeric($target))   $recs=[(int)$target];
        else $recs=[];
        $st=$pdo->prepare("INSERT INTO notifications(from_id,to_id,title,message,type) VALUES(?,?,?,?,?)");
        foreach ($recs as $rid) $st->execute([$uid,$rid,$title,$message,$type]);
        setFlash('success','Sent to '.count($recs).' recipients.');
    }
    redirect($_SERVER['PHP_SELF']);
}

// Fetch notifications for this user
$notifs=$pdo->prepare("SELECT n.*,u.name sender FROM notifications n LEFT JOIN users u ON n.from_id=u.id WHERE n.to_id=? ORDER BY n.sent_at DESC");
$notifs->execute([$uid]); $notifs=$notifs->fetchAll();

// For send form: all users
$allUsers=[];
if ($role==='admin') $allUsers=$pdo->query("SELECT id,name,email,role FROM users WHERE is_active=1 ORDER BY role,name")->fetchAll();

include '../../includes/header.php'; ?>
<div class="page-head">
  <h1>Notifications</h1>
  <?php if($role==='admin'): ?>
  <button class="btn btn-primary" onclick="openModal('sendModal')">+ Send Notification</button>
  <?php endif; ?>
</div>
<?= showFlash() ?>

<div class="card">
  <div class="card-header"><span class="card-title">Inbox (<?=count($notifs)?> messages)</span></div>
  <div class="card-body" style="padding:0">
    <?php if($notifs): ?>
    <?php foreach($notifs as $n): ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start">
      <div style="width:8px;height:8px;border-radius:50%;background:<?=['info'=>'var(--info)','success'=>'var(--success)','warning'=>'var(--warning)','danger'=>'var(--danger)'][$n['type']]??'var(--info)'?>;flex-shrink:0;margin-top:5px"></div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600"><?=e($n['title']??'Notice')?></div>
        <div style="font-size:13px;color:var(--text2);margin-top:3px"><?=nl2br(e($n['message']))?></div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px">
          From: <?=e($n['sender']??'System')?> &nbsp;|&nbsp; <?=date('d M Y, H:i',strtotime($n['sent_at']))?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="text-align:center;padding:40px;color:var(--text2)">No notifications yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- SEND MODAL (admin only) -->
<?php if($role==='admin'): ?>
<div class="modal-bg" id="sendModal">
<div class="modal-box wide">
  <div class="modal-title">Send Notification</div>
  <form method="POST">
    <div class="form-group"><label class="lbl">Send To</label>
      <select class="inp" name="target">
        <optgroup label="Groups">
          <option value="all">Everyone</option>
          <option value="parents">All Parents</option>
          <option value="students">All Students</option>
          <option value="teachers">All Teachers</option>
        </optgroup>
        <optgroup label="Individual">
          <?php foreach($allUsers as $u): ?><option value="<?=$u['id']?>">[<?=ucfirst($u['role'])?>] <?=e($u['name'])?></option><?php endforeach; ?>
        </optgroup>
      </select>
    </div>
    <div class="form-group"><label class="lbl">Type</label>
      <select class="inp" name="type">
        <option value="info">Info</option><option value="success">Success</option>
        <option value="warning">Warning</option><option value="danger">Alert</option>
      </select>
    </div>
    <div class="form-group"><label class="lbl">Title *</label><input class="inp" name="title" required placeholder="e.g. Exam Schedule Released"></div>
    <div class="form-group"><label class="lbl">Message *</label><textarea class="inp" name="message" rows="4" required placeholder="Write notification message..."></textarea></div>
    <div style="margin-bottom:12px">
      <div style="font-size:11px;color:var(--text2);margin-bottom:6px">Quick Templates:</div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button type="button" class="btn btn-secondary btn-xs" onclick="fillT('📅 Exam Notice','Exams will be held from [date]. All students must bring admit cards.')">Exam</button>
        <button type="button" class="btn btn-secondary btn-xs" onclick="fillT('🏫 Holiday','School will be closed on [date] due to [reason].')">Holiday</button>
        <button type="button" class="btn btn-secondary btn-xs" onclick="fillT('💰 Fee Reminder','Fees for [month] are due by [date]. Please visit accounts office.')">Fee</button>
        <button type="button" class="btn btn-secondary btn-xs" onclick="fillT('📊 Result Published','Results for [exam] have been published. Login to check.')">Result</button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Send</button>
      <button type="button" onclick="closeModal('sendModal')" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>
<script>
function fillT(title,msg){document.querySelector('[name=title]').value=title;document.querySelector('[name=message]').value=msg;}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
