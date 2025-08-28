// prisma.config.ts
import path from "node:path";
import { defineConfig } from "prisma/config";

export default defineConfig({
  schema: path.join("prisma", "schema.prisma"),
  migrations: {
    path: path.join("prisma", "migrations"),
    // ¡aquí va el seed! (TS con tsx; si usas JS: "node prisma/seed.js")
    seed: "tsx prisma/seed.ts",
  },
});
