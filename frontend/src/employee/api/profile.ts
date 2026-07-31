import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from '@/core/api/client'

const profileSchema = z.object({
  // للعرض فقط (تديرها HR)
  name: z.string().nullable(),
  employee_number: z.string().nullable(),
  job_title: z.string().nullable(),
  branch: z.string().nullable(),
  department: z.string().nullable(),
  manager: z.string().nullable(),
  // قابلة للتعديل الذاتي
  phone: z.string().nullable(),
  address: z.string().nullable(),
  photo_path: z.string().nullable(),
  emergency_contact_name: z.string().nullable(),
  emergency_contact_phone: z.string().nullable(),
})
export type Profile = z.infer<typeof profileSchema>

/** الحقول المسموح بتعديلها ذاتياً (يطابق الباك-إند — لا حقول مالية/تنظيمية). */
export interface ProfileUpdate {
  phone?: string | null
  address?: string | null
  photo_path?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
}

export function useProfile() {
  return useQuery({
    queryKey: ['me', 'profile'],
    queryFn: async () => profileSchema.parse(await api.get<unknown>('me/profile')),
  })
}

export function useUpdateProfile() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: ProfileUpdate) => api.patch<Profile>('me/profile', input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['me', 'profile'] })
    },
  })
}
