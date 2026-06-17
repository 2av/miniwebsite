<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/helpers/role_access_helper.php');
require('header.php');

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

if (!role_access_tables_exist($connect)) {
    echo '<div class="container-fluid" style="padding:20px;">';
    echo '<div class="alert alert-warning">';
    echo 'Role access tables are not set up yet. ';
    echo '<a href="create_role_access_settings_table.php">Run setup script</a> first.';
    echo '</div></div>';
    include('footer.php');
    exit;
}

$save_message = '';
$save_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_single_setting'])) {
    $setting_id = (int)($_POST['setting_id'] ?? 0);
    $is_na = isset($_POST['is_not_applicable']) ? 1 : 0;
    $value = isset($_POST['setting_value']) ? trim($_POST['setting_value']) : '';
    $admin_email = mysqli_real_escape_string($connect, $_SESSION['admin_email']);

    if ($setting_id > 0) {
        $stmt = mysqli_prepare($connect,
            "UPDATE role_access_settings
             SET is_not_applicable = ?, setting_value = ?, updated_by = ?
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'issi', $is_na, $value, $admin_email, $setting_id);
        if (mysqli_stmt_execute($stmt)) {
            $save_message = 'Setting updated successfully.';
            $save_type = 'success';
        } else {
            $save_message = 'Failed to update setting.';
            $save_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
}

$profiles = get_role_access_profiles($connect);
$features = get_role_access_features($connect);
$matrix = get_role_access_settings_matrix($connect);

$features_by_group = [];
foreach ($features as $feature) {
    $features_by_group[$feature['feature_group']][] = $feature;
}

function ras_display_label($field_type, $value) {
    if ($field_type === 'yes_no') {
        return strtoupper(trim($value)) === 'YES' ? 'Yes' : 'No';
    }
    $value = trim($value);
    if ($value === '') {
        return '—';
    }
    if (strlen($value) <= 40) {
        return $value;
    }
    return substr($value, 0, 37) . '…';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Role Access Settings</title>
<style>
.ras-page {
    padding: 24px;
    background: #f4f6f9;
    min-height: calc(100vh - 80px);
}
.ras-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
    background: #fff;
    overflow: hidden;
}
.ras-card-header {
    padding: 24px 28px;
    border-bottom: 1px solid #e8ecf1;
    background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
}
.ras-card-header h4 {
    font-weight: 700;
    color: #1a2b4c;
    letter-spacing: -0.02em;
}
.ras-card-body { padding: 0 0 24px; }
.ras-table-wrap {
    overflow: auto;
    max-width: 100%;
    margin: 0 20px;
    border: 1px solid #e8ecf1;
    border-radius: 10px;
}
.ras-table {
    min-width: 1200px;
    font-size: 13px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.ras-table thead th {
    background: #1a2b4c;
    color: #fff;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 14px 16px;
    vertical-align: middle;
    border: none;
    border-right: 1px solid rgba(255,255,255,0.08);
    position: sticky;
    top: 0;
    z-index: 2;
}
.ras-table thead th.feature-col {
    min-width: 240px;
    position: sticky;
    left: 0;
    z-index: 4;
    background: #152238;
}
.ras-table tbody td {
    padding: 0;
    vertical-align: middle;
    border: 1px solid #e8ecf1;
    min-width: 160px;
    height: 56px;
}
.ras-table tbody td.feature-col {
    position: sticky;
    left: 0;
    background: #f8fafc;
    z-index: 1;
    padding: 12px 16px;
    font-weight: 600;
    color: #334155;
    height: auto;
    min-width: 240px;
}
.ras-feature-key {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
    margin-top: 2px;
}
.ras-group-row td {
    background: #eef2f7;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    padding: 10px 16px !important;
    height: auto !important;
    border-top: 2px solid #dbe3ed;
}
.ras-cell-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 56px;
    padding: 8px 12px;
    position: relative;
}
.ras-cell-na {
    background: #f1f5f9;
}
.ras-cell-na .ras-cell-inner {
    /* intentionally blank */
}
.ras-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    cursor: default;
}
.ras-pill-yes { background: #dcfce7; color: #166534; }
.ras-pill-no  { background: #f1f5f9; color: #475569; }
.ras-pill-text {
    background: #eff6ff;
    color: #2563eb;
    width: 32px;
    height: 32px;
    padding: 0;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #dbeafe;
}
.ras-info-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #dbeafe;
    background: #eff6ff;
    color: #2563eb;
    cursor: pointer;
    transition: all 0.2s ease;
}
.ras-info-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}
.ras-edit-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 5px;
    background: transparent;
    color: #94a3b8;
    font-size: 11px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s ease, background 0.2s ease, color 0.2s ease;
}
.ras-cell-applicable:hover .ras-edit-btn { opacity: 1; }
.ras-edit-btn:hover {
    background: #e2e8f0;
    color: #334155;
}
.ras-tooltip-wrap {
    position: relative;
    display: inline-flex;
    max-width: 100%;
}
.ras-tooltip-wrap .ras-pill-text {
    cursor: help;
}
.ras-tooltip-box {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    min-width: 220px;
    max-width: 320px;
    padding: 10px 12px;
    background: #1e293b;
    color: #f8fafc;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.5;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.18);
    z-index: 20;
    white-space: pre-wrap;
    word-break: break-word;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
}
.ras-tooltip-box::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #1e293b;
}
.ras-tooltip-wrap:hover .ras-tooltip-box {
    visibility: visible;
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
.ras-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    padding: 16px 28px;
    background: #fafbfc;
    border-bottom: 1px solid #e8ecf1;
}
.ras-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #64748b;
}
.ras-legend-swatch {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}
.ras-profile-note {
    font-size: 10px;
    color: rgba(255,255,255,0.65);
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    margin-top: 4px;
    line-height: 1.4;
}
.ras-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 1050;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.ras-modal-overlay.show { display: flex; }
.ras-modal {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    overflow: hidden;
}
.ras-modal-head {
    padding: 18px 22px;
    background: #1a2b4c;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ras-modal-head h5 { margin: 0; font-size: 16px; font-weight: 600; }
.ras-modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    opacity: 0.8;
}
.ras-modal-body { padding: 22px; }
.ras-modal-body label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    display: block;
}
.ras-modal-body textarea,
.ras-modal-body select {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 13px;
}
.ras-modal-body textarea { min-height: 120px; resize: vertical; }
.ras-modal-meta {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 16px;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e8ecf1;
}
.ras-modal-foot {
    padding: 16px 22px;
    border-top: 1px solid #e8ecf1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.ras-na-toggle-modal {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #475569;
}
</style>

