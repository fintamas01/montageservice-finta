/**
 * Limits und Meldungen des Anfrageformulars.
 * Die Limits müssen mit backend/request-validation.php übereinstimmen.
 */
export const uploadLimits = {
  maxFiles: 5,
  maxFileSize: 5 * 1024 * 1024,
  maxTotalSize: 15 * 1024 * 1024,
  acceptedTypes: ["image/jpeg", "image/png", "image/webp"],
} as const;

/** Meldungen zu den Statuscodes, die send-request.php liefert. */
export const statusMessages: Record<string, string> = {
  missing: "Bitte füllen Sie alle Pflichtfelder aus.",
  privacy: "Bitte bestätigen Sie die Datenschutzhinweise.",
  email: "Bitte geben Sie eine gültige E-Mail-Adresse an.",
  phone: "Bitte geben Sie eine gültige Telefonnummer an.",
  postal_code: "Bitte geben Sie eine gültige Postleitzahl an.",
  service: "Bitte wählen Sie eine Leistung aus.",
  invalid: "Einige Angaben sind zu lang. Bitte kürzen Sie Ihre Eingaben.",
  files: "Bitte wählen Sie höchstens 5 Fotos aus.",
  filesize: "Die Fotos sind zu groß (max. 5 MB pro Foto, 15 MB insgesamt).",
  filetype: "Bitte laden Sie nur Fotos im Format JPG, PNG oder WebP hoch.",
  upload: "Beim Hochladen der Fotos ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.",
  rate: "Sie haben in kurzer Zeit mehrere Anfragen gesendet. Bitte versuchen Sie es in einigen Minuten erneut.",
  spam: "Ihre Anfrage konnte nicht verarbeitet werden. Bitte versuchen Sie es erneut.",
  send: "Ihre Anfrage konnte leider nicht gesendet werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns über Instagram.",
  network: "Keine Verbindung zum Server. Bitte prüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.",
};
