import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api, errorMessage } from '@/api/client'

export const MIN_QUERY_LENGTH = 2
export const MAX_QUERY_LENGTH = 100

export const useMoviesStore = defineStore('movies', () => {
    const movies = ref([])
    const page = ref(1)
    const totalPages = ref(0)
    const totalResults = ref(0)
    const query = ref('')
    const loading = ref(false)
    const error = ref(null)

    // Answers can arrive out of order while the user types; only the latest request may update the state.
    let latestRequest = 0

    async function load(targetPage = 1) {
        const requestId = ++latestRequest
        loading.value = true
        error.value = null

        const searching = query.value.length >= MIN_QUERY_LENGTH
        try {
            const { data } = searching
                ? await api.get('/movies/search', { params: { q: query.value, page: targetPage } })
                : await api.get('/movies', { params: { page: targetPage } })
            if (requestId !== latestRequest) {
                return
            }
            movies.value = data.results
            page.value = data.page
            totalPages.value = data.totalPages
            totalResults.value = data.totalResults
        } catch (e) {
            if (requestId !== latestRequest) {
                return
            }
            movies.value = []
            totalPages.value = 0
            totalResults.value = 0
            error.value = errorMessage(e)
        } finally {
            if (requestId === latestRequest) {
                loading.value = false
            }
        }
    }

    function search(term) {
        query.value = term.trim()

        return load(1)
    }

    return { movies, page, totalPages, totalResults, query, loading, error, load, search }
})
