/**
 * A-tour updater — a tiny sidecar that pulls a git ref, rebuilds and restarts the site on demand.
 * It is triggered from the admin panel (/admin/update/) and must never be exposed to the internet:
 * bind it to localhost / the docker network and protect it with UPDATER_TOKEN.
 *
 * Endpoints (all require `Authorization: Bearer <UPDATER_TOKEN>`):
 *   GET  /status           — current commit/branch, whether a deploy is running, last result
 *   GET  /check?ref=main   — `git fetch` and list commits the server is behind on the given ref
 *   POST /deploy {ref}     — start a deploy job (single-flight); returns {jobId}
 *   GET  /jobs/:id         — job status + log
 */
import http from "node:http";
import { spawn } from "node:child_process";
import { mkdirSync, readFileSync, writeFileSync, appendFileSync } from "node:fs";
import { join } from "node:path";

const PORT = Number(process.env.PORT ?? 4100);
const HOST = process.env.HOST ?? "0.0.0.0";
const TOKEN = process.env.UPDATER_TOKEN ?? "";
const REPO_DIR = process.env.REPO_DIR ?? process.cwd();
const DEPLOY_SCRIPT = process.env.DEPLOY_SCRIPT ?? join(REPO_DIR, "deploy.sh");
const LOG_DIR = process.env.LOG_DIR ?? join(REPO_DIR, "updater", "logs");
const ALLOWED_REF = /^[A-Za-z0-9][A-Za-z0-9._\/-]{0,120}$/;
const REMOTE = process.env.GIT_REMOTE ?? "origin";

if (!TOKEN || TOKEN.length < 16) {
  console.error("UPDATER_TOKEN must be set (min 16 chars)");
  process.exit(1);
}
mkdirSync(LOG_DIR, { recursive: true });

const state = { running: null, last: loadLast() };
const jobs = new Map();

function loadLast() {
  try { return JSON.parse(readFileSync(join(LOG_DIR, "last.json"), "utf8")); } catch { return null; }
}
function saveLast(job) {
  writeFileSync(join(LOG_DIR, "last.json"), JSON.stringify(job));
}

function run(cmd, args, opts = {}) {
  return new Promise((resolve) => {
    const child = spawn(cmd, args, { cwd: REPO_DIR, env: { ...process.env, GIT_TERMINAL_PROMPT: "0" }, ...opts });
    let out = "";
    child.stdout.on("data", (d) => { out += d; opts.onData?.(String(d)); });
    child.stderr.on("data", (d) => { out += d; opts.onData?.(String(d)); });
    child.on("close", (code) => resolve({ code, out }));
    child.on("error", (e) => resolve({ code: 1, out: String(e) }));
  });
}

async function git(...args) {
  const r = await run("git", args);
  return r.out.trim();
}

async function current() {
  const [commit, branch, date, subject] = await Promise.all([
    git("rev-parse", "HEAD"),
    git("rev-parse", "--abbrev-ref", "HEAD"),
    git("log", "-1", "--format=%cI"),
    git("log", "-1", "--format=%s"),
  ]);
  return { commit, short: commit.slice(0, 7), branch, date, subject };
}

async function check(ref) {
  await run("git", ["fetch", "--prune", REMOTE, ref]);
  const target = `${REMOTE}/${ref}`;
  const log = await git("log", "--format=%H%x1f%h%x1f%cI%x1f%an%x1f%s", `HEAD..${target}`);
  const commits = log ? log.split("\n").map((l) => { const [hash, short, date, author, subject] = l.split("\x1f"); return { hash, short, date, author, subject }; }) : [];
  const targetCommit = await git("rev-parse", target);
  return { ref, target: targetCommit, behind: commits.length, commits, current: await current() };
}

function startDeploy(ref) {
  if (state.running) return { error: "Deploy already running", jobId: state.running.id };
  const id = `${Date.now()}`;
  const logFile = join(LOG_DIR, `${id}.log`);
  const job = { id, ref, status: "running", startedAt: new Date().toISOString(), finishedAt: null, exitCode: null, logFile };
  jobs.set(id, job);
  state.running = job;
  const append = (s) => appendFileSync(logFile, s);
  append(`[updater] deploy ${ref} started ${job.startedAt}\n`);
  run("bash", [DEPLOY_SCRIPT, ref], { onData: append }).then(async (r) => {
    job.status = r.code === 0 ? "success" : "failed";
    job.exitCode = r.code;
    job.finishedAt = new Date().toISOString();
    job.commit = (await current()).commit;
    append(`[updater] finished with code ${r.code} at ${job.finishedAt}\n`);
    state.running = null;
    state.last = job;
    saveLast(job);
  });
  return { jobId: id };
}

function json(res, code, data) {
  res.writeHead(code, { "content-type": "application/json; charset=utf-8" });
  res.end(JSON.stringify(data));
}

function readBody(req) {
  return new Promise((resolve) => { let b = ""; req.on("data", (d) => (b += d)); req.on("end", () => resolve(b)); });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, "http://localhost");
  if (url.pathname === "/health") return json(res, 200, { ok: true });
  if (req.headers.authorization !== `Bearer ${TOKEN}`) return json(res, 401, { error: "Unauthorized" });
  try {
    if (req.method === "GET" && url.pathname === "/status") {
      return json(res, 200, { current: await current(), running: state.running, last: state.last });
    }
    if (req.method === "GET" && url.pathname === "/check") {
      const ref = url.searchParams.get("ref") ?? "main";
      if (!ALLOWED_REF.test(ref)) return json(res, 400, { error: "Bad ref" });
      return json(res, 200, await check(ref));
    }
    if (req.method === "POST" && url.pathname === "/deploy") {
      const body = JSON.parse((await readBody(req)) || "{}");
      const ref = String(body.ref ?? "main");
      if (!ALLOWED_REF.test(ref)) return json(res, 400, { error: "Bad ref" });
      const r = startDeploy(ref);
      return json(res, r.error ? 409 : 202, r);
    }
    const m = url.pathname.match(/^\/jobs\/(\d+)$/);
    if (req.method === "GET" && m) {
      const job = jobs.get(m[1]) ?? (state.last?.id === m[1] ? state.last : null);
      if (!job) return json(res, 404, { error: "Not found" });
      let log = "";
      try { log = readFileSync(job.logFile, "utf8"); } catch {}
      return json(res, 200, { ...job, log: log.slice(-200_000) });
    }
    json(res, 404, { error: "Not found" });
  } catch (e) {
    json(res, 500, { error: String(e) });
  }
});

server.listen(PORT, HOST, () => console.log(`updater listening on ${HOST}:${PORT}, repo ${REPO_DIR}`));
