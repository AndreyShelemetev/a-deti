export default function AuthLayout({ children }: LayoutProps<"/admin">) {
  return <div className="flex min-h-screen items-center justify-center bg-brand-50 p-4">{children}</div>;
}
