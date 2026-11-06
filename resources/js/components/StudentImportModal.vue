<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { CheckCircle2, Download, FileSpreadsheet, LoaderCircle, Upload, X } from '@lucide/vue'
import api from '../api'
import { useLocaleStore } from '../stores/locale'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; completed: [] }>()
const locale = useLocaleStore()
const file = ref<File | null>(null)
const importRun = ref<any>(null)
const uploading = ref(false)
const committing = ref(false)
const error = ref('')
let pollTimer: number | undefined
const processing = computed(() => ['queued', 'processing'].includes(importRun.value?.status))

function stopPolling() {
  if (pollTimer !== undefined) window.clearInterval(pollTimer)
  pollTimer = undefined
}

function reset() {
  stopPolling()
  file.value = null
  importRun.value = null
  error.value = ''
}

async function refresh() {
  if (!importRun.value?.public_id) return
  try {
    importRun.value = (await api.get(`/students/imports/${importRun.value.public_id}`)).data
    if (importRun.value.status === 'completed') emit('completed')
    if (['completed', 'failed'].includes(importRun.value.status)) stopPolling()
  } catch (e: any) {
    error.value = e.response?.data?.message || locale.tr('Unable to read import progress.')
    stopPolling()
  }
}

async function stageFile() {
  if (!file.value) return
  uploading.value = true
  error.value = ''
  importRun.value = null
  const form = new FormData()
  form.append('file', file.value)
  try {
    importRun.value = (await api.post('/students/imports', form)).data
  } catch (e: any) {
    error.value = e.response?.data?.message || locale.tr('Unable to stage this file.')
  } finally {
    uploading.value = false
  }
}

async function commitImport() {
  if (!importRun.value?.public_id) return
  committing.value = true
  error.value = ''
  try {
    importRun.value = (await api.post(`/students/imports/${importRun.value.public_id}/commit`)).data
    if (processing.value) pollTimer = window.setInterval(refresh, 1200)
    else if (importRun.value.status === 'completed') emit('completed')
  } catch (e: any) {
    error.value = e.response?.data?.message || locale.tr('Unable to start this import.')
  } finally {
    committing.value = false
  }
}

function downloadErrors() {
  if (importRun.value?.public_id) window.location.assign(`/api/students/imports/${importRun.value.public_id}/errors`)
}

watch(() => props.open, (open) => { if (open) reset(); else stopPolling() })
onUnmounted(stopPolling)
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
    <section class="centre-modal import-modal" role="dialog" aria-modal="true" aria-labelledby="student-import-title">
      <button class="modal-close" :aria-label="locale.tr('Close')" @click="emit('close')"><X/></button>
      <span class="modal-icon"><FileSpreadsheet/></span>
      <span class="eyebrow">{{locale.tr('STUDENT DATA')}}</span>
      <h2 id="student-import-title">{{locale.tr('Import students from a spreadsheet')}}</h2>
      <p>{{locale.tr('Upload a CSV or XLSX file. Review every row before importing valid records into this centre.')}}</p>
      <div class="modal-scroll-body">
      <p class="import-required-columns">{{locale.tr('Required columns: name_ar, guardian_name, guardian_phone. Optional columns can be left blank.')}}</p>

      <a class="import-template-link" href="/api/students/import-template"><Download/>{{locale.tr('Download the CSV template')}}</a>

      <form v-if="!importRun" class="import-upload" @submit.prevent="stageFile">
        <label>{{locale.tr('Spreadsheet file')}}
          <input type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required @change="file=($event.target as HTMLInputElement).files?.[0]||null">
          <small>{{locale.tr('CSV or XLSX, up to 5 MB and 5,000 student rows.')}}</small>
        </label>
        <div class="modal-actions"><button type="button" class="button secondary" @click="emit('close')">{{locale.tr('Cancel')}}</button><button class="button primary" :disabled="!file||uploading"><LoaderCircle v-if="uploading" class="spin"/><Upload v-else/>{{uploading?locale.tr('Preparing preview…'):locale.tr('Preview import')}}</button></div>
      </form>

      <template v-else>
        <div class="import-summary"><span><b>{{importRun.total_rows}}</b>{{locale.tr('Rows found')}}</span><span><b>{{importRun.valid_rows}}</b>{{locale.tr('Ready to import')}}</span><span class="invalid"><b>{{importRun.invalid_rows}}</b>{{locale.tr('Need correction')}}</span></div>
        <div class="import-preview-scroll"><table class="import-preview"><thead><tr><th>#</th><th>{{locale.tr('Student')}}</th><th>{{locale.tr('Guardian')}}</th><th>{{locale.tr('Validation')}}</th></tr></thead><tbody>
          <tr v-for="row in importRun.preview" :key="row.row_number" :class="{invalid:row.errors.length}"><td>{{row.row_number}}</td><td>{{row.data.name_ar}}<small>{{row.data.name_en}}</small></td><td>{{row.data.guardian_name}}<small>{{row.data.guardian_phone}}</small></td><td>{{row.errors.length?row.errors.join(' '):locale.tr('Ready')}}</td></tr>
        </tbody></table></div>
        <p v-if="importRun.total_rows>importRun.preview.length" class="import-preview-note">{{locale.tr('Showing the first 50 rows. Download the error file for all invalid rows.')}}</p>
        <div v-if="processing" class="import-progress"><div><span>{{locale.tr('Importing students…')}}</span><b>{{importRun.imported_rows}} / {{importRun.valid_rows}}</b></div><progress :max="importRun.valid_rows||1" :value="importRun.imported_rows"></progress></div>
        <div v-else-if="importRun.status==='completed'" class="import-complete"><CheckCircle2/>{{locale.tr('Import complete')}} · {{importRun.imported_rows}} {{locale.tr('students imported')}}</div>
        <div v-else-if="importRun.status==='failed'" class="form-error">{{locale.tr('The import stopped before all rows could be processed. Download the error file for details.')}}</div>
        <div class="modal-actions import-actions">
          <button v-if="importRun.invalid_rows||importRun.status==='failed'" type="button" class="button secondary" @click="downloadErrors"><Download/>{{locale.tr('Download error file')}}</button>
          <button type="button" class="button secondary" @click="reset">{{locale.tr('Choose another file')}}</button>
          <button v-if="importRun.status==='preview'" class="button primary" :disabled="!importRun.valid_rows||committing" @click="commitImport"><LoaderCircle v-if="committing" class="spin"/><Upload v-else/>{{locale.tr('Import valid rows')}}</button>
          <button v-else type="button" class="button primary" @click="emit('close')">{{locale.tr('Done')}}</button>
        </div>
      </template>
      <div v-if="error" class="form-error">{{error}}</div>
      </div>
    </section>
  </div>
</template>
