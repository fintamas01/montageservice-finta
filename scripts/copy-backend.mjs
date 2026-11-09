import { cpSync, existsSync, mkdirSync, copyFileSync } from "node:fs";
import { resolve } from "node:path";

const backendDir = resolve("backend");
const vendorDir = resolve(backendDir, "vendor");
const destination = resolve("dist/api");

if (!existsSync(vendorDir)) {
  console.error(
    "\n✖ backend/vendor fehlt. Bitte zuerst die PHP-Abhängigkeiten installieren:\n" +
      "    cd backend && composer install --no-dev --optimize-autoloader\n",
  );
  process.exit(1);
}

mkdirSync(destination, { recursive: true });

for (const file of ["send-request.php", "request-validation.php"]) {
  copyFileSync(resolve(backendDir, file), resolve(destination, file));
}

// PHPMailer + Composer-Autoloader
cpSync(vendorDir, resolve(destination, "vendor"), { recursive: true });

// Direkten Zugriff auf alles außer dem Endpunkt verhindern
copyFileSync(resolve(backendDir, "api.htaccess"), resolve(destination, ".htaccess"));

console.log("PHP backend copied to dist/api");
