'use client'

import type { ReactNode } from 'react'

import Box from '@mui/material/Box'
import GlobalStyles from '@mui/material/GlobalStyles'

import DocumentPrintFooter from '@components/app/Accounting/DocumentPrintFooter'
import DocumentPrintHeader from '@components/app/Accounting/DocumentPrintHeader'
import type { CompanySettingsRecord } from '@/features/(protected)/settings/model/settings.schema'

type PrintableReportFrameProps = {
  company: CompanySettingsRecord | null | undefined
  fallbackCompanyName?: string
  documentTitle: string
  subtitle?: string
  children: ReactNode
}

export default function PrintableReportFrame({
  company,
  fallbackCompanyName,
  documentTitle,
  subtitle,
  children
}: PrintableReportFrameProps) {
  return (
    <>
      <GlobalStyles
        styles={{
          '@page': {
            size: 'A4 portrait',
            margin: '12mm 14mm 18mm'
          },
          '@media print': {
            '.printable-report-frame, .printable-report-frame *': {
              printColorAdjust: 'exact !important',
              WebkitPrintColorAdjust: 'exact !important',
              visibility: 'visible'
            },
            html: {
              background: '#fff !important',
              backgroundColor: '#fff !important'
            },
            body: {
              background: '#fff !important',
              backgroundColor: '#fff !important',
              overflow: 'visible !important',
              margin: '0 !important',
              padding: '0 !important'
            },
            'body *': {
              visibility: 'hidden'
            },
            '.printable-report-frame': {
              position: 'absolute',
              inset: 0,
              width: '100%',
              minHeight: '100%',
              padding: '0 0 12mm',
              background: '#fff !important',
              backgroundColor: '#fff !important',
              color: '#111827 !important',
              colorScheme: 'light',
              fontFamily: 'Arial, Helvetica, sans-serif'
            },
            '.printable-report-frame .MuiPaper-root': {
              background: '#fff !important',
              backgroundColor: '#fff !important',
              color: '#111827 !important',
              width: '100% !important',
              maxWidth: '100% !important',
              boxShadow: 'none !important',
              borderRadius: '0 !important',
              padding: '0 !important'
            },
            '.printable-report-frame .MuiStack-root': {
              background: 'transparent !important',
              color: '#111827 !important'
            },
            '.printable-report-frame > .MuiPaper-root > .MuiStack-root, .printable-report-frame .MuiPaper-root > .MuiStack-root, .printable-report-frame .MuiPaper-root > .MuiStack-root > .MuiStack-root': {
              width: '100% !important',
              maxWidth: '100% !important',
              alignItems: 'stretch !important'
            },
            '.printable-report-frame .MuiPaper-root > .MuiStack-root': {
              gap: '10mm !important'
            },
            '.printable-report-frame .MuiTypography-root, .printable-report-frame .MuiTableCell-root, .printable-report-frame .MuiLink-root': {
              color: '#111827 !important'
            },
            '.printable-report-frame .MuiLink-root': {
              textDecorationColor: '#111827 !important'
            },
            '.printable-report-frame .MuiChip-root, .printable-report-frame i': {
              display: 'none !important'
            },
            '.printable-report-frame .report-screen-heading': {
              display: 'none !important'
            },
            '.printable-report-frame table': {
              width: '100% !important',
              borderCollapse: 'collapse !important',
              background: '#fff !important'
            },
            '.printable-report-frame .MuiTable-root': {
              background: '#fff !important'
            },
            '.printable-report-frame .MuiTableCell-root': {
              borderColor: '#111827 !important',
              borderBottom: '1px solid #d1d5db !important',
              fontSize: '11px !important',
              lineHeight: '1.25 !important',
              padding: '4px 8px !important',
              background: '#fff !important'
            },
            '.printable-report-frame .MuiTableHead-root .MuiTableCell-root': {
              background: '#fff !important',
              backgroundColor: '#fff !important',
              color: '#111827 !important',
              fontWeight: '700 !important',
              borderTop: '2px solid #111827 !important',
              borderBottom: '2px solid #111827 !important'
            },
            '.printable-report-frame .MuiTableBody-root > .MuiTableRow-root:first-of-type .MuiTableCell-root': {
              borderTop: '0 !important',
              borderBottom: '2px solid #111827 !important',
              fontWeight: '700 !important'
            },
            '.printable-report-frame .MuiTableRow-root': {
              breakInside: 'avoid',
              pageBreakInside: 'avoid'
            },
            '.printable-report-frame .MuiTableCell-root[align="right"], .printable-report-frame .MuiTableCell-alignRight': {
              textAlign: 'right !important'
            },
            '.printable-report-frame .MuiTableCell-root[colspan]': {
              fontWeight: '700 !important'
            }
          }
        }}
      />
      <Box
        className='printable-report-frame'
        sx={{
          '@media print': {
            bgcolor: '#fff !important',
            color: '#111827 !important',
            p: 0,
            '& .MuiPaper-root': {
              bgcolor: '#fff !important',
              color: '#111827 !important',
              boxShadow: 'none !important'
            }
          }
        }}
      >
        <DocumentPrintHeader
          company={company}
          fallbackCompanyName={fallbackCompanyName}
          documentTitle={documentTitle}
          subtitle={subtitle}
        />
        {children}
        <DocumentPrintFooter company={company} />
      </Box>
    </>
  )
}
