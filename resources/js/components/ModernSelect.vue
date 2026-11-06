<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { Check, ChevronDown } from '@lucide/vue'

type Option = { value: string | number; label: string; disabled?: boolean }
const props = withDefaults(defineProps<{ modelValue: string | number | null | undefined; options: Option[]; placeholder?: string; disabled?: boolean }>(), { placeholder: 'Select an option', disabled: false })
const emit = defineEmits<{ 'update:modelValue': [string | number | null] }>()
const open = ref(false)
const root = ref<HTMLElement | null>(null)
const activeIndex = ref(-1)
const selected = computed(() => props.options.find((option) => String(option.value) === String(props.modelValue)))
const label = computed(() => selected.value?.label || props.placeholder)

function close(event?: MouseEvent) { if (!event || !root.value?.contains(event.target as Node)) open.value = false }
function choose(option: Option) { if (option.disabled) return; emit('update:modelValue', option.value); open.value = false }
function toggle() { if (!props.disabled) { open.value = !open.value; if (open.value) nextTick(() => { activeIndex.value = Math.max(0, props.options.findIndex((item) => String(item.value) === String(props.modelValue))) }) } }
function keydown(event: KeyboardEvent) {
  if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) event.preventDefault()
  if (event.key === 'Escape') return void (open.value = false)
  if (!open.value && ['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) return toggle()
  if (event.key === 'ArrowDown') activeIndex.value = Math.min(props.options.length - 1, activeIndex.value + 1)
  if (event.key === 'ArrowUp') activeIndex.value = Math.max(0, activeIndex.value - 1)
  if (event.key === 'Enter' && activeIndex.value >= 0) choose(props.options[activeIndex.value])
}
document.addEventListener('click', close)
onBeforeUnmount(() => document.removeEventListener('click', close))
</script>

<template>
  <div ref="root" class="modern-select" :class="{ open, disabled }">
    <button type="button" class="modern-select-trigger" :disabled="disabled" @click.stop="toggle" @keydown="keydown">
      <span :class="{ muted: !selected }">{{ label }}</span><ChevronDown class="modern-select-chevron" />
    </button>
    <Transition name="select-menu">
      <div v-if="open" class="modern-select-menu" role="listbox">
        <button v-for="(option, index) in options" :key="String(option.value)" type="button" class="modern-select-option" :class="{ selected: String(option.value) === String(modelValue), active: index === activeIndex }" :disabled="option.disabled" role="option" :aria-selected="String(option.value) === String(modelValue)" @mouseenter="activeIndex = index" @click="choose(option)">
          <span>{{ option.label }}</span><Check v-if="String(option.value) === String(modelValue)" />
        </button>
      </div>
    </Transition>
  </div>
</template>
