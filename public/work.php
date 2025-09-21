<?php
declare(strict_types=1);

require __DIR__ . '/../app/db.php';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function renderValue(null|string|int|float $value): string {
    if ($value === null) {
        return '<span class="muted">—</span>';
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '<span class="muted">—</span>';
        }
        return e($value);
    }
    return e((string)$value);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    ?>
    <!doctype html>
    <meta charset="utf-8">
    <title>Obra no especificada · Bachpedia</title>
    <link rel="stylesheet" href="/assets/base.css">
    <div class="wrap" style="max-width:760px;margin:2rem auto;font:15px/1.6 system-ui,sans-serif">
        <h1>Obra no especificada</h1>
        <p>Debes indicar un parámetro <code>id</code> para consultar una obra.</p>
        <p><a href="/search.php">Volver a la búsqueda</a></p>
    </div>
    <?php
    exit;
}

$sql = <<<SQL
    SELECT w.*, g.name AS genreName, k.name AS keyName, p.name AS composerName
    FROM Work w
    LEFT JOIN Genre g ON w.genreId = g.id
    LEFT JOIN `Key` k ON w.keyId = k.id
    LEFT JOIN Person p ON w.composerId = p.id
    WHERE w.id = :id
    LIMIT 1
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$work = $stmt->fetch();

if (!$work) {
    http_response_code(404);
    ?>
    <!doctype html>
    <meta charset="utf-8">
    <title>Obra no encontrada · Bachpedia</title>
    <link rel="stylesheet" href="/assets/base.css">
    <div class="wrap" style="max-width:760px;margin:2rem auto;font:15px/1.6 system-ui,sans-serif">
        <h1>Obra no encontrada</h1>
        <p>No existe una obra con el identificador proporcionado.</p>
        <p><a href="/search.php">Volver a la búsqueda</a></p>
    </div>
    <?php
    exit;
}

$instrSql = <<<SQL
    SELECT DISTINCT i.name
    FROM WorkInstrumentation wi
    INNER JOIN Instrument i ON i.id = wi.instrumentId
    WHERE wi.workId = :id
    ORDER BY i.name ASC
SQL;

$stInstr = $pdo->prepare($instrSql);
$stInstr->execute([':id' => $id]);
$instruments = $stInstr->fetchAll(PDO::FETCH_COLUMN, 0);

$details = [
    'Compositor' => $work['composerName'] ?? null,
    'BWV completo' => $work['bwvFull'] ?? null,
    'Número BWV' => $work['bwvId'] ?? null,
    'Género' => $work['genreName'] ?? null,
    'Tonalidad' => $work['keyName'] ?? null,
    'Colección / Opus' => $work['opusOrCollection'] ?? null,
    'ID Open Opus' => $work['openOpusId'] ?? null,
    'ID interno' => $work['id'] ?? null,
];

$title = $work['title'] ?? 'Obra sin título';
$altTitles = $work['altTitles'] ?? null;
$notes = $work['notes'] ?? null;
?>
<!doctype html>
<meta charset="utf-8">
<title><?= e($title) ?> · Bachpedia</title>
<link rel="stylesheet" href="/assets/base.css">
<div class="wrap" style="max-width:760px;margin:2rem auto;font:15px/1.6 system-ui,sans-serif">
    <p><a href="/search.php" class="btn">← Volver a la búsqueda</a></p>
    <h1 style="margin-top:.5rem;"><?= e($title) ?></h1>

    <?php if ($altTitles && trim((string)$altTitles) !== ''): ?>
        <p style="margin-top:.4rem;color:#555;">Títulos alternativos: <?= e((string)$altTitles) ?></p>
    <?php endif; ?>

    <dl class="details" style="display:grid;grid-template-columns:160px 1fr;gap:.4rem 1rem;margin-top:1.5rem;">
        <?php foreach ($details as $label => $value): ?>
            <dt style="font-weight:600;color:#333;"><?= e($label) ?></dt>
            <dd style="margin:0;">
                <?= renderValue($value) ?>
            </dd>
        <?php endforeach; ?>
        <dt style="font-weight:600;color:#333;">Instrumentación</dt>
        <dd style="margin:0;">
            <?php if ($instruments): ?>
                <ul style="margin:0;padding-left:1.2rem;">
                    <?php foreach ($instruments as $name): ?>
                        <li><?= e((string)$name) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </dd>
        <dt style="font-weight:600;color:#333;">Notas</dt>
        <dd style="margin:0;">
            <?php if ($notes && trim((string)$notes) !== ''): ?>
                <div><?= nl2br(e((string)$notes)) ?></div>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </dd>
    </dl>
</div>
