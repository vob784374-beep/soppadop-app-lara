export interface PaginatedMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

export interface ApiResponse<T> {
  data: T | null
  message: string
  errors: Record<string, string[]> | null
  meta: PaginatedMeta | null
}

export interface User {
  id: number
  name: string
  email: string
  avatar: string | null
  email_verified_at: string | null
  created_at: string
}
