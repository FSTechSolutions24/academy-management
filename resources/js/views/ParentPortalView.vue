<script setup lang="ts">
import { AlertTriangle, BarChart3, CalendarDays, CheckCircle2, Clock3, GraduationCap, ShieldCheck, TrendingUp, UserCheck, XCircle } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import BrandLogo from '../components/BrandLogo.vue'
import LanguageToggle from '../components/LanguageToggle.vue'
import { useLocaleStore } from '../stores/locale'

const route = useRoute(); const locale = useLocaleStore(); const loading = ref(true); const student = ref<any>(null); const expiresAt = ref(''); const error = ref(false)
const attendance = ref<any>(null); const recentAttendance = ref<any[]>([]); const results = ref<any[]>([])
const displayName = computed(() => student.value ? (locale.locale === 'ar' ? (student.value.name_ar || student.value.name_en) : (student.value.name_en || student.value.name_ar)) : '')
const attendanceRate = computed(() => attendance.value?.total ? Math.round(((attendance.value.present + attendance.value.late) / attendance.value.total) * 100) : 0)
function label(value: any) { return locale.locale === 'ar' ? (value?.name_ar || value?.name_en) : (value?.name_en || value?.name_ar) }
async function load() { try { const response = (await api.get(`/parent/${route.params.token}`)).data; student.value = response.student; attendance.value = response.attendance; recentAttendance.value = response.attendance_recent || []; results.value = response.results || []; expiresAt.value = response.expires_at } catch { error.value = true } finally { loading.value = false } }
onMounted(load)
</script>

<template>
  <main class="parent-portal"><LanguageToggle/><header><BrandLogo/><span><ShieldCheck/> {{ locale.tr('Secure parent view') }}</span></header>
    <section v-if="loading" class="parent-state"><span class="loading-dot"></span><p>{{ locale.tr('Loading student profile…') }}</p></section>
    <section v-else-if="error" class="parent-state error"><AlertTriangle/><h1>{{ locale.tr('This link is no longer available') }}</h1><p>{{ locale.tr('The parent link may have expired or been revoked by the academy. Please contact the centre for a new link.') }}</p></section>
    <template v-else><section class="parent-card parent-identity"><div class="parent-card-top"><span class="parent-avatar"><GraduationCap/></span><div><small>{{ locale.tr('STUDENT PROFILE') }}</small><h1>{{ displayName }}</h1><b>{{ student.student_code }}</b></div></div><div class="parent-details"><article><CalendarDays/><div><small>{{ locale.tr('Academic year') }}</small><b>{{ student.academic_year?.name || locale.tr('Not assigned') }}</b></div></article><article><GraduationCap/><div><small>{{ locale.tr('Educational level') }}</small><b>{{ label(student.level) || locale.tr('Not assigned') }}</b></div></article></div><div class="parent-notice"><ShieldCheck/><p>{{ locale.tr('This private view is limited to this student and expires automatically.') }}</p></div><small class="parent-expiry">{{ locale.tr('Link expires') }} · {{ expiresAt }}</small></section>
      <section class="parent-dashboard"><div class="parent-section-heading"><div><small>{{ locale.tr('PROGRESS SNAPSHOT') }}</small><h2>{{ locale.tr('How your child is doing') }}</h2></div><TrendingUp/></div><div class="parent-stats"><article><span class="stat-icon attendance"><UserCheck/></span><small>{{ locale.tr('Attendance rate') }}</small><strong>{{ attendanceRate }}%</strong><em>{{ attendance?.total || 0 }} {{ locale.tr('sessions tracked') }}</em></article><article><span class="stat-icon exams"><BarChart3/></span><small>{{ locale.tr('Published exams') }}</small><strong>{{ results.length }}</strong><em>{{ locale.tr('results available') }}</em></article><article><span class="stat-icon present"><CheckCircle2/></span><small>{{ locale.tr('Present / late') }}</small><strong>{{ (attendance?.present || 0) + (attendance?.late || 0) }}</strong><em>{{ locale.tr('healthy participation') }}</em></article></div>
        <div class="parent-panels"><article class="parent-panel"><div class="parent-panel-head"><h3>{{ locale.tr('Exam results') }}</h3><BarChart3/></div><div v-if="!results.length" class="parent-empty"><BarChart3/><p>{{ locale.tr('No published results yet.') }}</p></div><div v-else class="result-list"><div v-for="result in results" :key="result.id" class="result-row"><span class="result-badge">{{ Math.round(result.percentage) }}%</span><div><b>{{ result.title }}</b><small>{{ label(result.subject) || locale.tr('General assessment') }} · {{ result.exam_date }}</small></div><strong>{{ result.mark }} / {{ result.total_marks }}</strong></div></div></article><article class="parent-panel"><div class="parent-panel-head"><h3>{{ locale.tr('Recent attendance') }}</h3><Clock3/></div><div v-if="!recentAttendance.length" class="parent-empty"><Clock3/><p>{{ locale.tr('No attendance records yet.') }}</p></div><div v-else class="attendance-list"><div v-for="record in recentAttendance" :key="`${record.date}-${record.group?.code}`" class="attendance-row"><span :class="['status-dot', record.status]"><CheckCircle2 v-if="record.status === 'present' || record.status === 'late'"/><XCircle v-else/></span><div><b>{{ label(record.group) || locale.tr('Class session') }}</b><small>{{ record.date }}</small></div><strong :class="record.status">{{ locale.tr(record.status) }}</strong></div></div></article></div>
      </section></template>
    <footer>AcademyHub · {{ locale.tr('Private family access') }}</footer>
  </main>
</template>
