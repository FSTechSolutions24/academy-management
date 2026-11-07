<script setup lang="ts">
import { Check, MessageCircle, Plus, RefreshCw, Send, ShieldCheck } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import api from '../api'
import TeacherAttendanceSidebar from '../components/TeacherAttendanceSidebar.vue'
import { useLocaleStore } from '../stores/locale'

const locale = useLocaleStore()
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const students = ref<any[]>([])
const templates = ref<any[]>([])
const consents = ref<any[]>([])
const consentForSelected = ref<any>(null)
const messages = ref<any[]>([])
const messagePagination = ref({ page: 1, last_page: 1, total: 0 })
const allowed = ref(false)
const studentSearch = ref('')
const studentId = ref('')
const guardianId = ref('')
const method = ref('written')
const optedIn = ref(true)
const consentNotes = ref('')
const templateName = ref('')
const providerTemplateName = ref('')
const templateLanguage = ref('en')
const variableNames = ref('')
const templatePreview = ref('')
const selectedTemplateId = ref('')
const parameters = ref<string[]>([])
const selectedStudent = computed(() => students.value.find((student) => student.public_id === studentId.value))
const guardians = computed(() => selectedStudent.value?.guardians || [])
const selectedGuardian = computed(() => guardians.value.find((guardian: any) => String(guardian.id) === guardianId.value))
const currentConsent = computed(() => consentForSelected.value)
const selectedTemplate = computed(() => templates.value.find((item) => String(item.id) === selectedTemplateId.value))
const templateVariables = computed(() => selectedTemplate.value?.variables || [])
const personName = (item: any) => locale.locale === 'ar' ? (item?.name_ar || item?.name_en || item?.name) : (item?.name_en || item?.name || item?.name_ar)

async function loadStudents() {
  const response = await api.get('/students', { params: { search: studentSearch.value || undefined, page: 1 } })
  students.value = response.data.students
  if (studentId.value && !students.value.some((student) => student.public_id === studentId.value)) studentId.value = ''
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const dashboard = (await api.get('/dashboard')).data
    allowed.value = Boolean(dashboard.membership?.capabilities?.send_messages)
    if (!allowed.value) return
    const [templateResponse, consentResponse, messageResponse] = await Promise.all([
      api.get('/communications/templates'), api.get('/communications/consents'), api.get('/communications/messages'), loadStudents(),
    ])
    templates.value = templateResponse.data.templates
    consents.value = consentResponse.data.consents
    messages.value = messageResponse.data.messages
    messagePagination.value = messageResponse.data.pagination
  } catch (exception: any) {
    error.value = exception.response?.data?.message || locale.tr('Unable to load communications.')
  } finally {
    loading.value = false
  }
}

async function saveConsent() {
  if (!selectedStudent.value || !selectedGuardian.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    await api.post('/communications/consents', { student_id: studentId.value, guardian_id: Number(guardianId.value), is_opted_in: optedIn.value, method: optedIn.value ? method.value : 'withdrawn', notes: consentNotes.value || null })
    notice.value = locale.tr(optedIn.value ? 'Consent saved.' : 'Consent withdrawn.')
    consentNotes.value = ''
    await refreshLists()
  } catch (exception: any) { error.value = exception.response?.data?.message || locale.tr('Unable to update consent.') }
  finally { busy.value = false }
}

async function createTemplate() {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const variables = variableNames.value.split(',').map((value) => value.trim()).filter(Boolean)
    const response = await api.post('/communications/templates', { name: templateName.value, provider_template_name: providerTemplateName.value, language: templateLanguage.value, category: 'utility', variables, body_preview: templatePreview.value || null })
    templates.value.push(response.data.template)
    templateName.value = ''; providerTemplateName.value = ''; variableNames.value = ''; templatePreview.value = ''
    notice.value = locale.tr('Template added.')
  } catch (exception: any) { error.value = exception.response?.data?.message || locale.tr('Unable to save template.') }
  finally { busy.value = false }
}

async function queueMessage() {
  if (!selectedStudent.value || !selectedGuardian.value || !selectedTemplate.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const response = await api.post('/communications/messages', { student_id: studentId.value, guardian_id: Number(guardianId.value), template_id: Number(selectedTemplateId.value), parameters: parameters.value })
    messages.value.unshift(response.data.message)
    notice.value = locale.tr('Message added to the outbox.')
    await refreshLists()
  } catch (exception: any) { error.value = exception.response?.data?.message || locale.tr('Unable to queue this message.') }
  finally { busy.value = false }
}

