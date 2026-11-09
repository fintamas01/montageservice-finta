/**
 * Prüft den Build-Output (dist/) auf typische SEO-, A11y- und Link-Fehler.
 * Ausführen nach `npm run build`:  npm run verify
 */
import { existsSync, readFileSync, readdirSync } from "node:fs";
import { join, resolve } from "node:path";

const dist = resolve("dist");
const SITE = "https://montagefinta.de";
const errors = [];
const warnings = [];

const pages = readdirSync(dist).filter((f) => f.endsWith(".html"));
const html = Object.fromEntries(pages.map((p) => [p, readFileSync(join(dist, p), "utf8")]));

const idsOf = (source) => new Set([...source.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1]));
const pageForPath = (path) => {
  if (path === "/" || path === "") return "index.html";
  const clean = path.replace(/^\//, "").replace(/\/$/, "");
  return `${clean}.html`;
};

for (const [page, source] of Object.entries(html)) {
  const err = (msg) => errors.push(`${page}: ${msg}`);

  if (!/<html lang="de"[\s>]/.test(source)) err("missing <html lang=\"de\">");
  if (!/<title>[^<]{10,70}<\/title>/.test(source)) err("title missing or not 10–70 chars");

  const description = source.match(/<meta name="description" content="([^"]*)"/)?.[1];
  if (!description) err("meta description missing");
  else if (description.length < 50 || description.length > 170)
    warnings.push(`${page}: meta description length ${description.length}`);

  const canonical = source.match(/<link rel="canonical" href="([^"]+)"/)?.[1];
  if (!canonical?.startsWith(SITE)) err(`canonical invalid: ${canonical}`);
  if (canonical?.endsWith(".html")) err("canonical must not end with .html");

  const h1Count = (source.match(/<h1[\s>]/g) ?? []).length;
  if (h1Count !== 1) err(`expected exactly one <h1>, found ${h1Count}`);

  for (const img of source.matchAll(/<img\b[^>]*>/g)) {
    // leeres alt (dekorativ) wird von Astro als boolesches Attribut ausgegeben
    if (!/\salt(="|[\s>])/.test(img[0])) err(`<img> without alt: ${img[0].slice(0, 80)}`);
    if (!/\swidth="\d+"/.test(img[0]) || !/\sheight="\d+"/.test(img[0]))
      err(`<img> without width/height (CLS): ${img[0].slice(0, 80)}`);
  }

  for (const link of source.matchAll(/<a\b[^>]*target="_blank"[^>]*>/g)) {
    if (!/rel="[^"]*noopener/.test(link[0]) && !/href="\//.test(link[0]))
      err(`external target=_blank without rel=noopener: ${link[0].slice(0, 80)}`);
  }

  if (/DOMAIN\.DE|\[NAME\]|Telefon folgt|E-Mail folgt|lorem ipsum/i.test(source))
    err("contains leftover placeholder text");

  if (/Angabe folgt\]/.test(source)) warnings.push(`${page}: pending legal/contact data (see src/data/site.ts)`);

  // Interne Links und Anker
  const pageIds = idsOf(source);
  for (const match of source.matchAll(/\shref="([^"]+)"/g)) {
    const href = match[1];
    if (/^(https?:|mailto:|tel:)/.test(href)) continue;

    if (href.startsWith("#")) {
      if (href.length > 1 && !pageIds.has(href.slice(1))) err(`broken in-page anchor ${href}`);
      continue;
    }

    const [pathWithQuery, hash] = href.split("#");
    const path = pathWithQuery.split("?")[0];

    if (/\.[a-z0-9]+$/i.test(path)) {
      // Asset-Datei; PHP-Endpunkt liegt nach dem Build in dist/api
      if (!existsSync(join(dist, path))) err(`missing asset ${href}`);
      continue;
    }

    const target = pageForPath(path);
    if (!html[target]) {
      err(`broken internal link ${href}`);
      continue;
    }
    if (hash && !idsOf(html[target]).has(hash)) err(`broken anchor ${href}`);
  }

  // aria-describedby/-controls/-labelledby müssen existieren
  for (const match of source.matchAll(/aria-(?:describedby|controls|labelledby)="([^"]+)"/g)) {
    for (const id of match[1].split(/\s+/)) {
      if (!pageIds.has(id)) err(`aria reference to missing id "${id}"`);
    }
  }
}

// Formular-Endpunkt
if (!existsSync(join(dist, "api/send-request.php"))) errors.push("dist/api/send-request.php missing");

// Sitemap
const sitemap = existsSync(join(dist, "sitemap-0.xml")) ? readFileSync(join(dist, "sitemap-0.xml"), "utf8") : "";
if (!sitemap) errors.push("sitemap-0.xml missing");
if (/\/(danke|404)</.test(sitemap)) errors.push("sitemap contains noindex pages");
if (/\.html</.test(sitemap)) errors.push("sitemap contains .html URLs");

for (const w of warnings) console.warn(`⚠ ${w}`);
if (errors.length) {
  for (const e of errors) console.error(`✖ ${e}`);
  console.error(`\n${errors.length} error(s) in ${pages.length} page(s).`);
  process.exit(1);
}
console.log(`✓ ${pages.length} pages verified (${warnings.length} warning(s)).`);
