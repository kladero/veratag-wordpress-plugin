const VISIBLE_PREFIX_LENGTH = 4
const VISIBLE_SUFFIX_LENGTH = 4
const MASK_CHAR = '*'

/**
 * Masks a secret as `VVVV********VVVV` — the first and last
 * {@link VISIBLE_PREFIX_LENGTH}/{@link VISIBLE_SUFFIX_LENGTH} characters
 * stay visible, everything between them becomes `{@link MASK_CHAR}`. A
 * secret too short to have a non-overlapping prefix and suffix is masked
 * in full instead, rather than showing the same character twice.
 */
export function maskSecret(secret: string): string {
  const minimumLengthForPartialReveal = VISIBLE_PREFIX_LENGTH + VISIBLE_SUFFIX_LENGTH
  if (secret.length <= minimumLengthForPartialReveal) {
    return MASK_CHAR.repeat(secret.length)
  }

  const prefix = secret.slice(0, VISIBLE_PREFIX_LENGTH)
  const suffix = secret.slice(secret.length - VISIBLE_SUFFIX_LENGTH)
  const maskedMiddleLength = secret.length - VISIBLE_PREFIX_LENGTH - VISIBLE_SUFFIX_LENGTH

  return prefix + MASK_CHAR.repeat(maskedMiddleLength) + suffix
}
