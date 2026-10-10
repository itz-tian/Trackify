<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

$auth = new Auth(); $auth->requireLogin(); $db = Database::getInstance();
$where='d.deleted_at IS NULL'; $params=[];
$role=$_SESSION['user_role'] ?? 'staff'; $dept=(int)($_SESSION['user_dept'] ?? 0);
if ($role === 'viewer') { $where .= " AND d.status='approved'"; } elseif ($role !== 'admin') { $where .= ' AND d.department_id=?'; $params[]=$dept; }
// Latest 50 documents, shown 10 per page (pagination appears once there are more than 10).
$perPage=10; $cap=50;
$cnt=$db->prepare("SELECT COUNT(*) FROM documents d WHERE $where"); $cnt->execute($params);
$total=min($cap,(int)$cnt->fetchColumn());
$pages=max(1,(int)ceil($total/$perPage));
$page=min($pages,max(1,(int)($_GET['page']??1)));
$offset=($page-1)*$perPage;
$stmt=$db->prepare("SELECT d.*,c.name category_name,dep.name department_name,CONCAT(u.first_name,' ',u.last_name) uploaded_by_name FROM documents d LEFT JOIN categories c ON d.category_id=c.id LEFT JOIN departments dep ON d.department_id=dep.id LEFT JOIN users u ON d.uploaded_by=u.id WHERE $where ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $docs=$stmt->fetchAll();
include __DIR__ . '/includes/header.php'; ?>
<div class="card"><div class="card-header"><span class="card-title"><i data-lucide="clock-3" style="color:var(--apple-blue);margin-right:8px;"></i>Recent Documents</span><span class="text-muted text-sm">Latest 50</span></div><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Document</th><th>Category</th><th>File Type</th><th>File Size</th><th>Uploaded By</th><th>Date</th></tr></thead><tbody>
<?php if(!$docs): ?><tr><td colspan="7" class="text-center text-muted" style="padding:45px;">No recent documents.</td></tr><?php else: foreach($docs as $d): ?><tr><td class="text-sm fw-600"><?= htmlspecialchars($d['reference_number']??'—') ?></td><td><a href="<?= APP_URL ?>/document_view.php?id=<?= (int)$d['id'] ?>" class="fw-600" style="color:var(--text-primary);text-decoration:none;"><?= htmlspecialchars(doc_title($d['title'], $d['reference_number'] ?? null)) ?></a><div class="text-sm text-muted"><?= htmlspecialchars($d['file_name']) ?></div></td><td class="text-sm"><?= htmlspecialchars($d['category_name']??'—') ?></td><td class="text-sm"><?= htmlspecialchars($d['file_type']??strtoupper(pathinfo($d['file_name'],PATHINFO_EXTENSION))) ?></td><td class="text-sm"><?= number_format(((int)$d['file_size'])/1048576,2) ?> MB</td><td class="text-sm"><?= htmlspecialchars($d['uploaded_by_name']??'—') ?></td><td class="text-sm text-muted"><?= date('M d, Y g:i a',strtotime($d['created_at'])) ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div><?= render_pagination($page,$pages,'page') ?></div><?php include __DIR__ . '/includes/footer.php'; ?>
