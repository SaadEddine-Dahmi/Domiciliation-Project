// app/types/user.ts

export type UserRole   = 'admin' | 'domiciliataire' | 'client'
export type UserStatus = 'pending' | 'approved' | 'active' | 'rejected'

export interface User {
  id: number
  nom: string
  prenom?: string
  email: string
  telephone?: string
  role: UserRole
  status: UserStatus
  activation_date?: string | null
  approved_by?: number | null
  approved_at?: string | null
  rejection_reason?: string | null
  created_at?: string
}

// Used in the admin pending list
export interface PendingUser extends User {
  status: 'pending'
}