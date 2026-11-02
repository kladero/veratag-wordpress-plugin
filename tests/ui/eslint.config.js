// Node-native React test harness (CommonJS), linted independently of the Vite application.
import js from '@eslint/js'
import globals from 'globals'

export default [
  {
    files: ['**/*.cjs'],
    ...js.configs.recommended,
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'commonjs',
      globals: { ...globals.node, ...globals.es2022 },
    },
  },
]
