import { chromium } from "playwright";
const out = process.env.SHOTS_DIR ?? "/tmp/claude-0/-home-user-a-deti/42dfaa29-2a70-5db8-8c2b-99626f0d0113/scratchpad/shots";
const base = process.env.BASE_URL ?? "http://localhost:3000";
const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" }).catch(() => chromium.launch());
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.on("dialog", (d) => d.accept());
const log = (...a) => console.log(...a);

await page.goto(`${base}/admin/login/`, { waitUntil: "networkidle" });
await page.fill('input[name="email"]', "admin@a-deti.ru"); await page.fill('input[name="password"]', "admin12345");
await page.click('button[type="submit"]');
await page.waitForURL((u) => u.pathname.startsWith("/admin") && !u.pathname.includes("login"), { timeout: 30000 });
await page.screenshot({ path: `${out}/crm_dashboard.png` });

// list + filters
await page.goto(`${base}/admin/tours/?type=CAMP&region=krasnodar&season=SUMMER&sort=next_shift`, { waitUntil: "networkidle" });
log("filtered list:", (await page.locator("h1").innerText()), "|", (await page.locator("table tbody tr").count()), "rows");
await page.screenshot({ path: `${out}/crm_tours_list.png` });
await page.goto(`${base}/admin/tours/?flag=past`, { waitUntil: "networkidle" });
log("flag=past rows:", await page.locator("table tbody tr").count());
await page.goto(`${base}/admin/tours/?from=2026-06-01&to=2026-06-30`, { waitUntil: "networkidle" });
log("shifts in June 2026 rows:", await page.locator("table tbody tr").count());

// create a tour step by step
await page.goto(`${base}/admin/tours/new/?type=CAMP`, { waitUntil: "networkidle" });
await page.fill('input[name="title"]', "Тестовый лагерь CRM 2027");
await page.selectOption('select[name="regionId"]', { label: "Россия, Крым" });
await page.fill('input[name="address"]', "Крым, Евпатория");
await page.fill('input[name="ageMin"]', "7"); await page.fill('input[name="ageMax"]', "15");
await page.click('button:has-text("Далее: Смены и цены")');
await page.click('summary:has-text("Сгенерировать смены подряд")');
await page.fill('details input[type="date"]', "2027-06-01");
await page.fill('details input[type="number"] >> nth=0', "21");
await page.fill('details input[type="number"] >> nth=1', "3");
await page.fill('details input[type="number"] >> nth=3', "79900");
await page.click('button:has-text("Создать 3 смен")');
const shiftNames = await page.locator('table tbody input[placeholder^="1 смена"]').evaluateAll((els) => els.map((e) => e.value));
log("generated shifts:", shiftNames.join(" | "));
await page.click('button:has-text("Далее: Фото")');
await page.click('button:has-text("Далее: Описание")');
await page.click('button:has-text("Вставить шаблон") >> nth=0');
await page.click('button:has-text("Далее: Документы")');
await page.click('button:has-text("Далее: Публикация")');
await page.click('label:has-text("Опубликован")');
await page.click('button[type="submit"]:has-text("Создать тур")');
await page.waitForURL(/\/admin\/tours\/\d+\/\?created=1/, { timeout: 30000 });
const editUrl = page.url(); log("created:", editUrl);
await page.waitForLoadState("networkidle");
await page.screenshot({ path: `${out}/crm_tour_editor.png` });
await page.click('button[role="tab"]:has-text("Смены")');
await page.screenshot({ path: `${out}/crm_tour_shifts.png` });

// public page shows the new tour with shifts & availability
await page.goto(`${base}/tour/testovyy-lager-crm-2027/`, { waitUntil: "networkidle" });
log("public page title:", await page.locator("h1").innerText());
log("public has shift range:", (await page.content()).includes("1–21 июня 2027") ? "OK" : "NO");

// calendar: change availability
await page.goto(`${base}/admin/shifts/?month=2027-06&q=CRM`, { waitUntil: "networkidle" });
log("calendar rows:", await page.locator("table tbody tr").count());
await page.selectOption("table tbody tr >> nth=0 >> select", "SOLD_OUT");
await page.waitForTimeout(1500);
await page.reload({ waitUntil: "networkidle" });
log("availability persisted:", await page.locator("table tbody tr >> nth=0 >> select").inputValue());
await page.screenshot({ path: `${out}/crm_shifts_calendar.png` });
await page.goto(`${base}/tour/testovyy-lager-crm-2027/`, { waitUntil: "networkidle" });
log("public sold-out badge:", (await page.content()).includes("Мест нет") ? "OK" : "NO");

// duplicate to next season from the list
await page.goto(`${base}/admin/tours/?q=CRM`, { waitUntil: "networkidle" });
await page.click('button:has-text("след. сезон") >> nth=0');
await page.waitForURL(/\?copied=1/, { timeout: 30000 });
await page.waitForLoadState("networkidle");
log("copy title:", await page.inputValue('input[name="title"]'));
await page.click('button[role="tab"]:has-text("Смены")');
log("copy first shift:", await page.locator('table tbody input[placeholder^="1 смена"] >> nth=0').inputValue());

// cleanup: archive then delete the test tours via bulk actions
await page.goto(`${base}/admin/tours/?q=CRM&status=all`, { waitUntil: "networkidle" });
await page.check('thead input[type="checkbox"]');
await page.click('button:has-text("Удалить")');
await page.waitForTimeout(2000);
await page.goto(`${base}/admin/tours/?q=CRM&status=all`, { waitUntil: "networkidle" });
log("after cleanup rows:", await page.locator("table tbody tr").count());
await browser.close();
