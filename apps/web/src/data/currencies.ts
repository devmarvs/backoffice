type CurrencyOption = {
  code: string
  label: string
}

type IntlWithValues = typeof Intl & {
  supportedValuesOf?: (key: string) => string[]
}

const COMMON_CODES = [
  'USD',
  'EUR',
  'GBP',
  'CAD',
  'AUD',
  'NZD',
  'CHF',
  'SEK',
  'NOK',
  'DKK',
  'JPY',
  'SGD',
  'HKD',
  'CNY',
  'INR',
  'IDR',
  'MYR',
  'THB',
  'KRW',
  'AED',
  'SAR',
  'ZAR',
  'MXN',
  'BRL',
  'PHP',
]

const buildOptions = (codes: string[]): CurrencyOption[] => {
  const displayNames =
    typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function'
      ? new Intl.DisplayNames(['en'], { type: 'currency' })
      : null

  const uniqueCodes = Array.from(new Set(codes.map((code) => code.toUpperCase()))).sort()

  return uniqueCodes.map((code) => {
    const name = displayNames?.of(code)
    return {
      code,
      label: name ? `${code} - ${name}` : code,
    }
  })
}

const getCurrencyOptions = (): CurrencyOption[] => {
  const intlWithValues = Intl as IntlWithValues
  if (typeof intlWithValues.supportedValuesOf === 'function') {
    try {
      const supported = new Set(
        intlWithValues.supportedValuesOf('currency').map((code) => code.toUpperCase())
      )
      const filtered = COMMON_CODES.filter((code) => supported.has(code))
      if (filtered.length > 0) {
        return buildOptions(filtered)
      }
    } catch {
      // Fall through to fallback list.
    }
  }

  return buildOptions(COMMON_CODES)
}

export const CURRENCIES = getCurrencyOptions()
