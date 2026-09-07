import { NextResponse, type NextRequest } from "next/server";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { REF_RE, updater } from "@/lib/updater";

/** Admin-only proxy to the updater sidecar: ?action=status | check&ref=… | job&id=… */
export async function GET(request: NextRequest) {
  const session = await getSession();
  if (!session || session.role !== "ADMIN") return NextResponse.json({ error: "Forbidden" }, { status: 403 });
  const sp = request.nextUrl.searchParams;
  const action = sp.get("action") ?? "status";
  try {
    if (action === "status") return NextResponse.json(await updater.status());
    if (action === "check") {
      const ref = sp.get("ref") ?? "main";
      if (!REF_RE.test(ref)) return NextResponse.json({ error: "Bad ref" }, { status: 400 });
      return NextResponse.json(await updater.check(ref));
    }
    if (action === "job") {
      const id = sp.get("id") ?? "";
      const job = await updater.job(id);
      if (job.status !== "running") {
        // persist the outcome once the sidecar reports completion (idempotent)
        await prisma.deployment.updateMany({
          where: { jobId: id, status: "running" },
          data: { status: job.status, commit: job.commit ?? null, finishedAt: job.finishedAt ? new Date(job.finishedAt) : new Date(), log: (job.log ?? "").slice(-50_000) },
        });
      }
      return NextResponse.json(job);
    }
    return NextResponse.json({ error: "Unknown action" }, { status: 400 });
  } catch (e) {
    return NextResponse.json({ error: e instanceof Error ? e.message : "Updater unavailable" }, { status: 502 });
  }
}
