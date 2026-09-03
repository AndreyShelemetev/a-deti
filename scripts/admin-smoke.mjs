import { chromium } from "playwright";
const out = process.env.SHOTS_DIR ?? "/tmp/claude-0/-home-user-a-deti/42dfaa29-2a70-5db8-8c2b-99626f0d0113/scratchpad/shots";
const base = process.env.BASE_URL ?? "http://localhost:3000";
const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" }).catch(() => chromium.launch());
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const log = (...a) => console.log(...a);

// 1. unauthenticated → redirect to login
const r = await page.goto(`${base}/admin/`, { waitUntil: "networkidle" });
log("admin redirect:", page.url().includes("/admin/login") ? "OK" : `FAIL ${page.url()} ${r?.status()}`);
// 2. login
await page.fill('input[name="email"]', "admin@a-deti.ru");
await page.fill('input[name="password"]', "admin12345");
await page.click('button[type="submit"]');
await page.waitForURL((u) => u.pathname.startsWith("/admin") && !u.pathname.includes("login"), { timeout: 30000 });
await page.waitForLoadState("networkidle");
log("after login:", page.url());
await page.screenshot({ path: `${out}/admin_dashboard.png` });
// 3. tours list + edit
await page.goto(`${base}/admin/tours/`, { waitUntil: "networkidle" });
const firstTour = await page.locator("table a[href^='/admin/tours/']").first().getAttribute("href");
log("first tour:", firstTour);
await page.goto(`${base}${firstTour}`, { waitUntil: "networkidle" });
await page.screenshot({ path: `${out}/admin_tour_edit.png`, fullPage: false });
const title = await page.inputValue('input[name="title"]');
await page.fill('input[name="short"], textarea[name="short"]', "Обновлённое краткое описание (smoke test)");
await page.click('button[type="submit"]:has-text("Сохранить")');
await page.waitForSelector("text=Тур сохранён", { timeout: 30000 });
log("tour saved OK:", title.slice(0, 40));
// 4. public page reflects change
await page.goto(`${base}/tour/`, { waitUntil: "networkidle" });
log("public catalog shows update:", (await page.content()).includes("smoke test") ? "OK" : "NOT FOUND");
// 5. requests page & settings
await page.goto(`${base}/admin/requests/`, { waitUntil: "networkidle" });
log("requests page:", await page.locator("h1").innerText());
await page.goto(`${base}/admin/settings/`, { waitUntil: "networkidle" });
log("settings page:", await page.locator("h1").innerText());
// 6. public form submit (callback) → appears in admin
await page.goto(`${base}/kak-nas-nayti/`, { waitUntil: "networkidle" });
await page.fill('form input[name="name"]', "Тест Родитель");
await page.fill('form input[name="phone"]', "+7 903 000-00-00");
await page.fill('form input[name="email"]', "parent@example.com");
await page.fill('form textarea[name="message"]', "Хочу путёвку в Орлёнок на 7 смену");
await page.check('form input[name="consent"]');
await page.click('form button[type="submit"]');
await page.waitForSelector("text=Спасибо", { timeout: 30000 });
log("public request form: OK");
await page.goto(`${base}/admin/requests/`, { waitUntil: "networkidle" });
log("request visible in admin:", (await page.content()).includes("Тест Родитель") ? "OK" : "FAIL");
// 7. logout
await page.click('button:has-text("Выйти")');
await page.waitForURL("**/admin/login/**", { timeout: 30000 });
log("logout: OK");
await browser.close();
