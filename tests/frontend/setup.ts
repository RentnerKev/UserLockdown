import './dom'

import type { TestingLibraryMatchers } from '@testing-library/jest-dom/matchers'
import { afterEach, expect, mock } from 'bun:test'

// Both libraries must bind to the DOM installed by the preload above.
const { cleanup } = await import('@testing-library/react')
const { default: _defaultMatchers, ...matchers } =
  await import('@testing-library/jest-dom/matchers')

declare module 'bun:test' {
  interface Matchers<T> extends Omit<
    TestingLibraryMatchers<ReturnType<typeof expect.stringContaining>, T>,
    'toBeEmpty'
  > {}
}

expect.extend(matchers)

mock.module('@nextcloud/l10n', () => ({
  translate: (
    _app: string,
    message: string,
    replacements: Readonly<Record<string, string | number>> = {},
  ) =>
    Object.entries(replacements).reduce(
      (translated, [key, value]) => translated.replace(`{${key}}`, String(value)),
      message,
    ),
}))

mock.module('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
}))

mock.module('@nextcloud/initial-state', () => ({
  loadState: mock((_app: string, _key: string, fallback: unknown) => fallback),
}))

afterEach(() => {
  cleanup()
  mock.clearAllMocks()
})
