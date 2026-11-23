'use client'

import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import useMediaQuery from '@mui/material/useMediaQuery'
import { useTheme } from '@mui/material/styles'

export type OnboardingWizardStep = {
  id: string
  title: string
}

type OnboardingWizardStepperProps = {
  activeStep: number
  steps: OnboardingWizardStep[]
}

function OnboardingWizardStepper({ activeStep, steps }: OnboardingWizardStepperProps) {
  const theme = useTheme()
  const isSmallScreen = useMediaQuery(theme.breakpoints.down('sm'))

  return (
    <Stack direction={isSmallScreen ? 'column' : 'row'} spacing={isSmallScreen ? 1.5 : 2} sx={{ width: '100%' }}>
      {steps.map((step, index) => {
        const isCompleted = index < activeStep
        const isActive = index === activeStep

        return (
          <Box key={step.id} sx={{ flex: 1, minWidth: 0 }}>
            <Stack direction='row' spacing={1} alignItems='center'>
              <Box
                sx={{
                  width: isActive ? 10 : 8,
                  height: isActive ? 10 : 8,
                  flexShrink: 0,
                  borderRadius: '50%',
                  bgcolor: isCompleted || isActive ? 'primary.main' : 'divider',
                  boxShadow: isActive ? theme => `0 0 0 4px ${theme.palette.primary.main}26` : 'none'
                }}
              />

              <Typography
                variant='caption'
                color={isActive || isCompleted ? 'text.primary' : 'text.secondary'}
                sx={{
                  fontWeight: isActive ? 700 : isCompleted ? 600 : 500,
                  whiteSpace: isSmallScreen ? 'normal' : 'nowrap',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis'
                }}
              >
                {step.title}
              </Typography>
            </Stack>
          </Box>
        )
      })}
    </Stack>
  )
}

export default OnboardingWizardStepper
