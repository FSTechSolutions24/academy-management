<script setup lang="ts">defineProps<{ light?: boolean; compact?: boolean }>()</script>
<template><RouterLink to="/" class="brand" :class="{light,compact}" aria-label="AcademyHub home"><span class="brand-mark"><i></i><b>A</b></span><span v-if="!compact" class="brand-copy"><strong>Academy<span>Hub</span></strong><small>Learn · Manage · Grow</small></span></RouterLink></template>
