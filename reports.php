<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth();
$auth->requireRole(['admin', 'department_head', 'staff']);
$db   = Database::getInstance();

$userRole = $_SESSION['user_role'] ?? 'staff';
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userDept = (int)($_SESSION['user_dept'] ?? 0);


$scopeWhere = 'deleted_at IS NULL';
$scopeParams = [];
if ($userRole !== 'admin') {
    $scopeWhere .= ' AND department_id = ?';
    $scopeParams[] = $userDept;
}


$statusStmt = $db->prepare("SELECT status, COUNT(*) AS cnt FROM documents WHERE $scopeWhere GROUP BY status");
$statusStmt->execute($scopeParams);
$statusData = $statusStmt->fetchAll();


$catStmt = $db->prepare("
    SELECT c.name, c.color, COUNT(d.id) AS cnt
    FROM categories c 
    LEFT JOIN documents d ON c.id = d.category_id AND $scopeWhere
    GROUP BY c.id 
    ORDER BY cnt DESC
");
$catStmt->execute($scopeParams);
$catData = $catStmt->fetchAll();


if ($userRole === 'admin') {
    $deptStmt = $db->query("
        SELECT dep.name, COUNT(d.id) AS cnt
        FROM departments dep 
        LEFT JOIN documents d ON dep.id = d.department_id AND d.deleted_at IS NULL
        GROUP BY dep.id, dep.name ORDER BY cnt DESC
    ");
    $deptData = $deptStmt->fetchAll();
} else {
    $deptStmt = $db->prepare("
        SELECT dep.name, COUNT(d.id) AS cnt
        FROM departments dep 
        LEFT JOIN documents d ON dep.id = d.department_id AND d.deleted_at IS NULL AND d.department_id = ?
        WHERE dep.id = ?
        GROUP BY dep.id, dep.name ORDER BY cnt DESC
    ");
    $deptStmt->execute([$userDept, $userDept]);
    $deptData = $deptStmt->fetchAll();
}


$monthlyStmt = $db->prepare("
    SELECT DATE_FORMAT(MIN(created_at),'%b %Y') AS month, COUNT(*) AS cnt
    FROM documents
    WHERE $scopeWhere AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY MIN(created_at) ASC
");
$monthlyStmt->execute($scopeParams);
$monthlyData = $monthlyStmt->fetchAll();


if ($userRole === 'admin') {
    $topStmt = $db->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name, COUNT(d.id) AS cnt
        FROM users u 
        LEFT JOIN documents d ON u.id = d.uploaded_by AND d.deleted_at IS NULL
        GROUP BY u.id, u.first_name, u.last_name ORDER BY cnt DESC LIMIT 8
    ");
    $topUsers = $topStmt->fetchAll();
} else {
    $topStmt = $db->prepare("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name, COUNT(d.id) AS cnt
        FROM users u 
        LEFT JOIN documents d ON u.id = d.uploaded_by AND d.deleted_at IS NULL AND d.department_id = ?
        WHERE u.department_id = ?
        GROUP BY u.id, u.first_name, u.last_name ORDER BY cnt DESC LIMIT 8
    ");
    $topStmt->execute([$userDept, $userDept]);
    $topUsers = $topStmt->fetchAll();
}


$totalsStmt = $db->prepare("SELECT
    COUNT(*) AS total,
    SUM(status='draft') AS draft,
    SUM(status='approved') AS approved,
    SUM(status='rejected') AS rejected,
    SUM(status='pending_review' OR status='under_review') AS pending,
    SUM(expires_at IS NOT NULL AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS expiring
FROM documents WHERE $scopeWhere");
$totalsStmt->execute($scopeParams);
$totals = $totalsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>


<div class="card mb-24" style="background:var(--bg-card); border-radius:18px; border:1px solid var(--border-card); box-shadow:var(--shadow-card); overflow:hidden;">
    <div class="card-header" style="border-bottom:1px solid var(--border-hairline); padding:16px 22px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="min-width:240px; flex:1;">
            <span class="card-title" style="font-size:15px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                <i data-lucide="line-chart" style="color:var(--apple-blue); width:18px; height:18px;"></i>
                Analytics &amp; System Reports
            </span>
            <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Consolidated analytics, status distributions, volume trends, and top contributors.</div>
        </div>
        <a href="<?= APP_URL ?>/export.php" class="btn btn-primary btn-sm" style="white-space:nowrap;">
            <i data-lucide="download"></i> Export CSV / Excel
        </a>
    </div>

    <div class="card-body" style="padding:22px;">
        
        <div class="overview-kpi-grid" style="margin-bottom:24px;">
            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Total Docs</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(0,113,227,0.12); color:var(--apple-blue); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="file-text" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--apple-blue); margin-top:4px; line-height:1.2;"><?= $totals['total'] ?? 0 ?></div>
            </div>

            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Pending</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(255,149,0,0.12); color:var(--apple-orange); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="clock" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--apple-orange); margin-top:4px; line-height:1.2;"><?= $totals['pending'] ?? 0 ?></div>
            </div>

            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Approved</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(52,199,89,0.12); color:var(--apple-green); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="circle-check" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--apple-green); margin-top:4px; line-height:1.2;"><?= $totals['approved'] ?? 0 ?></div>
            </div>

            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Rejected</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(255,59,48,0.12); color:var(--apple-red); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="circle-x" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--apple-red); margin-top:4px; line-height:1.2;"><?= $totals['rejected'] ?? 0 ?></div>
            </div>

            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Drafts</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(142,142,147,0.12); color:var(--apple-gray); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="square-pen" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--text-primary); margin-top:4px; line-height:1.2;"><?= $totals['draft'] ?? 0 ?></div>
            </div>

            <div class="overview-kpi-item">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em;">Expiring</div>
                    <div style="width:28px; height:28px; border-radius:7px; background:rgba(175,82,222,0.12); color:var(--apple-purple); display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="triangle-alert" style="width:14px; height:14px;"></i>
                    </div>
                </div>
                <div style="font-size:26px; font-weight:700; color:var(--apple-purple); margin-top:4px; line-height:1.2;"><?= $totals['expiring'] ?? 0 ?></div>
            </div>
        </div>

        
        <div style="border-top:1px solid var(--border-hairline); padding-top:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px; margin-bottom:24px;">
            
            <div style="background:var(--bg-card-subtle); border:1px solid var(--border-hairline); border-radius:14px; padding:18px;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="pie-chart" style="width:16px; height:16px; color:var(--apple-blue);"></i> Documents by Status
                </div>
                <div>
                    <?php
                    $total = array_sum(array_column($statusData,'cnt')) ?: 1;
                    $statusColors2 = [
                        'draft'          => ['#8e8e93', 'Draft'],
                        'pending_review' => ['#ff9500', 'Pending Review'],
                        'under_review'   => ['#0071e3', 'Under Review'],
                        'approved'       => ['#34c759', 'Approved'],
                        'rejected'       => ['#ff3b30', 'Rejected'],
                        'archived'       => ['#636366', 'Archived']
                    ];
                    foreach ($statusData as $s): 
                        $pct = round($s['cnt'] / $total * 100);
                    ?>
                    <div style="margin-bottom:11px;">
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:4px;">
                            <span><?= $statusColors2[$s['status']][1] ?? ucfirst($s['status']) ?></span>
                            <span class="fw-600"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
                        </div>
                        <div style="background:rgba(0,0,0,0.06); border-radius:999px; height:7px; overflow:hidden;">
                            <div style="width:<?= $pct ?>%; background:<?= $statusColors2[$s['status']][0] ?? '#8e8e93' ?>; height:7px; border-radius:999px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($statusData)): ?><div class="text-sm text-muted">No data available.</div><?php endif; ?>
                </div>
            </div>

            
            <div style="background:var(--bg-card-subtle); border:1px solid var(--border-hairline); border-radius:14px; padding:18px;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="tags" style="width:16px; height:16px; color:var(--apple-purple);"></i> Documents by Category
                </div>
                <div>
                    <?php 
                    $catCounts = array_column($catData, 'cnt') ?: [0];
                    $maxCat = max(1, (int)max($catCounts)); 
                    foreach ($catData as $cat): 
                        $pct = min(100, round(($cat['cnt'] / $maxCat) * 100)); 
                    ?>
                    <div style="margin-bottom:11px;">
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:4px;">
                            <span style="display:flex; align-items:center; gap:6px;">
                                <span style="width:8px; height:8px; border-radius:50%; background:<?= htmlspecialchars($cat['color']) ?>; display:inline-block;"></span>
                                <?= htmlspecialchars($cat['name']) ?>
                            </span>
                            <span class="fw-600"><?= $cat['cnt'] ?></span>
                        </div>
                        <div style="background:rgba(0,0,0,0.06); border-radius:999px; height:7px; overflow:hidden;">
                            <div style="width:<?= $pct ?>%; background:<?= htmlspecialchars($cat['color']) ?>; height:7px; border-radius:999px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($catData)): ?><div class="text-sm text-muted">No categories recorded.</div><?php endif; ?>
                </div>
            </div>
        </div>

        
        <div style="border-top:1px solid var(--border-hairline); padding-top:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
            
            <div style="background:var(--bg-card-subtle); border:1px solid var(--border-hairline); border-radius:14px; padding:18px; display:flex; flex-direction:column; justify-content:space-between;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="calendar" style="width:16px; height:16px; color:var(--apple-teal);"></i> Monthly Upload Trends (Last 6 Months)
                </div>
                <?php 
                $monthCounts = array_column($monthlyData, 'cnt') ?: [0];
                $maxMonth = max(1, (int)max($monthCounts)); 
                ?>
                <div style="display:flex; align-items:flex-end; gap:12px; height:130px; padding-top:10px;">
                    <?php foreach ($monthlyData as $m): $h = max(10, round(($m['cnt'] / $maxMonth) * 100)); ?>
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; height:100%; justify-content:flex-end;">
                        <div style="font-size:11.5px; font-weight:600; color:var(--text-primary);"><?= $m['cnt'] ?></div>
                        <div style="width:100%; max-width:38px; height:<?= $h ?>%; background:var(--apple-blue); border-radius:6px 6px 0 0; min-height:8px; transition:height .3s;"></div>
                        <div style="font-size:10px; color:var(--text-secondary); text-align:center; white-space:nowrap;"><?= $m['month'] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($monthlyData)): ?><p class="text-muted text-center" style="width:100%;">No upload records in the past 6 months.</p><?php endif; ?>
                </div>
            </div>

            
            <div style="background:var(--bg-card-subtle); border:1px solid var(--border-hairline); border-radius:14px; padding:18px;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="award" style="width:16px; height:16px; color:var(--apple-orange);"></i> Top Contributors
                </div>
                <div style="max-height:160px; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border-hairline); font-size:11px; text-transform:uppercase; color:var(--text-secondary);">
                                <th style="padding:4px 6px; text-align:left;">#</th>
                                <th style="padding:4px 6px; text-align:left;">User</th>
                                <th style="padding:4px 6px; text-align:right;">Uploads</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topUsers as $i => $u): ?>
                        <tr style="border-bottom:1px solid rgba(0,0,0,0.03); font-size:12.5px;">
                            <td class="text-muted" style="padding:6px;"><?= $i+1 ?></td>
                            <td style="padding:6px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="user-avatar user-avatar-sm" style="width:24px !important; height:24px !important; min-width:24px !important; min-height:24px !important;" title="<?= htmlspecialchars($u['name']) ?>">
                                        <i data-lucide="user-round" style="width:12px !important; height:12px !important;"></i>
                                    </div>
                                    <span class="fw-600" style="white-space:nowrap;"><?= htmlspecialchars($u['name']) ?></span>
                                </div>
                            </td>
                            <td style="text-align:right; font-weight:700; color:var(--apple-blue); padding:6px;"><?= $u['cnt'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topUsers)): ?><tr><td colspan="3" class="text-muted text-center" style="padding:16px;">No uploaders yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
