/**
 * Converts downloaded original images (scratch dir) into optimised WebP files under public/uploads,
 * keeping the original directory structure, and writes a JSON map { "/uploads/old.jpg": "/uploads/old.webp" }.
 * Usage: node scripts/convert-images.mjs <sourceDir> <mapFile>
 */
import { readdir, mkdir, copyFile, writeFile, stat } from "node:fs/promises";
import { join, extname, relative, dirname } from "node:path";
import sharp from "sharp";

const [src, mapFile] = process.argv.slice(2);
const dest = join(process.cwd(), "public");
const map = {};
let converted = 0, copied = 0, failed = 0;

async function walk(dir) {
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) { await walk(full); continue; }
    const rel = relative(src, full); // uploads/iblock/…
    const ext = extname(entry.name).toLowerCase();
    const outRel = [".jpg", ".jpeg", ".png", ".gif", ".bmp", ".tiff"].includes(ext) ? rel.slice(0, -ext.length) + ".webp" : rel;
    const out = join(dest, outRel);
    await mkdir(dirname(out), { recursive: true });
    try {
      if (outRel.endsWith(".webp") && ext !== ".webp") {
        const isSmall = rel.includes("/icon") || (await stat(full)).size < 30_000;
        await sharp(full, { failOn: "none" }).rotate().resize({ width: isSmall ? 800 : 1600, withoutEnlargement: true }).webp({ quality: 80 }).toFile(out);
        converted++;
      } else {
        await copyFile(full, out); copied++;
      }
      map["/" + rel.replace(/\\/g, "/")] = "/" + outRel.replace(/\\/g, "/");
    } catch (e) {
      failed++; console.error("FAILED", rel, e.message);
    }
  }
}
await walk(src);
await writeFile(mapFile, JSON.stringify(map, null, 1));
console.log({ converted, copied, failed, mapped: Object.keys(map).length });
