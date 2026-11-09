import type { ImageMetadata } from "astro";

import kitchenUShape from "../assets/images/kueche-u-form.jpeg";
import kitchenTall from "../assets/images/kueche-hochschrankzeile.jpeg";
import wardrobeNiche from "../assets/images/einbauschrank-nische.jpeg";
import doorGlass from "../assets/images/innentuer-glas.jpeg";
import doorGlassDetail from "../assets/images/innentuer-glas-detail.jpeg";
import shoeCabinet from "../assets/images/schuhschrank-eiche.jpeg";
import hallway from "../assets/images/flur-garderobe.jpeg";

/**
 * Zentrale Unternehmensdaten.
 *
 * WICHTIG: Hier stehen ausschließlich verifizierte Angaben.
 * Felder mit `null` sind noch nicht bestätigt und werden auf der
 * Website automatisch ausgeblendet bzw. als „ausstehend“ markiert.
 * Vor dem Livegang bitte vom Inhaber ergänzen lassen (siehe README).
 */
export const site = {
  name: "Montageservice Finta",
  url: "https://montagefinta.de",
  locale: "de_DE",
  tagline: "Küchen-, Möbel- und Türmontage",
  description:
    "Montageservice Finta: Küchenmontage, Möbelmontage sowie Montage von Türen und Zargen – präzise, sauber und zuverlässig ausgeführt.",
  instagram: {
    handle: "@montageservicefinta",
    url: "https://www.instagram.com/montageservicefinta/",
  },

  /** Öffentliche Kontaktdaten – erst eintragen, wenn bestätigt. */
  contact: {
    phone: null as string | null, // z. B. "+49 151 23456789"
    email: null as string | null, // z. B. "info@montagefinta.de"
  },

  /** Pflichtangaben für Impressum/Datenschutz (§ 5 DDG, Art. 13 DSGVO). */
  legal: {
    owner: null as string | null,
    street: null as string | null,
    postalCodeCity: null as string | null,
    vatId: null as string | null,
    hostingProvider: null as string | null,
  },
} as const;

export type ServiceSlug = "kuechenmontage" | "moebelmontage" | "tueren-zargen";

export interface Service {
  slug: ServiceSlug;
  /** Wert, der im Formular übermittelt und serverseitig geprüft wird. */
  formValue: string;
  title: string;
  short: string;
  description: string;
  image: ImageMetadata;
  alt: string;
}

export const services: Service[] = [
  {
    slug: "kuechenmontage",
    formValue: "Küchenmontage",
    title: "Küchenmontage",
    short: "Küchen",
    description:
      "Wir bauen Ihre neue Küche fachgerecht auf, richten Schränke und Fronten exakt aus und sorgen für saubere Fugen und Abschlüsse.",
    image: kitchenTall,
    alt: "Montierte Hochschrankzeile in Hellgrau mit eingebautem Backofen und Mikrowelle",
  },
  {
    slug: "moebelmontage",
    formValue: "Möbelmontage",
    title: "Möbelmontage",
    short: "Möbel",
    description:
      "Vom einzelnen Schrank bis zur kompletten Einrichtung: Wir montieren Möbel und Einbauschränke stabil, passgenau und sauber.",
    image: wardrobeNiche,
    alt: "Raumhoher weißer Einbauschrank mit Sitznische und Holzlamellen-Rückwand",
  },
  {
    slug: "tueren-zargen",
    formValue: "Türen und Zargen",
    title: "Türen & Zargen",
    short: "Türen",
    description:
      "Wir setzen Zargen und Innentüren lotrecht und spaltgenau ein – damit Türen leichtgängig schließen und lange Freude bereiten.",
    image: doorGlass,
    alt: "Neu montierte weiße Innentür mit Glasausschnitt und Edelstahl-Drücker",
  },
];

/** Zusätzliche Auswahl im Formular. */
export const otherServiceValue = "Sonstiges";

export interface Project {
  category: string;
  title: string;
  image: ImageMetadata;
  alt: string;
  layout: "feature" | "tall" | "standard";
}

export const projects: Project[] = [
  {
    category: "Küchenmontage",
    title: "Küche in U-Form",
    image: kitchenUShape,
    alt: "Küche in U-Form mit hellgrauen grifflosen Fronten, dunkler Arbeitsplatte und Inselhaube",
    layout: "feature",
  },
  {
    category: "Möbelmontage",
    title: "Einbauschrank mit Nische",
    image: wardrobeNiche,
    alt: "Raumhoher Einbauschrank in Weiß mit Sitznische aus Eiche und Lamellenwand",
    layout: "tall",
  },
  {
    category: "Möbelmontage",
    title: "Schuhschrank in Eiche",
    image: shoeCabinet,
    alt: "Schuhschrank mit weißen Fronten und Eichenkorpus unter einem Fenster",
    layout: "standard",
  },
  {
    category: "Türen & Zargen",
    title: "Innentür mit Lichtausschnitt",
    image: doorGlassDetail,
    alt: "Weiße Innentür mit großem Glasausschnitt in passender Zarge",
    layout: "standard",
  },
];

export const images = {
  hero: kitchenUShape,
  heroAlt:
    "Fertig montierte Küche in U-Form mit hellen Fronten und dunkler Arbeitsplatte",
  about: hallway,
  aboutAlt:
    "Heller Flur mit montiertem Schuhschrank aus Eiche und weißen Fronten",
};

export const navigation = [
  { label: "Leistungen", href: "/#leistungen" },
  { label: "Referenzen", href: "/#referenzen" },
  { label: "Ablauf", href: "/#ablauf" },
  { label: "Über uns", href: "/#ueber-uns" },
];

/** Formatiert eine Telefonnummer für `tel:`-Links. */
export const telHref = (phone: string) => `tel:${phone.replace(/[^\d+]/g, "")}`;
