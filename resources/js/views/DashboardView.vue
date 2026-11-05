<script setup lang="ts">
import {
  BarChart3, Bell, BookOpen, Building2, CalendarDays, Check, ChevronDown,
  ClipboardCheck, CreditCard, GraduationCap, LayoutDashboard, LogOut, MapPin,
  Menu, Plus, QrCode, Search, Settings, Users, UserRoundCog, X, ScrollText,
} from '@lucide/vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { useAuthStore } from '../stores/auth'
import { useLocaleStore } from '../stores/locale'
import BrandLogo from '../components/BrandLogo.vue'
import TeamModal from '../components/TeamModal.vue'
import AuditLogModal from '../components/AuditLogModal.vue'

type Branch = {
  public_id: string
  name_ar: string
  name_en: string | null
  governorate: string | null
  city: string | null
  is_head_office: boolean
}

const auth = useAuthStore()
const locale = useLocaleStore()
const router = useRouter()
const data = ref<any>(null)
const loading = ref(true)
const switching = ref<string | null>(null)
const menu = ref(false)
const branchMenu = ref(false)
const createOpen = ref(false)
const academicOpen = ref(false)
const teamOpen = ref(false)
const auditOpen = ref(false)
const academicTab = ref<'years' | 'levels' | 'subjects'>('years')
const structure = ref<any>({ academic_years: [], levels: [], subjects: [] })
const saving = ref(false)
const formError = ref('')
const branchForm = reactive({ name_ar: '', name_en: '', governorate: '', city: '', address: '', phone: '' })
const yearForm = reactive({ name: '2027/2028', starts_on: '2027-09-01', ends_on: '2028-06-30', status: 'active', is_active: true })
const levelForm = reactive({ name_ar: '', name_en: '', stage: 'secondary', sort_order: 1 })
const subjectForm = reactive({ name_ar: '', name_en: '', code: '', color: '#6d5dfc' })

const completed = computed(() => data.value?.setup.filter((item: any) => item.done).length || 0)
const branchInitials = computed(() => (data.value?.branch.name_en || data.value?.branch.name_ar || 'C').split(' ').map((part: string) => part[0]).join('').slice(0, 2).toUpperCase())
const nav = [
  ['Overview', LayoutDashboard], ['Students', Users], ['Groups & schedule', CalendarDays],
  ['Attendance', QrCode], ['Examinations', ClipboardCheck], ['Performance', BarChart3],
  ['Fees & payments', CreditCard], ['Staff & roles', UserRoundCog], ['Audit & security', ScrollText],
]

async function loadDashboard() {
  loading.value = true
  try { data.value = (await api.get('/dashboard')).data } finally { loading.value = false }
}

async function switchBranch(branch: Branch) {
  if (branch.public_id === data.value.branch.public_id) { branchMenu.value = false; return }
  switching.value = branch.public_id
  try { await api.post(`/branches/${branch.public_id}/select`); branchMenu.value = false; await loadDashboard() }
  finally { switching.value = null }
}

async function createBranch() {
  saving.value = true; formError.value = ''
  try {
    const { data: response } = await api.post('/branches', branchForm)
    await api.post(`/branches/${response.branch.public_id}/select`)
    createOpen.value = false; branchMenu.value = false
    Object.assign(branchForm, { name_ar: '', name_en: '', governorate: '', city: '', address: '', phone: '' })
    await loadDashboard()
  } catch (error: any) {
    const errors = error.response?.data?.errors
    formError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message || 'Unable to create the centre.')
  } finally { saving.value = false }
}

async function openAcademic(label = '') {
  formError.value = ''
  if (label.includes('level')) academicTab.value = 'levels'
  else if (label.includes('Subject')) academicTab.value = 'subjects'
  else academicTab.value = 'years'
  structure.value = (await api.get('/academic-structure')).data
  academicOpen.value = true
}

async function saveAcademic() {
  saving.value = true; formError.value = ''
  try {
    if (academicTab.value === 'years') await api.post('/academic-structure/years', yearForm)
    if (academicTab.value === 'levels') await api.post('/academic-structure/levels', levelForm)
    if (academicTab.value === 'subjects') await api.post('/academic-structure/subjects', subjectForm)
    structure.value = (await api.get('/academic-structure')).data
    await loadDashboard()
    if (academicTab.value === 'levels') Object.assign(levelForm, { name_ar: '', name_en: '', stage: 'secondary', sort_order: structure.value.levels.length + 1 })
    if (academicTab.value === 'subjects') Object.assign(subjectForm, { name_ar: '', name_en: '', code: '', color: '#6d5dfc' })
  } catch (error: any) {
    const errors = error.response?.data?.errors
    formError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message || 'Unable to save this academic record.')
  } finally { saving.value = false }
}

