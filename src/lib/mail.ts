import "server-only";
import nodemailer from "nodemailer";

type Mail = { subject: string; text: string; html?: string };

function transport() {
  const host = process.env.SMTP_HOST;
  if (!host) return null;
  const port = Number(process.env.SMTP_PORT ?? 465);
  return nodemailer.createTransport({
    host,
    port,
    secure: port === 465,
    auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASSWORD } : undefined,
  });
}

/** Sends a notification to the leads mailbox. Never throws — leads are always persisted in the DB first. */
export async function notifyLeads(mail: Mail) {
  const to = process.env.LEADS_EMAIL;
  const t = transport();
  if (!t || !to) {
    if (process.env.NODE_ENV !== "production") console.info(`[mail] SMTP not configured — skipped: ${mail.subject}`);
    return false;
  }
  try {
    await t.sendMail({ from: process.env.SMTP_FROM ?? process.env.SMTP_USER, to, subject: mail.subject, text: mail.text, html: mail.html });
    return true;
  } catch (e) {
    console.error("[mail] failed", e);
    return false;
  }
}
