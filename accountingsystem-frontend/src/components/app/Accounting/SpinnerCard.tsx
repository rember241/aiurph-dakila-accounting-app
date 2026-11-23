import { CircularProgress, Paper, Stack } from '@mui/material'

/**
 * Simple spinner component wrapped in a Paper component
 */
function SpinnerCard() {
  return (
    <Paper>
      <Stack sx={{ py: 25 }} alignItems='center'>
        <CircularProgress color='primary' />
      </Stack>
    </Paper>
  )
}

export default SpinnerCard