async function retryMessage(message: any) {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const response = await api.post(`/communications/messages/${message.id}/retry`)
    Object.assign(message, response.data.message)
    notice.value = locale.tr('Message queued for retry.')
  } catch (exception: any) { error.value = exception.response?.data?.message || locale.tr('Unable to retry this message.') }
  finally { busy.value = false }
}

async function refreshLists() {
  const [consentResponse, messageResponse] = await Promise.all([api.get('/communications/consents'), api.get('/communications/messages', { params: { page: messagePagination.value.page } })])
  consents.value = consentResponse.data.consents
  messages.value = messageResponse.data.messages
  messagePagination.value = messageResponse.data.pagination
  await refreshSelectedConsent()
}

async function changeMessagePage(page: number) {
  if (page < 1 || page > messagePagination.value.last_page || busy.value) return
  busy.value = true
  try {
    const response = await api.get('/communications/messages', { params: { page } })
    messages.value = response.data.messages
    messagePagination.value = response.data.pagination
  } catch (exception: any) { error.value = exception.response?.data?.message || locale.tr('Unable to load communications.') }
  finally { busy.value = false }
}

async function refreshSelectedConsent() {
  consentForSelected.value = null
  if (!studentId.value || !guardianId.value) return
  const response = await api.get('/communications/consents', { params: { student_id: studentId.value, guardian_id: Number(guardianId.value) } })
  consentForSelected.value = response.data.consents[0] || null
}

watch(studentSearch, () => { loadStudents().catch(() => {}) })
watch(studentId, () => { guardianId.value = ''; optedIn.value = true })
watch(guardianId, () => {
  refreshSelectedConsent().then(() => { optedIn.value = currentConsent.value ? Boolean(currentConsent.value.is_opted_in) : true }).catch(() => { consentForSelected.value = null })
})
watch(selectedTemplateId, () => { parameters.value = templateVariables.value.map(() => '') })
onMounted(loadData)
</script>

