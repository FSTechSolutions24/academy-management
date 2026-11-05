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
export default api
