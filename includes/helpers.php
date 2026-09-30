<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getStr($key, $default = '')
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function postStr($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function postRaw($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit();
}

function redirectSelf()
{
    $uri = '/' . ltrim((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/');
    header('Location: ' . $uri);
    exit();
}

function jsonOut($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function today()
{
    return date('Y-m-d');
}

function validDate($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function addDays($date, $days)
{
    $d = new DateTime($date);
    $d->modify(($days >= 0 ? '+' : '') . (int) $days . ' days');
    return $d->format('Y-m-d');
}

function daysBetween($from, $to)
{
    $a = new DateTime($from);
    $b = new DateTime($to);
    return (int) $a->diff($b)->format('%r%a');
}

function daysOverdue($dueDate, $asOf = null)
{
    $n = daysBetween($dueDate, $asOf ?: today());
    return $n > 0 ? $n : 0;
}

function calcFine($dueDate, $returnDate)
{
    return daysOverdue($dueDate, $returnDate) * FINE_PER_DAY;
}

function fmtDate($date, $format = 'M j, Y')
{
    if (!$date) {
        return '';
    }
    $t = strtotime($date);
    return $t ? date($format, $t) : '';
}

function money($amount)
{
    return CURRENCY_SYMBOL . number_format((float) $amount, 2);
}

function plural($n, $one, $many = null)
{
    return $n === 1 ? $one : ($many ?? $one . 's');
}

function truncate($text, $length)
{
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . "\u{2026}" : $text;
}

function likeTerm($value)
{
    return '%' . addcslashes($value, '%_\\') . '%';
}

function pickEnum($value, array $allowed, $default = '')
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function highlight($text, $term)
{
    $text = (string) $text;
    $term = trim((string) $term);
    if ($term === '' || mb_strlen($term) < 2) {
        return e($text);
    }
    $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return e($text);
    }
    $out = '';
    foreach ($parts as $i => $part) {
        $out .= $i % 2 === 1 ? '<mark>' . e($part) . '</mark>' : e($part);
    }
    return $out;
}

function queryString(array $overrides = [])
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || ($k === 'page' && (int) $v <= 1)) {
            unset($params[$k]);
        }
    }
    $qs = http_build_query($params);
    return $qs === '' ? '' : '?' . $qs;
}

function pageParam()
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function pagination($page, $totalPages)
{
    if ($totalPages <= 1) {
        return '';
    }
    $pages = [1];
    for ($i = $page - 1; $i <= $page + 1; $i++) {
        if ($i > 1 && $i < $totalPages) {
            $pages[] = $i;
        }
    }
    $pages[] = $totalPages;
    $pages = array_values(array_unique($pages));

    $html = '<nav class="pager" aria-label="Pagination"><ul>';
    if ($page > 1) {
        $html .= '<li><a href="' . e(queryString(['page' => $page - 1])) . '" rel="prev" aria-label="Previous page">' . icon('chevron-left') . '</a></li>';
    }
    $prev = 0;
    foreach ($pages as $p) {
        if ($p - $prev > 1) {
            $html .= '<li class="pager-gap" aria-hidden="true">...</li>';
        }
        $current = $p === $page;
        $html .= '<li><a href="' . e(queryString(['page' => $p])) . '"'
              . ($current ? ' aria-current="page"' : '') . '>' . $p . '</a></li>';
        $prev = $p;
    }
    if ($page < $totalPages) {
        $html .= '<li><a href="' . e(queryString(['page' => $page + 1])) . '" rel="next" aria-label="Next page">' . icon('chevron-right') . '</a></li>';
    }
    return $html . '</ul></nav>';
}

function tabs(array $items, $current, $baseParam)
{
    $html = '<nav class="tabs" aria-label="Filter">';
    foreach ($items as $value => $info) {
        $active = (string) $value === (string) $current;
        $html .= '<a href="' . e(queryString([$baseParam => $value, 'page' => 1])) . '"'
              . ($active ? ' aria-current="true"' : '') . '>'
              . e($info['label']) . '<span class="tab-count">' . number_format($info['count']) . '</span></a>';
    }
    return $html . '</nav>';
}

function loanState(array $tx)
{
    if ($tx['status'] === 'returned') {
        return 'returned';
    }
    return daysOverdue($tx['due_date']) > 0 ? 'overdue' : 'active';
}

function loanBadge(array $tx)
{
    $state = loanState($tx);
    $labels = ['returned' => 'Returned', 'overdue' => 'Overdue', 'active' => 'On loan'];
    return '<span class="badge badge-' . $state . '">' . $labels[$state] . '</span>';
}

function bookBadge($status)
{
    return $status === 'Available'
        ? '<span class="badge badge-available">Available</span>'
        : '<span class="badge badge-active">On loan</span>';
}

function dueNote(array $tx)
{
    $days = daysBetween(today(), $tx['due_date']);
    if ($days < 0) {
        $n = abs($days);
        return $n . ' ' . plural($n, 'day') . ' overdue';
    }
    if ($days === 0) {
        return 'Due today';
    }
    return 'Due in ' . $days . ' ' . plural($days, 'day');
}

function nextBookNumber($pdo)
{
    $last = $pdo->query("SELECT book_number FROM books WHERE book_number LIKE 'SA%' ORDER BY LENGTH(book_number) DESC, book_number DESC LIMIT 1")->fetchColumn();
    $num = 1;
    if ($last && preg_match('/SA(\d+)/i', $last, $m)) {
        $num = (int) $m[1] + 1;
    }
    return 'SA' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
}

function nextMemberId($pdo)
{
    $last = $pdo->query('SELECT member_id FROM users ORDER BY id DESC LIMIT 1')->fetchColumn();
    $num = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $num = (int) $m[1] + 1;
    }
    return 'MEM-' . date('Y') . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
}

