import { Box, Button, Paper, Stack, Typography } from '@mui/material'

// Props for this functional component
interface CardProps {
  icon: string
  mainText: string
  subText: string
  buttonText: string
  buttonAction: () => void
}

/**
 * Simple paper component to prompt a user to do an action
 */
function PromptUserCard({ icon, mainText, subText, buttonText, buttonAction }: CardProps) {
  return (
    <Paper sx={{ py: 10 }}>
      <Stack gap={2.5} alignItems='center'>
        {/* Icon */}
        <Box
          sx={{
            width: 64,
            height: 64,
            display: 'grid',
            placeItems: 'center',
            borderRadius: 3,
            color: 'primary.main',
            bgcolor: 'action.hover'
          }}
        >
          <i className={icon} style={{ fontSize: 30 }} />
        </Box>

        {/* Text */}
        <Stack gap={1} alignItems='center'>
          <Typography variant='h5'>{mainText}</Typography>
          <Typography variant='body2' color='text.secondary'>
            {subText}
          </Typography>
        </Stack>

        {/* Action Button */}
        <Button variant='contained' onClick={buttonAction}>
          {buttonText}
        </Button>
      </Stack>
    </Paper>
  )
}

export default PromptUserCard
