import { chromium } from "playwright";
const out = process.env.SHOTS_DIR ?? "/tmp/claude-0/-home-user-a-deti/42dfaa29-2a70-5db8-8c2b-99626f0d0113/scratchpad/shots";
const pages = process.argv.slice(2).length ? process.argv.slice(2) : ["/", "/tour/", "/tour/art-kvest-na-chernom-more/", "/novosti/", "/kak-nas-nayti/"];
const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" }).catch(() => chromium.launch());
for (const [name, vp] of [["desktop", { width: 1440, height: 900 }], ["mobile", { width: 390, height: 844 }]]) {
  const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  for (const p of pages) {
    await page.goto(`http://localhost:3000${p}`, { waitUntil: "networkidle", timeout: 60000 });
    await page.waitForTimeout(500);
    const file = `${out}/${name}${p.replace(/[^a-z0-9]+/gi, "_") || "_home"}.png`;
    await page.screenshot({ path: file, fullPage: true });
    console.log("saved", file);
  }
  await ctx.close();
}
await browser.close();
