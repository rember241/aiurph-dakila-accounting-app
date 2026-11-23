'use client'

import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import type { CompanySettingsRecord } from '@/features/(protected)/settings/model/settings.schema'

const DEFAULT_PRIMARY_COLOR = '#3949ab'
const DEFAULT_ACCENT_COLOR = '#64748b'

type DocumentPrintHeaderProps = {
  company: CompanySettingsRecord | null | undefined
  fallbackCompanyName?: string
  documentTitle: string
  subtitle?: string
}

function setting<T>(value: T | null | undefined, fallback: T): T {
  return value ?? fallback
}

export default function DocumentPrintHeader({
  company,
  fallbackCompanyName,
  documentTitle,
  subtitle
}: DocumentPrintHeaderProps) {
  const primaryColor = company?.invoice_primary_color ?? DEFAULT_PRIMARY_COLOR
  const accentColor = company?.document_accent_color ?? DEFAULT_ACCENT_COLOR
  const logoPosition = setting(company?.document_logo_position, 'left')
  const alignment = setting(company?.document_header_alignment, 'left')
  const style = setting(company?.document_header_style, 'standard')
  const showLogo = setting(company?.document_show_company_logo, true) && Boolean(company?.logo_url)
  const showCompanyName = setting(company?.document_show_company_name, true)
  const showAddress = setting(company?.document_show_company_address, true)
  const showContact = setting(company?.document_show_company_contact, true)
  const showDocumentTitle = setting(company?.document_show_document_title, true)
  const companyName = company?.display_name || company?.name || fallbackCompanyName || ''
  const contact = [company?.email, company?.phone].filter(Boolean).join(' · ')
  const compact = style === 'compact'
  const formal = style === 'formal'
  const logoSize = compact ? 44 : formal ? 68 : 56

  return (
    <Box
      className='document-print-header'
      sx={{
        display: 'none',
        '@media print': {
          display: 'block',
          color: '#111827',
          mb: compact ? 3 : 4,
          pb: compact ? 2 : 3,
          borderBottom: `2px solid ${primaryColor}`
        }
      }}
    >
      <Stack
        direction={logoPosition === 'center' ? 'column' : 'row'}
        spacing={2}
        alignItems={logoPosition === 'center' ? 'center' : 'flex-start'}
        justifyContent='space-between'
        sx={{ textAlign: logoPosition === 'center' ? 'center' : alignment }}
      >
        {showLogo && logoPosition !== 'right' ? (
          <Box
            component='img'
            src={company?.logo_url ?? undefined}
            alt={`${companyName} logo`}
            sx={{ width: logoSize, maxHeight: logoSize, objectFit: 'contain', flex: '0 0 auto' }}
          />
        ) : null}

        <Box sx={{ flex: 1 }}>
          {showCompanyName ? (
            <Typography sx={{ fontSize: formal ? 20 : 18, lineHeight: 1.2, fontWeight: 700, color: primaryColor }}>
              {companyName}
            </Typography>
          ) : null}

          {showAddress && company?.address ? (
            <Typography sx={{ mt: 0.4, fontSize: 10.5, whiteSpace: 'pre-line', color: '#475569' }}>
              {company.address}
            </Typography>
          ) : null}

          {showContact && contact ? (
            <Typography sx={{ mt: 0.4, fontSize: 10.5, color: '#475569' }}>{contact}</Typography>
          ) : null}
        </Box>

        {showLogo && logoPosition === 'right' ? (
          <Box
            component='img'
            src={company?.logo_url ?? undefined}
            alt={`${companyName} logo`}
            sx={{ width: logoSize, maxHeight: logoSize, objectFit: 'contain', flex: '0 0 auto' }}
          />
        ) : null}
      </Stack>

      {showDocumentTitle ? (
        <Box
          sx={{
            mt: compact ? 1.5 : 2.5,
            pt: compact ? 1 : 1.5,
            borderTop: `1px solid ${accentColor}55`,
            textAlign: alignment
          }}
        >
          <Typography sx={{ fontSize: compact ? 16 : 18, fontWeight: 800, letterSpacing: formal ? '0.08em' : '0.03em' }}>
            {documentTitle}
          </Typography>
          {subtitle ? (
            <Typography sx={{ mt: 0.35, fontSize: 10.5, color: '#64748b' }}>{subtitle}</Typography>
          ) : null}
        </Box>
      ) : null}
    </Box>
  )
}
