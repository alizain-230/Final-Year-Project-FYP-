<?php
require_once '../config/functions.php';
require_once '../config/db.php';
requireLogin('admin');
$pageTitle = 'Broadsheet';

$classes  = $pdo->query("SELECT * FROM classes ORDER BY name,section")->fetchAll();
$types    = ['Quiz','Midterm','Final','Assignment','Practical'];
$vcid     = (int)get('cid');
$vtype    = get('type','Midterm');
$sheet    = [];
$examCols = [];
$classInfo= null;

if ($vcid && $vtype) {
    foreach ($classes as $c) if ($c['id']==$vcid) $classInfo=$c;

    // Exams of this type for this class
    $exStmt=$pdo->prepare("SELECT e.*,s.name sn FROM exams e JOIN subjects s ON e.subject_id=s.id WHERE e.class_id=? AND e.type=? ORDER BY s.name");
    $exStmt->execute([$vcid,$vtype]); $examCols=$exStmt->fetchAll();

    if ($examCols) {
        $stuStmt=$pdo->prepare("SELECT s.*,u.name FROM students s JOIN users u ON s.user_id=u.id WHERE s.class_id=? ORDER BY s.roll_no");
        $stuStmt->execute([$vcid]); $students=$stuStmt->fetchAll();

        foreach ($students as $stu) {
            $row=['stu'=>$stu,'marks'=>[],'total_obt'=>0,'total_max'=>0];
            foreach ($examCols as $ex) {
                $mStmt=$pdo->prepare("SELECT obtained,grade FROM marks WHERE student_id=? AND exam_id=?");
                $mStmt->execute([$stu['id'],$ex['id']]);
                $m=$mStmt->fetch();
                $row['marks'][$ex['id']]=$m ?: ['obtained'=>null,'grade'=>null];
                if ($m) { $row['total_obt']+=(float)$m['obtained']; $row['total_max']+=$ex['total_marks']; }
            }
            $row['pct'] = $row['total_max']>0 ? round($row['total_obt']/$row['total_max']*100,1) : 0;
            $row['grade']= $row['total_max']>0 ? getGrade($row['total_obt'],$row['total_max']) : '—';
            $sheet[]=$row;
        }
        usort($sheet, fn($a,$b)=>$b['pct']<=>$a['pct']);
        foreach ($sheet as $i=>&$r) $r['rank']=$i+1;
        unset($r);
    }
}

include '../includes/header.php'; ?>
<div class="page-head">
  <h1>Broadsheet — Class Result</h1>
  <div class="page-head-right no-print">
    <?php if($sheet): ?>
    <button class="btn btn-success" onclick="printSheet()">🖨️ Print / PDF</button>
    <?php endif; ?>
  </div>
</div>

<form method="GET" class="filter-bar no-print">
  <select class="inp" style="width:180px" name="cid" required>
    <option value="">Select Class</option>
    <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$vcid==$c['id']?'selected':''?>><?=e($c['name'].' '.$c['section'])?></option><?php endforeach; ?>
  </select>
  <select class="inp" style="width:150px" name="type">
    <?php foreach($types as $t): ?><option <?=$vtype===$t?'selected':''?>><?=$t?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-primary">Generate</button>
</form>

<?php if($vcid && empty($examCols)): ?>
<div class="alert alert-warning">No <?=e($vtype)?> exams found for this class. Go to Exams and create them first.</div>
<?php endif; ?>

