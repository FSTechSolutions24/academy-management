<script setup lang="ts">
import { Building2, CreditCard, LayoutDashboard, LifeBuoy, LogOut, Settings, ShieldCheck, Users, X } from '@lucide/vue'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import BrandLogo from './BrandLogo.vue'
import { useAuthStore } from '../stores/auth'
import { useLocaleStore } from '../stores/locale'
const props=defineProps<{open?:boolean}>()
const emit=defineEmits<{close:[]}>()
const router=useRouter();const auth=useAuthStore();const locale=useLocaleStore();const localOpen=ref(false);const sidebarOpen=computed(()=>props.open??localOpen.value)
const operations=[['overview','Command centre',LayoutDashboard],['academies','Academies',Building2],['subscriptions','Subscriptions',CreditCard],['users','Users & access',Users],['security','Security & audit',ShieldCheck],['support','Support operations',LifeBuoy]]
function closeSidebar(){localOpen.value=false;emit('close')}
function openSidebar(){localOpen.value=true}
async function logout(){await auth.logout();router.replace('/')}
function initials(name:string){return name.split(' ').map(part=>part[0]).join('').slice(0,2).toUpperCase()}
defineExpose({open:openSidebar})
</script>
<template><button class="platform-sidebar-mobile-open" type="button" aria-label="Open platform navigation" @click="openSidebar"><span></span><span></span><span></span></button><aside class="platform-sidebar" :class="{open:sidebarOpen}"><div class="sidebar-head"><BrandLogo light/><button class="platform-sidebar-mobile-close" type="button" aria-label="Close platform navigation" @click="closeSidebar"><X/></button></div><div class="platform-identity"><span><ShieldCheck/></span><div><small>PLATFORM CONSOLE</small><b>AcademyHub SaaS</b></div><em>LIVE</em></div><nav><small>PLATFORM OPERATIONS</small><RouterLink v-for="([key,label,Icon]) in operations" :key="String(key)" :to="`/admin/${key}`" @click="closeSidebar"><component :is="Icon"/> {{label}}</RouterLink><small>CONFIGURATION</small><RouterLink to="/admin/settings" @click="closeSidebar"><Settings/> Platform settings</RouterLink><RouterLink to="/admin/settings/plans" @click="closeSidebar"><CreditCard/> Plans & payments</RouterLink></nav><div class="sidebar-foot"><span>{{initials(auth.user?.name||'PA')}}</span><div><b>{{auth.user?.name}}</b><small>{{locale.tr('Platform administrator')}}</small></div><button type="button" :title="locale.tr('Sign out')" @click="logout"><LogOut/></button></div></aside><button v-if="sidebarOpen" class="platform-sidebar-backdrop" aria-label="Close platform navigation" @click="closeSidebar"></button></template>
