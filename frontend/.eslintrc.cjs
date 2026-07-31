/* eslint config (classic) — TypeScript + React hooks. */
module.exports = {
  root: true,
  env: { browser: true, es2021: true },
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:react-hooks/recommended',
  ],
  parser: '@typescript-eslint/parser',
  parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
  plugins: ['@typescript-eslint', 'react-refresh'],
  ignorePatterns: ['dist', 'node_modules', '*.cjs', 'vite.config.ts', 'playwright-report', 'test-results'],
  rules: {
    'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
  },
  overrides: [
    {
      // اختبارات E2E وإعداد Playwright — بيئة Node + المتصفّح، بلا قيد react-refresh.
      files: ['e2e/**/*.ts', 'playwright.config.ts'],
      env: { browser: true, node: true, es2021: true },
      rules: {
        'react-refresh/only-export-components': 'off',
        '@typescript-eslint/no-empty-function': 'off',
      },
    },
  ],
}
