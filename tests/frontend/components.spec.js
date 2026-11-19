import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppPagination from '@/components/AppPagination.vue'
import MovieCard from '@/components/MovieCard.vue'
import MovieList from '@/components/MovieList.vue'
import SearchBar from '@/components/SearchBar.vue'

describe('SearchBar', () => {
    beforeEach(() => vi.useFakeTimers())
    afterEach(() => vi.useRealTimers())

    it('emits the trimmed term once typing pauses for 400 ms', async () => {
        const wrapper = mount(SearchBar)
        const input = wrapper.get('input')

        await input.setValue('mat')
        await input.setValue(' matrix ')
        vi.advanceTimersByTime(399)
        expect(wrapper.emitted('search')).toBeUndefined()

        vi.advanceTimersByTime(1)
        expect(wrapper.emitted('search')).toEqual([['matrix']])
    })

    it('does not search for a single character and explains why', async () => {
        const wrapper = mount(SearchBar)

        await wrapper.get('input').setValue('m')
        vi.advanceTimersByTime(1000)

        expect(wrapper.emitted('search')).toBeUndefined()
        expect(wrapper.text()).toContain('at least 2 characters')
    })

    it('limits the input to 100 characters', () => {
        expect(mount(SearchBar).get('input').attributes('maxlength')).toBe('100')
    })

    it('clears the field and returns to popular movies immediately', async () => {
        const wrapper = mount(SearchBar, { props: { modelValue: 'matrix' } })

        await wrapper.get('button[aria-label="Clear search"]').trigger('click')

        expect(wrapper.get('input').element.value).toBe('')
        expect(wrapper.emitted('search')).toEqual([['']])
        vi.advanceTimersByTime(1000)
        expect(wrapper.emitted('search')).toHaveLength(1)
    })
})

describe('AppPagination', () => {
    it('disables "previous" on the first page', () => {
        const wrapper = mount(AppPagination, { props: { page: 1, totalPages: 3 } })

        expect(wrapper.get('[data-test="previous"]').attributes('disabled')).toBeDefined()
        expect(wrapper.get('[data-test="next"]').attributes('disabled')).toBeUndefined()
    })

    it('disables "next" on the last page', () => {
        const wrapper = mount(AppPagination, { props: { page: 3, totalPages: 3 } })

        expect(wrapper.get('[data-test="next"]').attributes('disabled')).toBeDefined()
    })

    it('emits the neighbouring page', async () => {
        const wrapper = mount(AppPagination, { props: { page: 2, totalPages: 3 } })

        await wrapper.get('[data-test="next"]').trigger('click')
        await wrapper.get('[data-test="previous"]').trigger('click')

        expect(wrapper.emitted('change')).toEqual([[3], [1]])
    })

    it('is hidden for a single page', () => {
        expect(mount(AppPagination, { props: { page: 1, totalPages: 1 } }).find('nav').exists()).toBe(false)
    })
})

const movie = {
    id: 603,
    title: 'The Matrix',
    overview: 'A hacker learns the truth.',
    posterUrl: 'https://image.tmdb.org/t/p/w342/poster.jpg',
    releaseYear: 1999,
    rating: 8.2,
    voteCount: 26420,
}

describe('MovieCard', () => {
    it('shows poster, title, year and rating', () => {
        const wrapper = mount(MovieCard, { props: { movie } })

        expect(wrapper.get('img').attributes()).toMatchObject({ src: movie.posterUrl, loading: 'lazy' })
        expect(wrapper.text()).toContain('The Matrix')
        expect(wrapper.text()).toContain('1999')
        expect(wrapper.text()).toContain('★ 8.2')
    })

    it('copes with missing poster, date and votes', () => {
        const wrapper = mount(MovieCard, {
            props: { movie: { ...movie, posterUrl: null, releaseYear: null, voteCount: 0, overview: '' } },
        })

        expect(wrapper.find('[data-test="no-poster"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Release date unknown')
        expect(wrapper.text()).not.toContain('★')
    })
})

describe('MovieList', () => {
    it('shows skeletons while the first page loads', () => {
        const wrapper = mount(MovieList, { props: { movies: [], loading: true } })

        expect(wrapper.findAll('[data-test="skeleton"]').length).toBeGreaterThan(0)
    })

    it('shows the error instead of movies', () => {
        const wrapper = mount(MovieList, { props: { movies: [movie], error: 'TMDB is down' } })

        expect(wrapper.get('[role="alert"]').text()).toBe('TMDB is down')
    })

    it('shows an empty state', () => {
        expect(mount(MovieList, { props: { movies: [] } }).text()).toContain('No movies found.')
    })

    it('renders one card per movie', () => {
        const wrapper = mount(MovieList, { props: { movies: [movie, { ...movie, id: 604 }] } })

        expect(wrapper.findAllComponents(MovieCard)).toHaveLength(2)
    })
})
