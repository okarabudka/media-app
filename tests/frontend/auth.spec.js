import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/client', async (importOriginal) => ({
    ...(await importOriginal()),
    api: { get: vi.fn(), post: vi.fn() },
}))

const unauthorized = Object.assign(new Error('401'), { response: { status: 401, data: {} } })

describe('auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.mocked(api.get).mockReset()
        vi.mocked(api.post).mockReset()
    })

    it('logs in and keeps only the username', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { username: 'demo' } })
        const auth = useAuthStore()

        await auth.login('demo', 'secret')

        expect(api.post).toHaveBeenCalledWith('/auth/login', { username: 'demo', password: 'secret' }, { skipAuthRedirect: true })
        expect(auth.isAuthenticated).toBe(true)
        expect(auth.username).toBe('demo')
    })

    it('restores the session from the cookie once', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { username: 'demo' } })
        const auth = useAuthStore()

        await auth.fetchUser()
        await auth.fetchUser()

        expect(api.get).toHaveBeenCalledOnce()
        expect(auth.isAuthenticated).toBe(true)
    })

    it('treats 401 on the session probe as logged out', async () => {
        vi.mocked(api.get).mockRejectedValue(unauthorized)
        const auth = useAuthStore()

        await auth.fetchUser()

        expect(auth.isAuthenticated).toBe(false)
        expect(auth.sessionError).toBeNull()
    })

    it('reports other session probe failures', async () => {
        vi.mocked(api.get).mockRejectedValue(Object.assign(new Error('down'), { response: { status: 502, data: {} } }))
        const auth = useAuthStore()

        await auth.fetchUser()

        expect(auth.isAuthenticated).toBe(false)
        expect(auth.sessionError).toBe('Could not reach the server.')
    })

    it('forgets the user on logout even if the request fails', async () => {
        vi.mocked(api.post).mockResolvedValueOnce({ data: { username: 'demo' } }).mockRejectedValueOnce(new Error('offline'))
        const auth = useAuthStore()
        await auth.login('demo', 'secret')

        await expect(auth.logout()).rejects.toThrow('offline')

        expect(auth.isAuthenticated).toBe(false)
    })
})
