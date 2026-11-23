import Accordion from '@mui/material/Accordion'
import AccordionDetails from '@mui/material/AccordionDetails'
import AccordionSummary from '@mui/material/AccordionSummary'
import Box from '@mui/material/Box'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Typography from '@mui/material/Typography'

const PERMISSION_TYPES = [
  {
    permission: 'Select',
    description: 'Allows the user to search and see records.',
    example: 'The user can select a Customer in Sales Order but cannot open the Customer master.'
  },
  {
    permission: 'Read',
    description: 'Allows the user to view the document.',
    example: 'The user can view Sales Invoices but cannot modify any field values in them.'
  },
  {
    permission: 'Write',
    description: 'Allows the user to edit existing records they have access to.',
    example:
      'The user can update a customer or any other fields in an existing Sales Order but cannot create a new Sales Order.'
  },
  {
    permission: 'Create',
    description: 'Allows the user to create new documents.',
    example: 'The user can create a new Item but cannot edit existing items.'
  },
  {
    permission: 'Delete',
    description: 'Allows the user to delete documents.',
    example: 'The user can delete Draft / Cancelled documents.'
  },
  {
    permission: 'Print',
    description: 'Allows printing or PDF download of documents.',
    example: 'The print button is enabled for the user in the document.'
  },
  {
    permission: 'Email',
    description: 'Allows the user to email from the document.',
    example: 'The email button is enabled for the user in the document.'
  },
  {
    permission: 'Report',
    description: 'Allows the user to access reports related to the document.',
    example: 'If the user has access to Employee and Report is enabled, they can view Employee-based reports.'
  },
  {
    permission: 'Export',
    description: 'Allows the user to export data from the Report view.',
    example: 'The user can export report data.'
  },
  {
    permission: 'Import',
    description: 'Allows the user to use Data Import tool to create / update records.',
    example: 'The user can import new records or update existing data for the document.'
  },
  {
    permission: 'Share',
    description: 'Allows sharing document access with other users.',
    example: 'The user can share document access with another user.'
  }
] as const

const PERMISSION_LEVEL_NOTES = [
  'Permissions at level 0 are document-level permissions and are primary for document access.',
  'If a role does not have access at level 0, higher levels have no effect.',
  'Permissions at higher levels are field-level permissions. This is useful when fields need to be hidden or read-only by role.',
  'You can use Customize Form to set permission levels at field level (Setup > Customize Form).'
] as const

const USER_PERMISSION_NOTES = [
  'User Permissions are used to limit users to specific records (Setup > User Permissions).',
  'Select document types to define where User Permissions are applied.',
  'Once enabled, users can only access linked documents where the permission link exists (for example Blog Post linked to Blogger).',
  'Roles with Set User Permissions can define User Permissions for other users on that document type.'
] as const

type PermissionsReferenceProps = {
  defaultExpanded?: boolean
}

function PermissionsReference({ defaultExpanded = false }: PermissionsReferenceProps) {
  return (
    <Paper sx={{ mb: 3 }}>
      <Accordion defaultExpanded={defaultExpanded} disableGutters sx={{ boxShadow: 'none' }}>
        <AccordionSummary
          expandIcon={<i className='bx bx-chevron-down' style={{ fontSize: 18 }} />}
          aria-controls='users-permissions-reference-content'
          id='users-permissions-reference-header'
          sx={{ px: 5, py: 1 }}
        >
          <Stack direction='row' spacing={1.2} alignItems='center'>
            <i className='bx bx-shield-quarter' style={{ fontSize: 20 }} />
            <Typography variant='h5'>Permission Reference</Typography>
          </Stack>
        </AccordionSummary>

        <AccordionDetails sx={{ px: 5, pb: 5, pt: 0 }}>
          <Stack spacing={3}>
            <Typography variant='subtitle1' sx={{ fontWeight: 600 }}>
              Meaning of Different Permission Types
            </Typography>

            <TableContainer>
              <Table size='small' sx={{ minWidth: 980 }}>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ fontWeight: 700, width: 140 }}>Permission</TableCell>
                    <TableCell sx={{ fontWeight: 700, width: 380 }}>Description</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Example</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {PERMISSION_TYPES.map(item => (
                    <TableRow key={item.permission}>
                      <TableCell>{item.permission}</TableCell>
                      <TableCell>{item.description}</TableCell>
                      <TableCell>{item.example}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>

            <Box>
              <Typography variant='subtitle1' sx={{ fontWeight: 600, mb: 1 }}>
                Permission Levels
              </Typography>
              <Box component='ol' sx={{ m: 0, pl: 3 }}>
                {PERMISSION_LEVEL_NOTES.map(note => (
                  <Typography key={note} component='li' variant='body2' color='text.secondary' sx={{ mb: 0.6 }}>
                    {note}
                  </Typography>
                ))}
              </Box>
            </Box>

            <Box>
              <Typography variant='subtitle1' sx={{ fontWeight: 600, mb: 1 }}>
                User Permissions
              </Typography>
              <Box component='ol' sx={{ m: 0, pl: 3 }}>
                {USER_PERMISSION_NOTES.map(note => (
                  <Typography key={note} component='li' variant='body2' color='text.secondary' sx={{ mb: 0.6 }}>
                    {note}
                  </Typography>
                ))}
              </Box>
            </Box>
          </Stack>
        </AccordionDetails>
      </Accordion>
    </Paper>
  )
}

export default PermissionsReference
