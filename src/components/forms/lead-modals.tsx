"use client";

import { useState, type ReactNode } from "react";
import { usePathname } from "next/navigation";
import { Modal } from "@/components/ui/modal";
import { CallbackForm } from "./callback-form";
import { RequestForm } from "./request-form";
import { SubscribeForm } from "./subscribe-form";
import { BookingForm } from "./booking-form";

type ButtonProps = { className?: string; children: ReactNode };

export function CallbackButton({ className = "btn-outline", children }: ButtonProps) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>{children}</button>
      <Modal open={open} onClose={() => setOpen(false)} title="Заказ обратного звонка">
        <CallbackForm pageUrl={pathname} />
      </Modal>
    </>
  );
}

export function RequestButton({ className = "btn-primary", children }: ButtonProps) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>{children}</button>
      <Modal open={open} onClose={() => setOpen(false)} title="Отправить заявку" size="lg">
        <RequestForm pageUrl={pathname} />
      </Modal>
    </>
  );
}

export function SubscribeButton({ className = "btn-sun", children }: ButtonProps) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>{children}</button>
      <Modal open={open} onClose={() => setOpen(false)} title="Подписка на новости">
        <SubscribeForm />
      </Modal>
    </>
  );
}

export function BookingButton({ className = "btn-primary", children, tourId, tourTitle, shiftName, amount }: ButtonProps & { tourId: number; tourTitle: string; shiftName?: string | null; amount?: number | null }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>{children}</button>
      <Modal open={open} onClose={() => setOpen(false)} title="Забронировать тур" size="lg">
        <BookingForm tourId={tourId} tourTitle={tourTitle} shiftName={shiftName} amount={amount} />
      </Modal>
    </>
  );
}
