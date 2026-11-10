<?php
$base = '/admin/classes/' . rawurlencode($classSlug);
$examBase = $base . '/exams/' . rawurlencode($exam['id']);
$fmtTime = function (int $s): string {
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
};
$groups = ['working' => [], 'submitted' => [], 'marked' => []];
foreach ($submissions as $s) {
    $groups[$s['status']][] = $s;
}
?>
<meta http-equiv="refresh" content="20">
<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $base ?>/exams">Exams</a> &rsaquo;
    <a href="<?= $examBase ?>"><?= View::e($exam['title']) ?></a>
</p>
<h1><?= View::e($exam['title']) ?> &middot; Submissions</h1>
<p class="empty">This page refreshes every 20 seconds. Max score: <?= round($maxScore, 2) ?></p>

<h2>Still working (<?= count($groups['working']) ?>)</h2>
<?php if (!$groups['working']): ?>
    <p class="empty">Nobody is working on this exam right now.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($groups['working'] as $s): ?>
            <li>
                <a href="<?= $examBase ?>/submissions/<?= $s['id'] ?>"><?= View::e($s['lastName'] . ' ' . $s['firstName']) ?></a>
                <span class="actions">
                    <?php $fl = Exams::flagSummary($s); if ($fl['count']): ?><span class="flag"><?= $fl['count'] ?> flag<?= $fl['count'] === 1 ? '' : 's' ?></span><?php endif; ?>
                    <span class="muted">started <?= date('H:i', $s['startedAt']) ?> &middot; <?= $fmtTime(max(0, time() - $s['startedAt'])) ?> elapsed</span>
                    <form method="post" action="<?= $examBase ?>/submissions/<?= $s['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Remove this unfinished attempt?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Remove</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Submitted (<?= count($groups['submitted']) + count($groups['marked']) ?>)</h2>
<?php if (!$groups['submitted'] && !$groups['marked']): ?>
    <p class="empty">No submissions yet.</p>
<?php else: ?>
    <table class="data-table">
        <thead><tr><th>Student</th><th>Submitted</th><th>Time taken</th><th>Score</th><th>Flags</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (array_merge($groups['submitted'], $groups['marked']) as $s): ?>
            <tr>
                <td><a href="<?= $examBase ?>/submissions/<?= $s['id'] ?>"><?= View::e($s['lastName'] . ' ' . $s['firstName']) ?></a></td>
                <td><?= date('Y-m-d H:i', (int)$s['submittedAt']) ?></td>
                <td><?= $fmtTime((int)($s['durationSec'] ?? 0)) ?></td>
                <td><?= round(Exams::totalScore($s), 2) ?> / <?= round($maxScore, 2) ?></td>
                <?php $fl = Exams::flagSummary($s); ?>
                <td><?php if ($fl['count']): ?><span class="flag" title="Left the page for <?= $fl['awaySec'] ?>s in total"><?= $fl['count'] ?> &#9873;<?= $fl['awaySec'] ? ' · ' . sprintf('%d:%02d', intdiv($fl['awaySec'], 60), $fl['awaySec'] % 60) . ' away' : '' ?></span><?php else: ?><span class="muted">–</span><?php endif; ?></td>
                <td><span class="badge <?= $s['status'] === 'marked' ? 'badge-ok' : '' ?>"><?= $s['status'] === 'marked' ? 'Marked' : 'To mark' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
