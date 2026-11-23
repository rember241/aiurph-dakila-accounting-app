export type PrimaryColorConfig = {
  name?: string
  light?: string
  main: string
  dark?: string
}

// Primary color config object
const primaryColorConfig: PrimaryColorConfig[] = [
  {
    name: 'emerald-green',
    light: '#4C9582',
    main: '#1F7A63',
    dark: '#1C6E59'
  },
  {
    name: 'navy-blue',
    light: '#394957',
    main: '#081C2D',
    dark: '#071929'
  }
]

export default primaryColorConfig
