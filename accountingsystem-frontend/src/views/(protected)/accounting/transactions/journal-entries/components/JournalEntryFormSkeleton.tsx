import { Button, Paper, Skeleton, Stack, TextField, Typography } from '@mui/material'

function JournalEntryFormSkeleton() {
  return (
    <Stack spacing={5} onSubmit={event => event.preventDefault()} component='form' alignItems='stretch' justifyContent='flex-start'>
      <Paper sx={{ p: 4, gap: 8, display: 'flex', justifyContent: 'space-between' }}>
        <Stack spacing={4} sx={{ flex: 1 }}>
          <Stack spacing={2}>
            <Skeleton>
              <Typography>Journal #</Typography>
            </Skeleton>
            <Stack
              direction='row'
              sx={{ gap: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
            >
              <Skeleton>
                <TextField />
              </Skeleton>
              <Skeleton>
                <TextField />
              </Skeleton>
            </Stack>
          </Stack>

          <Stack spacing={2}>
            <Skeleton>
              <Typography>Entry Date</Typography>
            </Skeleton>
            <Skeleton width={'full'}>
              <TextField />
            </Skeleton>
          </Stack>

          <Stack spacing={2}>
            <Skeleton>
              <Typography>Journal Type</Typography>
            </Skeleton>
            <Skeleton width={'full'}>
              <TextField />
            </Skeleton>
          </Stack>

          <Stack spacing={2}>
            <Skeleton>
              <Typography>Fund Source</Typography>
            </Skeleton>
            <Skeleton width={'full'}>
              <TextField />
            </Skeleton>
          </Stack>

          <Stack spacing={2}>
            <Skeleton>
              <Typography>Reference #</Typography>
            </Skeleton>
            <Skeleton width={'full'}>
              <TextField />
            </Skeleton>
          </Stack>
        </Stack>

        <Stack spacing={2} sx={{ flex: 2 }}>
          <Skeleton>
            <Typography>Note</Typography>
          </Skeleton>
          <Skeleton width={'full'}>
            <TextField />
          </Skeleton>
        </Stack>
      </Paper>

      <Stack spacing={2} alignItems='stretch' justifyContent='flex-start'>
        <Stack direction='row' alignItems='center' justifyContent='flex-start'>
          <Skeleton>
            <Button size='large'>Add New Row</Button>
          </Skeleton>
        </Stack>

        <Paper sx={{ p: 4 }}>
          <Stack spacing={4}>
            <Skeleton variant='rectangular' width={'full'} height={50} />
            <Skeleton variant='rectangular' width={'full'} height={50} />
            <Skeleton variant='rectangular' width={'full'} height={50} />
            <Skeleton variant='rectangular' width={'full'} height={50} />
          </Stack>
        </Paper>

        <Stack spacing={2} direction='row' alignItems='center' justifyContent='flex-start'>
          <Skeleton>
            <Button size='large'>Post Entry</Button>
          </Skeleton>
          <Skeleton>
            <Button size='large'>Save as Draft</Button>
          </Skeleton>
          <Skeleton>
            <Button size='large'>Cancel</Button>
          </Skeleton>
        </Stack>
      </Stack>
    </Stack>
  )
}

export default JournalEntryFormSkeleton
