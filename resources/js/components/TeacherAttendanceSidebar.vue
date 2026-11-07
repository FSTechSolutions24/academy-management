<script setup lang="ts">
import { BarChart3, CalendarDays, ClipboardCheck, GraduationCap, LayoutDashboard, MessageCircle, QrCode, Users } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import api from '../api'
import BrandLogo from './BrandLogo.vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLocaleStore } from '../stores/locale'

const auth = useAuthStore(); const locale = useLocaleStore(); const route = useRoute(); const canSendMessages = ref(false)
onMounted(async () => { try { const response = await api.get('/dashboard'); canSendMessages.value = Boolean(response.data.membership?.capabilities?.send_messages) } catch {} })
</script>
<template>
  <aside class="teacher-global-sidebar">
    <div class="sidebar-head"><BrandLogo light/></div>
    <div class="teacher-identity"><span><GraduationCap/></span><div><small>{{locale.tr('TEACHER WORKSPACE')}}</small><b>{{locale.tr('Attendance desk')}}</b></div></div>
    <nav><small>{{locale.tr('TEACHING')}}</small><RouterLink to="/teacher/overview"><LayoutDashboard/>{{locale.tr('Teaching overview')}}</RouterLink><RouterLink to="/teacher/students"><Users/>{{locale.tr('My students')}}</RouterLink><RouterLink to="/teacher/attendance" :class="{active:route.path==='/teacher/attendance'}"><QrCode/>{{locale.tr('Attendance')}}</RouterLink><RouterLink to="/teacher/schedule"><CalendarDays/>{{locale.tr('My schedule')}}</RouterLink><RouterLink to="/teacher/exams"><ClipboardCheck/>{{locale.tr('Exams & marks')}}</RouterLink><RouterLink to="/teacher/performance"><BarChart3/>{{locale.tr('Performance')}}</RouterLink><RouterLink v-if="canSendMessages" to="/teacher/communications" :class="{active:route.path==='/teacher/communications'}"><MessageCircle/>{{locale.tr('Parent communications')}}</RouterLink></nav>
    <div class="sidebar-foot"><span>{{auth.user?.name?.slice(0,2).toUpperCase()}}</span><div><b>{{auth.user?.name}}</b><small>{{locale.tr('Teacher')}}</small></div></div>
  </aside>
</template>