<?php if($sheet): ?>
<div id="printArea" class="card">

  <!-- Print header (hidden on screen) -->
  <div id="printHead" style="display:none;text-align:center;padding:16px 20px 8px;border-bottom:2px solid #000;margin-bottom:12px">
    <h2 style="font-size:18px;margin-bottom:4px">SCHOOL MANAGEMENT SYSTEM</h2>
    <div style="font-size:13px">University of Agriculture Faisalabad — Sub Campus Toba Tek Singh</div>
    <div style="font-size:14px;font-weight:700;margin-top:6px">CLASS BROADSHEET — <?=e($classInfo['name'].' '.$classInfo['section'])?> | <?=e($vtype)?> | <?=date('Y')?></div>
  </div>

  <div class="card-header no-print"><span class="card-title"><?=e($classInfo['name'].' '.$classInfo['section'])?> | <?=e($vtype)?> | <?=count($sheet)?> Students</span></div>
  <div class="table-wrap">
    <table class="tbl" style="font-size:12px">
      <thead>
        <tr>
          <th>Rank</th><th>Roll No.</th><th>Student Name</th>
          <?php foreach($examCols as $ex): ?><th title="Total: <?=e($ex['total_marks'])?>"><?=e($ex['sn'])?><br><span style="font-size:9px;font-weight:400">/ <?=e($ex['total_marks'])?></span></th><?php endforeach; ?>
          <th>Total</th><th>%</th><th>Grade</th><th>Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($sheet as $row): ?>
        <?php $pass=$row['pct']>=40; ?>
        <tr>
          <td style="text-align:center;font-weight:700;color:<?=['#ffd700','#c0c0c0','#cd7f32'][$row['rank']-1]??'inherit'?>">#<?=$row['rank']?></td>
          <td><?=e($row['stu']['roll_no'])?></td>
          <td style="font-weight:500"><?=e($row['stu']['name'])?></td>
          <?php foreach($examCols as $ex): ?>
          <td style="text-align:center">
            <?php $m=$row['marks'][$ex['id']]; ?>
            <?= $m['obtained']!==null ? e($m['obtained']) : '—' ?>
            <?php if($m['grade']): ?><br><span class="badge <?=gradeBadge($m['grade'])?>" style="font-size:9px"><?=e($m['grade'])?></span><?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td style="text-align:center;font-weight:600"><?=$row['total_obt']?>/<?=$row['total_max']?></td>
          <td style="text-align:center;font-weight:700;color:<?=$row['pct']>=75?'#4ade80':($row['pct']>=50?'#fbbf24':'#f87171')?>"><?=$row['pct']?>%</td>
          <td style="text-align:center"><span class="badge <?=gradeBadge($row['grade'])?>"><?=e($row['grade'])?></span></td>
          <td style="text-align:center"><span class="badge <?=$pass?'badge-success':'badge-danger'?>"><?=$pass?'Pass':'Fail'?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:rgba(37,99,235,0.08);font-weight:600">
          <td colspan="3" style="padding:10px 14px">Class Statistics</td>
          <?php foreach($examCols as $ex):
            $vals=array_filter(array_map(fn($r)=>$r['marks'][$ex['id']]['obtained'],$sheet),fn($v)=>$v!==null);
            $avg=count($vals)?round(array_sum($vals)/count($vals),1):'—';
          ?><td style="text-align:center">Avg: <?=$avg?></td><?php endforeach; ?>
          <?php $passCount=count(array_filter($sheet,fn($r)=>$r['pct']>=40)); ?>
          <td colspan="4" style="padding:10px 14px">
            Class Avg: <?=round(array_sum(array_column($sheet,'pct'))/count($sheet),1)?>% |
            Pass: <?=$passCount?>/<?=count($sheet)?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Signature row for print -->
  <div id="sigRow" style="display:none;margin:30px 20px 10px;display:none">
    <div style="display:flex;justify-content:space-between">
      <div style="text-align:center;width:140px;border-top:1px solid #000;padding-top:5px;font-size:12px">Class Teacher</div>
      <div style="text-align:center;width:140px;border-top:1px solid #000;padding-top:5px;font-size:12px">Principal</div>
      <div style="text-align:center;width:140px;border-top:1px solid #000;padding-top:5px;font-size:12px">Date: __________</div>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
@media print {
  .no-print,.topbar,.sidebar,.page-head,.filter-bar,.alert { display:none!important; }
  .app { display:block; } .content { padding:0; }
  body,td,th { color:#000!important; background:#fff!important; }
  .card { border:none!important; }
  #printHead { display:block!important; }
  #sigRow { display:flex!important; }
  .badge { border:1px solid #999!important; background:transparent!important; color:#000!important; }
}
</style>
<script>
function printSheet() {
  document.getElementById('printHead').style.display='block';
  document.getElementById('sigRow').style.display='flex';
  window.print();
  document.getElementById('printHead').style.display='none';
  document.getElementById('sigRow').style.display='none';
}
</script>

<?php include '../includes/footer.php'; ?>
