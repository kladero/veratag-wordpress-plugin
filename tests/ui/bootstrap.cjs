// Zero-additional-dependency React UI test harness.
// Uses production React + ReactDOMServer and the existing TypeScript dependency.
// These are SSR/component-state tests, not browser-interaction/E2E tests.
const fs = require('node:fs')
const ts = require('typescript')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
for (const ext of ['.ts', '.tsx']) {
  require.extensions[ext] = (mod, filename) => {
    const source = fs.readFileSync(filename, 'utf8')
    const transpiled = ts.transpileModule(source, {
      fileName: filename,
      compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
        jsx: ts.JsxEmit.ReactJSX,
        esModuleInterop: true,
      },
      reportDiagnostics: true,
    })
    if (transpiled.diagnostics?.length) {
      const errors = transpiled.diagnostics.filter(
        (d) => d.category === ts.DiagnosticCategory.Error,
      )
      if (errors.length)
        throw new Error(
          errors.map((d) => ts.flattenDiagnosticMessageText(d.messageText, '\n')).join('\n'),
        )
    }
    mod._compile(transpiled.outputText, filename)
  }
}
require.extensions['.css'] = (mod) => {
  mod.exports = {}
}
for (const ext of ['.png', '.svg', '.woff2']) {
  require.extensions[ext] = (mod) => {
    mod.exports = ''
  }
}
function resetWindow() {
  const window = new EventTarget()
  window.veratagAdminConfig = {
    currentPage: 'general',
    currentUserDisplayName: 'Synthetic Admin',
    restApiBaseUrl: 'https://site.example.test/wp-json/veratag/v1/',
    restNonce: 'TEST_NONCE_DO_NOT_USE',
    channelDashboardUrl: 'https://dashboard.example.test/app/',
    settingsPageUrl: 'https://site.example.test/wp-admin/?page=veratag-settings',
    wordmarkUrl: 'https://site.example.test/assets/wordmark.svg',
    iconsBaseUrl: 'https://site.example.test/assets/icons/',
    formIcons: {
      selectChevron: 'https://site.example.test/assets/icons/l/chevron-right.svg',
      server: 'https://site.example.test/assets/icons/l/server.svg',
      userRound: 'https://site.example.test/assets/icons/l/user-round.svg',
    },
    statusIcons: {
      failure: 'https://site.example.test/assets/icons/l/circle-x.svg',
      success: 'https://site.example.test/assets/icons/l/circle-check-big.svg',
    },
  }
  global.window = window
  if (typeof global.CustomEvent === 'undefined') {
    global.CustomEvent = class CustomEvent extends Event {
      constructor(name, config = {}) {
        super(name)
        this.detail = config.detail
      }
    }
  }
  return window
}
resetWindow()
const {
  NotificationProvider,
} = require('../../src/ui/admin/src/app/ui-components/shared/notifications/notification-provider/NotificationProvider.tsx')
function renderUi(Component, props = {}, { notifications = true } = {}) {
  const component = React.createElement(Component, props)
  return renderToStaticMarkup(
    notifications ? React.createElement(NotificationProvider, null, component) : component,
  )
}
function jsonResponse(json, status = 200) {
  return { ok: status >= 200 && status < 300, status, json: async () => json }
}
module.exports = { React, renderUi, resetWindow, jsonResponse }
