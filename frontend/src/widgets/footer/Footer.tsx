import Link from 'next/link';

const externalLinks = [
  { href: 'http://localhost:8000/api/documentation', label: 'API Docs (Swagger)' },
  { href: 'http://localhost:5601', label: 'Kibana — Logs' },
  { href: 'http://localhost:15672', label: 'RabbitMQ' },
];

export function Footer() {
  return (
    <footer className="mt-16 border-t border-slate-200 bg-slate-50">
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-x-3">
          <Link href="/site-map" className="hover:text-slate-900 hover:underline">
            Site Haritası
          </Link>
          <span className="text-slate-300">·</span>
          <Link
            href="/products"
            className="hover:text-slate-900 hover:underline"
          >
            Ürünler
          </Link>
        </div>
        <div className="flex flex-wrap gap-3">
          {externalLinks.map((link) => (
            <a
              key={link.href}
              href={link.href}
              target="_blank"
              rel="noopener noreferrer"
              className="hover:text-slate-900 hover:underline"
            >
              {link.label} ↗
            </a>
          ))}
        </div>
      </div>
    </footer>
  );
}
