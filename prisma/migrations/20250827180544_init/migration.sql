-- CreateTable
CREATE TABLE `Genre` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,

    UNIQUE INDEX `Genre_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Key` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,

    UNIQUE INDEX `Key_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Person` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `defaultRole` VARCHAR(191) NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Instrument` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `family` VARCHAR(191) NULL,

    UNIQUE INDEX `Instrument_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Work` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `composerId` INTEGER NOT NULL,
    `bwvId` INTEGER NULL,
    `bwvFull` VARCHAR(191) NOT NULL,
    `anhang` BOOLEAN NOT NULL DEFAULT false,
    `title` VARCHAR(191) NOT NULL,
    `altTitles` VARCHAR(191) NULL,
    `genreId` INTEGER NOT NULL,
    `subgenre` VARCHAR(191) NULL,
    `keyId` INTEGER NULL,
    `mode` VARCHAR(191) NULL,
    `opusOrCollection` VARCHAR(191) NULL,
    `movementsCount` INTEGER NULL,
    `durationMinEst` INTEGER NULL,
    `language` VARCHAR(191) NULL,
    `authenticity` VARCHAR(191) NOT NULL DEFAULT 'authentic',
    `dateCompStart` VARCHAR(191) NULL,
    `dateCompEnd` VARCHAR(191) NULL,
    `placeComp` VARCHAR(191) NULL,
    `versionNotes` VARCHAR(191) NULL,
    `workGroup` VARCHAR(191) NULL,
    `parentWorkId` INTEGER NULL,
    `notes` VARCHAR(191) NULL,
    `primarySourceUrl` VARCHAR(191) NULL,
    `spotifyUrl` VARCHAR(191) NULL,
    `youtubeUrl` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `Work_bwvFull_key`(`bwvFull`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `WorkInstrumentation` (
    `workId` INTEGER NOT NULL,
    `instrumentId` INTEGER NOT NULL,
    `count` INTEGER NULL,
    `obbligato` BOOLEAN NOT NULL DEFAULT false,
    `notes` VARCHAR(191) NULL,

    PRIMARY KEY (`workId`, `instrumentId`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `Work` ADD CONSTRAINT `Work_composerId_fkey` FOREIGN KEY (`composerId`) REFERENCES `Person`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Work` ADD CONSTRAINT `Work_genreId_fkey` FOREIGN KEY (`genreId`) REFERENCES `Genre`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Work` ADD CONSTRAINT `Work_keyId_fkey` FOREIGN KEY (`keyId`) REFERENCES `Key`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Work` ADD CONSTRAINT `Work_parentWorkId_fkey` FOREIGN KEY (`parentWorkId`) REFERENCES `Work`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `WorkInstrumentation` ADD CONSTRAINT `WorkInstrumentation_workId_fkey` FOREIGN KEY (`workId`) REFERENCES `Work`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `WorkInstrumentation` ADD CONSTRAINT `WorkInstrumentation_instrumentId_fkey` FOREIGN KEY (`instrumentId`) REFERENCES `Instrument`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
