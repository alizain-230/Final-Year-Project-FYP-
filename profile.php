<?php
require_once 'config/functions.php';
require_once 'config/db.php';
requireLogin();
$pageTitle = 'My Profile';
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Load user
$user = $pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$uid]);
$user = $user->fetch();

// Change password
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='password') {
    $current = post('current_password');
    $new     = post('new_password');
    $confirm = post('confirm_password');

    if (!password_verify($current, $user['password'])) {
        setFlash('error', 'Current password is incorrect.');
    } elseif (strlen($new) < 6) {
        setFlash('error', 'New password must be at least 6 characters.');
    } elseif ($new !== $confirm) {
        setFlash('error', 'New password and confirm password do not match.');
    } else {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
        setFlash('success', 'Password changed successfully.');
    }
    redirect('profile.php');
}

// Update name/email
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='update') {
    $name  = post('name');
    $email = post('email');
    if (!$name || !$email) {
        setFlash('error', 'Name and email are required.');
    } else {
        $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$name, $email, $uid]);
        $_SESSION['name']  = $name;
        $_SESSION['email'] = $email;
        setFlash('success', 'Profile updated.');
    }
    redirect('profile.php');
}

// Load extra info based on role
$extraInfo = null;
if ($role === 'teacher') {
    $q = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
    $q->execute([$uid]); $extraInfo = $q->fetch();
} elseif ($role === 'student') {
    $q = $pdo->prepare("SELECT s.*,c.name cn,c.section cs FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.user_id=?");
    $q->execute([$uid]); $extraInfo = $q->fetch();
} elseif ($role === 'parent') {
    $q = $pdo->prepare("SELECT * FROM parents WHERE user_id=?");
    $q->execute([$uid]); $extraInfo = $q->fetch();
}

include 'includes/header.php';
?>
<div class="page-head"><h1>My Profile</h1></div>
<?= showFlash() ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

    <!-- Profile info -->
    <div class="card">
        <div class="card-header"><span class="card-title">Account Information</span></div>
        <div class="card-body">
            <!-- Avatar -->
            <div style="text-align:center;margin-bottom:20px">
                <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;margin:0 auto">
                    <?= strtoupper(substr($user['name'],0,2)) ?>
                </div>
                <div style="margin-top:10px;font-size:16px;font-weight:600"><?= e($user['name']) ?></div>
                <span class="badge badge-primary" style="margin-top:4px"><?= ucfirst($role) ?></span>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <label class="lbl">Full Name</label>
                    <input class="inp" type="text" name="name" value="<?= e($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="lbl">Email Address</label>
                    <input class="inp" type="email" name="email" value="<?= e($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="lbl">Role</label>
                    <input class="inp" value="<?= ucfirst($role) ?>" readonly>
                </div>
                <div class="form-group">
                    <label class="lbl">Account Created</label>
                    <input class="inp" value="<?= date('d M Y', strtotime($user['created_at'])) ?>" readonly>
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <div>
        <!-- Change password -->
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="password">
                    <div class="form-group">
                        <label class="lbl">Current Password</label>
                        <input class="inp" type="password" name="current_password" required placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label class="lbl">New Password</label>
                        <input class="inp" type="password" name="new_password" required placeholder="Minimum 6 characters">
                    </div>
                    <div class="form-group">
                        <label class="lbl">Confirm New Password</label>
                        <input class="inp" type="password" name="confirm_password" required placeholder="Repeat new password">
                    </div>
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Role-specific info -->
        <?php if ($extraInfo): ?>
        <div class="card">
            <div class="card-header"><span class="card-title"><?= ucfirst($role) ?> Details</span></div>
            <div class="card-body" style="padding:0">
                <?php if ($role==='student'): ?>
                <?php $rows = [['Roll No.', $extraInfo['roll_no']??'—'],['Class', ($extraInfo['cn']??'—').' '.($extraInfo['cs']??'')],['Gender',$extraInfo['gender']??'—'],['Date of Birth',$extraInfo['dob']??'—'],['Phone',$extraInfo['phone']??'—'],['Fee Status',$extraInfo['fee_status']??'—']]; ?>
                <?php elseif ($role==='teacher'): ?>
                <?php $rows = [['Employee Code',$extraInfo['emp_code']??'—'],['Qualification',$extraInfo['qualification']??'—'],['Phone',$extraInfo['phone']??'—'],['Joined',$extraInfo['joined_date']??'—']]; ?>
                <?php elseif ($role==='parent'): ?>
                <?php $rows = [['Phone',$extraInfo['phone']??'—'],['Address',$extraInfo['address']??'—']]; ?>
                <?php else: ?>
                <?php $rows = []; ?>
                <?php endif; ?>

                <?php foreach($rows as [$label,$value]): ?>
                <div style="display:flex;padding:10px 18px;border-bottom:1px solid var(--border)">
                    <div style="width:140px;font-size:12px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px"><?= $label ?></div>
                    <div style="flex:1;font-size:13px"><?= e($value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
