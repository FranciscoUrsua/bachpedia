'use client';

import { useSearchParams, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';

type Item = {
  id: number;
  bwvId: number | null;
  bwvFull: string;
  title: string;
  genreId: number | null;
  keyId: number | null;
  relevance?: number;
};
type Facet = { id: number; name: string; n: number };
type Sort = 'relevance' | 'bwv_asc' | 'bwv_desc' | 'title_asc' | 'title_desc';

export function SearchClient() {
  const sp = useSearchParams();
  const router = useRouter();

  // Estado
  const [q, setQ] = useState(sp.get('q') ?? '');
  const [genreId, setGenreId] = useState(sp.get('genreId') ?? '');
  const [keyId, setKeyId] = useState(sp.get('keyId') ?? '');
  const [instrumentId, setInstrumentId] = useState(sp.get('instrumentId') ?? '');
  const [sort, setSort] = useState<Sort>((sp.get('sort') as Sort) ?? 'relevance');
  const [page, setPage] = useState(Number(sp.get('page') ?? '1'));

  const [items, setItems] = useState<Item[]>([]);
  const [total, setTotal] = useState(0);
  const [facets, setFacets] = useState<{ genres: Facet[]; keys: Facet[]; instruments: Facet[] } | null>(null);

  const pageSize = 20; // <-- necesario

  function pushQS(nextPage = 1) {
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (genreId) params.set('genreId', genreId);
    if (keyId) params.set('keyId', keyId);
    if (instrumentId) params.set('instrumentId', instrumentId);
    if (sort) params.set('sort', sort);
    if (nextPage > 1) params.set('page', String(nextPage));
    router.push(`/search?${params.toString()}`);
  }

  useEffect(() => {
    const controller = new AbortController();
    const url = new URL('/api/works/search', window.location.origin);
    if (q) url.searchParams.set('q', q);
    if (genreId) url.searchParams.set('genreId', genreId);
    if (keyId) url.searchParams.set('keyId', keyId);
    if (instrumentId) url.searchParams.set('instrumentId', instrumentId);
    if (sort) url.searchParams.set('sort', sort);
    url.searchParams.set('page', String(page));
    url.searchParams.set('pageSize', String(pageSize));

    fetch(url.toString(), { signal: controller.signal })
      .then((r) => r.json())
      .then((data) => {
        setItems(data.items ?? []);
        setTotal(data.total ?? 0);
        setFacets(data.facets ?? null);
      })
      .catch(() => { /* ignore abort */ });

    return () => controller.abort();
  }, [q, genreId, keyId, instrumentId, sort, page]);

  return (
    <div className="space-y-4">
      <form
        onSubmit={(e) => { e.preventDefault(); setPage(1); pushQS(1); }}
        className="grid grid-cols-1 md:grid-cols-6 gap-2"
      >
        <input
          className="border rounded px-3 py-2 md:col-span-2"
          placeholder='BWV, título, colección (operadores: + -"frase" *)'
          value={q}
          onChange={(e) => { setQ(e.target.value); }}
        />
        <select
          className="border rounded px-3 py-2"
          value={genreId}
          onChange={(e) => { setGenreId(e.target.value); setPage(1); }}
        >
          <option value="">Género</option>
          {facets?.genres?.map((g) => <option key={g.id} value={g.id}>{g.name} ({g.n})</option>)}
        </select>
        <select
          className="border rounded px-3 py-2"
          value={keyId}
          onChange={(e) => { setKeyId(e.target.value); setPage(1); }}
        >
          <option value="">Tonalidad</option>
          {facets?.keys?.map((k) => <option key={k.id} value={k.id}>{k.name} ({k.n})</option>)}
        </select>
        <select
          className="border rounded px-3 py-2"
          value={instrumentId}
          onChange={(e) => { setInstrumentId(e.target.value); setPage(1); }}
        >
          <option value="">Instrumento</option>
          {facets?.instruments?.map((i) => <option key={i.id} value={i.id}>{i.name} ({i.n})</option>)}
        </select>
        <select
          className="border rounded px-3 py-2"
          value={sort}
          onChange={(e) => { setSort(e.target.value as Sort); setPage(1); }}
        >
          <option value="relevance">Orden: relevancia</option>
          <option value="bwv_asc">Orden: BWV ↑</option>
          <option value="bwv_desc">Orden: BWV ↓</option>
          <option value="title_asc">Orden: título A–Z</option>
          <option value="title_desc">Orden: título Z–A</option>
        </select>
        <button className="border rounded px-3 py-2">Buscar</button>
      </form>

      <p className="text-sm text-gray-600">{total} resultados • página {page}</p>

      <ul className="divide-y">
        {items.map((w) => (
          <li key={w.id} className="py-3">
            <div className="font-medium">{w.bwvFull} — {w.title}</div>
            <div className="text-sm text-gray-600">
              {w.relevance !== undefined ? `relevancia: ${Number(w.relevance).toFixed(3)} • ` : ''}
              {w.bwvId ? `BWV ${w.bwvId}` : ''}
            </div>
          </li>
        ))}
      </ul>

      <div className="flex gap-2">
        {page > 1 && (
          <button
            className="border rounded px-3 py-2"
            onClick={() => { setPage(page - 1); pushQS(page - 1); }}
          >
            ← Anterior
          </button>
        )}
        {(page * pageSize) < total && (
          <button
            className="border rounded px-3 py-2"
            onClick={() => { setPage(page + 1); pushQS(page + 1); }}
          >
            Siguiente →
          </button>
        )}
      </div>
    </div>
  );
}
