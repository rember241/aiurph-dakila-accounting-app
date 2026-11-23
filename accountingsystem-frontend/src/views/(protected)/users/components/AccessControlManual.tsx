import Accordion from '@mui/material/Accordion'
import AccordionDetails from '@mui/material/AccordionDetails'
import AccordionSummary from '@mui/material/AccordionSummary'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

const MANUAL_STEPS = [
  'Go to Roles and configure access level first.',
  'Choose permission scope: All Pages for global control, or Per Page for module-by-module control.',
  'Adjust module checkboxes under Detailed Permissions.',
  'Use a non-owner user to test restrictions. Company owners still have full access, but the Admin role now follows its selected module permissions.',
  'Keep role status as Active before assigning it to users.',
  'Go to User Management and assign the correct role to each user.',
  'Use user status only for account activation or suspension.',
  'Open the user profile panel to verify the effective access inherited from the role.'
] as const

type AccessControlManualProps = {
  defaultExpanded?: boolean
}

function AccessControlManual({ defaultExpanded = false }: AccessControlManualProps) {
  return (
    <Paper sx={{ mb: 3 }}>
      <Accordion defaultExpanded={defaultExpanded} disableGutters sx={{ boxShadow: 'none' }}>
        <AccordionSummary
          expandIcon={<i className='bx bx-chevron-down' style={{ fontSize: 18 }} />}
          aria-controls='users-access-manual-content'
          id='users-access-manual-header'
          sx={{ px: 5, py: 1 }}
        >
          <Stack direction='row' spacing={1.2} alignItems='center'>
            <i className='bx bx-book-content' style={{ fontSize: 20 }} />
            <Typography variant='h5'>Access Control Manual</Typography>
          </Stack>
        </AccordionSummary>

        <AccordionDetails sx={{ px: 5, pb: 5, pt: 0 }}>
          <Stack spacing={2}>
            <Alert severity='info' icon={false} sx={{ alignItems: 'center' }} variant='outlined'>
              <Stack direction='row' spacing={2} alignItems='center'>
                <Box component='i' className={'bx-error-circle'} sx={{ fontSize: 20 }} />

                <Stack direction='row' spacing={2} alignItems='center'>
                  <Box component='span' sx={{ fontWeight: 'bold' }}>
                    Best practice:
                  </Box>
                  <Box component='span'>Manage permission checkboxes in Roles, then assign those roles to users.</Box>
                </Stack>
              </Stack>
            </Alert>

            <Box component='ol' sx={{ m: 0, pl: 3 }}>
              {MANUAL_STEPS.map(step => (
                <Typography key={step} component='li' variant='body2' color='text.secondary' sx={{ mb: 0.8 }}>
                  {step}
                </Typography>
              ))}
            </Box>
          </Stack>
        </AccordionDetails>
      </Accordion>
    </Paper>
  )
}

export default AccessControlManual
