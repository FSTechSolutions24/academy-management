<script setup lang="ts">
import { Building2, CreditCard, LayoutDashboard, LifeBuoy, Settings, ShieldCheck, Users } from '@lucide/vue'
import { useRoute } from 'vue-router'
import BrandLogo from './BrandLogo.vue'
const route=useRoute();const nav=[['overview','Command centre',LayoutDashboard],['academies','Academies',Building2],['subscriptions','Subscriptions',CreditCard],['users','Users & access',Users],['security','Security & audit',ShieldCheck],['support','Support operations',LifeBuoy]]
</script>
<template><aside class="platform-sidebar"><div class="sidebar-head"><BrandLogo light/></div><div class="platform-identity"><span><ShieldCheck/></span><div><small>PLATFORM CONSOLE</small><b>AcademyHub SaaS</b></div><em>LIVE</em></div><nav><small>PLATFORM OPERATIONS</small><RouterLink v-for="([key,label,Icon]) in nav" :key="String(key)" :to="`/admin/${key}`" :class="{active:route.params.section===key}"><component :is="Icon"/> {{label}}</RouterLink><small>CONFIGURATION</small><RouterLink to="/admin/settings"><Settings/> Platform settings</RouterLink><RouterLink to="/admin/settings/plans"><CreditCard/> Plans & payments</RouterLink></nav></aside></template>
