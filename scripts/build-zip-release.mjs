// Build an installable WordPress plugin ZIP from this source repository.
import {
  readFileSync,
  readdirSync,
  lstatSync,
  mkdirSync,
  writeFileSync,
  existsSync,
} from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { deflateRawSync } from "node:zlib";
import { fileURLToPath } from "node:url";

const PLUGIN_SLUG = "veratag-wordpress-plugin";
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const TEXT_EXT = new Set([
  ".php",
  ".js",
  ".jsx",
  ".ts",
  ".tsx",
  ".css",
  ".json",
  ".md",
  ".txt",
  ".html",
  ".svg",
  ".xml",
]);
const ASSET_DIRECTORIES = ["admin", "icons", "logos", "poppins"];

function readVersion() {
  const pkg = JSON.parse(readFileSync(path.join(root, "package.json"), "utf8"));
  const lock = JSON.parse(
    readFileSync(path.join(root, "package-lock.json"), "utf8"),
  );
  if (!/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(pkg.version)) {
    throw new Error(`Invalid package.json version: ${pkg.version}`);
  }
  if (
    lock.version !== pkg.version ||
    lock.packages?.[""]?.version !== pkg.version
  ) {
    throw new Error(
      "package-lock.json version differs from package.json. Run npm install --package-lock-only.",
    );
  }
  return pkg.version;
}
function normalize(filename, bytes) {
  return TEXT_EXT.has(path.extname(filename).toLowerCase())
    ? Buffer.from(bytes.toString("utf8").replace(/\r\n?/g, "\n"), "utf8")
    : bytes;
}
function readTree(folder, prefix) {
  if (!existsSync(folder)) throw new Error(`Missing directory: ${folder}`);
  const entries = [];
  for (const name of readdirSync(folder).sort()) {
    if (name === ".DS_Store" || name === "Thumbs.db") continue;
    const file = path.join(folder, name);
    const filename = `${prefix}/${name}`;
    const stat = lstatSync(file);
    if (stat.isSymbolicLink())
      throw new Error(`Refusing symlink in release: ${file}`);
    if (stat.isDirectory()) entries.push(...readTree(file, filename));
    else if (stat.isFile())
      entries.push({
        path: filename,
        bytes: normalize(filename, readFileSync(file)),
      });
    else throw new Error(`Unsupported file type in release: ${file}`);
  }
  return entries;
}
function textEntry(filename, text) {
  return {
    path: filename,
    bytes: normalize(filename, Buffer.from(text, "utf8")),
  };
}
function stampPluginVersion(source, version) {
  if (!/^\s*\*\s*Version:\s*\S+/m.test(source))
    throw new Error("Missing WordPress plugin Version header");
  return source.replace(/^(\s*\*\s*Version:)[ \t]*[^\r\n]+/m, `$1 ${version}`);
}
const CRC_TABLE = Uint32Array.from({ length: 256 }, (_, index) => {
  let crc = index;
  for (let i = 0; i < 8; i++)
    crc = crc & 1 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
  return crc >>> 0;
});
function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) crc = CRC_TABLE[(crc ^ byte) & 255] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}
function writeZip(entries, output) {
  const names = new Set();
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const { path: filename, bytes } of [...entries].sort((a, b) =>
    a.path.localeCompare(b.path, "en"),
  )) {
    if (
      !filename ||
      filename.startsWith("/") ||
      filename.includes("\\") ||
      filename.split("/").some((p) => !p || p === "." || p === "..")
    ) {
      throw new Error(`Unsafe ZIP path: ${filename}`);
    }
    if (names.has(filename)) throw new Error(`Duplicate ZIP file: ${filename}`);
    names.add(filename);
    const name = Buffer.from(filename, "utf8");
    const compressed = deflateRawSync(bytes, { level: 9 });
    if (
      name.length > 65535 ||
      bytes.length > 0xffffffff ||
      compressed.length > 0xffffffff ||
      offset > 0xffffffff
    ) {
      throw new Error("ZIP64 is not supported by this release builder.");
    }
    const crc = crc32(bytes);
    const time = ((2020 - 1980) << 9) | (1 << 5) | 1;
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6);
    local.writeUInt16LE(8, 8);
    local.writeUInt16LE(0, 10);
    local.writeUInt16LE(time, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(compressed.length, 18);
    local.writeUInt32LE(bytes.length, 22);
    local.writeUInt16LE(name.length, 26);
    locals.push(local, name, compressed);
    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE((3 << 8) | 20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(8, 10);
    central.writeUInt16LE(0, 12);
    central.writeUInt16LE(time, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(compressed.length, 20);
    central.writeUInt32LE(bytes.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt32LE((0o100644 << 16) >>> 0, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);
    offset += local.length + name.length + compressed.length;
  }
  if (names.size > 65535) throw new Error("ZIP entry limit exceeded");
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(names.size, 8);
  end.writeUInt16LE(names.size, 10);
  end.writeUInt32LE(
    centrals.reduce((sum, buffer) => sum + buffer.length, 0),
    12,
  );
  end.writeUInt32LE(offset, 16);
  mkdirSync(path.dirname(output), { recursive: true });
  writeFileSync(output, Buffer.concat([...locals, ...centrals, end]));
}
function runMandatoryTests() {
  // Test gate applies even when calling this script directly, bypassing npm hooks.
  const npmCli = [
    process.env.npm_execpath,
    path.join(path.dirname(process.execPath), "node_modules", "npm", "bin", "npm-cli.js"),
    path.resolve(path.dirname(process.execPath), "../lib/node_modules/npm/bin/npm-cli.js"),
  ].find((candidate) => candidate && existsSync(candidate));
  if (!npmCli) throw new Error("npm-cli.js not found: run the build with npm");
  const outcome = spawnSync(process.execPath, [npmCli, "run", "test"], {
    cwd: root,
    stdio: "inherit",
    windowsHide: true,
  });
  if (outcome.error || outcome.status !== 0) {
    throw new Error(`Mandatory release tests failed: ${outcome.error?.message ?? outcome.status}`);
  }
}
function buildRelease() {
  runMandatoryTests();
  const version = readVersion();
  const phpRoot = path.join(root, "src/php");
  const prefix = `${PLUGIN_SLUG}/`;
  const phpEntrypoint = readFileSync(
    path.join(root, `${PLUGIN_SLUG}.php`),
    "utf8",
  );
  const php = readTree(phpRoot, `${PLUGIN_SLUG}/src/php`).filter(
    (item) =>
      item.path !== `${prefix}src/php/Bootstrap/GeneratedBuildConfig.php`,
  );
  php.push(
    textEntry(
      `${prefix}src/php/Bootstrap/GeneratedBuildConfig.php`,
      readFileSync(
        path.join(phpRoot, "Bootstrap/GeneratedBuildConfig.php"),
        "utf8",
      ),
    ),
  );
  const assets = ASSET_DIRECTORIES.flatMap((directory) =>
    readTree(
      path.join(root, "assets", directory),
      `${prefix}assets/${directory}`,
    ),
  );
  for (const required of ["veratag-admin-ui.js", "veratag-admin-ui.css"]) {
    if (
      !assets.some((item) => item.path === `${prefix}assets/admin/${required}`)
    ) {
      throw new Error(
        `Missing built admin UI asset: ${required}; run npm run build:ui`,
      );
    }
  }
  const docs = [
    textEntry(
      `${prefix}${PLUGIN_SLUG}.php`,
      stampPluginVersion(phpEntrypoint, version),
    ),
    ...["LICENSE", "TRADEMARKS.md", "THIRD_PARTY_NOTICES.txt"].map((filename) =>
      textEntry(
        `${prefix}${filename}`,
        readFileSync(path.join(root, filename), "utf8"),
      ),
    ),
  ];
  const output = path.join(root, "dist", `${PLUGIN_SLUG}-release-${version}.zip`);
  writeZip([...assets, ...php, ...docs], output);
  console.log(`Built vera[tag] WordPress release v${version}: ${output}`);
}
if (process.argv.length !== 2) {
  console.error("Usage: node scripts/build-zip-release.mjs");
  process.exitCode = 1;
} else {
  try {
    buildRelease();
  } catch (error) {
    console.error(error.stack ?? String(error));
    process.exitCode = 1;
  }
}
