import Form from "next/form";
import { Search } from "lucide-react";
import { Select } from "@/components/ui/form-fields";
import { AGE_OPTIONS, SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";

export function QuickSearch({ regions }: { regions: { slug: string; name: string }[] }) {
  return (
    <Form action="/tour/" className="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" aria-label="Быстрый подбор тура">
      <div className="lg:col-span-5 -mb-1 text-sm font-extrabold text-ink-900">Быстрый подбор: кому, где и когда</div>
      <label className="block"><span className="label">Возраст ребёнка</span>
        <Select name="age" defaultValue=""><option value="">Любой</option>{AGE_OPTIONS.map((a) => <option key={a} value={a}>{a} {a < 5 ? "года" : "лет"}</option>)}</Select>
      </label>
      <label className="block"><span className="label">Тип отдыха</span>
        <Select name="type" defaultValue=""><option value="">Любой</option>{Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
      </label>
      <label className="block"><span className="label">Регион</span>
        <Select name="region" defaultValue=""><option value="">Любой</option>{regions.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}</Select>
      </label>
      <label className="block"><span className="label">Сезон</span>
        <Select name="season" defaultValue=""><option value="">Любой</option>{Object.entries(SEASON_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
      </label>
      <button type="submit" className="btn-accent h-[52px] w-full"><Search className="h-4 w-4" /> Подобрать</button>
    </Form>
  );
}
