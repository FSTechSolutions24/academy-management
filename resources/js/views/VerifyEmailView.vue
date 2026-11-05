<script setup lang="ts">
import { CheckCircle2, LogOut, MailCheck, RefreshCw } from '@lucide/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import BrandLogo from '../components/BrandLogo.vue'
import { useAuthStore } from '../stores/auth'

const auth=useAuthStore();const router=useRouter();const loading=ref(false);const message=ref('');const error=ref('')
async function resend(){loading.value=true;message.value='';error.value='';try{const {data}=await api.post('/email/verification-notification');message.value=data.message}catch(e:any){error.value=e.response?.data?.message||'We could not send another email yet.'}finally{loading.value=false}}
async function logout(){await auth.logout();router.replace('/login')}
</script>
<template><main class="auth-layout"><section class="auth-story"><BrandLogo light/><div class="story-copy"><span class="eyebrow light">ONE QUICK SECURITY STEP</span><h1>Protect every centre,<br><em>from the first login.</em></h1><p>Verification keeps academy records and team access connected to the right person.</p><ul><li><CheckCircle2/>Secure tenant access</li><li><CheckCircle2/>Protected password recovery</li><li><CheckCircle2/>One verified identity across your centres</li></ul></div><footer>ACADEMYHUB <span>IDENTITY PROTECTED</span></footer></section><section class="auth-panel"><div class="auth-box status-box"><span class="status-icon"><MailCheck/></span><span class="eyebrow">CHECK YOUR INBOX</span><h2>Verify your email.</h2><p>We sent a secure link to <strong>{{auth.user?.email}}</strong>. Open it to unlock your academy workspace.</p><div v-if="message" class="form-success">{{message}}</div><div v-if="error" class="form-error">{{error}}</div><button class="button primary wide" :disabled="loading" @click="resend"><RefreshCw/>{{loading?'Sending…':'Resend verification email'}}</button><button class="status-link" @click="logout"><LogOut/>Use a different account</button><small class="fine-print">The link is time-limited. Check your spam folder if it does not arrive.</small></div></section></main></template>
