export const SUPPORTED_CURRENCY_CODES = ['PHP', 'USD', 'EUR', 'SGD', 'GBP', 'JPY', 'AUD'] as const

export type SupportedCurrencyCode = (typeof SUPPORTED_CURRENCY_CODES)[number]

export const CURRENCY_OPTIONS: Array<{ value: SupportedCurrencyCode; label: string; locale: string }> = [
  { value: 'PHP', label: 'Philippine Peso (PHP)', locale: 'en-PH' },
  { value: 'USD', label: 'US Dollar (USD)', locale: 'en-US' },
  { value: 'EUR', label: 'Euro (EUR)', locale: 'en-IE' },
  { value: 'SGD', label: 'Singapore Dollar (SGD)', locale: 'en-SG' },
  { value: 'GBP', label: 'British Pound (GBP)', locale: 'en-GB' },
  { value: 'JPY', label: 'Japanese Yen (JPY)', locale: 'ja-JP' },
  { value: 'AUD', label: 'Australian Dollar (AUD)', locale: 'en-AU' }
]

function resolveCurrencyLocale(currencyCode: SupportedCurrencyCode) {
  return CURRENCY_OPTIONS.find(option => option.value === currencyCode)?.locale ?? 'en-PH'
}

export function createCurrencyFormatter(currencyCode: SupportedCurrencyCode = 'PHP') {
  return new Intl.NumberFormat(resolveCurrencyLocale(currencyCode), {
    style: 'currency',
    currency: currencyCode,
    currencySign: 'accounting',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

export function formatCurrencyAmount(value: number, currencyCode: SupportedCurrencyCode = 'PHP') {
  return createCurrencyFormatter(currencyCode).format(value)
}

// Default peso formatter used by existing accounting screens.
export const currencyFormatter = createCurrencyFormatter('PHP')
