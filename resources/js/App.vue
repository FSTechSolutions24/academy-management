<script setup lang="ts">
import { onMounted } from 'vue'
import LanguageToggle from './components/LanguageToggle.vue'
import { useLocaleStore } from './stores/locale'
const locale=useLocaleStore();onMounted(()=>locale.apply())
</script>
<template><RouterView/><LanguageToggle/></template>