function searchBooks($pdo, $q, $limit = 10)
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = likeTerm($q);
    $stmt = $pdo->prepare(
        'SELECT id, book_number, title, author, copyright_year, edition, status
         FROM books
         WHERE book_number LIKE ? OR title LIKE ? OR author LIKE ?
         ORDER BY (book_number = ?) DESC, (status = \'Available\') DESC, title ASC, book_number ASC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([$like, $like, $like, $q]);
    $out = [];
    foreach ($stmt->fetchAll() as $b) {
        $available = $b['status'] === 'Available';
        $meta = $b['author'];
        if ($b['copyright_year']) {
            $meta .= ', ' . $b['copyright_year'];
        }
        $out[] = [
            'id'       => (int) $b['id'],
            'code'     => $b['book_number'],
            'title'    => $b['title'],
            'meta'     => $meta,
            'note'     => $available ? 'Available' : 'On loan',
            'disabled' => !$available,
        ];
    }
    return $out;
}

function borrowerNameSql()
{
    return "CASE WHEN t.user_id IS NULL THEN 'Walk-in borrower' WHEN u.id IS NULL THEN 'Deleted member' ELSE u.full_name END";
}

function borrowerIdSql()
{
    return "CASE WHEN u.id IS NULL THEN '' ELSE u.member_id END";
}

function icon($name, $class = '')
{
    return '<svg class="icon' . ($class ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
}

function iconSprite()
{
    $icons = [
        'book'          => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'search'        => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus'          => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'          => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'grid'          => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'log-out'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'clock'         => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        'check'         => '<polyline points="20 6 9 17 4 12"/>',
        'alert'         => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'x'             => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'trash'         => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'printer'       => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'menu'          => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'chevron-left'  => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'arrow-left'    => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'arrow-right'   => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-out'     => '<line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>',
        'arrow-in'      => '<line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/>',
        'file'          => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    ];
    $out = '<svg class="sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><defs>';
    foreach ($icons as $name => $paths) {
        $out .= '<symbol id="i-' . $name . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
    }
    return $out . '</defs></svg>';
}

function activityChart(array $labels, array $borrows, array $returns)
{
    $w = 600;
    $h = 200;
    $padL = 28;
    $padR = 8;
    $padT = 12;
    $padB = 28;
    $max = max(1, max($borrows), max($returns));
    $top = $max < 4 ? 4 : (int) (ceil($max / 2) * 2);
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $n = count($labels);
    $slot = $plotW / max(1, $n);
    $barW = min(18, $slot / 3);

    $summary = 'Borrows and returns over the last ' . $n . ' days. Borrowed ' . array_sum($borrows) . ', returned ' . array_sum($returns) . '.';
    $svg = '<svg class="chart-svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($summary) . '">';
    foreach ([0, $top / 2, $top] as $tick) {
        $y = $padT + $plotH - ($tick / $top) * $plotH;
        $svg .= '<line class="chart-grid" x1="' . $padL . '" x2="' . ($w - $padR) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
        $svg .= '<text class="chart-tick" x="' . ($padL - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end">' . (int) $tick . '</text>';
    }
    foreach ($labels as $i => $label) {
        $cx = $padL + $slot * $i + $slot / 2;
        $bh = ($borrows[$i] / $top) * $plotH;
        $rh = ($returns[$i] / $top) * $plotH;
        $base = $padT + $plotH;
        $svg .= '<rect class="chart-bar-borrow" x="' . round($cx - $barW - 1.5, 1) . '" y="' . round($base - $bh, 1) . '" width="' . round($barW, 1) . '" height="' . round($bh, 1) . '"><title>' . e($label . ': ' . $borrows[$i] . ' borrowed') . '</title></rect>';
        $svg .= '<rect class="chart-bar-return" x="' . round($cx + 1.5, 1) . '" y="' . round($base - $rh, 1) . '" width="' . round($barW, 1) . '" height="' . round($rh, 1) . '"><title>' . e($label . ': ' . $returns[$i] . ' returned') . '</title></rect>';
        $svg .= '<text class="chart-tick" x="' . round($cx, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e($label) . '</text>';
    }
    return $svg . '</svg>';
}

function renderPicker(array $c)
{
    $id       = $c['id'];
    $sel      = $c['selected'] ?? null;
    $required = !empty($c['required']);
    $hasValue = $sel !== null;

    ob_start();
    ?>
    <div class="picker" data-picker data-endpoint="<?= e($c['endpoint']) ?>"<?= $required ? ' data-required' : '' ?>>
        <div class="field">
            <label class="label" for="<?= e($id) ?>-input"><?= e($c['label']) ?><?= $required ? '' : ' <span class="opt">(optional)</span>' ?></label>
            <div data-picker-search<?= $hasValue ? ' hidden' : '' ?>>
                <div class="search">
                    <?= icon('search') ?>
                    <input type="text" class="input" id="<?= e($id) ?>-input" data-picker-input
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= e($id) ?>-list"
                           autocomplete="off" spellcheck="false" placeholder="<?= e($c['placeholder']) ?>">
                </div>
                <ul class="picker-list" id="<?= e($id) ?>-list" role="listbox" aria-label="<?= e($c['label']) ?> results" data-picker-list hidden></ul>
            </div>
            <div class="picker-selected" data-picker-selected<?= $hasValue ? '' : ' hidden' ?>>
                <span class="picker-code" data-picker-code><?= e($sel['code'] ?? '') ?></span>
                <span class="picker-text">
                    <span class="picker-title" data-picker-title><?= e($sel['title'] ?? '') ?></span>
                    <span class="picker-meta" data-picker-meta><?= e($sel['meta'] ?? '') ?></span>
                </span>
                <button type="button" class="btn btn-quiet btn-sm" data-picker-change>Change</button>
            </div>
            <input type="hidden" name="<?= e($c['name']) ?>" value="<?= $hasValue ? (int) $sel['id'] : '' ?>" data-picker-value>
            <?php if (!empty($c['hint'])): ?><span class="hint"><?= e($c['hint']) ?></span><?php endif; ?>
            <span class="hint text-danger" data-picker-error hidden><?= e($c['error'] ?? 'Choose an option to continue.') ?></span>
            <span class="sr-only" role="status" aria-live="polite" data-picker-status></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function noticeHtml($type, $message)
{
    $type = in_array($type, ['success', 'info'], true) ? $type : 'error';
    $ic   = $type === 'success' ? 'check' : ($type === 'info' ? 'info' : 'alert');
    return '<div class="notice is-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '"'
        . ($type === 'success' ? ' data-autodismiss' : '') . '>'
        . icon($ic) . '<p>' . e($message) . '</p>'
        . '<button type="button" class="notice-close" aria-label="Dismiss message">' . icon('x') . '</button></div>';
}

function errorsHtml(array $errors, $heading = 'Please fix the following.')
{
    if (!$errors) {
        return '';
    }
    if (count($errors) === 1) {
        return '<div class="notice is-error" role="alert">' . icon('alert') . '<p>' . e($errors[0]) . '</p></div>';
    }
    $html = '<div class="notice is-error" role="alert">' . icon('alert') . '<div><p>' . e($heading) . '</p><ul>';
    foreach ($errors as $err) {
        $html .= '<li>' . e($err) . '</li>';
    }
    return $html . '</ul></div></div>';
}

function bookPickerItem($pdo, $id, $onlyAvailable = true)
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, book_number, title, author, copyright_year, status FROM books WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b || ($onlyAvailable && $b['status'] !== 'Available')) {
        return null;
    }
    return [
        'id'    => (int) $b['id'],
        'code'  => $b['book_number'],
        'title' => $b['title'],
        'meta'  => $b['author'] . ($b['copyright_year'] ? ', ' . $b['copyright_year'] : ''),
    ];
}
