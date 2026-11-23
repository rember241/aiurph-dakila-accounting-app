import { Divider, Stack, Typography } from '@mui/material'

import type { Decimal as DecimalType } from 'decimal.js'

import { default as DecimalClass } from 'decimal.js'

import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

import type { AccountTransaction } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Props to pass into this functional component
interface TAccountsViewProps {
  totals: {
    debit: DecimalType
    credit: DecimalType
  } // An object that contains the computed debits and credits
  transactionData: AccountTransaction[]
}

function TAccountsView({ totals, transactionData }: TAccountsViewProps) {
  return (
    <>
      {/* Debit and Credit Header */}
      <Stack sx={{ mb: 3 }} direction='row' alignItems='center'>
        <Stack sx={{ flex: 1 }} alignItems='center'>
          <Typography variant='subtitle1' fontWeight='bold'>
            DEBIT
          </Typography>
        </Stack>
        <Stack sx={{ flex: 1 }} alignItems='center'>
          <Typography variant='subtitle1' fontWeight='bold'>
            CREDIT
          </Typography>
        </Stack>
      </Stack>

      <Divider />

      {/* Transaction Amounts */}
      <Stack gap={6} sx={{ my: 3, px: 3 }} direction='row' alignItems='center'>
        {/* Debit Side */}
        <Stack gap={3} sx={{ flex: 1 }} alignItems='center'>
          {transactionData.map(transaction => (
            <Stack
              key={`${transaction.id}-debit`}
              sx={{ width: '100%' }}
              direction='row'
              justifyContent='space-between'
            >
              {transaction.debit === 0 ? (
                <br />
              ) : (
                <>
                  <Typography variant='subtitle2'>
                    {transaction.debit === 0
                      ? ''
                      : `(${transaction.journalNumber}) ${transaction.description.length ? transaction.description : '-'}`}
                  </Typography>
                  <Typography variant='subtitle2'>
                    {transaction.debit === 0
                      ? ''
                      : currencyFormatter.format(new DecimalClass(transaction.debit).toNumber())}
                  </Typography>
                </>
              )}
            </Stack>
          ))}
        </Stack>

        <Divider orientation='vertical' flexItem />

        {/* Credit Side */}
        <Stack gap={3} sx={{ flex: 1 }} alignItems='center'>
          {transactionData.map(transaction => (
            <Stack
              key={`${transaction.id}-credit`}
              sx={{ width: '100%' }}
              direction='row'
              justifyContent='space-between'
            >
              {transaction.credit === 0 ? (
                <br />
              ) : (
                <>
                  <Typography variant='subtitle2'>
                    {transaction.credit === 0
                      ? ''
                      : `(${transaction.journalNumber}) ${transaction.description.length ? transaction.description : '-'}`}
                  </Typography>
                  <Typography variant='subtitle2'>
                    {transaction.credit === 0
                      ? ''
                      : currencyFormatter.format(new DecimalClass(transaction.credit).toNumber())}
                  </Typography>
                </>
              )}
            </Stack>
          ))}
        </Stack>
      </Stack>

      <Divider />

      {/* Footer */}
      <Stack sx={{ mt: 3, px: 3 }} direction='row' alignItems='center'>
        <Stack sx={{ flex: 1, pr: 6 }} direction='row' alignItems='center' justifyContent='space-between'>
          <Typography variant='subtitle2'>TOTAL DEBITS</Typography>
          <Typography variant='subtitle2'>{currencyFormatter.format(totals.debit.toNumber())}</Typography>
        </Stack>

        <Stack sx={{ flex: 1, pl: 6 }} direction='row' alignItems='center' justifyContent='space-between'>
          <Typography variant='subtitle2'>TOTAL CREDITS</Typography>
          <Typography variant='subtitle2'>{currencyFormatter.format(totals.credit.toNumber())}</Typography>
        </Stack>
      </Stack>
    </>
  )
}

export default TAccountsView
