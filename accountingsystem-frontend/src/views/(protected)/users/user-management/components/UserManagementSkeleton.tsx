import Paper from '@mui/material/Paper'
import Skeleton from '@mui/material/Skeleton'
import Stack from '@mui/material/Stack'

function UserManagementSkeleton() {
  return (
    <Paper sx={{ p: 5 }}>
      <Stack spacing={2}>
        <Skeleton variant='rounded' height={44} />
        <Skeleton variant='rounded' height={44} />
        <Skeleton variant='rounded' height={44} />
        <Skeleton variant='rounded' height={44} />
        <Skeleton variant='rounded' height={44} />
        <Skeleton variant='rounded' height={44} />
      </Stack>
    </Paper>
  )
}

export default UserManagementSkeleton
