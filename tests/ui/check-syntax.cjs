// Syntax validation of all UI tests, including the test harness itself.
const { readdirSync } = require('node:fs')
const path = require('node:path')
const { spawnSync } = require('node:child_process')

const directory = __dirname
const files = readdirSync(directory).filter((name) => name.endsWith('.cjs'))
if (files.length < 2) {
  throw new Error('No UI test files found')
}
for (const file of files) {
  const result = spawnSync(process.execPath, ['--check', path.join(directory, file)], {
    stdio: 'inherit',
  })
  if (result.error || result.status !== 0) {
    throw new Error(`UI test syntax failed: ${file}`)
  }
}
const config = path.join(directory, 'eslint.config.js')
const configCheck = spawnSync(process.execPath, ['--check', config], {
  stdio: 'inherit',
})
if (configCheck.error || configCheck.status !== 0) {
  throw new Error('UI ESLint configuration syntax failed')
}
console.log(
  `[React] ${files.length} UI test files and the ESLint configuration passed Node syntax checks.`,
)