<div class="container-fluid ras-page">
    <div class="ras-card">
        <div class="ras-card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
            <div class="d-flex align-items-center" style="gap:14px;">
                <button type="button" class="btn btn-light btn-sm" onclick="location.href='index.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h4 class="mb-1">Role Access Settings</h4>
                    <small class="text-muted">Role-wise visibility and access configuration matrix</small>
                </div>
            </div>
        </div>

        <?php if ($save_message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($save_type); ?> mx-4 mt-3 mb-0"><?php echo htmlspecialchars($save_message); ?></div>
        <?php endif; ?>

        <div class="ras-legend">
            <div class="ras-legend-item">
                <span class="ras-legend-swatch" style="background:#f1f5f9;"></span>
                Not Applicable
            </div>
            <div class="ras-legend-item">
                <span class="ras-legend-swatch" style="background:#eff6ff; border-color:#bfdbfe;"></span>
                Configured — hover for details
            </div>
            <div class="ras-legend-item">
                <i class="fas fa-pen ras-legend-swatch" style="display:flex;align-items:center;justify-content:center;background:#fff;font-size:10px;color:#94a3b8;"></i>
                Hover cell to edit
            </div>
        </div>

        <div class="ras-card-body">
            <div class="ras-table-wrap">
                <table class="table ras-table">
                    <thead>
                        <tr>
                            <th class="feature-col">Feature</th>
                            <?php foreach ($profiles as $profile): ?>
                                <th>
                                    <?php echo htmlspecialchars($profile['profile_label']); ?>
                                    <div class="ras-profile-note">
                                        <?php echo htmlspecialchars($profile['base_role']); ?>
                                        <?php if ($profile['requires_collaboration'] !== 'ANY'): ?>
                                            · Collab <?php echo htmlspecialchars($profile['requires_collaboration']); ?>
                                        <?php endif; ?>
                                        <?php if ($profile['requires_influencer'] !== 'ANY'): ?>
                                            · Influencer <?php echo htmlspecialchars($profile['requires_influencer']); ?>
                                        <?php endif; ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($features_by_group as $group_name => $group_features): ?>
                            <tr class="ras-group-row">
                                <td colspan="<?php echo count($profiles) + 1; ?>"><?php echo htmlspecialchars($group_name); ?></td>
                            </tr>
                            <?php foreach ($group_features as $feature):
                                $feature_key = $feature['feature_key'];
                                $field_type = $feature['field_type'];
                            ?>
                            <tr>
                                <td class="feature-col">
                                    <?php echo htmlspecialchars($feature['feature_label']); ?>
                                    <div class="ras-feature-key"><?php echo htmlspecialchars($feature_key); ?></div>
                                </td>
                                <?php foreach ($profiles as $profile):
                                    $pk = $profile['profile_key'];
                                    $cell = $matrix[$pk][$feature_key] ?? ['setting_id' => 0, 'is_not_applicable' => true, 'setting_value' => ''];
                                    $setting_id = (int)$cell['setting_id'];
                                    $is_na = !empty($cell['is_not_applicable']);
                                    $value = $cell['setting_value'] ?? '';
                                    $tooltip_text = trim($value);
                                    $display = ras_display_label($field_type, $value);
                                ?>
                                <td class="<?php echo $is_na ? 'ras-cell-na' : 'ras-cell-applicable'; ?>">
                                    <div class="ras-cell-inner">
                                        <?php if ($is_na): ?>
                                            <!-- blank for Not Applicable -->
                                        <?php elseif ($setting_id > 0): ?>
                                            <?php if ($field_type === 'yes_no'): ?>
                                                <div class="ras-tooltip-wrap">
                                                    <span class="ras-pill <?php echo strtoupper($value) === 'YES' ? 'ras-pill-yes' : 'ras-pill-no'; ?>">
                                                        <?php echo htmlspecialchars($display); ?>
                                                    </span>
                                                    <?php if ($tooltip_text !== ''): ?>
                                                        <div class="ras-tooltip-box"><?php echo htmlspecialchars($tooltip_text); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="ras-tooltip-wrap">
                                                    <span class="ras-pill ras-pill-text">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                    <?php if ($tooltip_text !== ''): ?>
                                                        <div class="ras-tooltip-box"><?php echo htmlspecialchars($tooltip_text); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <button type="button" class="ras-edit-btn" title="Edit"
                                                data-setting-id="<?php echo $setting_id; ?>"
                                                data-profile="<?php echo htmlspecialchars($profile['profile_label']); ?>"
                                                data-feature="<?php echo htmlspecialchars($feature['feature_label']); ?>"
                                                data-field-type="<?php echo htmlspecialchars($field_type); ?>"
                                                data-is-na="0"
                                                data-value="<?php echo htmlspecialchars($value); ?>">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div class="ras-modal-overlay" id="rasEditModal">
    <div class="ras-modal">
        <div class="ras-modal-head">
            <h5>Edit Setting</h5>
            <button type="button" class="ras-modal-close" id="rasModalClose">&times;</button>
        </div>
        <form method="POST" id="rasEditForm">
            <input type="hidden" name="save_single_setting" value="1">
            <input type="hidden" name="setting_id" id="rasSettingId">
            <div class="ras-modal-body">
                <div class="ras-modal-meta" id="rasModalMeta"></div>
                <div class="ras-na-toggle-modal">
                    <input type="checkbox" name="is_not_applicable" id="rasModalNa" value="1">
                    <label for="rasModalNa" style="margin:0;font-weight:500;">Mark as Not Applicable</label>
                </div>
                <div id="rasValueWrap">
                    <label for="rasModalValue">Value</label>
                    <select name="setting_value" id="rasModalSelect" style="display:none;">
                        <option value="YES">Yes</option>
                        <option value="NO">No</option>
                    </select>
                    <textarea name="setting_value" id="rasModalTextarea" style="display:none;"></textarea>
                </div>
            </div>
            <div class="ras-modal-foot">
                <button type="button" class="btn btn-light btn-sm" id="rasModalCancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('rasEditModal');
    const form = document.getElementById('rasEditForm');
    const settingId = document.getElementById('rasSettingId');
    const modalMeta = document.getElementById('rasModalMeta');
    const modalNa = document.getElementById('rasModalNa');
    const modalSelect = document.getElementById('rasModalSelect');
    const modalTextarea = document.getElementById('rasModalTextarea');
    const valueWrap = document.getElementById('rasValueWrap');

    function closeModal() {
        modal.classList.remove('show');
    }

    function syncValueFields(fieldType, value) {
        modalSelect.style.display = 'none';
        modalTextarea.style.display = 'none';
        modalSelect.removeAttribute('name');
        modalTextarea.removeAttribute('name');

        if (fieldType === 'yes_no') {
            modalSelect.style.display = 'block';
            modalSelect.setAttribute('name', 'setting_value');
            modalSelect.value = (value || 'NO').toUpperCase() === 'YES' ? 'YES' : 'NO';
        } else {
            modalTextarea.style.display = 'block';
            modalTextarea.setAttribute('name', 'setting_value');
            modalTextarea.value = value || '';
        }
    }

    function toggleNaState() {
        const isNa = modalNa.checked;
        valueWrap.style.opacity = isNa ? '0.45' : '1';
        valueWrap.style.pointerEvents = isNa ? 'none' : 'auto';
    }

    document.querySelectorAll('.ras-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const fieldType = btn.getAttribute('data-field-type');
            const value = btn.getAttribute('data-value') || '';
            settingId.value = btn.getAttribute('data-setting-id');
            modalMeta.innerHTML = '<strong>' + btn.getAttribute('data-profile') + '</strong><br>' + btn.getAttribute('data-feature');
            modalNa.checked = btn.getAttribute('data-is-na') === '1';
            syncValueFields(fieldType, value);
            toggleNaState();
            modal.classList.add('show');
        });
    });

    modalNa.addEventListener('change', toggleNaState);
    document.getElementById('rasModalClose').addEventListener('click', closeModal);
    document.getElementById('rasModalCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
})();
</script>

<?php include('footer.php'); ?>
</head>
</html>
