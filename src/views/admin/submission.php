<?php
$base = '/admin/classes/' . rawurlencode($classSlug);
$examBase = $base . '/exams/' . rawurlencode($exam['id']);
$subBase = $examBase . '/submissions/' . $sub['id'];
$answers = (array)$sub['answers'];
$scores = (array)$sub['scores'];
$auto = (array)($sub['autoScores'] ?? []);
$working = $sub['status'] === 'working';

$optText = function (array $q, string $id): string {
    foreach ($q['options'] as $o) {
        if ($o['id'] === $id) {
            return $o['text'];
        }
    }
    return '?';
};
$pairById = function (array $q, string $id): ?array {
    foreach ($q['pairs'] as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
};
?>
<p class="breadcrumbs">
    <a href="/admin"><?= View::tr('Admin') ?></a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $examBase ?>/submissions"><?= View::e($exam['title']) ?></a>
</p>
<h1><?= View::e($sub['firstName'] . ' ' . $sub['lastName']) ?>
    <span class="badge <?= $sub['status'] === 'marked' ? 'badge-ok' : '' ?>"><?= View::tr(['working' => 'Still working', 'submitted' => 'To mark', 'marked' => 'Marked'][$sub['status']]) ?></span>
</h1>
<p class="muted">
    <?= View::tr('Started') ?> <?= date('Y-m-d H:i:s', $sub['startedAt']) ?>
    <?php if (!$working): ?>
        &middot; <?= View::tr('submitted') ?> <?= date('H:i:s', (int)$sub['submittedAt']) ?>
        &middot; <?= View::tr('took') ?> <?= sprintf('%d:%02d', intdiv((int)$sub['durationSec'], 60), (int)$sub['durationSec'] % 60) ?>
        <?php if (!empty($sub['late'])): ?>&middot; <span class="flag"><?= View::tr('Submitted {time} after the time limit', ['time' => sprintf('%d:%02d', intdiv((int)$sub['overtimeSec'], 60), (int)$sub['overtimeSec'] % 60)]) ?></span><?php endif; ?>
    <?php endif; ?>
</p>

<?php if ($working): ?>
    <p class="empty"><?= View::tr('The student has not submitted yet; answers appear here once they do. The activity log below updates when you reload.') ?></p>
<?php else: ?>
<form method="post" action="<?= $subBase ?>/save">
    <?= Csrf::field() ?>
    <?php foreach ($exam['questions'] as $i => $q): ?>
        <?php $a = $answers[$q['id']] ?? null; ?>
        <section class="q review">
            <div class="q-head">
                <span class="q-num"><?= $i + 1 ?>.</span>
                <span class="q-text"><?= View::e($q['text']) ?></span>
                <span class="q-points"><?= View::tr('max') ?> <?= round((float)$q['points'], 2) ?></span>
            </div>

            <div class="answer">
                <?php switch ($q['type']):
                    case 'radio':
                    case 'select': ?>
                        <?= $a === null ? '<em class="muted">' . View::tr('(no answer)') . '</em>' : View::e($optText($q, (string)$a)) ?>
                        <?php break;
                    case 'checkbox': ?>
                        <?= empty($a) ? '<em class="muted">' . View::tr('(no answer)') . '</em>' : View::e(implode(', ', array_map(fn($id) => $optText($q, $id), $a))) ?>
                        <?php break;
                    case 'match': ?>
                        <ul class="plain">
                        <?php foreach ($q['pairs'] as $p):
                            $given = $a[$p['id']] ?? null;
                            $givenPair = $given === null ? null : $pairById($q, $given);
                            $ok = $given === $p['id']; ?>
                            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= View::e($p['left']) ?> &rarr; <?= $givenPair ? View::e($givenPair['right']) : '<em>' . View::tr('(none)') . '</em>' ?></li>
                        <?php endforeach; ?>
                        </ul>
                        <?php break;
                    case 'upload':
                    case 'draw':
                        if (empty($a['file'])): ?><em class="muted"><?= View::tr('(nothing uploaded)') ?></em>
                        <?php else:
                            $url = $subBase . '/files/' . rawurlencode($a['file']);
                            if ($q['type'] === 'draw'): ?>
                                <a href="<?= $url ?>" target="_blank"><img class="draw-preview" src="<?= $url ?>" alt="<?= View::tr('Drawing') ?>"></a>
                            <?php else: ?>
                                <a href="<?= $url ?>"><?= View::e($a['name']) ?></a>
                            <?php endif;
                        endif;
                        break;
                    default: ?>
                        <div class="text-answer"><?= trim((string)$a) === '' ? '<em class="muted">' . View::tr('(no answer)') . '</em>' : View::e((string)$a) ?></div>
                <?php endswitch; ?>
            </div>

            <?php
            $correct = null;
            if (in_array($q['type'], ['radio', 'checkbox', 'select'], true)) {
                $c = array_column(array_filter($q['options'], fn($o) => $o['correct']), 'text');
                $correct = $c ? implode(' / ', $c) : null;
            } elseif (in_array($q['type'], ['short_text', 'number'], true) && !empty($q['accepted'])) {
                $correct = implode(' / ', $q['accepted']);
            }
            if ($correct !== null): ?>
                <p class="muted correct"><?= View::tr('Correct:') ?> <?= View::e($correct) ?></p>
            <?php endif; ?>

            <label class="score"><?= View::tr('Score') ?>
                <input type="number" step="0.01" name="score[<?= View::e($q['id']) ?>]" value="<?= isset($scores[$q['id']]) ? (float)$scores[$q['id']] : '' ?>" placeholder="–">
                / <?= round((float)$q['points'], 2) ?>
                <?php if (array_key_exists($q['id'], $auto)): ?>
                    <span class="muted">(<?= View::tr('auto:') ?> <?= $auto[$q['id']] === null ? View::tr('manual') : round((float)$auto[$q['id']], 2) ?>)</span>
                <?php endif; ?>
            </label>
        </section>
    <?php endforeach; ?>

    <p class="total"><?= View::tr('Total:') ?> <strong><?= round(Exams::totalScore($sub), 2) ?></strong> / <?= round($maxScore, 2) ?>
        <span class="muted"><?= View::tr('(updates after saving)') ?></span></p>

    <label><?= View::tr('Comment (teachers only)') ?>
        <textarea name="comment" rows="3" class="wide"><?= View::e((string)($sub['comment'] ?? '')) ?></textarea>
    </label>

    <div class="form-actions">
        <button type="submit" name="action" value="save" class="button-secondary"><?= View::tr('Save') ?></button>
        <?php if ($sub['status'] === 'marked'): ?>
            <button type="submit" name="action" value="unmark" class="button-secondary"><?= View::tr('Save & reopen') ?></button>
        <?php else: ?>
            <button type="submit" name="action" value="mark"><?= View::tr('Save & mark as Marked') ?></button>
        <?php endif; ?>
    </div>
</form>

<?php endif; ?>

<?php $flags = Exams::flagSummary($sub); ?>
<?php if (!empty($sub['events'])): ?>
    <h2><?= View::tr('Activity log') ?></h2>
    <?php if ($flags['count']): ?>
        <p class="flag-summary"><?= View::tr($flags['count'] === 1 ? '{n} flagged event' : '{n} flagged events', ['n' => $flags['count']]) ?><?= $flags['awaySec'] ? ' &middot; ' . View::tr('{time} away from the page', ['time' => sprintf('%d:%02d', intdiv($flags['awaySec'], 60), $flags['awaySec'] % 60)]) : '' ?></p>
    <?php endif; ?>
    <ul class="plain event-log">
        <?php foreach ($sub['events'] as $e): [$label, $flagged] = Exams::describeEvent($e); ?>
            <li class="<?= $flagged ? 'flag' : 'muted' ?>"><?= date('H:i:s', (int)$e['at']) ?> &middot; <?= View::e($label) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="<?= $subBase ?>/delete" onsubmit="return confirm(<?= View::jsq('Delete this submission permanently?') ?>);" style="margin-top:2rem;">
    <?= Csrf::field() ?>
    <button type="submit" class="danger"><?= View::tr('Delete submission') ?></button>
</form>
