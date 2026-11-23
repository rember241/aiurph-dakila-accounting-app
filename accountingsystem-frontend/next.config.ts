import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Keep dependency and CSS resolution inside this frontend project.
  turbopack: { root: __dirname },
  basePath: process.env.BASEPATH,
  allowedDevOrigins: ['localhost', '127.0.0.1', '192.168.1.150'],

  async rewrites() {
    const backend = process.env.LARAVEL_API_URL ?? process.env.NEXT_PUBLIC_LARAVEL_API_URL

    if (!backend) throw new Error('Missing LARAVEL_API_URL')

    // Keep browser sessions on the app origin, including the CSRF bootstrap.
    // The destination is server configuration, never a URL supplied by a client.
    return [{ source: '/api/laravel/:path*', destination: `${backend.replace(/\/$/, '')}/:path*` }]
  },

  redirects: async () => {
    return [
      {
        source: '/',
        destination: '/home',
        permanent: true,
        locale: false
      }
    ]
  }
}

export default nextConfig
