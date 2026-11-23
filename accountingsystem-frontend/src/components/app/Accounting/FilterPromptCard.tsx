import { Paper, Stack, Typography } from '@mui/material'

// Props for this functional component
interface CardProps {
  mainText: string
  subText: string
}

function FilterPromptCard({ mainText, subText }: CardProps) {
  return (
    <Paper sx={{ p: 5 }}>
      <Stack sx={{ m: 20 }} alignItems='center' justifyContent='center'>
        <Typography variant='h5'>{mainText}</Typography>
        <Typography variant='subtitle1'>{subText}</Typography>
      </Stack>
    </Paper>
  )
}

export default FilterPromptCard
