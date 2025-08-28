import { PrismaClient } from '@prisma/client';
const prisma = new PrismaClient();

async function main() {
  // Composer
  const bach = await prisma.person.upsert({
    where: { id: 1 },
    update: {},
    create: { name: 'Johann Sebastian Bach', defaultRole: 'composer' },
  });

  // Genres
  const [concerto, suite, keyboard, sacred, instrumental] = await Promise.all([
    prisma.genre.upsert({ where: { name: 'Concerto' }, update: {}, create: { name: 'Concerto' } }),
    prisma.genre.upsert({ where: { name: 'Suite' }, update: {}, create: { name: 'Suite' } }),
    prisma.genre.upsert({ where: { name: 'Keyboard' }, update: {}, create: { name: 'Keyboard' } }),
    prisma.genre.upsert({ where: { name: 'Sacred vocal' }, update: {}, create: { name: 'Sacred vocal' } }),
    prisma.genre.upsert({ where: { name: 'Instrumental' }, update: {}, create: { name: 'Instrumental' } }),
  ]);

  // Keys
  const [Amin, Gmaj, Dmaj, Bmin, Cmaj] = await Promise.all([
    prisma.key.upsert({ where: { name: 'A minor' }, update: {}, create: { name: 'A minor' } }),
    prisma.key.upsert({ where: { name: 'G major' }, update: {}, create: { name: 'G major' } }),
    prisma.key.upsert({ where: { name: 'D major' }, update: {}, create: { name: 'D major' } }),
    prisma.key.upsert({ where: { name: 'B minor' }, update: {}, create: { name: 'B minor' } }),
    prisma.key.upsert({ where: { name: 'C major' }, update: {}, create: { name: 'C major' } }),
  ]);

  // Instruments
  const [violin, strings, continuo, cello, oboe, trumpet, timpani, keyboardInstr] = await Promise.all([
    prisma.instrument.upsert({ where: { name: 'Violin' }, update: {}, create: { name: 'Violin', family: 'strings' } }),
    prisma.instrument.upsert({ where: { name: 'Strings' }, update: {}, create: { name: 'Strings', family: 'strings' } }),
    prisma.instrument.upsert({ where: { name: 'Basso continuo' }, update: {}, create: { name: 'Basso continuo', family: 'keyboard' } }),
    prisma.instrument.upsert({ where: { name: 'Violoncello' }, update: {}, create: { name: 'Violoncello', family: 'strings' } }),
    prisma.instrument.upsert({ where: { name: 'Oboe' }, update: {}, create: { name: 'Oboe', family: 'woodwinds' } }),
    prisma.instrument.upsert({ where: { name: 'Trumpet' }, update: {}, create: { name: 'Trumpet', family: 'brass' } }),
    prisma.instrument.upsert({ where: { name: 'Timpani' }, update: {}, create: { name: 'Timpani', family: 'percussion' } }),
    prisma.instrument.upsert({ where: { name: 'Keyboard' }, update: {}, create: { name: 'Keyboard', family: 'keyboard' } }),
  ]);

  // Works
  const w1041 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 1041' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 1041,
      bwvFull: 'BWV 1041',
      title: 'Violin Concerto in A minor',
      genreId: concerto.id,
      keyId: Amin.id,
      mode: 'minor',
      movementsCount: 3,
      durationMinEst: 16,
      placeComp: 'Leipzig',
      authenticity: 'authentic',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00000938',
    }
  });

  const w1007 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 1007' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 1007,
      bwvFull: 'BWV 1007',
      title: 'Cello Suite No. 1',
      genreId: instrumental.id,
      subgenre: 'Suite',
      keyId: Gmaj.id,
      mode: 'major',
      opusOrCollection: 'Six Cello Suites',
      movementsCount: 6,
      durationMinEst: 18,
      placeComp: 'Cöthen',
      dateCompStart: '1717',
      dateCompEnd: '1723',
      authenticity: 'authentic',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00001267',
      workGroup: 'BWV 1007–1012 (Cello Suites)',
    }
  });

  const w1068 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 1068' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 1068,
      bwvFull: 'BWV 1068',
      title: 'Orchestral Suite No. 3',
      genreId: suite.id,
      subgenre: 'Orchestral suite',
      keyId: Dmaj.id,
      mode: 'major',
      opusOrCollection: 'Orchestral Suites',
      movementsCount: 6,
      durationMinEst: 20,
      placeComp: 'Leipzig',
      authenticity: 'authentic',
      versionNotes: 'Includes Air ("Air on the G String")',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00000968',
      workGroup: 'Orchestral Suites',
    }
  });

  const w232 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 232' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 232,
      bwvFull: 'BWV 232',
      title: 'Mass in B minor',
      genreId: sacred.id,
      subgenre: 'Mass (Ordinarium Missae)',
      keyId: Bmin.id,
      mode: 'minor',
      movementsCount: 27,
      durationMinEst: 105,
      placeComp: 'Leipzig',
      dateCompStart: '1733',
      dateCompEnd: '1749',
      authenticity: 'authentic',
      versionNotes: 'Composite; Kyrie/Gloria 1733; finalised 1748–49',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00000268',
    }
  });

  const w846 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 846' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 846,
      bwvFull: 'BWV 846',
      title: 'The Well-Tempered Clavier I: Prelude and Fugue No. 1',
      genreId: keyboard.id,
      subgenre: 'Prelude and Fugue',
      keyId: Cmaj.id,
      mode: 'major',
      opusOrCollection: 'Das wohltemperirte Clavier I',
      movementsCount: 2,
      durationMinEst: 4,
      placeComp: 'Köthen',
      dateCompStart: '1722',
      dateCompEnd: '1722',
      authenticity: 'authentic',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00000646',
      workGroup: 'WTC I (BWV 846–869)',
      spotifyUrl: '',
      youtubeUrl: ''
    }
  });

  const w1080 = await prisma.work.upsert({
    where: { bwvFull: 'BWV 1080' },
    update: {},
    create: {
      composerId: bach.id,
      bwvId: 1080,
      bwvFull: 'BWV 1080',
      title: 'The Art of Fugue',
      genreId: keyboard.id,
      subgenre: 'Fugues and Canons',
      // tonalidad abierta: dejamos keyId en null
      mode: 'minor',
      movementsCount: 14,
      durationMinEst: 75,
      placeComp: 'Leipzig',
      dateCompStart: '1740',
      dateCompEnd: '1750',
      authenticity: 'authentic',
      versionNotes: 'Open instrumentation; autograph and posthumous print',
      primarySourceUrl: 'https://www.bach-digital.de/receive/BachDigitalWork_work_00000206',
    }
  });

  await prisma.workInstrumentation.createMany({
    data: [
      { workId: w1041.id, instrumentId: violin.id },
      { workId: w1041.id, instrumentId: strings.id },
      { workId: w1041.id, instrumentId: continuo.id },

      { workId: w1007.id, instrumentId: cello.id },

      { workId: w1068.id, instrumentId: oboe.id },
      { workId: w1068.id, instrumentId: trumpet.id },
      { workId: w1068.id, instrumentId: timpani.id },
      { workId: w1068.id, instrumentId: strings.id },
      { workId: w1068.id, instrumentId: continuo.id },

      { workId: w846.id, instrumentId: keyboardInstr.id },
    ],
    skipDuplicates: true,
  });

  console.log('Seed OK');
}

main()
  .catch((e) => { console.error(e); process.exit(1); })
  .finally(async () => { await prisma.$disconnect(); });
