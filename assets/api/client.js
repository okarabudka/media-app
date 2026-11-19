import axios from 'axios'

/**
 * All API calls go to the Symfony backend on the same origin. The JWT lives in an httpOnly cookie,
 * so the browser attaches it; JavaScript never sees the token.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    timeout: 15000,
    headers: { Accept: 'application/json' },
})

let unauthorizedHandler = () => {}

export function setUnauthorizedHandler(handler) {
    unauthorizedHandler = handler
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Login and the session probe expect 401 as a normal answer; everything else means "logged out".
        if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
            unauthorizedHandler()
        }

        return Promise.reject(error)
    },
)

/**
 * The user-facing message of a failed request: the API's problem "detail" when there is one.
 */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const detail = error?.response?.data?.detail

    return typeof detail === 'string' && detail !== '' ? detail : fallback
}
