import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function middleware(request: NextRequest) {
  const pathname = request.nextUrl.pathname

  // Define public paths that don't require auth
  const publicPaths = [
    '/',
    '/admin/auth/login',
    '/admin/auth/register',
    '/api/v1/login',
    '/api/v1/register',
    '/api/v1/refresh',
    '/api/v1/forgot-password',
    '/api/v1/reset-password',
  ]

  // Check if the current path is public
  const isPublic = publicPaths.some(
    (path) => pathname === path || pathname.startsWith(path + '/')
  )

  // Get token from cookie (set during login)
  const token = request.cookies.get('token')?.value

  // If accessing protected route without token, redirect to login
  if (!isPublic && !token) {
    const loginUrl = new URL('/auth/login', request.url)
    return NextResponse.redirect(loginUrl)
  }

  // If accessing admin auth page while already logged in, redirect to admin dashboard
  if (isPublic && token && pathname.includes('/admin/auth')) {
    return NextResponse.redirect(new URL('/admin/dashboard', request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - public folder
     */
    '/((?!_next/static|_next/image|favicon.ico|.*\\..*$|public).*)',
  ],
}
