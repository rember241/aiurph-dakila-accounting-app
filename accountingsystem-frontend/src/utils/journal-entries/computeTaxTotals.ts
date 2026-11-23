import type { TaxColumn } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

// A single item from a journal entry
type JournalItem = {
  accountId: string
  description?: string | undefined
  taxId?: string | undefined
  debit?: unknown
  credit?: unknown
}

// The total amount of the debit and credit side grouped by tax
type TaxTotal = { id: string; name: string; rate: number; debit: number; credit: number }

/**
 * Computes the total debit and credit tax amounts for each unique tax
 * applied across all journal items, grouped and returned as an array
 *
 * @param journalItems - The list of journal items from the form
 * @param taxes - The list of available taxes to match against
 * @returns An array of tax totals with their corresponding debit and credit amounts
 */
export function computeTaxTotals(journalItems: JournalItem[], taxes: TaxColumn[]): TaxTotal[] {
  const totals = journalItems.reduce(
    (acc, item) => {
      // Ignore journal items without a tax applied
      if (!item.taxId) return acc

      // Find the tax details from the available taxes list
      const tax = taxes.find(t => t.id === item.taxId)

      // Ignore if the tax ID doesn't match any available tax
      if (!tax) return acc

      // Initialize the tax entry if it hasn't been encountered yet
      acc[item.taxId] ??= { id: tax.id, name: tax.name, rate: tax.rate, debit: 0, credit: 0 }

      const debit = Number(item.debit) || 0
      const credit = Number(item.credit) || 0

      // Accumulate the tax amounts for both the debit and credit sides
      acc[tax.id].debit += debit * (tax.rate * 0.01)
      acc[tax.id].credit += credit * (tax.rate * 0.01)

      return acc
    },
    {} as Record<string, TaxTotal>
  )

  // Convert the grouped record into an array
  return Object.values(totals)
}
