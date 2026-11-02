// Cross-platform Node test discovery. No browser, Docker, extra test framework or WordPress.
const fs = require('node:fs')
const path = require('node:path')
const { spawnSync } = require('node:child_process')
const paths = fs
  .readdirSync(__dirname)
  .filter((name) => name.endsWith('.test.cjs'))
  .map((name) => path.join(__dirname, name))
  .sort()
if (!paths.length) {
  console.error('No UI tests found. Failing closed.')
  process.exit(1)
}
const result = spawnSync(process.execPath, ['--test', ...paths], {
  stdio: 'inherit',
  cwd: path.resolve(__dirname, '..', '..'),
})
if (result.error) {
  console.error(result.error.message)
  process.exit(1)
}
process.exit(result.status === null ? 1 : result.status)
