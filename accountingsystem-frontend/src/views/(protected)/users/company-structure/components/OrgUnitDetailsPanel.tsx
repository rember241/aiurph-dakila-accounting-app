import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import {
  canDeleteOrgUnit,
  getOrgUnitDeleteGuardReason,
  getOrgUnitStatusLabel,
  getOrgUnitTypeLabel
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type { CompanyStructureRow } from '@/features/(protected)/users/company-structure/model/company-structure.types'

type OrgUnitDetailsPanelProps = {
  orgUnit: CompanyStructureRow | null
  canCreate: boolean
  canEdit: boolean
  canDelete: boolean
  onCreateRoot: () => void
  onCreateChild: (orgUnit: CompanyStructureRow) => void
  onEdit: (orgUnit: CompanyStructureRow) => void
  onToggleStatus: (orgUnit: CompanyStructureRow) => void
  onDelete: (orgUnit: CompanyStructureRow) => void
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <Stack spacing={0.35}>
      <Typography variant='caption' color='text.secondary'>
        {label}
      </Typography>
      <Typography variant='body2' fontWeight={600}>
        {value}
      </Typography>
    </Stack>
  )
}

function OrgUnitDetailsPanel({
  orgUnit,
  canCreate,
  canEdit,
  canDelete,
  onCreateRoot,
  onCreateChild,
  onEdit,
  onToggleStatus,
  onDelete
}: OrgUnitDetailsPanelProps) {
  if (!orgUnit) {
    return (
      <Box
        sx={{
          p: 3.5,
          height: '100%',
          borderLeft: theme => ({ lg: `1px solid ${theme.palette.divider}` })
        }}
      >
        <Stack spacing={2.5}>
          <Box>
            <Typography variant='h5'>Org Unit Details</Typography>
            <Typography variant='body2' color='text.secondary'>
              Select a unit from the hierarchy or table to review its details.
            </Typography>
          </Box>

          {canCreate ? (
            <Button variant='contained' onClick={onCreateRoot} startIcon={<i className='bx bx-plus' />}>
              Create Org Unit
            </Button>
          ) : null}
        </Stack>
      </Box>
    )
  }

  const deleteGuardReason = getOrgUnitDeleteGuardReason(orgUnit)
  const isDeletable = canDeleteOrgUnit(orgUnit)

  return (
    <Box
      sx={{
        p: 8,
        height: '100%',
        borderLeft: theme => ({ lg: `1px solid ${theme.palette.divider}` })
      }}
    >
      <Stack spacing={2.5} sx={{ height: '100%' }}>
        <Stack spacing={1}>
          <Typography variant='h5'>{orgUnit.name}</Typography>
          <Stack direction='row' spacing={1} flexWrap='wrap' useFlexGap>
            <Chip size='small' color='primary' variant='tonal' label={getOrgUnitTypeLabel(orgUnit.type)} />
            <Chip
              size='small'
              color={orgUnit.status === 'active' ? 'success' : 'default'}
              variant='tonal'
              label={getOrgUnitStatusLabel(orgUnit.status)}
            />
            <Chip size='small' variant='outlined' label={orgUnit.code} />
          </Stack>
        </Stack>

        <Divider />

        <Grid container spacing={2.5}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Reports To' value={orgUnit.parentName ?? 'Top level company unit'} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Sub-units' value={String(orgUnit.directChildrenCount)} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Assigned Members' value={String(orgUnit.membersCount)} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Linked Roles' value={String(orgUnit.linkedRolesCount)} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Location' value={orgUnit.location ?? 'Not set'} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Field label='Code' value={orgUnit.code} />
          </Grid>
          <Grid size={{ xs: 12 }}>
            <Field label='Description' value={orgUnit.description ?? 'No description added'} />
          </Grid>
        </Grid>

        {canCreate || canEdit || canDelete ? (
          <>
            <Divider />

            <Stack spacing={1.5}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} flexWrap='wrap' useFlexGap>
                {canCreate && orgUnit.type !== 'team' ? (
                  <Button
                    variant='contained'
                    startIcon={<i className='bx bx-plus' />}
                    onClick={() => onCreateChild(orgUnit)}
                    size='small'
                  >
                    Add Child Unit
                  </Button>
                ) : null}
                {canEdit ? (
                  <>
                    <Button
                      variant='outlined'
                      startIcon={<i className='bx bx-pencil' />}
                      onClick={() => onEdit(orgUnit)}
                      size='small'
                    >
                      Edit
                    </Button>
                    <Button
                      variant='text'
                      color={orgUnit.status === 'active' ? 'error' : 'success'}
                      startIcon={
                        <i className={`bx ${orgUnit.status === 'active' ? 'bx-lock-alt' : 'bx-lock-open-alt'}`} />
                      }
                      onClick={() => onToggleStatus(orgUnit)}
                      size='small'
                    >
                      {orgUnit.status === 'active' ? 'Deactivate' : 'Activate'}
                    </Button>
                  </>
                ) : null}
                {canDelete ? (
                  <Button
                    variant='text'
                    color='error'
                    startIcon={<i className='bx bx-trash' />}
                    onClick={() => onDelete(orgUnit)}
                    disabled={!isDeletable}
                    size='small'
                  >
                    Delete
                  </Button>
                ) : null}
              </Stack>

              {canDelete && !isDeletable && deleteGuardReason ? (
                <Typography variant='caption' color='text.secondary'>
                  {deleteGuardReason}
                </Typography>
              ) : null}
            </Stack>
          </>
        ) : null}
      </Stack>
    </Box>
  )
}

export default OrgUnitDetailsPanel
