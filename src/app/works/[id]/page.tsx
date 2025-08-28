import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import { prisma } from '@/lib/prisma';

// Tipos para Next 15: params es un Promise
type Params = Promise<{ id: string }>;

async function getWork(id: number) {
  return prisma.work.findUnique({
    where: { id },
    include: {
      genre: true,
      key: true,
      instruments: { include: { instrument: true } }, // ajusta si tu relación se llama distinto
    },
  });
}

export async function generateMetadata(
  { params }: { params: Params }
): Promise<Metadata> {
  const { id } = await params;                 // 👈 await
  const numericId = Number(id);
  if (Number.isNaN(numericId)) return { title: 'Obra no encontrada | Bachpedia' };

  const w = await prisma.work.findUnique({
    where: { id: numericId },
    select: { title: true, bwvFull: true, opusOrCollection: true },
  });
  if (!w) return { title: 'Obra no encontrada | Bachpedia' };

  const title = `${w.bwvFull} — ${w.title} | Bachpedia`;
  const description = [w.opusOrCollection, 'Catálogo de J. S. Bach'].filter(Boolean).join(' · ');

  return {
    title,
    description,
    openGraph: { title, description, type: 'article' },
    twitter: { card: 'summary', title, description },
  };
}

export default async function Page({ params }: { params: Params }) {
  const { id } = await params;                 // 👈 await
  const numericId = Number(id);
  if (Number.isNaN(numericId)) return notFound();

  const w = await getWork(numericId);
  if (!w) return notFound();

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'MusicComposition',
    name: `${w.bwvFull} — ${w.title}`,
    identifier: w.bwvFull,
    inLanguage: 'de',
    genre: w.genre?.name,
    key: w.key?.name,
    isPartOf: w.opusOrCollection || undefined,
    sameAs: [w.primarySourceUrl, w.spotifyUrl, w.youtubeUrl].filter(Boolean),
  };

  return (
    <div className="mx-auto max-w-4xl p-6 space-y-4">
      <h1 className="text-2xl font-semibold">{w.bwvFull} — {w.title}</h1>

      <dl className="grid grid-cols-2 gap-2 text-sm">
        <dt className="text-gray-500">Género</dt><dd>{w.genre?.name ?? '—'}</dd>
        <dt className="text-gray-500">Tonalidad</dt><dd>{w.key?.name ?? '—'}</dd>
        <dt className="text-gray-500">Colección</dt><dd>{w.opusOrCollection ?? '—'}</dd>
      </dl>

      <div>
        <h2 className="font-medium mb-1">Instrumentación</h2>
        <ul className="list-disc ml-5">
          {w.instruments.map((wi) => (
            <li key={`${wi.workId}-${wi.instrumentId}`}>
              {wi.instrument.name}
              {wi.count ? ` ×${wi.count}` : ''}
              {wi.obbligato ? ' (obbligato)' : ''}
            </li>
          ))}
        </ul>

      </div>

      <div className="flex gap-3 text-sm">
        {w.primarySourceUrl && <a className="underline" href={w.primarySourceUrl} target="_blank">Bach-Digital</a>}
        {w.youtubeUrl && <a className="underline" href={w.youtubeUrl} target="_blank">YouTube</a>}
        {w.spotifyUrl && <a className="underline" href={w.spotifyUrl} target="_blank">Spotify</a>}
        <Link className="underline" href="/search">← Volver a búsqueda</Link>
      </div>

      <script type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
    </div>
  );
}
