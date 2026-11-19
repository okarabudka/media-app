import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory } from 'vue-router'
import { api } from '@/api/client'
import { createAppRouter, safeRedirect } from '@/router'

vi.mock('@/api/client', async (importOriginal) => ({
    ...(await importOriginal()),
    api: { get: vi.fn(), post: vi.fn() },
}))

describe('router guard', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.mocked(api.get).mockReset()
    })

    it('sends visitors without a session to the login page', async () => {
        vi.mocked(api.get).mockRejectedValue({ response: { status: 401, data: {} } })
        const router = createAppRouter(createMemoryHistory())

        await router.push('/')

        expect(router.currentRoute.value.name).toBe('login')
        expect(router.currentRoute.value.query.redirect).toBe('/')
    })

    it('lets logged-in users in and keeps them away from the login page', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { username: 'demo' } })
        const router = createAppRouter(createMemoryHistory())

        await router.push('/')
        expect(router.currentRoute.value.name).toBe('home')

        await router.push('/login')
        expect(router.currentRoute.value.name).toBe('home')
    })

    it('shows the 404 page for unknown paths', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { username: 'demo' } })
        const router = createAppRouter(createMemoryHistory())

        await router.push('/does/not/exist')

        expect(router.currentRoute.value.name).toBe('not-found')
    })
})

describe('safeRedirect', () => {
    it.each([
        ['/', '/'],
        ['/?page=2', '/?page=2'],
        ['//evil.test', '/'],
        ['https://evil.test', '/'],
        [undefined, '/'],
        [['/a', '/b'], '/'],
    ])('%j → %s', (target, expected) => {
        expect(safeRedirect(target)).toBe(expected)
    })
})