async function logout() { await auth.logout(); router.replace('/') }
function openNavigation(label: unknown) { if (label === 'Staff & roles') teamOpen.value = true; if (label === 'Audit & security') auditOpen.value = true }
onMounted(loadDashboard)
</script>

<template>
  <div class="app-shell">
    <aside :class="{ open: menu }">
      <div class="sidebar-head"><BrandLogo light /><button @click="menu = false"><X /></button></div>

      <div class="centre-switcher">
        <button class="workspace-switch" :aria-expanded="branchMenu" @click="branchMenu = !branchMenu">
          <span>{{ branchInitials }}</span>
          <div><small>{{locale.tr('ACTIVE CENTRE')}}</small><b>{{ locale.locale==='ar'?(data?.branch.name_ar||data?.branch.name_en):(data?.branch.name_en||data?.branch.name_ar) || 'Loading…' }}</b></div>
          <ChevronDown :class="{ rotated: branchMenu }" />
        </button>
        <div v-if="branchMenu && data" class="centre-menu">
          <div class="centre-menu-head"><span><small>{{locale.tr('YOUR CENTRES')}}</small><b>{{locale.tr('Switch workspace')}}</b></span><em>{{ data.branches.length }}</em></div>
          <button v-for="branch in data.branches" :key="branch.public_id" class="centre-option" :class="{ active: branch.public_id === data.branch.public_id }" @click="switchBranch(branch)">
            <span>{{ (branch.name_en || branch.name_ar).slice(0, 2).toUpperCase() }}</span>
            <div><b>{{ locale.locale==='ar'?(branch.name_ar||branch.name_en):(branch.name_en||branch.name_ar) }}</b><small><MapPin />{{ [branch.city, branch.governorate].filter(Boolean).join(', ') || 'Location not set' }}</small></div>
            <Check v-if="branch.public_id === data.branch.public_id" />
            <i v-else-if="switching === branch.public_id"></i>
          </button>
          <button class="create-centre-link" @click="createOpen = true"><Plus />{{locale.tr('Create another centre')}}</button>
        </div>
      </div>

      <nav>
        <small>{{locale.tr('CENTRE WORKSPACE')}}</small>
        <a v-for="([label, Icon], index) in nav" :key="String(label)" :class="{ active: index === 0 }" href="#" @click.prevent="openNavigation(label)">
          <component :is="Icon" />{{ locale.tr(String(label)) }}<em v-if="label === 'Students'">0</em>
        </a>
        <small>{{locale.tr('SETUP')}}</small><a href="#" @click.prevent="openAcademic()"><BookOpen />{{locale.tr('Academic structure')}}</a><a href="#"><Settings />{{locale.tr('Settings')}}</a>
      </nav>
      <div class="sidebar-foot"><span>{{ auth.user?.name.slice(0, 2).toUpperCase() }}</span><div><b>{{ auth.user?.name }}</b><small>{{locale.tr('Organisation owner')}}</small></div><button :title="locale.tr('Sign out')" @click="logout"><LogOut /></button></div>
    </aside>

    <main class="dashboard-main">
      <header><button class="mobile-nav" @click="menu = true"><Menu /></button><div class="global-search"><Search /><input :placeholder="locale.tr('Search this centre…')"><kbd>⌘ K</kbd></div><div class="header-actions"><button><Bell /><i></i></button><span>{{locale.locale.toUpperCase()}}</span></div></header>
      <div v-if="loading" class="loading-state"><span></span>{{locale.tr('Preparing your centre workspace…')}}</div>
      <div v-else class="dashboard-content">
        <div class="branch-context-banner"><span><Building2 /></span><div><small>{{locale.tr('CURRENT CENTRE')}}</small><b>{{ locale.locale==='ar'?(data.branch.name_ar||data.branch.name_en):(data.branch.name_en||data.branch.name_ar) }}</b><p>{{locale.tr('All students, years, attendance, exams and finance below belong only to this centre.')}}</p></div><em v-if="data.branch.is_head_office">{{locale.tr('Head office')}}</em></div>
        <div class="page-heading"><div><span>{{new Intl.DateTimeFormat(locale.locale==='ar'?'ar-EG':'en-GB',{dateStyle:'full'}).format(new Date())}}</span><h1>{{locale.tr('Good evening')}}، {{ auth.user?.name.split(' ')[0] }}.</h1><p>{{locale.tr('Here’s what’s happening at')}} {{ locale.locale==='ar'?(data.branch.name_ar||data.branch.name_en):(data.branch.name_en||data.branch.name_ar) }} {{locale.tr('today.')}}</p></div><button class="button primary">+ {{locale.tr('Add student')}}</button></div>
        <section class="stats-grid"><article v-for="stat in data.stats" :key="stat.label" :class="stat.tone"><span><component :is="stat.label.includes('student') ? Users : stat.label.includes('Attendance') ? QrCode : stat.label.includes('result') ? BarChart3 : CreditCard" /></span><small>{{ stat.label }}</small><b>{{ stat.value }}</b><em>{{ stat.change }}</em></article></section>
        <section class="dashboard-grid">
          <article class="setup-card"><div class="card-head"><div><span class="eyebrow">{{locale.tr('CENTRE SETUP')}}</span><h2>{{locale.tr('Set up this centre')}}</h2><p>{{locale.tr('Every centre has its own independent academic structure.')}}</p></div><div class="progress-ring"><b>{{ completed }}<small>/6</small></b></div></div><div class="progress"><i :style="{ width: (completed / 6 * 100) + '%' }"></i></div><ul><li v-for="(item, index) in data.setup" :key="item.label" :class="{ done: item.done }"><span>{{ item.done ? '✓' : Number(index) + 1 }}</span><b>{{locale.tr(item.label)}}</b><button v-if="!item.done && ['Academic year','Educational levels','Subjects'].includes(item.label)" @click="openAcademic(item.label)">{{locale.tr('Set up')}} →</button><button v-else-if="!item.done">{{locale.tr('Coming next')}}</button></li></ul></article>
          <article class="today-card"><div class="card-head"><div><span class="eyebrow">{{locale.tr('TODAY’S PULSE')}}</span><h2>{{locale.tr('Your first teaching day starts here')}}</h2></div><CalendarDays /></div><div class="empty-illustration"><div><i></i><i></i><i></i></div><b>{{locale.tr('No sessions scheduled yet')}}</b><p>{{locale.tr('Create a teaching group and its weekly schedule to see this centre’s classes here.')}}</p><button class="button secondary">{{locale.tr('Create teaching group')}}</button></div></article>
          <article class="subjects-card"><div class="card-head"><div><span class="eyebrow">{{locale.tr('ACADEMIC STRUCTURE')}}</span><h2>{{locale.tr(data.academic_year ? 'Ready to organise learning' : 'This centre is a clean slate')}}</h2></div><GraduationCap /></div><div v-if="data.academic_year" class="year-row"><span><CalendarDays /></span><div><small>{{locale.tr('ACTIVE ACADEMIC YEAR')}}</small><b>{{ data.academic_year.name }}</b></div><em>{{locale.tr('Active')}}</em></div><div v-else class="clean-slate"><BookOpen /><div><b>{{locale.tr('No academic year yet')}}</b><p>{{locale.tr('Add this centre’s first year, levels and subjects.')}}</p></div></div><div class="subject-list"><span v-for="subject in data.subjects" :key="subject.id"><i :style="{ background: subject.color }"></i><b>{{ locale.locale==='ar'?(subject.name_ar||subject.name_en):(subject.name_en||subject.name_ar) }}</b><small>{{ subject.code }}</small></span></div></article>
          <article class="activity-card"><div class="card-head"><div><span class="eyebrow">CENTRE ACTIVITY</span><h2>A clear record from day one</h2></div></div><div class="activity"><span>✓</span><div><b>Centre workspace active</b><p>Data on this screen is isolated from your other centres.</p><small>Now</small></div></div><div class="activity"><span>+</span><div><b>{{ data.subjects.length ? 'Academic defaults ready' : 'Ready for centre setup' }}</b><p>{{ data.subjects.length ? 'Year, levels and subjects are ready to edit.' : 'Start by creating an academic year.' }}</p><small>Now</small></div></div></article>
        </section>
      </div>
    </main>

    <div v-if="createOpen" class="modal-backdrop" @click.self="createOpen = false">
      <section class="centre-modal" role="dialog" aria-modal="true" aria-labelledby="new-centre-title">
        <button class="modal-close" @click="createOpen = false"><X /></button><span class="modal-icon"><Building2 /></span><span class="eyebrow">NEW OPERATIONAL WORKSPACE</span><h2 id="new-centre-title">Create another centre</h2><p>This centre starts with independent years, students, attendance, exams and finance.</p>
        <form @submit.prevent="createBranch"><div class="modal-grid"><label>Centre name in Arabic<input v-model="branchForm.name_ar" required placeholder="مركز مدينة نصر"></label><label>Centre name in English<input v-model="branchForm.name_en" placeholder="Nasr City Centre"></label><label>Governorate<input v-model="branchForm.governorate" placeholder="Cairo"></label><label>City or district<input v-model="branchForm.city" placeholder="Nasr City"></label><label class="full">Address<input v-model="branchForm.address" placeholder="Street, building and floor"></label><label class="full">Centre phone<input v-model="branchForm.phone" placeholder="+20 10 0000 0000"></label></div><div v-if="formError" class="form-error">{{ formError }}</div><div class="modal-actions"><button type="button" class="button secondary" @click="createOpen = false">Cancel</button><button class="button primary" :disabled="saving">{{ saving ? 'Creating centre…' : 'Create and switch' }}<Plus /></button></div></form>
      </section>
    </div>

    <div v-if="academicOpen" class="modal-backdrop" @click.self="academicOpen = false">
      <section class="centre-modal academic-modal" role="dialog" aria-modal="true" aria-labelledby="academic-title">
        <button class="modal-close" @click="academicOpen = false"><X /></button><span class="modal-icon"><GraduationCap /></span><span class="eyebrow">{{ data.branch.name_en || data.branch.name_ar }}</span><h2 id="academic-title">Academic structure</h2><p>Years, levels and subjects created here belong only to this centre.</p>
        <div class="academic-tabs"><button :class="{ active: academicTab === 'years' }" @click="academicTab = 'years'">Academic years <em>{{ structure.academic_years.length }}</em></button><button :class="{ active: academicTab === 'levels' }" @click="academicTab = 'levels'">Levels <em>{{ structure.levels.length }}</em></button><button :class="{ active: academicTab === 'subjects' }" @click="academicTab = 'subjects'">Subjects <em>{{ structure.subjects.length }}</em></button></div>
        <div class="academic-content">
          <div class="structure-list"><span class="structure-list-title">CURRENT IN THIS CENTRE</span><div v-if="academicTab === 'years'" class="structure-items"><article v-for="year in structure.academic_years" :key="year.id"><span><CalendarDays /></span><div><b>{{ year.name }}</b><small>{{ year.starts_on }} → {{ year.ends_on }}</small></div><em :class="{ live: year.is_active }">{{ year.is_active ? 'Active' : year.status }}</em></article><p v-if="!structure.academic_years.length">No academic years yet.</p></div><div v-if="academicTab === 'levels'" class="structure-items"><article v-for="level in structure.levels" :key="level.id"><span><GraduationCap /></span><div><b>{{ level.name_en || level.name_ar }}</b><small>{{ level.name_ar }} · {{ level.stage || 'Custom' }}</small></div></article><p v-if="!structure.levels.length">No educational levels yet.</p></div><div v-if="academicTab === 'subjects'" class="structure-items"><article v-for="subject in structure.subjects" :key="subject.id"><i :style="{ background: subject.color }"></i><div><b>{{ subject.name_en || subject.name_ar }}</b><small>{{ subject.name_ar }} · {{ subject.code }}</small></div></article><p v-if="!structure.subjects.length">No subjects yet.</p></div></div>
          <form class="structure-form" @submit.prevent="saveAcademic"><span class="structure-list-title">ADD NEW</span><template v-if="academicTab === 'years'"><label>Display name<input v-model="yearForm.name" required placeholder="2027/2028"></label><div class="inline-fields"><label>Starts on<input v-model="yearForm.starts_on" type="date" required></label><label>Ends on<input v-model="yearForm.ends_on" type="date" required></label></div><label>Status<select v-model="yearForm.status"><option value="upcoming">Upcoming</option><option value="active">Active</option><option value="closed">Closed</option><option value="archived">Archived</option></select></label><label class="toggle-label"><input v-model="yearForm.is_active" type="checkbox">Make this the active academic year</label></template><template v-if="academicTab === 'levels'"><label>Name in Arabic<input v-model="levelForm.name_ar" required placeholder="الصف الثالث الثانوي"></label><label>Name in English<input v-model="levelForm.name_en" placeholder="Secondary 3"></label><div class="inline-fields"><label>Stage<select v-model="levelForm.stage"><option value="primary">Primary</option><option value="preparatory">Preparatory</option><option value="secondary">Secondary</option><option value="custom">Custom</option></select></label><label>Order<input v-model.number="levelForm.sort_order" type="number" min="0"></label></div></template><template v-if="academicTab === 'subjects'"><label>Name in Arabic<input v-model="subjectForm.name_ar" required placeholder="الفيزياء"></label><label>Name in English<input v-model="subjectForm.name_en" placeholder="Physics"></label><div class="inline-fields"><label>Short code<input v-model="subjectForm.code" required placeholder="PHY"></label><label>Colour<input v-model="subjectForm.color" type="color"></label></div></template><div v-if="formError" class="form-error">{{ formError }}</div><button class="button primary wide" :disabled="saving">{{ saving ? 'Saving…' : 'Add to this centre' }}<Plus /></button></form>
        </div>
      </section>
    </div>
    <TeamModal v-if="teamOpen" @close="teamOpen = false" />
    <AuditLogModal v-if="auditOpen" @close="auditOpen = false" />
  </div>
</template>
