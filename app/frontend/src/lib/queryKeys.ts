type ItemFilters = {
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  [key: string]: unknown
}

export const queryKeys = {
  users: {
    all: ['users'] as const,
    detail: (id: number) => ['users', id] as const,
    profile: (id: number) => ['users', id, 'profile'] as const,
  },
  items: {
    all: ['items'] as const,
    list: (filters: ItemFilters) => ['items', 'list', filters] as const,
    detail: (id: number) => ['items', id] as const,
  },
} as const
