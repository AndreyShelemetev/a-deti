"use server";

import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/auth";
import { REF_RE, updater } from "@/lib/updater";

export type DeployResult = { ok: boolean; message?: string; deploymentId?: number; jobId?: string };

/** Starts a deploy of the given git ref through the updater sidecar and records it in the Deployment table. */
export async function startDeploy(ref: string): Promise<DeployResult> {
  const me = await requireAdmin();
  const target = ref.trim();
  if (!REF_RE.test(target)) return { ok: false, message: "Некорректное имя ветки или тега" };
  const running = await prisma.deployment.findFirst({ where: { status: "running" }, orderBy: { startedAt: "desc" } });
  if (running && Date.now() - running.startedAt.getTime() < 30 * 60 * 1000) return { ok: false, message: `Обновление #${running.id} уже выполняется` };
  try {
    const { jobId } = await updater.deploy(target);
    const row = await prisma.deployment.create({ data: { ref: target, jobId, startedBy: me.email } });
    return { ok: true, deploymentId: row.id, jobId };
  } catch (e) {
    return { ok: false, message: e instanceof Error ? e.message : "Не удалось запустить обновление" };
  }
}
