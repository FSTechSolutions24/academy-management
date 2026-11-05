import { defineStore } from 'pinia'
import api from '../api'

type User = { id: number; name: string; email: string; verified: boolean; is_platform_admin?: boolean }
export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null as User | null, ready: false }),
  actions: {
    async hydrate() { try { const { data } = await api.get('/session'); this.user = data.user; if(data.csrf_token) api.defaults.headers.common['X-CSRF-TOKEN']=data.csrf_token } finally { this.ready = true } },
    async login(payload: {email:string;password:string;remember:boolean}) { const { data } = await api.post('/login', payload); this.user = data.user; if(data.csrf_token) api.defaults.headers.common['X-CSRF-TOKEN']=data.csrf_token; return data },
    async register(payload: Record<string,string>) { const { data } = await api.post('/register', payload); this.user = data.user; if(data.csrf_token) api.defaults.headers.common['X-CSRF-TOKEN']=data.csrf_token; return data },
    async logout() { await api.post('/logout'); this.user = null },
  },
})
