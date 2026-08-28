import { useEffect, useState } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

const queryClient = new QueryClient()

interface HealthResponse {
  status: string;
  database: string;
  project: string;
}

function MainScreen() {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetch('/api/v1/auth/me') // Just testing connection/proxy
      .then(() => {
        // Fallback check on raw health endpoint
        return fetch('/health')
      })
      .then((res) => {
        if (!res.ok) throw new Error('Backend returned status ' + res.status)
        return res.json()
      })
      .then((data: HealthResponse) => {
        setHealth(data)
        setLoading(false)
      })
      .catch((err) => {
        setError(err.message)
        setLoading(false)
      })
  }, [])

  return (
    <div className="min-h-screen flex flex-col items-center justify-center p-6">
      <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full border border-gray-100">
        <div className="flex items-center space-x-3 mb-6">
          <span className="text-3xl">⭕</span>
          <h1 className="text-2xl font-bold text-gray-900">GymCircle Web</h1>
        </div>

        <p className="text-gray-600 mb-6">
          Phase 0: Scaffolded successfully. React, Vite, TailwindCSS, and TanStack Query are active.
        </p>

        <div className="border-t border-gray-100 pt-6">
          <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">
            Backend Connection Status
          </h2>

          {loading && (
            <div className="flex items-center text-yellow-600 space-x-2">
              <div className="animate-spin rounded-full h-4 w-4 border-2 border-yellow-600 border-t-transparent"></div>
              <span>Checking connection...</span>
            </div>
          )}

          {error && (
            <div className="bg-red-50 text-red-700 p-3 rounded-md text-sm border border-red-100">
              <p className="font-semibold">Disconnected</p>
              <p className="text-xs mt-1">{error}</p>
            </div>
          )}

          {health && (
            <div className="space-y-2">
              <div className="flex justify-between items-center text-sm">
                <span className="text-gray-500">API Status:</span>
                <span className="font-semibold text-green-600 capitalize">{health.status}</span>
              </div>
              <div className="flex justify-between items-center text-sm">
                <span className="text-gray-500">Database connection:</span>
                <span className="font-semibold text-green-600 capitalize">{health.database}</span>
              </div>
              <div className="flex justify-between items-center text-sm">
                <span className="text-gray-500">SaaS Project:</span>
                <span className="font-semibold text-gray-800">{health.project}</span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <MainScreen />
    </QueryClientProvider>
  )
}
