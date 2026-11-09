// @ts-check
import { defineConfig } from "astro/config";
import tailwindcss from "@tailwindcss/vite";
import sitemap from "@astrojs/sitemap";

const site = "https://montagefinta.de";

// Seiten, die nicht in die Sitemap gehören (noindex)
const excludedFromSitemap = new Set([`${site}/danke`, `${site}/404`]);

// https://astro.build/config
export default defineConfig({
  site,

  // Erzeugt /anfrage.html statt /anfrage/index.html. Der Apache-Server
  // liefert diese Dateien über public/.htaccess unter /anfrage aus.
  build: {
    format: "file",
  },
  trailingSlash: "never",

  // Erzeugt eine Content-Security-Policy mit Hashes für alle
  // Skripte und Styles (inkl. der von Astro inline eingebundenen).
  security: {
    csp: true,
  },

  // Kein Markdown-Code auf der Seite; Shiki würde CSP-inkompatible
  // Inline-Styles erzeugen.
  markdown: {
    syntaxHighlight: false,
  },

  vite: {
    plugins: [tailwindcss()],
  },

  integrations: [
    sitemap({
      filter: (page) => !excludedFromSitemap.has(page.replace(/\/$/, "")),
    }),
  ],
});