<template>
  <div class="app-shell teacher-shell communications-workspace">
    <TeacherAttendanceSidebar />
    <main class="dashboard-main">
      <header><div class="global-search"><MessageCircle/><span>{{locale.tr('Parent communications')}}</span></div><RouterLink class="button secondary" to="/teacher/overview">{{locale.tr('Teacher workspace')}}</RouterLink></header>
      <div v-if="loading" class="loading-state"><span></span>{{locale.tr('Loading communications…')}}</div>
      <div v-else class="dashboard-content teacher-content communications-content">
        <div class="teacher-page-head"><div><span>{{locale.tr('FAMILY CONTACT')}}</span><h1>{{locale.tr('Parent communications')}}</h1><p>{{locale.tr('Record guardian preferences and send approved WhatsApp updates.')}}</p></div></div>
        <div v-if="!allowed" class="teacher-card communication-notice"><ShieldCheck/><div><b>{{locale.tr('Message permission required')}}</b><p>{{locale.tr('Ask an organisation administrator to grant send_messages access.')}}</p></div></div>
        <template v-else>
          <div v-if="error" class="form-error">{{error}}</div><div v-if="notice" class="communication-success"><Check/>{{notice}}</div>
          <section class="communication-grid">
            <article class="teacher-card communication-card">
              <div class="teacher-card-head"><div><small>{{locale.tr('PARENT PREFERENCE')}}</small><h2>{{locale.tr('Guardian consent')}}</h2></div><ShieldCheck/></div>
              <label>{{locale.tr('Find a student')}}<input v-model="studentSearch" :placeholder="locale.tr('Search by student name or code')"></label>
              <label>{{locale.tr('Student')}}<select v-model="studentId"><option value="">{{locale.tr('Select student')}}</option><option v-for="student in students" :key="student.public_id" :value="student.public_id">{{personName(student)}} · {{student.student_code}}</option></select></label>
              <label>{{locale.tr('Guardian')}}<select v-model="guardianId" :disabled="!guardians.length"><option value="">{{locale.tr('Select guardian')}}</option><option v-for="guardian in guardians" :key="guardian.id" :value="String(guardian.id)">{{guardian.name}} · {{guardian.whatsapp_phone||guardian.phone}}</option></select></label>
              <div v-if="selectedGuardian" class="consent-state" :class="currentConsent?.is_opted_in?'enabled':'disabled'"><span>{{locale.tr('Current status')}}</span><b>{{locale.tr(currentConsent?.is_opted_in?'Opted in':'Not opted in')}}</b></div>
              <label class="consent-toggle"><input v-model="optedIn" type="checkbox"><span>{{locale.tr('Guardian has agreed to WhatsApp updates')}}</span></label>
              <label v-if="optedIn">{{locale.tr('Consent captured by')}}<select v-model="method"><option value="written">{{locale.tr('Written')}}</option><option value="verbal">{{locale.tr('Verbal')}}</option><option value="online">{{locale.tr('Online')}}</option></select></label>
              <label>{{locale.tr('Consent note')}}<textarea v-model="consentNotes" rows="2" :placeholder="locale.tr('Optional reference or opt-out details')"></textarea></label>
              <button class="button primary" :disabled="busy||!selectedGuardian" @click="saveConsent">{{locale.tr(optedIn?'Save consent':'Withdraw consent')}}<Check/></button>
            </article>

            <article class="teacher-card communication-card">
              <div class="teacher-card-head"><div><small>{{locale.tr('APPROVED CONTENT')}}</small><h2>{{locale.tr('Utility templates')}}</h2></div><Plus/></div>
              <div v-if="!templates.length" class="teacher-empty compact"><MessageCircle/><b>{{locale.tr('No templates added yet.')}}</b><p>{{locale.tr('Add the exact approved template name from WhatsApp Manager.')}}</p></div>
              <div v-else class="template-list"><div v-for="template in templates" :key="template.id"><b>{{template.name}}</b><small>{{template.provider_template_name}} · {{template.language}} · {{template.variables?.length||0}} {{locale.tr('variables')}}</small></div></div>
              <form class="template-form" @submit.prevent="createTemplate"><label>{{locale.tr('Display name')}}<input v-model="templateName" required maxlength="100"></label><label>{{locale.tr('Approved WhatsApp template name')}}<input v-model="providerTemplateName" required pattern="[A-Za-z0-9_-]+" maxlength="100" placeholder="class_update"></label><div class="modal-grid"><label>{{locale.tr('Language code')}}<input v-model="templateLanguage" required maxlength="12" placeholder="en"></label><label>{{locale.tr('Body variables, in order')}}<input v-model="variableNames" :placeholder="locale.tr('student, date')"></label></div><label>{{locale.tr('Message preview')}}<textarea v-model="templatePreview" rows="2"></textarea></label><button class="button secondary" :disabled="busy||!templateName||!providerTemplateName"><Plus/>{{locale.tr('Add utility template')}}</button></form>
            </article>

            <article class="teacher-card communication-card send-card">
              <div class="teacher-card-head"><div><small>{{locale.tr('CONSENT REQUIRED')}}</small><h2>{{locale.tr('Queue a WhatsApp update')}}</h2></div><Send/></div>
              <div class="send-summary"><b>{{personName(selectedGuardian)||locale.tr('Choose a guardian')}}</b><span>{{selectedGuardian?.whatsapp_phone||selectedGuardian?.phone||locale.tr('No phone selected')}}</span><em :class="currentConsent?.is_opted_in?'enabled':'disabled'">{{locale.tr(currentConsent?.is_opted_in?'Opted in':'Consent required')}}</em></div>
              <label>{{locale.tr('Approved template')}}<select v-model="selectedTemplateId"><option value="">{{locale.tr('Select template')}}</option><option v-for="template in templates" :key="template.id" :value="String(template.id)">{{template.name}}</option></select></label>
              <label v-for="(variable,index) in templateVariables" :key="variable">{{variable}}<input v-model="parameters[Number(index)]" required maxlength="500"></label>
              <button class="button primary" :disabled="busy||!selectedGuardian||!currentConsent?.is_opted_in||!selectedTemplate" @click="queueMessage"><Send/>{{locale.tr('Queue message')}}</button>
              <p class="communication-hint">{{locale.tr('Only approved utility templates can be sent. The worker checks consent again before delivery.')}}</p>
            </article>

            <article class="teacher-card communication-card outbox-card">
              <div class="teacher-card-head"><div><small>{{locale.tr('MESSAGE HISTORY')}}</small><h2>{{locale.tr('Outbox')}}</h2></div><MessageCircle/></div>
              <div v-if="!messages.length" class="teacher-empty compact"><MessageCircle/><b>{{locale.tr('No messages in the outbox yet.')}}</b></div>
              <div v-else class="outbox-list"><div v-for="message in messages" :key="message.id" class="outbox-row"><span class="outbox-icon"><MessageCircle/></span><div class="outbox-copy"><b>{{personName(message.guardian)}} · {{personName(message.student)}}</b><small>{{message.template_name}} · {{message.to_phone}}</small><small v-if="message.last_error" class="outbox-error">{{message.last_error}}</small></div><span class="outbox-status" :class="message.status">{{locale.tr(message.status)}}</span><button v-if="message.status==='failed'" class="icon-button" :disabled="busy" :title="locale.tr('Retry message')" @click="retryMessage(message)"><RefreshCw/></button></div></div>
              <div v-if="messagePagination.total" class="outbox-pagination"><span>{{messagePagination.page}} / {{messagePagination.last_page}} · {{messagePagination.total}}</span><div><button class="button secondary" :disabled="busy||messagePagination.page<=1" @click="changeMessagePage(messagePagination.page-1)">{{locale.tr('Previous')}}</button><button class="button secondary" :disabled="busy||messagePagination.page>=messagePagination.last_page" @click="changeMessagePage(messagePagination.page+1)">{{locale.tr('Next')}}</button></div></div>
              <p class="communication-hint">{{locale.tr('Sent means accepted by WhatsApp. Delivery receipts are not connected yet.')}}</p>
            </article>
          </section>
        </template>
      </div>
    </main>
  </div>
