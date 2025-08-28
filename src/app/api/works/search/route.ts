import { NextRequest, NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';
import { Prisma } from '@prisma/client';

type SqlParam = number | string;
type Sort = 'relevance' | 'bwv_asc' | 'bwv_desc' | 'title_asc' | 'title_desc';

interface WorkRow {
  id: number;
  bwvId: number | null;
  bwvFull: string;
  title: string;
  subgenre: string | null;
  mode: string | null;
  durationMinEst: number | null;
  opusOrCollection: string | null;
  primarySourceUrl: string | null;
  spotifyUrl: string | null;
  youtubeUrl: string | null;
  genreId: number | null;
  keyId: number | null;
  relevance?: number;
}
interface CountRow { total: bigint; }
interface FacetRow { id: number; name: string; n: number; }

function buildWhere(
  { q, genreId, keyId, instrumentId }:
  { q?: string; genreId?: number; keyId?: number; instrumentId?: number }
) {
  const where: string[] = [];
  const params: SqlParam[] = [];
  if (genreId) { where.push('w.genreId = ?'); params.push(genreId); }
  if (keyId)   { where.push('w.keyId = ?');   params.push(keyId); }
  if (instrumentId) {
    where.push(`EXISTS (
      SELECT 1 FROM WorkInstrumentation wi2
      WHERE wi2.workId = w.id AND wi2.instrumentId = ?
    )`);
    params.push(instrumentId);
  }
  if (q && q.trim()) {
    where.push(`MATCH (w.bwvFull, w.title, w.altTitles, w.opusOrCollection, w.notes)
                AGAINST (? IN BOOLEAN MODE)`);
    params.push(q);
  }
  return { sql: where.length ? 'WHERE ' + where.join(' AND ') : '', params };
}

function getOrderBySql(sort: Sort, hasQuery: boolean) {
  switch (sort) {
    case 'relevance':
      return hasQuery
        ? 'ORDER BY relevance DESC, w.bwvId IS NULL, w.bwvId ASC, w.bwvFull ASC'
        : 'ORDER BY w.bwvId IS NULL, w.bwvId ASC, w.bwvFull ASC';
    case 'bwv_desc':   return 'ORDER BY w.bwvId IS NULL, w.bwvId DESC, w.bwvFull DESC';
    case 'title_asc':  return 'ORDER BY w.title ASC,  w.bwvFull ASC';
    case 'title_desc': return 'ORDER BY w.title DESC, w.bwvFull DESC';
    case 'bwv_asc':
    default:           return 'ORDER BY w.bwvId IS NULL, w.bwvId ASC,  w.bwvFull ASC';
  }
}

export async function GET(req: NextRequest) {
  const sp = req.nextUrl.searchParams;

  const q = sp.get('q') ?? undefined;
  const genreId = sp.get('genreId') ? Number(sp.get('genreId')) : undefined;
  const keyId = sp.get('keyId') ? Number(sp.get('keyId')) : undefined;
  const instrumentId = sp.get('instrumentId') ? Number(sp.get('instrumentId')) : undefined;
  const sort = (sp.get('sort') as Sort) ?? 'relevance';

  const page = Math.max(1, Number(sp.get('page') ?? '1'));
  const pageSize = Math.min(100, Math.max(1, Number(sp.get('pageSize') ?? '20')));
  const offset = (page - 1) * pageSize;

  const { sql: whereSql, params } = buildWhere({ q, genreId, keyId, instrumentId });
  const hasQuery = Boolean(q && q.trim());

  const selectRelevance = hasQuery
    ? `, MATCH (w.bwvFull, w.title, w.altTitles, w.opusOrCollection, w.notes)
         AGAINST (? IN BOOLEAN MODE) AS relevance`
    : `, 0 AS relevance`;

  const orderBySql = getOrderBySql(sort, hasQuery);
  const paramsItems: SqlParam[] = hasQuery ? [...params, q!, pageSize, offset] : [...params, pageSize, offset];

  const itemsSql = `
    SELECT
      w.id, w.bwvId, w.bwvFull, w.title, w.subgenre, w.mode, w.durationMinEst,
      w.opusOrCollection, w.primarySourceUrl, w.spotifyUrl, w.youtubeUrl,
      w.genreId, w.keyId
      ${selectRelevance}
    FROM Work w
    ${whereSql}
    ${orderBySql}
    LIMIT ? OFFSET ?`;

  const countSql = `SELECT COUNT(DISTINCT w.id) AS total FROM Work w ${whereSql}`;

  const whereForGenres = buildWhere({ q, keyId, instrumentId });
  const facetGenreSql = `
    SELECT g.id, g.name, COUNT(DISTINCT w.id) AS n
    FROM Genre g
    LEFT JOIN Work w ON w.genreId = g.id
    ${whereForGenres.sql}
    GROUP BY g.id, g.name
    ORDER BY g.name ASC`;

  const whereForKeys = buildWhere({ q, genreId, instrumentId });
  const facetKeySql = `
    SELECT k.id, k.name, COUNT(DISTINCT w.id) AS n
    FROM \`Key\` k
    LEFT JOIN Work w ON w.keyId = k.id
    ${whereForKeys.sql}
    GROUP BY k.id, k.name
    ORDER BY k.name ASC`;

  const whereForInstr = buildWhere({ q, genreId, keyId });
  const facetInstrSql = `
    SELECT i.id, i.name, COUNT(DISTINCT w.id) AS n
    FROM Instrument i
    LEFT JOIN WorkInstrumentation wi ON wi.instrumentId = i.id
    LEFT JOIN Work w ON w.id = wi.workId
    ${whereForInstr.sql}
    GROUP BY i.id, i.name
    ORDER BY i.name ASC`;

  try {
    const [items, countRows, facetGenres, facetKeys, facetInstruments] = await Promise.all([
      prisma.$queryRawUnsafe<WorkRow[]>(itemsSql, ...paramsItems),
      prisma.$queryRawUnsafe<CountRow[]>(countSql, ...params),
      prisma.$queryRawUnsafe<FacetRow[]>(facetGenreSql, ...whereForGenres.params),
      prisma.$queryRawUnsafe<FacetRow[]>(facetKeySql,   ...whereForKeys.params),
      prisma.$queryRawUnsafe<FacetRow[]>(facetInstrSql, ...whereForInstr.params),
    ]);

    const total = Number(countRows?.[0]?.total ?? 0);

    return NextResponse.json({
      items, total, page, pageSize, sort,
      facets: { genres: facetGenres, keys: facetKeys, instruments: facetInstruments },
    });
  } catch {
    // Fallback sin FULLTEXT
    const andClauses: Prisma.WorkWhereInput[] = [];
    if (genreId) andClauses.push({ genreId });
    if (keyId) andClauses.push({ keyId });
    if (instrumentId) andClauses.push({ instruments: { some: { instrumentId } } });
    if (q) andClauses.push({
      OR: [
        { title: { contains: q } },
        { altTitles: { contains: q } },
        { opusOrCollection: { contains: q } },
        { bwvFull: { contains: q } },
      ],
    });
    const prismaWhere: Prisma.WorkWhereInput = andClauses.length ? { AND: andClauses } : {};

    const [items, total] = await Promise.all([
      prisma.work.findMany({
        where: prismaWhere,
        orderBy:
          sort === 'title_asc'  ? [{ title: 'asc' },  { bwvFull: 'asc' }] :
          sort === 'title_desc' ? [{ title: 'desc' }, { bwvFull: 'desc' }] :
          sort === 'bwv_desc'   ? [{ bwvId: 'desc' }, { bwvFull: 'desc' }] :
                                  [{ bwvId: 'asc' },  { bwvFull: 'asc' }],
        take: pageSize,
        skip: offset,
      }),
      prisma.work.count({ where: prismaWhere }),
    ]);

    return NextResponse.json({ items, total, page, pageSize, sort, facets: null });
  }
}
