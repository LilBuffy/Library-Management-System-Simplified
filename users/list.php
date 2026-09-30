<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$pdo     = getDB();
$types   = ['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff', 'public' => 'Public'];
$states  = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];
$search  = getStr('search');
$type    = pickEnum(getStr('type'), array_keys($types));
$status  = pickEnum(getStr('status'), array_keys($states));
$perPage = 20;

$where  = [];
$params = [];
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.member_id LIKE ? OR u.email LIKE ?)';
    $params  = array_merge($params, array_fill(0, 4, likeTerm($search)));
}
if ($type !== '') {
    $where[]  = 'u.membership_type = ?';
    $params[] = $type;
}
if ($status !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min(pageParam(), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.status = 'borrowed') AS on_loan
     FROM users u $whereSql
     ORDER BY u.full_name ASC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$area      = 'admin';
$navActive = 'members';
$pageTitle = 'Members';
$pageLead  = 'People who can borrow books. Members register themselves from the sign-in page.';
require APP_ROOT . '/includes/header.php';
?>

<section class="panel" aria-label="Members">
    <form method="get" class="toolbar" role="search">
        <div class="search">
            <?= icon('search') ?>
            <input class="input" type="search" name="search" value="<?= e($search) ?>" placeholder="Search name, username, member ID or email" aria-label="Search members">
        </div>
        <select class="select" name="type" aria-label="Membership type">
            <option value="">All types</option>
            <?php foreach ($types as $v => $l): ?><option value="<?= e($v) ?>"<?= $type === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="status" aria-label="Account status">
            <option value="">Any status</option>
            <?php foreach ($states as $v => $l): ?><option value="<?= e($v) ?>"<?= $status === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter</button>
        <?php if ($search !== '' || $type !== '' || $status !== ''): ?><a class="btn btn-quiet" href="<?= e(url('users/list.php')) ?>">Clear</a><?php endif; ?>
    </form>

    <p class="result-line"><strong><?= number_format($total) ?></strong> <?= plural($total, 'member') ?></p>

    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
                <tr><th>Member</th><th>ID</th><th>Type</th><th>Status</th><th>On loan</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                <tr><td colspan="7" class="cell-empty"><?= $total === 0 && $search === '' && $type === '' && $status === '' ? 'No members have registered yet.' : 'No members match those filters.' ?></td></tr>
                <?php else: foreach ($users as $u): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title"><?= highlight($u['full_name'], $search) ?></span>
                        <span class="cell-sub"><?= highlight($u['username'], $search) ?><?= $u['email'] ? ', ' . highlight($u['email'], $search) : '' ?></span>
                    </td>
                    <td data-label="ID"><span class="mono"><?= highlight($u['member_id'], $search) ?></span></td>
                    <td data-label="Type"><?= e($types[$u['membership_type']] ?? ucfirst($u['membership_type'])) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($u['status'] === 'active' ? 'available' : $u['status']) ?>"><?= e($states[$u['status']] ?? ucfirst($u['status'])) ?></span></td>
                    <td data-label="On loan" class="num"><?= (int) $u['on_loan'] ?></td>
                    <td data-label="Joined" class="nowrap"><?= e(fmtDate($u['joined_date'])) ?></td>
                    <td class="cell-actions col-end">
                        <span class="row-actions">
                            <a class="btn btn-sm btn-quiet" href="<?= e(url('users/edit.php?id=' . (int) $u['id'])) ?>" aria-label="Edit <?= e($u['full_name']) ?>"><?= icon('edit') ?><span>Edit</span></a>
                            <form method="post" action="<?= e(url('users/delete.php')) ?>"
                                  data-confirm="<?= e('Delete ' . $u['full_name'] . ' (' . $u['member_id'] . ')? Their past loans stay in the history as a deleted member. To keep their record, set the account to inactive instead.') ?>"
                                  data-confirm-title="Delete this member?" data-confirm-label="Delete member">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-quiet" aria-label="Delete <?= e($u['full_name']) ?>"><?= icon('trash') ?><span>Delete</span></button>
                            </form>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($page, $totalPages) ?>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
