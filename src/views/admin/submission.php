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
    <a href="/admin">Admin</a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $examBase ?>/submissions"><?= View::e($exam['title']) ?></a>
</p>
<h1><?= View::e($sub['firstName'] . ' ' . $sub['lastName']) ?>
    <span class="badge <?= $sub['status'] === 'marked' ? 'badge-ok' : '' ?>"><?= ['working' => 'Still working', 'submitted' => 'To mark', 'marked' => 'Marked'][$sub['status']] ?></span>
</h1>
<p class="muted">
    Started <?= date('Y-m-d H:i:s', $sub['startedAt']) ?>
    <?php if (!$working): ?>
        &middot; submitted <?= date('H:i:s', (int)$sub['submittedAt']) ?>
        &middot; took <?= sprintf('%d:%02d', intdiv((int)$sub['durationSec'], 60), (int)$sub['durationSec'] % 60) ?>
    <?php endif; ?>
</p>

<?php if ($working): ?>
    <p class="empty">The student has not submitted yet; answers appear here once they do. The activity log below updates when you reload.</p>
<?php else: ?>
<form method="post" action="<?= $subBase ?>/save">
    <?= Csrf::field() ?>
    <?php foreach ($exam['questions'] as $i => $q): ?>
        <?php $a = $answers[$q['id']] ?? null; ?>
        <section class="q review">
            <div class="q-head">
                <span class="q-num"><?= $i + 1 ?>.</span>
                <span class="q-text"><?= View::e($q['text']) ?></span>
                <span class="q-points">max <?= round((float)$q['points'], 2) ?></span>
            </div>

            <div class="answer">
                <?php switch ($q['type']):
                    case 'radio':
                    case 'select': ?>
                        <?= $a === null ? '<em class="muted">(no answer)</em>' : View::e($optText($q, (string)$a)) ?>
                        <?php break;
                    case 'checkbox': ?>
                        <?= empty($a) ? '<em class="muted">(no answer)</em>' : View::e(implode(', ', array_map(fn($id) => $optText($q, $id), $a))) ?>
                        <?php break;
                    case 'match': ?>
                        <ul class="plain">
                        <?php foreach ($q['pairs'] as $p):
                            $given = $a[$p['id']] ?? null;
                            $givenPair = $given === null ? null : $pairById($q, $given);
                            $ok = $given === $p['id']; ?>
                            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= View::e($p['left']) ?> &rarr; <?= $givenPair ? View::e($givenPair['right']) : '<em>(none)</em>' ?></li>
                        <?php endforeach; ?>
                        </ul>
                        <?php break;
                    case 'upload':
                    case 'draw':
                        if (empty($a['file'])): ?><em class="muted">(nothing uploaded)</em>
                        <?php else:
                            $url = $subBase . '/files/' . rawurlencode($a['file']);
                            if ($q['type'] === 'draw'): ?>
                                <a href="<?= $url ?>" target="_blank"><img class="draw-preview" src="<?= $url ?>" alt="Drawing"></a>
                            <?php else: ?>
                                <a href="<?= $url ?>"><?= View::e($a['name']) ?></a>
                            <?php endif;
                        endif;
                        break;
                    default: ?>
                        <div class="text-answer"><?= trim((string)$a) === '' ? '<em class="muted">(no answer)</em>' : View::e((string)$a) ?></div>
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
                <p class="muted correct">Correct: <?= View::e($correct) ?></p>
            <?php endif; ?>

            <label class="score">Score
                <input type="number" step="0.01" name="score[<?= View::e($q['id']) ?>]" value="<?= isset($scores[$q['id']]) ? (float)$scores[$q['id']] : '' ?>" placeholder="–">
                / <?= round((float)$q['points'], 2) ?>
                <?php if (array_key_exists($q['id'], $auto)): ?>
                    <span class="muted">(auto: <?= $auto[$q['id']] === null ? 'manual' : round((float)$auto[$q['id']], 2) ?>)</span>
                <?php endif; ?>
            </label>
        </section>
    <?php endforeach; ?>

    <p class="total">Total: <strong><?= round(Exams::totalScore($sub), 2) ?></strong> / <?= round($maxScore, 2) ?>
        <span class="muted">(updates after saving)</span></p>

    <label>Comment (teachers only)
        <textarea name="comment" rows="3" class="wide"><?= View::e((string)($sub['comment'] ?? '')) ?></textarea>
    </label>

    <div class="form-actions">
        <button type="submit" name="action" value="save" class="button-secondary">Save</button>
        <?php if ($sub['status'] === 'marked'): ?>
            <button type="submit" name="action" value="unmark" class="button-secondary">Save &amp; reopen</button>
        <?php else: ?>
            <button type="submit" name="action" value="mark">Save &amp; mark as Marked</button>
        <?php endif; ?>
    </div>
</form>

<?php endif; ?>

<?php $flags = Exams::flagSummary($sub); ?>
<?php if (!empty($sub['events'])): ?>
    <h2>Activity log</h2>
    <?php if ($flags['count']): ?>
        <p class="flag-summary"><?= $flags['count'] ?> flagged event<?= $flags['count'] === 1 ? '' : 's' ?><?= $flags['awaySec'] ? ' &middot; ' . sprintf('%d:%02d', intdiv($flags['awaySec'], 60), $flags['awaySec'] % 60) . ' away from the page' : '' ?></p>
    <?php endif; ?>
    <ul class="plain event-log">
        <?php foreach ($sub['events'] as $e): [$label, $flagged] = Exams::describeEvent($e); ?>
            <li class="<?= $flagged ? 'flag' : 'muted' ?>"><?= date('H:i:s', (int)$e['at']) ?> &middot; <?= View::e($label) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="<?= $subBase ?>/delete" onsubmit="return confirm('Delete this submission permanently?');" style="margin-top:2rem;">
    <?= Csrf::field() ?>
    <button type="submit" class="danger">Delete submission</button>
</form>
