'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'

export default function Home() {
  const router = useRouter()
  const [user, setUser] = useState<{ name?: string; email?: string } | null>(null)

  useEffect(() => {
    const storedUser = localStorage.getItem('user')
    if (storedUser) {
      setUser(JSON.parse(storedUser))
    }
  }, [])

  const handleLogout = async () => {
    try {
      await fetch('/api/v1/logout', { method: 'POST' })
    } catch (e) {
      console.error('Logout error:', e)
    } finally {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      document.cookie = 'token=; path=/; max-age=0'
      router.push('/auth/login')
    }
  }

  return (
    <div className="flex flex-col flex-1 items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex flex-1 w-full max-w-3xl flex-col items-center justify-between py-32 px-16 bg-white dark:bg-black sm:items-start">
        <div className="flex flex-col items-center gap-6 text-center sm:items-start sm:text-left w-full">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white">
            Welcome{user?.name ? `, ${user.name}` : ''}!
          </h1>

          {user && (
            <p className="text-lg text-gray-600 dark:text-gray-400">
              Logged in as {user.email}
            </p>
          )}

          {!user && (
            <p className="text-lg text-gray-600 dark:text-gray-400">
              You are viewing the home page.
            </p>
          )}

          <nav className="flex gap-4 mt-4">
            <a
              href="/admin/dashboard"
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
              Go to Admin Dashboard
            </a>
            {user && (
              <button
                onClick={handleLogout}
                className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
              >
                Logout
              </button>
            )}
          </nav>
        </div>
      </main>
    </div>
  );
}
