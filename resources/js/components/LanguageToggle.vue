<script setup lang="ts">
import { Languages } from '@lucide/vue'
import { useLocaleStore } from '../stores/locale'
const locale=useLocaleStore()
</script>
<template><button class="language-toggle" :title="locale.tr(locale.locale==='en'?'Switch to Arabic':'Switch to English')" @click="locale.toggle"><Languages/><span>{{locale.locale==='en'?'العربية':'English'}}</span></button></template>
