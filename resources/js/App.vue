<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, watch } from 'vue'
import LanguageToggle from './components/LanguageToggle.vue'
import TeacherAttendanceSidebar from './components/TeacherAttendanceSidebar.vue'
import { useRoute } from 'vue-router'
import { useLocaleStore } from './stores/locale'
const locale=useLocaleStore();const route=useRoute();let observer:MutationObserver|null=null;const originalText=new WeakMap<Text,string>();const originalAttrs=new WeakMap<HTMLElement,Record<string,string>>()
function translateDom(){const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let node:Node|null;while((node=walker.nextNode())){const text=node as Text;if(!originalText.has(text))originalText.set(text,text.nodeValue||'');const source=originalText.get(text)||'';const trimmed=source.trim();if(!trimmed)continue;const translated=locale.translateText(trimmed);text.nodeValue=locale.locale==='ar'?source.replace(trimmed,translated):source}document.querySelectorAll<HTMLElement>('[placeholder],[title],[aria-label]').forEach(element=>{const attrs=originalAttrs.get(element)||{};for(const key of ['placeholder','title','aria-label']){const value=element.getAttribute(key);if(value&&!attrs[key])attrs[key]=value;const source=attrs[key];if(source)element.setAttribute(key,locale.locale==='ar'?locale.translateText(source):source)}originalAttrs.set(element,attrs)})}
onMounted(()=>{locale.apply();translateDom();observer=new MutationObserver(()=>translateDom());observer.observe(document.body,{childList:true,subtree:true});});watch(()=>locale.locale,async()=>{locale.apply();await nextTick();translateDom()});onUnmounted(()=>observer?.disconnect())
</script>
<template><TeacherAttendanceSidebar v-if="route.path === '/teacher/attendance'"/><RouterView/><LanguageToggle/></template>
