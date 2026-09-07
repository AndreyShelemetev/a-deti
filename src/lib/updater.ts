import "server-only";

export type UpdaterCurrent = { commit: string; short: string; branch: string; date: string; subject: string };
export type UpdaterJob = { id: string; ref: string; status: "running" | "success" | "failed"; startedAt: string; finishedAt: string | null; exitCode: number | null; commit?: string; log?: string };
export type UpdaterStatus = { current: UpdaterCurrent; running: UpdaterJob | null; last: UpdaterJob | null };
export type UpdaterCheck = { ref: string; target: string; behind: number; commits: { hash: string; short: string; date: string; author: string; subject: string }[]; current: UpdaterCurrent };

export const UPDATER_DEFAULT_REF = process.env.UPDATER_DEFAULT_REF ?? "main";

export function updaterConfigured() {
  return Boolean(process.env.UPDATER_URL && process.env.UPDATER_TOKEN);
}

async function call<T>(path: string, init?: RequestInit): Promise<T> {
  const base = process.env.UPDATER_URL;
  const token = process.env.UPDATER_TOKEN;
  if (!base || !token) throw new Error("UPDATER_URL / UPDATER_TOKEN не настроены");
  const res = await fetch(`${base.replace(/\/$/, "")}${path}`, {
    ...init,
    headers: { authorization: `Bearer ${token}`, "content-type": "application/json", ...(init?.headers ?? {}) },
    cache: "no-store",
    signal: AbortSignal.timeout(path.startsWith("/check") ? 60_000 : 10_000),
  });
  const data = (await res.json().catch(() => ({}))) as T & { error?: string };
  if (!res.ok) throw new Error(data.error ?? `Updater ответил ${res.status}`);
  return data;
}

export const updater = {
  status: () => call<UpdaterStatus>("/status"),
  check: (ref: string) => call<UpdaterCheck>(`/check?ref=${encodeURIComponent(ref)}`),
  deploy: (ref: string) => call<{ jobId: string }>("/deploy", { method: "POST", body: JSON.stringify({ ref }) }),
  job: (id: string) => call<UpdaterJob>(`/jobs/${encodeURIComponent(id)}`),
};

export const REF_RE = /^[A-Za-z0-9][A-Za-z0-9._/-]{0,120}$/;
