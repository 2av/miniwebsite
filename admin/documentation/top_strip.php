<div class="doc-admin-strip border-bottom bg-white py-2 px-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small shadow-sm rounded-3">
    <a href="../index.php" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left me-1"></i>Admin dashboard</a>
    <span class="text-muted"><?php echo htmlspecialchars((string) ($_SESSION['admin_email'] ?? '')); ?></span>
    <a href="../logout.php" class="text-decoration-none text-danger">Logout</a>
</div>
