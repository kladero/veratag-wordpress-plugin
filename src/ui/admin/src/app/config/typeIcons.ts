import { loadVeratagAdminConfig } from './loadVeratagAdminConfig'

/**
 * Centralized icon lookup for backend-owned web content type identifiers.
 * The keys mirror `WebContentType.uniqueName` in the backend. A newly added
 * or otherwise unknown value falls back to {@link FALLBACK_ICON_PATH}.
 *
 * Paths are relative to `iconsBaseUrl` (see `loadVeratagAdminConfig`), which
 * mirrors `assets/icons/` at the plugin root (see `AdminUiBootstrap::enqueueAssets`).
 */

/** Shown for any type string with no entry in a map below. */
const FALLBACK_ICON_PATH = 'l/ban.svg'

const CONTENT_TYPE_ICON_PATH: Record<string, string> = {
  heading: 'l/book-type.svg',
  paragraph: 'l/text-initial.svg',
  image: 'l/image.svg',
  'content-area': 'l/square-text.svg',
}

function resolveIconUrl(relativePath: string): string {
  const iconsBaseUrl = loadVeratagAdminConfig().iconsBaseUrl
  if (iconsBaseUrl === '') return ''
  return `${iconsBaseUrl.replace(/\/$/, '')}/${relativePath}`
}

function lookupOrFallback(map: Record<string, string>, key: string): string {
  return resolveIconUrl(map[key] ?? FALLBACK_ICON_PATH)
}

export function getContentTypeIconUrl(contentType: string): string {
  return lookupOrFallback(CONTENT_TYPE_ICON_PATH, contentType)
}
