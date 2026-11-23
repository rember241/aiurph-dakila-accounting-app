'use client'

import * as React from 'react'

/**
 * Delays a rapidly changing value so search boxes and filter bars do not fire
 * expensive work on every keypress.
 */
export function useDebouncedValue<T>(value: T, delayMs = 400) {
  const [debounced, setDebounced] = React.useState(value)

  React.useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delayMs)

    return () => clearTimeout(t)
  }, [value, delayMs])

  return debounced
}
