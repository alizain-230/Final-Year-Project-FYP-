<?php
// ============================================================
// config/functions.php - All Helper Functions
// ============================================================

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth: check if logged in with correct role ──────────────
function requireLogin($role = null) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /sms/index.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        // Check if array of roles
        if (is_array($role) && !in_array($_SESSION['role'], $role)) {
            header('Location: /sms/index.php?err=access');
            exit;
        } elseif (!is_array($role) && $_SESSION['role'] !== $role) {
            header('Location: /sms/index.php?err=access');
            exit;
        }
    }
}

// ── Safe HTML output ────────────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── Get POST value safely ────────────────────────────────────
function post($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

// ── Get GET value safely ─────────────────────────────────────
function get($key, $default = '') {
    return trim($_GET[$key] ?? $default);
}

// ── Grade from percentage ────────────────────────────────────
function getGrade($obtained, $total) {
    if ($total <= 0) return '—';
    $pct = ($obtained / $total) * 100;
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    return 'F';
}

// ── Grade badge CSS class ────────────────────────────────────
function gradeBadge($grade) {
    return match($grade) {
        'A+','A' => 'badge-success',
        'B'      => 'badge-info',
        'C'      => 'badge-warning',
        'D'      => 'badge-orange',
        'F'      => 'badge-danger',
        default  => 'badge-secondary',
    };
}

// ── Flash message: set ───────────────────────────────────────
function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

// ── Flash message: show and clear ───────────────────────────
function showFlash() {
    if (!isset($_SESSION['flash'])) return '';
    $f   = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert '.$cls.'">'.e($f['msg']).'</div>';
}

// ── Redirect ─────────────────────────────────────────────────
function redirect($url) {
    header("Location: $url");
    exit;
}

// ── Unread notification count for nav badge ─────────────────
function unreadCount($pdo, $userId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE to_id=? AND is_read=0");
    $s->execute([$userId]);
    return $s->fetchColumn();
}

// ── Pagination helper ────────────────────────────────────────
function paginate($total, $perPage, $current, $url) {
    $pages = max(1, ceil($total / $perPage));
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i == $current) ? 'active' : '';
        $sep    = strpos($url, '?') ? '&' : '?';
        $html  .= '<a href="'.$url.$sep.'page='.$i.'" class="page-btn '.$active.'">'.$i.'</a>';
    }
    $html .= '<span class="page-info">Page '.$current.' of '.$pages.'</span></div>';
    return $html;
}

// ── Days of week array ───────────────────────────────────────
function getDays() {
    return ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
}

// ── Period times ─────────────────────────────────────────────
function getPeriods() {
    return [
        1 => ['label'=>'Period 1','start'=>'08:00','end'=>'08:45'],
        2 => ['label'=>'Period 2','start'=>'08:45','end'=>'09:30'],
        3 => ['label'=>'Period 3','start'=>'09:30','end'=>'10:15'],
        4 => ['label'=>'Period 4','start'=>'10:30','end'=>'11:15'],
        5 => ['label'=>'Period 5','start'=>'11:15','end'=>'12:00'],
        6 => ['label'=>'Period 6','start'=>'12:00','end'=>'12:45'],
        7 => ['label'=>'Period 7','start'=>'13:30','end'=>'14:15'],
        8 => ['label'=>'Period 8','start'=>'14:15','end'=>'15:00'],
    ];
}
