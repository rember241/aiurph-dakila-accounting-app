'use client'

import { useEffect, useRef, useState } from 'react'

import Box from '@mui/material/Box'
import Typography from '@mui/material/Typography'

import { formatDocumentPageNumber } from '@components/app/Accounting/DocumentPageNumber'

import type { CompanySettingsRecord } from '@/features/(protected)/settings/model/settings.schema'

type DocumentPrintFooterProps = {
  company: CompanySettingsRecord | null | undefined
}

const A4_PRINTABLE_CONTENT_HEIGHT_MM = 267
const PAGE_NUMBER_TOP_OFFSET_MM = A4_PRINTABLE_CONTENT_HEIGHT_MM - 8

function setting<T>(value: T | null | undefined, fallback: T): T {
  return value ?? fallback
}

export default function DocumentPrintFooter({ company }: DocumentPrintFooterProps) {
  const anchorRef = useRef<HTMLDivElement | null>(null)
  const [pageCount, setPageCount] = useState(1)
  const footerText = company?.document_footer_text ?? ''
  const footerAlignment = setting(company?.document_footer_alignment, 'center')
  const showPageNumbers = company?.document_show_page_numbers ?? true

  useEffect(() => {
    const anchor = anchorRef.current

    if (!anchor) return

    const printableRoot = anchor.closest<HTMLElement>('.printable-report-frame, .document-print-root')

    if (!printableRoot) return

    const mmToPx = (mm: number) => mm * (96 / 25.4)

    const recompute = () => {
      const footerAndMarkerAllowancePx = mmToPx(18)
      const printablePageHeightPx = mmToPx(A4_PRINTABLE_CONTENT_HEIGHT_MM)
      const documentHeightPx = Math.max(printableRoot.scrollHeight - footerAndMarkerAllowancePx, 1)

      setPageCount(Math.max(1, Math.ceil(documentHeightPx / printablePageHeightPx)))
    }

    recompute()

    const onBeforePrint = () => recompute()
    const resizeObserver = new ResizeObserver(recompute)

    resizeObserver.observe(printableRoot)
    window.addEventListener('beforeprint', onBeforePrint)

    return () => {
      resizeObserver.disconnect()
      window.removeEventListener('beforeprint', onBeforePrint)
    }
  }, [])

  return (
    <>
      <Box ref={anchorRef} sx={{ display: 'none' }} />
      <Box
        className='document-print-footer'
        sx={{
          display: 'none',
          '@media print': {
            position: 'fixed',
            right: 0,
            bottom: 0,
            left: 0,
            display: 'grid',
            gridTemplateColumns: '1fr',
            gap: '8mm',
            alignItems: 'center',
            minHeight: '8mm',
            pt: '2mm',
            color: '#475569',
            background: '#fff',
            fontFamily: 'Arial, Helvetica, sans-serif'
          }
        }}
      >
        <Typography
          className='document-print-footer-text'
          sx={{
            fontSize: 9.5,
            lineHeight: 1.35,
            color: '#475569',
            textAlign: footerAlignment,
            whiteSpace: 'pre-wrap'
          }}
        >
          {footerText}
        </Typography>
      </Box>

      {showPageNumbers ? (
        <Box
          className='document-print-page-numbers'
          aria-hidden='true'
          sx={{
            display: 'none',
            '@media print': {
              position: 'absolute',
              top: 0,
              right: 0,
              left: 0,
              height: 0,
              display: 'block',
              overflow: 'visible',
              pointerEvents: 'none',
              fontFamily: 'Arial, Helvetica, sans-serif',
              zIndex: 2
            }
          }}
        >
          {Array.from({ length: pageCount }, (_, index) => (
            <Typography
              key={index}
              className='document-print-footer-page-number'
              sx={{
                position: 'absolute',
                top: `calc(${index} * ${A4_PRINTABLE_CONTENT_HEIGHT_MM}mm + ${PAGE_NUMBER_TOP_OFFSET_MM}mm)`,
                right: 0,
                m: 0,
                fontSize: 9.5,
                lineHeight: 1.35,
                color: '#475569',
                fontVariantNumeric: 'tabular-nums',
                whiteSpace: 'nowrap'
              }}
            >
              {formatDocumentPageNumber(index + 1, pageCount)}
            </Typography>
          ))}
        </Box>
      ) : null}
    </>
  )
}
