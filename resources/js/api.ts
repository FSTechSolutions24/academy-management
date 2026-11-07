import axios from 'axios'
const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
  },
  withCredentials: true,
})
api.interceptors.response.use((response) => response, (error) => {
  if (error.response?.status === 402 && error.response?.data?.subscription_required && window.location.pathname !== '/subscribe') window.location.assign('/subscribe')
  return Promise.reject(error)
})
export default api
