import { existsSync, readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { join } from "node:path";

import { auditPermissionCatalog } from "../resources/js/Composables/usePermissionLabels.js";

const phpCode = [
  "require 'vendor/autoload.php';",
  "$app = require 'bootstrap/app.php';",
  "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();",
  "echo json_encode(App\\Models\\Permission::query()->orderBy('name')->pluck('name')->all());",
].join(" ");

function resolvePhpCommand() {
  if (process.env.PHP_BINARY && existsSync(process.env.PHP_BINARY)) {
    return process.env.PHP_BINARY;
  }

  if (process.platform === "win32") {
    const laragonIni = "C:\\laragon\\usr\\laragon.ini";

    if (existsSync(laragonIni)) {
      const phpVersion = readFileSync(laragonIni, "utf8").match(/^\[php\][\s\S]*?^Version=(.+)$/m)?.[1]?.trim();
      const laragonPhp = phpVersion && join("C:\\laragon\\bin\\php", phpVersion, "php.exe");

      if (laragonPhp && existsSync(laragonPhp)) {
        return laragonPhp;
      }
    }
  }

  return "php";
}

const databaseResult = spawnSync(resolvePhpCommand(), ["-r", phpCode], {
  cwd: process.cwd(),
  encoding: "utf8",
});

if (databaseResult.status !== 0) {
  process.stderr.write(databaseResult.stderr);
  process.exit(databaseResult.status ?? 1);
}

const permissionNames = JSON.parse(databaseResult.stdout);
const catalogAudit = auditPermissionCatalog(permissionNames);
const seederSource = readFileSync("database/seeders/PermissionSeeder.php", "utf8");
const declaredNames = [...seederSource.matchAll(/'([a-z][a-z0-9.-]+)'/g)]
  .map((match) => match[1])
  .filter((name) => name.includes("."));
const duplicateSeederDeclarations = [...new Set(
  declaredNames.filter((name, index) => declaredNames.indexOf(name) !== index),
)];

const failures = {
  ...catalogAudit,
  duplicateSeederDeclarations,
};
const hasFailures = Object.values(failures).some((items) => items.length > 0);

if (hasFailures) {
  console.error(JSON.stringify(failures, null, 2));
  process.exit(1);
}

console.log(
  `Catálogo válido: ${permissionNames.length} permisos, sin duplicados, omisiones ni submódulos inválidos.`,
);
