const { test } = require('node:test')
const assert = require('node:assert/strict')
const { renderUi, resetWindow } = require('./bootstrap.cjs')
const {
  PushSynchronizationConfigurationSection: Section,
} = require('../../src/ui/admin/src/app/ui-components/pages/settings-page/sections/push-synchronization-configuration-section/PushSynchronizationConfigurationSection.tsx')
const status = {
  configured: false,
  enabled: false,
  endpointUrl: null,
  hasCredential: false,
  secretStub: null,
  lastPushAttemptAt: null,
  lastPushAttemptStatus: null,
  lastPushAttemptStatusMessage: null,
  lastSuccessfulPushAt: null,
  lastSuccessfulPushMessage: null,
  receivingUrl: 'https://site.example.test/wp-json/veratag/push',
}
function display(props = {}) {
  resetWindow()
  return renderUi(Section, {
    status,
    hasApiKey: true,
    hasConnectedSuccessfullyAtLeastOnce: true,
    onStatusUpdated() {},
    ...props,
  })
}
test('push UI: no successful channel connection hides push setup', () => {
  assert.doesNotMatch(
    display({ hasConnectedSuccessfullyAtLeastOnce: false }),
    /Push Synchronization API Key/,
  )
})
test('push UI: unconfigured push status displays setup option', () => {
  const html = display()
  assert.match(html, /Push Synchronization API Key/)
  assert.match(html, /Not configured/)
  assert.match(html, /site\.example\.test/)
})
for (const enabled of [false, true]) {
  test(`push UI: configured enabled=${enabled} displays correct state`, () => {
    const html = display({
      status: {
        ...status,
        configured: true,
        enabled,
        hasCredential: true,
        secretStub: 'TEST***KEY',
      },
    })
    assert.match(html, /Push Synchronization API Key/)
    assert.match(html, enabled ? /aria-checked="true"/ : /aria-checked="false"/)
    assert.match(html, enabled ? /<span>Enabled<\/span>/ : /<span>Disabled<\/span>/)
  })
}
test('push UI: secret stub is masked before display', () => {
  const html = display({
    status: {
      ...status,
      configured: true,
      enabled: true,
      hasCredential: true,
      secretStub: 'synthetic-only-placeholder',
    },
  })
  assert.doesNotMatch(html, /synthetic-only-placeholder/)
  assert.match(html, /synt[*]+lder/)
})
