import { Plane } from "lucide-react";
import Link from "next/link";
import { RequestButton } from "@/components/forms/lead-modals";

export function CtaBanner({ ticketText }: { ticketText?: string }) {
  return (
    <section className="container-site py-6">
      <div className="relative overflow-hidden rounded-4xl bg-gradient-to-r from-brand-600 via-brand-500 to-brand-400 p-8 text-white shadow-pop sm:p-10">
        <div className="blob -right-10 -top-16 h-56 w-56 bg-sun-300/40" />
        <div className="blob -bottom-20 left-1/4 h-56 w-56 bg-berry-300/30" />
        <div className="relative grid items-center gap-6 md:grid-cols-12">
          <div className="md:col-span-8">
            <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-extrabold uppercase tracking-wide"><Plane className="h-4 w-4" /> Авиа и ж/д</div>
            <h2 className="text-2xl font-black sm:text-3xl">{ticketText ? ticketText[0].toUpperCase() + ticketText.slice(1) : "Приобрести групповой авиабилет"}</h2>
            <p className="mt-2 max-w-xl text-white/85">Организуем централизованную доставку детей в «Орлёнок», «Артек», лагеря Анапы и Крыма: билеты, сопровождение, питание в дороге.</p>
          </div>
          <div className="flex flex-wrap gap-3 md:col-span-4 md:justify-end">
            <Link href="/transfer/" className="btn-sun">Расписание и цены</Link>
            <RequestButton className="btn bg-white text-brand-700 hover:bg-brand-50">Оставить заявку</RequestButton>
          </div>
        </div>
      </div>
    </section>
  );
}
