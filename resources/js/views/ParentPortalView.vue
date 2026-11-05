<script setup lang="ts">
import { AlertTriangle, CalendarDays, GraduationCap, ShieldCheck } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import BrandLogo from '../components/BrandLogo.vue'
import LanguageToggle from '../components/LanguageToggle.vue'
import { useLocaleStore } from '../stores/locale'

const route=useRoute();const locale=useLocaleStore();const loading=ref(true);const student=ref<any>(null);const expiresAt=ref('');const error=ref(false)
async function load(){try{const response=(await api.get(`/parent/${route.params.token}`)).data;student.value=response.student;expiresAt.value=response.expires_at}catch{error.value=true}finally{loading.value=false}}
onMounted(load)
</script>
<template><main class="parent-portal"><LanguageToggle/><header><BrandLogo/><span><ShieldCheck/> {{locale.tr('Secure parent view')}}</span></header><section v-if="loading" class="parent-state"><span class="loading-dot"></span><p>{{locale.tr('Loading student profile…')}}</p></section><section v-else-if="error" class="parent-state error"><AlertTriangle/><h1>{{locale.tr('This link is no longer available')}}</h1><p>{{locale.tr('The parent link may have expired or been revoked by the academy. Please contact the centre for a new link.')}}</p></section><section v-else class="parent-card"><div class="parent-card-top"><span class="parent-avatar"><GraduationCap/></span><div><small>{{locale.tr('STUDENT PROFILE')}}</small><h1>{{locale.locale==='ar'?(student.name_ar||student.name_en):(student.name_en||student.name_ar)}}</h1><b>{{student.student_code}}</b></div></div><div class="parent-details"><article><CalendarDays/><div><small>{{locale.tr('Academic year')}}</small><b>{{student.academic_year?.name||locale.tr('Not assigned')}}</b></div></article><article><GraduationCap/><div><small>{{locale.tr('Educational level')}}</small><b>{{locale.locale==='ar'?(student.level?.name_ar||student.level?.name_en):(student.level?.name_en||student.level?.name_ar)||locale.tr('Not assigned')}}</b></div></article></div><div class="parent-notice"><ShieldCheck/><p>{{locale.tr('This private view is limited to this student and expires automatically.')}}</p></div><small class="parent-expiry">{{locale.tr('Link expires')}} · {{expiresAt}}</small></section><footer>AcademyHub · {{locale.tr('Private family access')}}</footer></main></template>
