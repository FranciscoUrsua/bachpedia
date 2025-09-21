<?php
declare(strict_types=1);
require __DIR__ . '/../app/db.php'; // devuelve $pdo (PDO conectado)

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// --- leer querystring
$q            = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$genreId      = isset($_GET['genreId']) ? (int)$_GET['genreId'] : 0;
$keyId        = isset($_GET['keyId']) ? (int)$_GET['keyId'] : 0;
$instrumentId = isset($_GET['instrumentId']) ? (int)$_GET['instrumentId'] : 0;
$sort         = $_GET['sort'] ?? 'relevance'; // relevance | bwv_asc | bwv_desc | title_asc | title_desc
$page         = max(1, (int)($_GET['page'] ?? 1));
$pageSize     = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));
$offset       = ($page - 1) * $pageSize;

// --- eliges FULLTEXT solo si hay algún token con >=3 letras o >=3 dígitos (InnoDB default: 3)
function is_fulltext_candidate(string $q): bool {
  return (bool)preg_match('/\p{L}{3,}|\d{3,}/u', $q);
}
$useFulltext = ($q !== '') && is_fulltext_candidate($q);

// --- construir WHERE común (con o sin MATCH)
function build_where(array $opts): array {
  ['q'=>$q, 'genreId'=>$genreId, 'keyId'=>$keyId, 'instrumentId'=>$instrumentId, 'useFulltext'=>$useFulltext] = $opts;
  $where = [];
  $params = [];
  if ($genreId)      { $where[] = 'w.genreId = :genreId'; $params[':genreId'] = $genreId; }
  if ($keyId)        { $where[] = 'w.keyId   = :keyId';   $params[':keyId']   = $keyId; }
  if ($instrumentId) {
    $where[] = 'EXISTS (SELECT 1 FROM WorkInstrumentation wi WHERE wi.workId = w.id AND wi.instrumentId = :instrId)';
    $params[':instrId'] = $instrumentId;
  }
  if ($useFulltext) {
    $where[] = 'MATCH (w.bwvFull, w.title, w.altTitles, w.opusOrCollection, w.notes) AGAINST (:q IN BOOLEAN MODE)';
    $params[':q'] = $q;
  } elseif ($q !== '') {
    // Fallback para consultas cortas/stopwords
    $where[] = '(w.title LIKE :like OR w.altTitles LIKE :like OR w.opusOrCollection LIKE :like OR w.bwvFull LIKE :like)';
    $params[':like'] = '%' . $q . '%';
  }
  $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  return [$sql, $params];
}

[$whereSql, $params] = build_where(compact('q','genreId','keyId','instrumentId','useFulltext'));

// --- ORDER BY
switch ($sort) {
  case 'bwv_desc':   $orderBy = 'w.bwvId IS NULL, w.bwvId DESC, w.bwvFull DESC'; break;
  case 'title_asc':  $orderBy = 'w.title ASC,  w.bwvFull ASC'; break;
  case 'title_desc': $orderBy = 'w.title DESC, w.bwvFull DESC'; break;
  case 'bwv_asc':    $orderBy = 'w.bwvId IS NULL, w.bwvId ASC,  w.bwvFull ASC'; break;
  case 'relevance':
  default:
    $orderBy = $useFulltext
      ? 'relevance DESC, w.bwvId IS NULL, w.bwvId ASC, w.bwvFull ASC'
      : 'w.bwvId IS NULL, w.bwvId ASC, w.bwvFull ASC';
}

$selectRelevance = $useFulltext
  ? ', MATCH (w.bwvFull, w.title, w.altTitles, w.opusOrCollection, w.notes) AGAINST (:q2 IN BOOLEAN MODE) AS relevance'
  : ', 0 AS relevance';

// --- ITEMS
$sqlItems = "
  SELECT w.id, w.bwvId, w.bwvFull, w.title, w.genreId, w.keyId
         $selectRelevance
  FROM Work w
  $whereSql
  ORDER BY $orderBy
  LIMIT :limit OFFSET :offset
";
$paramsItems = $params;
if ($useFulltext) { $paramsItems[':q2'] = $q; }
$paramsItems[':limit']  = $pageSize;
$paramsItems[':offset'] = $offset;

$stmt = $pdo->prepare($sqlItems);
$stmt->execute($paramsItems);
$items = $stmt->fetchAll();

// --- COUNT
$sqlCount = "SELECT COUNT(DISTINCT w.id) AS total FROM Work w $whereSql";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)($stmt->fetchColumn() ?: 0);

// --- FACETS: recomputar WHERE excluyendo cada filtro de su propia faceta
// Géneros
[$wg, $pg] = build_where(['q'=>$q, 'genreId'=>0, 'keyId'=>$keyId, 'instrumentId'=>$instrumentId, 'useFulltext'=>$useFulltext]);
$sqlFG = "
  SELECT g.id, g.name, COUNT(DISTINCT w.id) AS n
  FROM Genre g
  LEFT JOIN Work w ON w.genreId = g.id
  $wg
  GROUP BY g.id, g.name
  ORDER BY g.name ASC
