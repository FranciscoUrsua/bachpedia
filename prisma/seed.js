const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

async function main() {
  const genre = await prisma.genre.upsert({
    where: { name: 'Concerto' },
    update: {},
    create: { name: 'Concerto' },
  });

  await prisma.work.upsert({
    where: { bwvFull: 'BWV 1041' },
    update: {},
    create: {
      bwvFull: 'BWV 1041',
      title: 'Violin Concerto in A minor',
      genreId: genre.id,
    }
  });

  console.log('Seed OK');
}
main().finally(()=>prisma.$disconnect());
