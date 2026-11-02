const { test } = require('node:test')
const assert = require('node:assert/strict')
const { resetWindow, jsonResponse } = require('./bootstrap.cjs')
const bridge = require('../../src/ui/admin/src/app/api/adminUiBridge.ts')
function setup(json, status = 200) {
  resetWindow()
  const calls = []
  global.fetch = async (url, init) => {
    calls.push({ url, init })
    return jsonResponse(json, status)
  }
  return calls
}
const dummySettings = {
  apiKey: '',
  compatibility: { status: 'ok', message: null },
  channelDashboardUrl: 'https://dashboard.example.test/app/',
}
test('bridge: settings request uses same-origin credentials and synthetic nonce', async () => {
  const calls = setup(dummySettings)
  const result = await bridge.loadAdminSettings()
  assert.equal(result, dummySettings)
  assert.equal(calls[0].url, 'https://site.example.test/wp-json/veratag/v1/settings')
  assert.equal(calls[0].init.credentials, 'same-origin')
  assert.equal(calls[0].init.headers['X-WP-Nonce'], 'TEST_NONCE_DO_NOT_USE')
  assert.equal(calls[0].init.method, 'GET')
})
test('bridge: loadAdminSettings dispatches settings-updated event', async () => {
  setup(dummySettings)
  let detail
  global.window.addEventListener('veratag:settings-updated', (event) => {
    detail = event.detail
  })
  await bridge.loadAdminSettings()
  assert.deepEqual(detail, dummySettings)
})
test('bridge: API key POST sends JSON body (synthetic only)', async () => {
  const calls = setup(dummySettings)
  await bridge.updateAdminSettings({ apiKey: 'TEST_KEY_ONLY' })
  assert.equal(calls[0].init.method, 'POST')
  assert.deepEqual(JSON.parse(calls[0].init.body), { apiKey: 'TEST_KEY_ONLY' })
})
for (const [preferredMode, fallback] of [
  ['visitor_preferred', 'de'],
  ['server_preferred', 'en'],
  ['visitor_preferred', 'fr'],
]) {
  test(`bridge: language settings PUT mode=${preferredMode} fallback=${fallback}`, async () => {
    const result = { preferredMode, fallbackLanguageBcp47: fallback, hasBeenSynced: true }
    const calls = setup(result)
    assert.equal(
      await bridge.updateVisitorDisclosureLanguageSettings(preferredMode, fallback),
      result,
    )
    assert.equal(calls[0].init.method, 'PUT')
    assert.match(calls[0].url, /visitor-disclosure-language-settings$/)
    assert.deepEqual(JSON.parse(calls[0].init.body), {
      preferredMode,
      fallbackLanguageBcp47: fallback,
    })
  })
}
test('bridge: backend failure message is surfaced without altering it', async () => {
  setup({ message: 'Synthetic API failure' }, 403)
  await assert.rejects(() => bridge.loadAdminSettings(), /Synthetic API failure/)
})
test('bridge: malformed backend error uses safe generic fallback', async () => {
  setup({ irrelevant: true }, 500)
  await assert.rejects(() => bridge.loadAdminSettings(), /Request failed/)
})
for (const [name, call, method, path] of [
  ['pull sync', () => bridge.runPullSynchronization(), 'POST', /pull-synchronization\/run$/],
  [
    'pull status',
    () => bridge.loadPullSynchronizationStatus(),
    'GET',
    /pull-synchronization\/status$/,
  ],
  ['push configure', () => bridge.configurePushApi(), 'PUT', /push-api$/],
  ['push disable', () => bridge.setPushApiEnabled(false), 'PATCH', /push-api\/enabled$/],
  ['push remove', () => bridge.removePushApi(), 'DELETE', /push-api$/],
  [
    'verification create',
    () => bridge.configureVerificationFile('TEST_VERIFICATION'),
    'POST',
    /verification-file$/,
  ],
  ['verification delete', () => bridge.removeVerificationFile(), 'DELETE', /verification-file$/],
  [
    'render status',
    () => bridge.loadRenderScriptSyncStatus(),
    'GET',
    /render-script-sync\/status$/,
  ],
]) {
  test(`bridge: ${name} endpoint and verb`, async () => {
    const calls = setup({ ok: true })
    await call()
    assert.equal(calls[0].init.method, method)
    assert.match(calls[0].url, path)
  })
}
test('bridge: outgoing push toggle carries boolean false', async () => {
  const calls = setup({ ok: true })
  await bridge.setPushApiEnabled(false)
  assert.deepEqual(JSON.parse(calls[0].init.body), { enabled: false })
})
test('bridge: outgoing push toggle carries boolean true', async () => {
  const calls = setup({ ok: true })
  await bridge.setPushApiEnabled(true)
  assert.deepEqual(JSON.parse(calls[0].init.body), { enabled: true })
})
test('bridge: content overview filter encodes search/page/issue fields', async () => {
  const calls = setup({ items: [] })
  await bridge.loadContentOverviewPage({
    searchText: ' test ',
    contentTypes: ['image'],
    disclosureTypes: ['ai_generated'],
    pageUrls: ['/article/'],
    isDisclosed: true,
    issueImpacts: ['action_required'],
    sortBy: null,
    sortDir: 'asc',
    pageNumber: 2,
    pageSize: 10,
  })
  const url = new URL(calls[0].url)
  assert.equal(url.searchParams.get('searchText'), 'test')
  assert.deepEqual(url.searchParams.getAll('pageUrl[]'), ['/article/'])
  assert.deepEqual(url.searchParams.getAll('issueImpact[]'), ['action_required'])
  assert.equal(url.searchParams.get('pageNumber'), '2')
  assert.equal(url.searchParams.get('isDisclosed'), 'true')
})
