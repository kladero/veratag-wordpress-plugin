# Running vera[tag] tests

This public source includes offline PHP and React test suites using synthetic fixtures. No WordPress server, Docker, database or backend credentials are required.

## Commands

Run from the repository root with PHP 8.3+ and Node 22.12+:

```bash
npm ci
npm run test:php
npm run test:ui
npm test
npm run zip:release
```

The ZIP builder runs `npm test` before packaging and fails if tests fail. Installable WordPress releases exclude test files. The tests use Node's built-in test runner, ReactDOMServer, and the repository's existing TypeScript and React dependencies.

## Coverage and limits

- PHP tests run the actual language selector, disclosure loader, page/domain matching, generated JSON/script injection, issue aggregation, push authentication, push instruction input validation, and backend-response DTOs against in-memory WordPress/SQL adapters. Regression cases verify that malformed content records are skipped individually without blocking valid disclosures on the same page.
- React tests render actual components on the server and test the real admin API bridge with synthetic HTTP results. They do **not** simulate browser clicks, effects, or a live WordPress UI.
- Tests validate the WordPress PHP output contract, **not** the separate disclosure rendering engine, a running Java backend, or a live WordPress installation.
- The current WordPress issue mirror intentionally does not store the backend-provided `headline`, and its UI derives issue titles from `reason`. A six-field backend DTO projection test documents that loss; database/browser behavior is not proven by these suites.
- Synthetic fixtures only: no working keys, credentials, domain-verification tokens or customer data are included.
- For higher assurance, separately validate WordPress hooks and live browser interaction in an isolated integration environment.
