// ============================================================
// assets/js/main.js - Global JavaScript
// School Management System | Ali Zain | FYP 2026
// ============================================================

// ── Sidebar toggle ──────────────────────────────────────────
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    if (!sb) return;
    if (window.innerWidth <= 768) {
        sb.classList.toggle('open');
    } else {
        sb.classList.toggle('collapsed');
        document.getElementById('mainArea').classList.toggle('expanded');
    }
}

// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e) {
    const sb = document.getElementById('sidebar');
    const btn = document.querySelector('.menu-btn');
    if (!sb || !btn) return;
    if (window.innerWidth <= 768 && sb.classList.contains('open')) {
        if (!sb.contains(e.target) && !btn.contains(e.target)) {
            sb.classList.remove('open');
        }
    }
});

// ── Modal open/close ────────────────────────────────────────
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('show');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('show');
}

// Close modal on background click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-bg')) {
        e.target.classList.remove('show');
    }
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-bg.show').forEach(m => m.classList.remove('show'));
    }
});

// ── Confirm delete ──────────────────────────────────────────
function confirmDel(url, name) {
    if (confirm('Delete "' + name + '"?\nThis action cannot be undone.')) {
        window.location.href = url;
    }
}

// ── Auto-hide alerts after 5s ───────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert').forEach(function(a) {
        setTimeout(function() {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }, 5000);
    });
});

// ── Live grade calculator ───────────────────────────────────
// Usage: oninput="calcGrade(this, total, 'grade_id')"
function calcGrade(input, total, badgeId) {
    const val = parseFloat(input.value);
    const el  = document.getElementById(badgeId);
    if (!el) return;

    if (isNaN(val) || val < 0 || total <= 0) {
        el.textContent = '—';
        el.className = 'badge badge-secondary';
        return;
    }
    const pct = (val / total) * 100;
    let grade = 'F', cls = 'badge-danger';
    if (pct >= 90) { grade = 'A+'; cls = 'badge-success'; }
    else if (pct >= 80) { grade = 'A';  cls = 'badge-success'; }
    else if (pct >= 70) { grade = 'B';  cls = 'badge-info'; }
    else if (pct >= 60) { grade = 'C';  cls = 'badge-warning'; }
    else if (pct >= 50) { grade = 'D';  cls = 'badge-orange'; }

    el.textContent = grade;
    el.className = 'badge ' + cls;
}

// ── Mark all attendance ─────────────────────────────────────
function markAll(status) {
    document.querySelectorAll('input[name^="status["]').forEach(function(r) {
        if (r.value === status) r.checked = true;
    });
}

// ── Image preview ───────────────────────────────────────────
function previewImg(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(previewId);
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Chart default config ────────────────────────────────────
const chartDefaults = {
    plugins: {
        legend: { labels: { color: '#8b949e', font: { size: 11, family: 'Inter' } } }
    },
    scales: {
        x: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: 'rgba(48,54,61,0.8)' } },
        y: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: 'rgba(48,54,61,0.8)' } }
    }
};
