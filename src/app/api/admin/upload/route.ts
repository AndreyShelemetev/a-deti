import { NextResponse } from "next/server";
import { mkdir, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { randomUUID } from "node:crypto";
import sharp from "sharp";
import { getSession } from "@/lib/auth";

const MAX_BYTES = 15 * 1024 * 1024;
const MAX_WIDTH = 1920;

/** Admin-only image upload: converts to WebP, resizes to ≤1920px, stores under public/uploads/cms/YYYY/MM/. */
export async function POST(request: Request) {
  const session = await getSession();
  if (!session) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  const form = await request.formData();
  const file = form.get("file");
  if (!(file instanceof File)) return NextResponse.json({ error: "Файл не передан" }, { status: 400 });
  if (file.size > MAX_BYTES) return NextResponse.json({ error: "Файл больше 15 МБ" }, { status: 413 });
  if (!file.type.startsWith("image/")) return NextResponse.json({ error: "Можно загружать только изображения" }, { status: 415 });

  const buffer = Buffer.from(await file.arrayBuffer());
  const now = new Date();
  const rel = join("uploads", "cms", String(now.getFullYear()), String(now.getMonth() + 1).padStart(2, "0"));
  const dir = join(process.env.UPLOADS_DIR ?? join(process.cwd(), "public"), rel);
  await mkdir(dir, { recursive: true });
  const name = `${randomUUID()}.webp`;
  try {
    const out = await sharp(buffer, { failOn: "none" }).rotate().resize({ width: MAX_WIDTH, withoutEnlargement: true }).webp({ quality: 82 }).toBuffer();
    await writeFile(join(dir, name), out);
  } catch {
    return NextResponse.json({ error: "Не удалось обработать изображение" }, { status: 422 });
  }
  return NextResponse.json({ url: `/${rel.replace(/\\/g, "/")}/${name}` });
}
