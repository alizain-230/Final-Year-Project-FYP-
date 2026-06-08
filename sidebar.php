<?php
// includes/sidebar.php
$base = 'http://localhost/sms';
$role    = $_SESSION['role']    ?? '';
$current = basename($_SERVER['PHP_SELF']);

function navLink($href, $icon, $label, $current, $file) {
    $active = ($current === $file) ? 'active' : '';
    return '<a href="'.$href.'" class="nav-link '.$active.'">
              <span class="nav-icon">'.$icon.'</span>
              <span class="nav-label">'.$label.'</span>
            </a>';
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">S</div>
    <div>
      <div class="brand-text">EduManage</div>
      <div class="brand-sub">SMS v2.0</div>
    </div>
  </div>

  <nav>
    <?php if ($role === 'admin'): ?>
      <div class="nav-section">Main</div>
      <?= navLink("$base/admin/dashboard.php",      '📊', 'Dashboard',      $current, 'dashboard.php') ?>
      <div class="nav-section">Academic</div>
      <?= navLink("$base/admin/students.php",        '🎒', 'Students',       $current, 'students.php') ?>
      <?= navLink("$base/admin/teachers.php",        '👨‍🏫', 'Teachers',  $current, 'teachers.php') ?>
      <?= navLink("$base/admin/classes.php",         '🏫', 'Classes',        $current, 'classes.php') ?>
      <?= navLink("$base/admin/subjects.php",        '📚', 'Subjects',       $current, 'subjects.php') ?>
      <?= navLink("$base/admin/exams.php",           '📋', 'Exams',          $current, 'exams.php') ?>
      <?= navLink("$base/admin/timetable.php",       '📅', 'Timetable',      $current, 'timetable.php') ?>
      <div class="nav-section">Reports</div>
      <?= navLink("$base/admin/broadsheet.php",      '📄', 'Broadsheet',     $current, 'broadsheet.php') ?>
      <?= navLink("$base/admin/analytics.php",       '📈', 'Analytics',      $current, 'analytics.php') ?>
      <div class="nav-section">Communication</div>
      <?= navLink("$base/admin/notifications.php",   '🔔', 'Notifications',  $current, 'notifications.php') ?>

    <?php elseif ($role === 'teacher'): ?>
      <div class="nav-section">Main</div>
      <?= navLink("$base/teacher/dashboard.php",     '📊', 'Dashboard',      $current, 'dashboard.php') ?>
      <div class="nav-section">Classroom</div>
      <?= navLink("$base/teacher/attendance.php",    '✅', 'Attendance',     $current, 'attendance.php') ?>
      <?= navLink("$base/teacher/marks.php",         '📝', 'Enter Marks',    $current, 'marks.php') ?>
      <?= navLink("$base/teacher/assignments.php",   '📁', 'Assignments',    $current, 'assignments.php') ?>
      <?= navLink("$base/teacher/timetable.php",     '📅', 'My Timetable',   $current, 'timetable.php') ?>
      <div class="nav-section">Alerts</div>
      <?= navLink("$base/teacher/notifications.php", '🔔', 'Notifications',  $current, 'notifications.php') ?>

    <?php elseif ($role === 'student'): ?>
      <div class="nav-section">Main</div>
      <?= navLink("$base/student/dashboard.php",     '📊', 'Dashboard',      $current, 'dashboard.php') ?>
      <div class="nav-section">Academic</div>
      <?= navLink("$base/student/results.php",       '📈', 'My Results',     $current, 'results.php') ?>
      <?= navLink("$base/student/attendance.php",    '✅', 'Attendance',     $current, 'attendance.php') ?>
      <?= navLink("$base/student/assignments.php",   '📋', 'Assignments',    $current, 'assignments.php') ?>
      <?= navLink("$base/student/timetable.php",     '📅', 'Timetable',      $current, 'timetable.php') ?>
      <div class="nav-section">Alerts</div>
      <?= navLink("$base/student/notifications.php", '🔔', 'Notifications',  $current, 'notifications.php') ?>

    <?php elseif ($role === 'parent'): ?>
      <div class="nav-section">Main</div>
      <?= navLink("$base/parent/dashboard.php",      '📊', 'Dashboard',      $current, 'dashboard.php') ?>
      <div class="nav-section">Child Info</div>
      <?= navLink("$base/parent/progress.php",       '📈', 'Progress',       $current, 'progress.php') ?>
      <?= navLink("$base/parent/attendance.php",     '✅', 'Attendance',     $current, 'attendance.php') ?>
      <div class="nav-section">Alerts</div>
      <?= navLink("$base/parent/notifications.php",  '🔔', 'Notifications',  $current, 'notifications.php') ?>
    <?php endif; ?>

    <div class="nav-section">Account</div>
    <?= navLink("$base/profile.php", '👤', 'My Profile', $current, 'profile.php') ?>
    <a href="<?= $base ?>/logout.php" class="nav-link" style="color:#f87171">
      <span class="nav-icon">🚪</span>
      <span class="nav-label">Logout</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    Ali Zain | FYP 2026<br>UAF Sub Campus TTS
  </div>
</aside>
