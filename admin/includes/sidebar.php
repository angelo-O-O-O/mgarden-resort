<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img 
            src="<?= BASE_URL ?>/images/mgardenlogo.jpg" 
            alt="MGarden" 
            class="sidebar-logo"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="sidebar-logo-fallback" style="display:none;">M</div>
        <div class="sidebar-brand-text">
            <span class="sidebar-title">Admin Panel</span>
            <span class="sidebar-subtitle">MGarden</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= ADMIN_URL ?>/pages/dashboard.php"
           class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= ADMIN_URL ?>/pages/guests.php"
           class="nav-item <?= $current === 'guests.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Guests</span>
        </a>
        <a href="<?= ADMIN_URL ?>/pages/receptionists.php"
           class="nav-item <?= $current === 'receptionists.php' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i>
            <span>Receptionists</span>
        </a>
        <a href="<?= ADMIN_URL ?>/pages/transaction_logs.php"
           class="nav-item <?= $current === 'transaction_logs.php' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i>
            <span>Transaction Logs</span>
        </a>
        <a href="<?= ADMIN_URL ?>/pages/profile.php"
           class="nav-item <?= $current === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i>
            <span>My Profile</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar">
                <span><?= strtoupper(substr(htmlspecialchars($_SESSION['admin_name'] ?? 'A'), 0, 1)) ?></span>
            </div>
            <div class="admin-info-text">
                <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                <span class="admin-role">Administrator</span>
            </div>
        </div>
        <button onclick="location.href='<?= ADMIN_URL ?>/pages/logout.php'" class="btn-logout">
            <i class="fas fa-right-from-bracket"></i>
            <span>Logout</span>
        </button>
    </div>
</aside>