<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ cards: any[]; centreName: string }>()
const emit = defineEmits<{ close: [] }>()
function printCards() { window.print() }

const patterns = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112']

function barcode(token: string) {
  const pairs = token.match(/\d{2}/g) || []
  const values = pairs.map(Number)
  const checksum = (105 + values.reduce((sum, value, index) => sum + value * (index + 1), 0)) % 103
  const encoded = [105, ...values, checksum, 106].map(value => patterns[value]).join('')
  const bars: { x: number; width: number }[] = []
  let x = 10
  for (let index = 0; index < encoded.length; index++) {
    const width = Number(encoded[index])
    if (index % 2 === 0) bars.push({ x, width })
    x += width
  }
  return { bars, width: x + 10 }
}

const printable = computed(() => props.cards.map((card: any) => ({ ...card, barcode: barcode(card.token) })))
</script>

<template>
  <div class="modal-overlay card-print-overlay" @click.self="emit('close')">
    <section class="modal-card student-card-print-modal">
      <div class="card-print-actions"><div><h2>Student cards</h2><p>Each barcode contains a revocable card token. Scan it in attendance.</p></div><button class="button secondary" @click="emit('close')">Close</button><button class="button primary" @click="printCards">Print / Save as PDF</button></div>
      <div id="student-card-print-page" class="student-card-grid">
        <article v-for="card in printable" :key="card.student.public_id" class="student-id-card">
          <header><b>ACADEMYHUB</b><small>{{ centreName }}</small></header>
          <strong>{{ card.student.name_en || card.student.name_ar }}</strong>
          <small>{{ card.student.student_code }}</small>
          <svg class="student-card-barcode" :viewBox="`0 0 ${card.barcode.width} 54`" role="img" :aria-label="`Barcode for ${card.student.student_code}`">
            <rect v-for="(bar, index) in card.barcode.bars" :key="index" :x="bar.x" y="0" :width="bar.width" height="48" fill="#111" />
          </svg>
          <code>{{ card.token }}</code>
        </article>
      </div>
    </section>
  </div>
</template>

<style>
.student-card-print-modal{width:min(900px,100%);max-height:calc(100vh - 28px);overflow:auto}.card-print-actions{display:flex;align-items:center;gap:8px;margin-bottom:18px}.card-print-actions>div{margin-right:auto}.card-print-actions h2{margin:0 0 5px}.card-print-actions p{margin:0;font-size:10px;color:#777385}.student-card-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10mm}.student-id-card{height:50mm;padding:5mm;border:1px solid #d9d6e0;border-radius:3mm;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2mm;text-align:center;overflow:hidden}.student-id-card header{width:100%;display:flex;justify-content:space-between;gap:5px;align-items:center;color:#6658df;font-size:9pt}.student-id-card header small{font-size:7pt;color:#777}.student-id-card>strong{font-size:13pt}.student-id-card>small{font-size:8pt;color:#666}.student-card-barcode{width:90%;height:14mm;max-width:100%}.student-id-card code{font:8pt monospace;letter-spacing:1.5px}@media print{@page{size:A4 portrait;margin:8mm}body *{visibility:hidden!important}.card-print-overlay,.card-print-overlay *{visibility:visible!important}.card-print-overlay{position:absolute!important;inset:0!important;padding:0!important;background:#fff!important;display:block!important}.student-card-print-modal{width:100%!important;max-width:none!important;max-height:none!important;overflow:visible!important;padding:0!important;box-shadow:none!important;border:0!important}.card-print-actions{display:none!important}.student-card-grid{grid-template-columns:repeat(2,85mm);grid-auto-rows:50mm;gap:5mm;justify-content:center}.student-id-card{width:85mm;height:50mm;break-inside:avoid;page-break-inside:avoid;border-color:#aaa;print-color-adjust:exact;-webkit-print-color-adjust:exact}}@media(max-width:600px){.card-print-actions{align-items:flex-start;flex-wrap:wrap}.card-print-actions>div{flex-basis:100%}.student-card-grid{grid-template-columns:1fr}}
</style>
