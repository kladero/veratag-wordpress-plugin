const { test } = require('node:test')
const assert = require('node:assert/strict')
const { renderUi, resetWindow } = require('./bootstrap.cjs')
const {
  SetupRequiredSection,
} = require('../../src/ui/admin/src/app/ui-components/pages/general-page/sections/setup-required-section/SetupRequiredSection.tsx')
const {
  ErrorPage,
} = require('../../src/ui/admin/src/app/ui-components/shared/error-page/ErrorPage.tsx')
test('setup UI: missing API key explains connecting to platform', () => {
  resetWindow()
  const html = renderUi(SetupRequiredSection)
  assert.match(html, /Setup Required/)
  assert.match(html, /API key/)
  assert.match(html, /Open Settings/)
})
test('setup UI: settings link uses synthetic configured admin URL', () => {
  resetWindow()
  const html = renderUi(SetupRequiredSection)
  assert.match(html, /https:\/\/site\.example\.test\/wp-admin/)
})
test('setup UI: missing settings page URL disables navigation', () => {
  resetWindow()
  global.window.veratagAdminConfig.settingsPageUrl = ''
  const html = renderUi(SetupRequiredSection)
  assert.match(html, /disabled/)
})
for (const [title, code] of [
  ['Update required', '426'],
  ['Integration disabled', '403'],
  ['Unable to verify plugin compatibility', null],
]) {
  test(`error page: renders supplied title ${title} and message`, () => {
    resetWindow()
    const html = renderUi(ErrorPage, {
      title,
      message: 'Synthetic compatibility message',
      ...(code ? { code } : {}),
    })
    assert.match(html, new RegExp(title))
    assert.match(html, /Synthetic compatibility message/)
  })
}
