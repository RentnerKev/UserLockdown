import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
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

vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => path,
}))

vi.mock('@nextcloud/initial-state', () => ({
  loadState: vi.fn((_app: string, _key: string, fallback: unknown) => fallback),
}))

afterEach(() => {
  cleanup()
  vi.clearAllMocks()
})
