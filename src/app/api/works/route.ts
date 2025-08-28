import { NextResponse } from 'next/server';
import { prisma } from '@/lib/prisma';

export async function GET() {
  const works = await prisma.work.findMany({ include: { genre: true } });
  return NextResponse.json(works);
}
