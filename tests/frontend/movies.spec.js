import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '@/api/client'
import { useMoviesStore } from '@/stores/movies'

vi.mock('@/api/client', async (importOriginal) => ({
    ...(await importOriginal()),
    api: { get: vi.fn() },
}))

function list(page, results = [{ id: 1, title: 'Movie' }]) {
    return { data: { page, totalPages: 3, totalResults: 42, results } }
}

describe('movies store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.mocked(api.get).mockReset()
    })

    it('loads popular movies without a query', async () => {
        vi.mocked(api.get).mockResolvedValue(list(2))
        const store = useMoviesStore()

        await store.load(2)

        expect(api.get).toHaveBeenCalledWith('/movies', { params: { page: 2 } })
        expect(store.page).toBe(2)
        expect(store.totalPages).toBe(3)
        expect(store.movies).toHaveLength(1)
        expect(store.loading).toBe(false)
    })

    it('searches with the trimmed query from page 1', async () => {
        vi.mocked(api.get).mockResolvedValue(list(1))
        const store = useMoviesStore()

        await store.search('  matrix ')

        expect(api.get).toHaveBeenCalledWith('/movies/search', { params: { q: 'matrix', page: 1 } })
        expect(store.query).toBe('matrix')
    })

    it('goes back to popular movies when the query is cleared', async () => {
        vi.mocked(api.get).mockResolvedValue(list(1))
        const store = useMoviesStore()

        await store.search('')

        expect(api.get).toHaveBeenCalledWith('/movies', { params: { page: 1 } })
    })

    it('shows the API error message', async () => {
        vi.mocked(api.get).mockRejectedValue({ response: { status: 503, data: { detail: 'The movie database is busy.' } } })
        const store = useMoviesStore()

        await store.load(1)

        expect(store.error).toBe('The movie database is busy.')
        expect(store.movies).toEqual([])
    })

    it('ignores answers that arrive after a newer request', async () => {
        let resolveSlow
        vi.mocked(api.get)
            .mockReturnValueOnce(new Promise((resolve) => { resolveSlow = resolve }))
            .mockResolvedValueOnce(list(1, [{ id: 2, title: 'Newer' }]))
        const store = useMoviesStore()

        const slow = store.search('ma')
        await store.search('matrix')
        resolveSlow(list(1, [{ id: 1, title: 'Older' }]))
        await slow

        expect(store.movies.map((movie) => movie.title)).toEqual(['Newer'])
    })
})