";
$stFG = $pdo->prepare($sqlFG);
$stFG->execute($pg);
$facetGenres = $stFG->fetchAll();

// Tonalidades
[$wk, $pk] = build_where(['q'=>$q, 'genreId'=>$genreId, 'keyId'=>0, 'instrumentId'=>$instrumentId, 'useFulltext'=>$useFulltext]);
$sqlFK = "
  SELECT k.id, k.name, COUNT(DISTINCT w.id) AS n
  FROM `Key` k
  LEFT JOIN Work w ON w.keyId = k.id
  $wk
  GROUP BY k.id, k.name
  ORDER BY k.name ASC
";
$stFK = $pdo->prepare($sqlFK);
$stFK->execute($pk);
$facetKeys = $stFK->fetchAll();

// Instrumentos
[$wi, $pi] = build_where(['q'=>$q, 'genreId'=>$genreId, 'keyId'=>$keyId, 'instrumentId'=>0, 'useFulltext'=>$useFulltext]);
$sqlFI = "
  SELECT i.id, i.name, COUNT(DISTINCT w.id) AS n
  FROM Instrument i
  LEFT JOIN WorkInstrumentation wi ON wi.instrumentId = i.id
  LEFT JOIN Work w ON w.id = wi.workId
  $wi
  GROUP BY i.id, i.name
  ORDER BY i.name ASC
";
$stFI = $pdo->prepare($sqlFI);
$stFI->execute($pi);
$facetInstr = $stFI->fetchAll();

// --- HTML mínimo
?>
<!doctype html>
<meta charset="utf-8">
<title>Bachpedia — Búsqueda</title>
<link rel="stylesheet" href="/assets/base.css">
<div class="wrap" style="max-width:960px;margin:2rem auto;font:14px system-ui,sans-serif">
  <h1>Búsqueda</h1>

  <form method="get" style="display:grid;grid-template-columns:1fr 180px 180px 220px 180px auto;gap:.5rem;">
    <input name="q" value="<?= e($q) ?>" placeholder='BWV, título, "frase", +incluye -excluye *prefijo'>
    <select name="genreId">
      <option value="">Género</option>
      <?php foreach ($facetGenres as $g): ?>
        <option value="<?= (int)$g['id'] ?>" <?= $genreId===(int)$g['id']?'selected':'' ?>>
          <?= e($g['name']) ?> (<?= (int)$g['n'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="keyId">
      <option value="">Tonalidad</option>
      <?php foreach ($facetKeys as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= $keyId===(int)$k['id']?'selected':'' ?>>
          <?= e($k['name']) ?> (<?= (int)$k['n'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="instrumentId">
      <option value="">Instrumento</option>
      <?php foreach ($facetInstr as $i): ?>
        <option value="<?= (int)$i['id'] ?>" <?= $instrumentId===(int)$i['id']?'selected':'' ?>>
          <?= e($i['name']) ?> (<?= (int)$i['n'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Orden: relevancia</option>
      <option value="bwv_asc"   <?= $sort==='bwv_asc'?'selected':''   ?>>Orden: BWV ↑</option>
      <option value="bwv_desc"  <?= $sort==='bwv_desc'?'selected':''  ?>>Orden: BWV ↓</option>
      <option value="title_asc" <?= $sort==='title_asc'?'selected':'' ?>>Orden: título A–Z</option>
      <option value="title_desc"<?= $sort==='title_desc'?'selected':''?>>Orden: título Z–A</option>
    </select>
    <button>Buscar</button>
  </form>

  <p style="margin:.5rem 0;color:#666"><?= $total ?> resultados • página <?= $page ?></p>

<ul style="list-style:none;padding:0;margin:0;border-top:1px solid #ddd">
  <?php foreach ($items as $w): ?>
    <li style="padding:.6rem 0;border-bottom:1px solid #eee">
      <div style="font-weight:600">
        <a href="/work.php?id=<?= (int)$w['id'] ?>">
          <?= e($w['title']) ?>
        </a>
      </div>
      <div style="color:#555;font-size:12px">
        <?php if ($useFulltext && isset($w['relevance'])): ?>
          relevancia: <?= number_format((float)$w['relevance'], 3) ?>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>


  <div style="display:flex;gap:.5rem;margin-top:.8rem">
    <?php if ($page>1): ?>
      <a class="btn" href="?<?= e(http_build_query(['q'=>$q,'genreId'=>$genreId?:null,'keyId'=>$keyId?:null,'instrumentId'=>$instrumentId?:null,'sort'=>$sort,'page'=>$page-1,'pageSize'=>$pageSize])) ?>">← Anterior</a>
    <?php endif; ?>
    <?php if ($page*$pageSize < $total): ?>
      <a class="btn" href="?<?= e(http_build_query(['q'=>$q,'genreId'=>$genreId?:null,'keyId'=>$keyId?:null,'instrumentId'=>$instrumentId?:null,'sort'=>$sort,'page'=>$page+1,'pageSize'=>$pageSize])) ?>">Siguiente →</a>
    <?php endif; ?>
  </div>
</div>
