import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, errorMessage, setUnauthorizedHandler } from '@/api/client'

// Replaces the HTTP adapter, so no request leaves the test.
function respondWith(status, data = {}) {
    api.defaults.adapter = (config) => {
        const response = { status, data, headers: {}, config, statusText: '' }
        if (status >= 400) {
            const error = new Error(`Request failed with status ${status}`)
            error.config = config
            error.response = response

            return Promise.reject(error)
        }

        return Promise.resolve(response)
    }
}

describe('api client', () => {
    const originalAdapter = api.defaults.adapter

    afterEach(() => {
        api.defaults.adapter = originalAdapter
        setUnauthorizedHandler(() => {})
    })

    it('targets the same-origin API and sends the auth cookie', () => {
        expect(api.defaults.baseURL).toBe('/api')
        expect(api.defaults.withCredentials).toBe(true)
    })

    it('never sets an Authorization header', async () => {
        let sentHeaders
        api.defaults.adapter = (config) => {
            sentHeaders = config.headers

            return Promise.resolve({ status: 200, data: {}, headers: {}, config })
        }

        await api.get('/movies')

        expect(sentHeaders.Authorization).toBeUndefined()
    })

    it('calls the unauthorized handler on 401', async () => {
        const handler = vi.fn()
        setUnauthorizedHandler(handler)
        respondWith(401)

        await expect(api.get('/movies')).rejects.toThrow()
        expect(handler).toHaveBeenCalledOnce()
    })

    it('leaves 401 to the caller when skipAuthRedirect is set', async () => {
        const handler = vi.fn()
        setUnauthorizedHandler(handler)
        respondWith(401)

        await expect(api.post('/auth/login', {}, { skipAuthRedirect: true })).rejects.toThrow()
        expect(handler).not.toHaveBeenCalled()
    })

    it('ignores other errors', async () => {
        const handler = vi.fn()
        setUnauthorizedHandler(handler)
        respondWith(503)

        await expect(api.get('/movies')).rejects.toThrow()
        expect(handler).not.toHaveBeenCalled()
    })
})

describe('errorMessage', () => {
    it('uses the problem detail', () => {
        expect(errorMessage({ response: { data: { detail: 'Movie not found.' } } })).toBe('Movie not found.')
    })

    it('falls back without a detail', () => {
        expect(errorMessage(new Error('Network Error'), 'Offline')).toBe('Offline')
    })
})
