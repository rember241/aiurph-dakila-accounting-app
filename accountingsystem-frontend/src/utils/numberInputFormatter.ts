import type React from 'react'

/**
 * Sanitizes input to allow only numeric values and a single decimal point
 *
 * @param {React.ChangeEvent<HTMLInputElement>} event The change event from the input field
 */
export const numberInputFormatter = (event: React.ChangeEvent<HTMLInputElement>) => {
  // Remove anything that isn't a digit or a dot
  let cleanValue = event.target.value.replace(/[^0-9.]/g, '')

  const parts = cleanValue.split('.')

  // Prevent entering multiple decimal points
  if (parts.length > 2) {
    cleanValue = parts[0] + '.' + parts.slice(1).join('')
  }

  event.target.value = cleanValue
}
