import type { MetadataRoute } from 'next';
import { prisma } from '@/lib/prisma';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = 'https://bachpedia.com';

  const works = await prisma.work.findMany({
    select: { id: true, updatedAt: true },
    take: 5000,
  });

  const workUrls: MetadataRoute.Sitemap = works.map((w) => ({
    url: `${base}/works/${w.id}`,
    lastModified: w.updatedAt ?? new Date(),
    changeFrequency: 'monthly',
    priority: 0.6,
  }));

  return [
    { url: `${base}/`,       lastModified: new Date(), changeFrequency: 'weekly', priority: 0.8 },
    { url: `${base}/search`, lastModified: new Date(), changeFrequency: 'weekly', priority: 0.7 },
    ...workUrls,
  ];
}
