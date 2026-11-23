import React from 'react'

// MUI Imports
import Typography from '@mui/material/Typography'
import LinearProgress from '@mui/material/LinearProgress'
import type { LinearProgressProps } from '@mui/material/LinearProgress'

const LinearProgressWithLabel = (props: LinearProgressProps & { value: number }) => {
  return (
    <div className='flex items-center gap-3'>
      <div className='is-full'>
        <LinearProgress variant='determinate' {...props} />
      </div>
      <Typography
        variant='body2'
        color='text.secondary'
        className='font-medium'
      >{`${Math.round(props.value)}%`}</Typography>
    </div>
  )
}

export default LinearProgressWithLabel