</template>

<style scoped>
.communications-workspace{min-height:100vh}.communications-content{max-width:1480px}.communication-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.communication-card{padding:22px;display:flex;flex-direction:column;gap:14px;min-width:0}.communication-card label{display:flex;flex-direction:column;gap:7px;font-size:11px;font-weight:700;color:#3d3a59}.communication-card input,.communication-card select,.communication-card textarea{width:100%;border:1px solid #e5e2ef;border-radius:10px;padding:11px 12px;background:#fff;color:#28253e;font:inherit;font-size:12px;outline:none}.communication-card input:focus,.communication-card select:focus,.communication-card textarea:focus{border-color:#7868ff;box-shadow:0 0 0 3px #7868ff1c}.communication-card textarea{resize:vertical}.communication-card .button{align-self:flex-start}.consent-state{display:flex;justify-content:space-between;align-items:center;border-radius:10px;padding:11px 13px;background:#f2f1fa;font-size:11px}.consent-state b,.outbox-status{font-size:10px}.enabled{color:#16784d!important}.disabled{color:#a05439!important}.consent-toggle{display:flex!important;flex-direction:row!important;align-items:center;gap:9px!important}.consent-toggle input{width:16px;height:16px;accent-color:#6d5dfc}.template-list,.outbox-list{display:flex;flex-direction:column;gap:8px}.template-list>div,.outbox-row{border:1px solid #eeecf4;border-radius:10px;padding:11px;display:flex;flex-direction:column;gap:5px}.template-list b,.outbox-copy b{font-size:11px}.template-list small,.outbox-copy small{font-size:9px;color:#85829b}.template-form{display:flex;flex-direction:column;gap:11px;padding-top:12px;border-top:1px solid #eeecf4}.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.send-summary{display:grid;grid-template-columns:1fr auto;gap:5px 10px;padding:12px;border-radius:10px;background:#f8f7fc}.send-summary b{font-size:12px}.send-summary span{font-size:10px;color:#89869e}.send-summary em{grid-column:1/-1;font-size:10px;font-style:normal}.communication-hint{font-size:10px;line-height:1.6;color:#8a879f;margin:0}.communication-success{display:flex;align-items:center;gap:8px;border:1px solid #bce5d1;border-radius:10px;background:#f0faf5;color:#19754c;padding:11px 14px;font-size:12px}.communication-notice{display:flex;align-items:center;gap:14px;padding:22px}.communication-notice p{margin:5px 0 0;color:#827f96;font-size:12px}.outbox-row{flex-direction:row;align-items:center;gap:10px}.outbox-icon{width:32px;height:32px;display:grid;place-items:center;background:#f0eeff;border-radius:9px;color:#6d5dfc;flex:none}.outbox-copy{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}.outbox-copy small{overflow-wrap:anywhere}.outbox-error{color:#b54735!important}.outbox-status{border-radius:20px;padding:5px 8px;background:#f1eff8;color:#69647e}.outbox-status.sent{background:#eaf8f0;color:#18764d}.outbox-status.failed{background:#fff0ed;color:#b54735}.outbox-status.sending{background:#fff7e7;color:#8a6414}.icon-button{border:0;background:transparent;color:#6d5dfc;cursor:pointer}.teacher-page-head p{max-width:620px}@media(max-width:900px){.communication-grid{grid-template-columns:1fr}}@media(max-width:760px){.communications-workspace .teacher-global-sidebar{display:none}.communications-workspace .dashboard-main{margin-left:0}.outbox-row{align-items:flex-start;flex-wrap:wrap}.outbox-status{margin-left:42px}.modal-grid{grid-template-columns:1fr}}
.outbox-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#85829b;font-size:10px}.outbox-pagination>div{display:flex;gap:7px}.outbox-pagination .button{padding:7px 10px;font-size:10px}
</style>
