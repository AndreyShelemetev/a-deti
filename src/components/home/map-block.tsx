import { Clock, Mail, MapPin, Phone } from "lucide-react";
import { SectionTitle } from "@/components/ui/section-title";
import { telHref } from "@/components/layout/header";
import type { SiteSettings } from "@/server/queries/settings";

export function YandexMap({ lat, lng, className = "" }: { lat: string; lng: string; className?: string }) {
  const src = `https://yandex.ru/map-widget/v1/?ll=${lng}%2C${lat}&z=16&pt=${lng}%2C${lat}%2Cpm2rdl`;
  return <iframe src={src} title="Карта проезда" loading="lazy" className={`h-full w-full border-0 ${className}`} allowFullScreen />;
}

export function MapBlock({ settings, title = true }: { settings: SiteSettings; title?: boolean }) {
  const lat = settings["contacts.mapLat"] ?? "55.7651446";
  const lng = settings["contacts.mapLng"] ?? "37.6161005";
  return (
    <section className="container-site py-12">
      {title ? <SectionTitle accent="Мы">на карте</SectionTitle> : null}
      <div className="grid gap-5 lg:grid-cols-12">
        <div className="card space-y-4 p-6 lg:col-span-4">
          <div className="flex gap-3"><MapPin className="mt-0.5 h-5 w-5 shrink-0 text-coral-500" /><div><div className="text-xs font-bold uppercase text-ink-400">Адрес офиса</div><div className="font-bold text-ink-900">{settings["contacts.address"]}</div></div></div>
          <div className="flex gap-3"><Phone className="mt-0.5 h-5 w-5 shrink-0 text-brand-500" /><div><div className="text-xs font-bold uppercase text-ink-400">Телефоны</div><a href={telHref(settings["contacts.phone1"] ?? "")} className="block font-bold text-ink-900 hover:text-brand-700">{settings["contacts.phone1"]}</a><a href={telHref(settings["contacts.phone2"] ?? "")} className="block font-bold text-ink-900 hover:text-brand-700">{settings["contacts.phone2"]}</a></div></div>
          <div className="flex gap-3"><Mail className="mt-0.5 h-5 w-5 shrink-0 text-mint-500" /><div><div className="text-xs font-bold uppercase text-ink-400">E-mail</div><a href={`mailto:${settings["contacts.email"]}`} className="font-bold text-ink-900 hover:text-brand-700">{settings["contacts.email"]}</a></div></div>
          <div className="flex gap-3"><Clock className="mt-0.5 h-5 w-5 shrink-0 text-sun-500" /><div><div className="text-xs font-bold uppercase text-ink-400">Режим работы</div><div className="font-bold text-ink-900">{settings["contacts.hours"]}</div></div></div>
        </div>
        <div className="h-80 overflow-hidden rounded-3xl shadow-card lg:col-span-8 lg:h-auto lg:min-h-96">
          <YandexMap lat={lat} lng={lng} />
        </div>
      </div>
    </section>
  );
}
