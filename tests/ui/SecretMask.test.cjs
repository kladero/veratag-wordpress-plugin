const { test } = require('node:test')
const assert = require('node:assert/strict')
require('./bootstrap.cjs')
const {
  maskSecret,
} = require('../../src/ui/admin/src/app/ui-components/shared/secret-mask/maskSecret.ts')
for (const [value, expected] of [
  ['', ''],
  ['a', '*'],
  ['abcd', '****'],
  ['abcdefgh', '********'],
  ['abcdefghi', 'abcd* fghi'.replace(' ', '')],
  ['syntheticTESTKEY', 'synt********TKEY'],
]) {
  test(`UI secret mask: synthetic length ${value.length}`, () => {
    assert.equal(maskSecret(value), expected)
  })
}
