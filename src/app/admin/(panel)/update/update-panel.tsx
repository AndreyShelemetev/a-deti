"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { GitBranch, Loader2, RefreshCw, Rocket, CheckCircle2, XCircle } from "lucide-react";
import { startDeploy } from "@/server/actions/admin/update";
import type { UpdaterCheck, UpdaterJob, UpdaterStatus } from "@/lib/updater";
import { formatDate } from "@/lib/utils";

type Props = { initialStatus: UpdaterStatus | null; initialError: string | null; defaultRef: string; buildCommit: string | null; runningDeployment: { jobId: string | null } | null };

export function UpdatePanel({ initialStatus, initialError, defaultRef, buildCommit, runningDeployment }: Props) {
  const router = useRouter();
  const [ref, setRef] = useState(defaultRef);
  const [status, setStatus] = useState(initialStatus);
  const [check, setCheck] = useState<UpdaterCheck | null>(null);
  const [job, setJob] = useState<UpdaterJob | null>(initialStatus?.running ?? null);
  const [error, setError] = useState<string | null>(initialError);
  const [checking, setChecking] = useState(false);
  const [pending, start] = useTransition();
  const logRef = useRef<HTMLPreElement>(null);
  const jobId = job?.status === "running" ? job.id : null;

  // poll a running job
  useEffect(() => {
    const id = jobId ?? (runningDeployment?.jobId ?? null);
    if (!id) return;
    let stopped = false;
    const tick = async () => {
      try {
        const res = await fetch(`/api/admin/update/?action=job&id=${id}`, { cache: "no-store" });
        const data = (await res.json()) as UpdaterJob & { error?: string };
        if (stopped) return;
        if (data.error) { setError(data.error); return; }
        setJob(data);
        if (data.status !== "running") { router.refresh(); return; }
        setTimeout(tick, 2500);
      } catch (e) { if (!stopped) setError(e instanceof Error ? e.message : "Ошибка связи"); }
    };
    void tick();
    return () => { stopped = true; };
  }, [jobId, runningDeployment?.jobId, router]);

  useEffect(() => { logRef.current?.scrollTo({ top: logRef.current.scrollHeight }); }, [job?.log]);

  async function runCheck() {
    setChecking(true); setError(null); setCheck(null);
    try {
      const res = await fetch(`/api/admin/update/?action=check&ref=${encodeURIComponent(ref)}`, { cache: "no-store" });
      const data = (await res.json()) as UpdaterCheck & { error?: string };
      if (data.error) setError(data.error); else { setCheck(data); setStatus((s) => (s ? { ...s, current: data.current } : s)); }
    } catch (e) { setError(e instanceof Error ? e.message : "Ошибка связи"); }
    finally { setChecking(false); }
  }

  function deploy() {
    if (!window.confirm(`Обновить сайт до «${ref}»? Сайт будет пересобран и перезапущен (обычно 2–5 минут).`)) return;
    setError(null);
    start(async () => {
      const r = await startDeploy(ref);
      if (!r.ok) { setError(r.message ?? "Не удалось запустить"); return; }
      setJob({ id: r.jobId!, ref, status: "running", startedAt: new Date().toISOString(), finishedAt: null, exitCode: null, log: "" });
      router.refresh();
    });
  }

  const cur = status?.current;
  const busy = job?.status === "running" || pending;

  return (
    <div className="grid gap-6 xl:grid-cols-12">
      <section className="rounded-2xl bg-white p-5 shadow-card xl:col-span-5">
        <h2 className="mb-3 text-lg font-extrabold">Текущая версия</h2>
        {cur ? (
          <dl className="space-y-1.5 text-sm">
            <div className="flex gap-2"><dt className="w-24 shrink-0 font-bold text-ink-500">Ветка</dt><dd className="inline-flex items-center gap-1 font-bold"><GitBranch className="h-4 w-4 text-brand-500" />{cur.branch}</dd></div>
            <div className="flex gap-2"><dt className="w-24 shrink-0 font-bold text-ink-500">Коммит</dt><dd className="font-mono">{cur.short}</dd></div>
            <div className="flex gap-2"><dt className="w-24 shrink-0 font-bold text-ink-500">Дата</dt><dd>{formatDate(cur.date, { day: "2-digit", month: "long", year: "numeric", hour: "2-digit", minute: "2-digit" })}</dd></div>
            <div className="flex gap-2"><dt className="w-24 shrink-0 font-bold text-ink-500">Описание</dt><dd className="text-ink-700">{cur.subject}</dd></div>
            {buildCommit ? <div className="flex gap-2"><dt className="w-24 shrink-0 font-bold text-ink-500">Сборка</dt><dd className="font-mono text-xs text-ink-500">{buildCommit.slice(0, 7)}{buildCommit !== cur.commit ? " (отличается от кода в репозитории — нужна пересборка)" : ""}</dd></div> : null}
          </dl>
        ) : <p className="text-sm text-coral-600">{error ?? "Сервис обновления недоступен"}</p>}

        <div className="mt-5 border-t border-ink-100 pt-4">
          <label className="label">Ветка или тег для обновления</label>
          <div className="flex gap-2">
            <input value={ref} onChange={(e) => setRef(e.target.value)} disabled={busy} className="input !py-2 !text-sm font-mono" placeholder="main, v1.2.0, claude/…" />
            <button type="button" onClick={runCheck} disabled={busy || checking} className="btn-outline shrink-0 !py-2 !text-xs">{checking ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />} Проверить</button>
          </div>
          {check ? (
            <div className="mt-3 rounded-xl bg-ink-50 p-3 text-sm">
              {check.behind === 0 ? (
                <p className="font-bold text-mint-700">Установлена последняя версия ветки «{check.ref}».</p>
              ) : (
                <>
                  <p className="font-bold text-ink-900">Доступно обновлений: {check.behind}</p>
                  <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs">
                    {check.commits.map((c) => <li key={c.hash}><span className="font-mono text-brand-600">{c.short}</span> <span className="text-ink-400">{formatDate(c.date, { day: "2-digit", month: "2-digit" })}</span> {c.subject}</li>)}
                  </ul>
                </>
              )}
            </div>
          ) : null}
          <button type="button" onClick={deploy} disabled={busy || !cur} className="btn-accent mt-4 w-full">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />} {busy ? "Обновление выполняется…" : `Обновить до «${ref}»`}
          </button>
          <p className="mt-2 text-xs text-ink-400">Сайт может быть недоступен несколько секунд во время перезапуска. Откат: укажите предыдущий коммит или тег и нажмите «Обновить».</p>
        </div>
        {error ? <p className="mt-3 rounded-xl bg-coral-100 px-3 py-2 text-sm font-semibold text-coral-700">{error}</p> : null}
      </section>

      <section className="rounded-2xl bg-white p-5 shadow-card xl:col-span-7">
        <h2 className="mb-3 flex items-center gap-2 text-lg font-extrabold">
          Журнал {job ? <span className="text-sm font-bold text-ink-500">· {job.ref} · #{job.id}</span> : null}
          {job?.status === "running" ? <Loader2 className="h-4 w-4 animate-spin text-brand-500" /> : job?.status === "success" ? <CheckCircle2 className="h-5 w-5 text-mint-500" /> : job?.status === "failed" ? <XCircle className="h-5 w-5 text-coral-500" /> : null}
        </h2>
        <pre ref={logRef} className="h-[28rem] overflow-auto rounded-xl bg-ink-900 p-4 font-mono text-xs leading-relaxed text-ink-100">{job?.log || (job ? "Ожидание вывода…" : status?.last ? `Последнее обновление: ${status.last.ref} · ${status.last.status} · ${status.last.finishedAt ?? ""}` : "Здесь появится вывод сборки.")}</pre>
      </section>
    </div>
  );
}
