import { Head, Link, usePage } from "@inertiajs/react";
import type { ReactNode } from "react";

export default function Layout({ title, children }: { title: string; children?: ReactNode }) {
  const { site, navigation } = usePage().props;

  return (
    <>
      <Head title={title ? `${title} – ${site.title}` : site.title} />
      <header className="border-b border-neutral-200">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center gap-6 px-6 py-4">
          <Link href={site.home} className="font-semibold">
            {site.title}
          </Link>
          <nav aria-label="Main" className="flex flex-1 gap-4">
            {navigation
              .flatMap((item) => [item, ...item.children])
              .map((item) => (
                <Link
                  key={item.uid}
                  href={item.link}
                  aria-current={item.current ? "page" : undefined}
                  className={item.current ? "underline" : "hover:underline"}
                >
                  {item.title}
                </Link>
              ))}
          </nav>
          <nav aria-label="Language" className="flex gap-2 text-sm">
            {site.languages.map((language) => (
              <a
                key={language.id}
                href={language.link}
                hrefLang={language.code}
                aria-current={language.active ? "true" : undefined}
                className={language.active ? "font-semibold" : "hover:underline"}
              >
                {language.code.toUpperCase()}
              </a>
            ))}
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-10">
        <h1 className="mb-8 text-4xl font-bold">{title}</h1>
        {children}
      </main>
    </>
  );
}
