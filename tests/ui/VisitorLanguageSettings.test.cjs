const { test } = require('node:test')
const assert = require('node:assert/strict')
const { renderUi, resetWindow } = require('./bootstrap.cjs')
const {
  VisitorDisclosureLanguageConfigurationSection: Section,
} = require('../../src/ui/admin/src/app/ui-components/pages/settings-page/sections/visitor-disclosure-language-configuration-section/VisitorDisclosureLanguageConfigurationSection.tsx')
function settings(
  preferredMode = 'visitor_preferred',
  fallbackLanguageBcp47 = 'en',
  hasBeenSynced = true,
) {
  return { preferredMode, fallbackLanguageBcp47, hasBeenSynced }
}
function display(props) {
  resetWindow()
  return renderUi(Section, {
    hasApiKey: true,
    settings: settings(),
    onSettingsSaved() {},
    ...props,
  })
}
// Assert the selected value in the trigger, not a label somewhere in the option list.
function selectedControlLabel(html, controlId) {
  const marker = `id="${controlId}"`
  const index = html.indexOf(marker)
  assert.ok(index !== -1, `Missing dropdown control ${controlId}`)
  const buttonStart = html.lastIndexOf('<button', index)
  const buttonEnd = html.indexOf('</button>', index)
  assert.ok(buttonStart !== -1 && buttonEnd !== -1, `Invalid dropdown markup ${controlId}`)
  const controlHtml = html.slice(buttonStart, buttonEnd)
  const selected = controlHtml.match(/<span class="veratag-input-select__value">([^<]+)<\/span>/)
  assert.ok(selected, `Control ${controlId} does not show a selected value`)
  return selected[1]
}
test('visitor UI: no API key hides entire language-settings section', () => {
  assert.doesNotMatch(display({ hasApiKey: false }), /Visitor Disclosure Language/)
})
test('visitor UI: API key without synchronized settings shows corrective message', () => {
  const html = display({ settings: settings('', '', false) })
  assert.match(html, /Visitor Disclosure Language/)
  assert.match(html, /have not been received/)
  assert.match(html, /pull synchronization/i)
  assert.doesNotMatch(html, /Save<\/button>/)
})
for (const mode of ['visitor_preferred', 'server_preferred']) {
  test(`visitor UI: configured ${mode} shows selected label`, () => {
    const html = display({ settings: settings(mode, 'en') })
    assert.match(html, /Preferred language mode/)
    assert.equal(
      selectedControlLabel(html, 'veratag-visitor-disclosure-language-mode'),
      mode === 'visitor_preferred' ? 'Visitor-preferred' : 'Site-preferred',
    )
    assert.match(html, /Fallback language/)
    assert.match(html, /Save/)
  })
}
for (const language of ['en', 'de', 'fr', 'es', 'pl', 'nl', 'sv', 'it']) {
  test(`visitor UI: configured fallback ${language} is displayed`, () => {
    const html = display({ settings: settings('visitor_preferred', language) })
    const labels = {
      en: 'English',
      de: 'German',
      fr: 'French',
      es: 'Spanish',
      pl: 'Polish',
      nl: 'Dutch',
      sv: 'Swedish',
      it: 'Italian',
    }
    assert.equal(
      selectedControlLabel(html, 'veratag-visitor-disclosure-fallback-language'),
      labels[language],
    )
  })
}
test('visitor UI: description explicitly explains fallback semantics', () => {
  const html = display({})
  assert.match(html, /fallback language/i)
  assert.match(html, /browser language/i)
  assert.match(html, /WordPress site/i)
})
test('visitor UI: synthetic settings render without unrelated configuration URLs', () => {
  const html = display({})
  assert.doesNotMatch(html, /kladero-dev|vera-tag\.kladero\.com|TEST_SECRET/)
})
