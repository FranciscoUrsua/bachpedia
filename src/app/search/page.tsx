import { Suspense } from 'react';
import { SearchClient } from './search-client';

export default function Page() {
  return (
    <div className="mx-auto max-w-6xl p-6">
      <h1 className="text-2xl font-semibold mb-4">Búsqueda</h1>
      <Suspense fallback={<div>Loading search…</div>}>
        <SearchClient />
      </Suspense>
    </div>
  );
}
