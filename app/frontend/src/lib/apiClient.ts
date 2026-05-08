import axios from 'axios'
import { useAuthStore } from '@/stores/authStore'

if (!process.env.NEXT_PUBLIC_API_URL && typeof window !== 'undefined') {
  console.error('[apiClient] NEXT_PUBLIC_API_URL is not set — all API requests will use relative URLs')
}

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

let redirecting = false

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      error.response?.status === 401 &&
      typeof window !== 'undefined' &&
      !window.location.pathname.includes('/login') &&
      !redirecting
    ) {
      redirecting = true
      useAuthStore.getState().clearAuth()
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)
