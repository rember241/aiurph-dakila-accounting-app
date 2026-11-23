import type React from 'react'

/**
 * Formats numbers by adding two zeroes after the decimal point
 *
 * @param event
 */
export const numberOutputFormatter = (event: React.FocusEvent<HTMLInputElement>) => {
  const value = parseFloat(event.target.value)

  if (!isNaN(value)) {
    event.target.value = value.toFixed(2)
  } else {
    event.target.value = '0.00'
  }
}
