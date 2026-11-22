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
    <a href="/admin"><?= View::tr('Admin') ?></a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $base ?>/exams"><?= View::tr('Exams') ?></a> &rsaquo;
    <a href="<?= $examBase ?>"><?= View::e($exam['title']) ?></a>
</p>
<h1><?= View::e($exam['title']) ?> &middot; <?= View::tr('Submissions') ?></h1>
<p class="empty"><?= View::tr('This page refreshes every 20 seconds. Max score: {n}', ['n' => round($maxScore, 2)]) ?></p>

<h2><?= View::tr('Still working ({n})', ['n' => count($groups['working'])]) ?></h2>
<?php if (!$groups['working']): ?>
    <p class="empty"><?= View::tr('Nobody is working on this exam right now.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($groups['working'] as $s): ?>
            <li>
                <a href="<?= $examBase ?>/submissions/<?= $s['id'] ?>"><?= View::e($s['lastName'] . ' ' . $s['firstName']) ?></a>
                <span class="actions">
                    <?php $fl = Exams::flagSummary($s); if ($fl['count']): ?><span class="flag"><?= View::tr($fl['count'] === 1 ? '{n} flag' : '{n} flags', ['n' => $fl['count']]) ?></span><?php endif; ?>
                    <span class="muted"><?= View::tr('started {at} · {elapsed} elapsed', ['at' => date('H:i', $s['startedAt']), 'elapsed' => $fmtTime(max(0, time() - $s['startedAt']))]) ?></span>
                    <form method="post" action="<?= $examBase ?>/submissions/<?= $s['id'] ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Remove this unfinished attempt?') ?>);">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger"><?= View::tr('Remove') ?></button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2><?= View::tr('Submitted ({n})', ['n' => count($groups['submitted']) + count($groups['marked'])]) ?></h2>
<?php if (!$groups['submitted'] && !$groups['marked']): ?>
    <p class="empty"><?= View::tr('No submissions yet.') ?></p>
<?php else: ?>
    <table class="data-table">
        <thead><tr><th><?= View::tr('Student') ?></th><th><?= View::tr('Submitted') ?></th><th><?= View::tr('Time taken') ?></th><th><?= View::tr('Score') ?></th><th><?= View::tr('Flags') ?></th><th><?= View::tr('Status') ?></th></tr></thead>
        <tbody>
        <?php foreach (array_merge($groups['submitted'], $groups['marked']) as $s): ?>
            <tr>
                <td><a href="<?= $examBase ?>/submissions/<?= $s['id'] ?>"><?= View::e($s['lastName'] . ' ' . $s['firstName']) ?></a></td>
                <td><?= date('Y-m-d H:i', (int)$s['submittedAt']) ?></td>
                <td><?= $fmtTime((int)($s['durationSec'] ?? 0)) ?></td>
                <td><?= round(Exams::totalScore($s), 2) ?> / <?= round($maxScore, 2) ?></td>
                <?php $fl = Exams::flagSummary($s); ?>
                <td><?php if ($fl['count']): ?><span class="flag" title="<?= View::tr('Left the page for {n}s in total', ['n' => $fl['awaySec']]) ?>"><?= $fl['count'] ?> &#9873;<?= $fl['awaySec'] ? ' · ' . sprintf('%d:%02d', intdiv($fl['awaySec'], 60), $fl['awaySec'] % 60) . ' ' . I18n::ui('away') : '' ?></span><?php else: ?><span class="muted">–</span><?php endif; ?></td>
                <td><span class="badge <?= $s['status'] === 'marked' ? 'badge-ok' : '' ?>"><?= $s['status'] === 'marked' ? View::tr('Marked') : View::tr('To mark') ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
