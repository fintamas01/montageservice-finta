import {
  cpSync,
  existsSync,
  mkdirSync,
  copyFileSync,
} from "node:fs";

import { resolve } from "node:path";

const backendDir = resolve("backend");
const destination = resolve("dist/api");

if (!existsSync(backendDir)) {
  console.error("Backend directory not found.");
  process.exit(1);
}

mkdirSync(destination, {
  recursive: true,
});

// PHP endpoint
copyFileSync(
  resolve(backendDir, "send-request.php"),
  resolve(destination, "send-request.php")
);

// PHPMailer + Composer autoloader
cpSync(
  resolve(backendDir, "vendor"),
  resolve(destination, "vendor"),
  {
    recursive: true,
  }
);

console.log("PHP backend copied to dist/api");