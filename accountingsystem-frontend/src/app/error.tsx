'use client'

export default function ErrorPage() {
  return (
    <main style={{ maxWidth: 560, margin: '15vh auto', padding: 24, fontFamily: 'sans-serif' }}>
      <h1>We couldn’t load this page</h1>
      <p>The server may be temporarily unavailable. Your session has not been cleared. Try again when the connection is restored.</p>
      <button onClick={() => window.location.reload()} style={{ padding: '12px 24px', cursor: 'pointer' }}>Try again</button>
    </main>
  )
}
