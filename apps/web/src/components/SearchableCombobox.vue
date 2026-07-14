<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string | null | undefined
  options: string[]
  placeholder?: string
  id?: string
  name?: string
  disabled?: boolean
}>(), {
  modelValue: '',
  placeholder: '',
  id: undefined,
  name: undefined,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const root = ref<HTMLElement | null>(null)
const isOpen = ref(false)
const activeIndex = ref(-1)

const inputValue = computed(() => props.modelValue ?? '')
const listboxId = computed(() => props.id ? props.id + '-listbox' : undefined)
const activeOptionId = computed(() =>
  activeIndex.value >= 0 && filteredOptions.value[activeIndex.value]
    ? optionId(activeIndex.value)
    : undefined,
)
const filteredOptions = computed(() => {
  const query = inputValue.value.trim().toLowerCase()

  if (query === '') {
    return props.options
  }

  return props.options.filter((option) => option.toLowerCase().includes(query))
})

function optionId(index: number): string | undefined {
  return props.id ? props.id + '-option-' + index : undefined
}

function openDropdown(): void {
  if (props.disabled) {
    return
  }

  isOpen.value = true
  activeIndex.value = filteredOptions.value.length > 0 ? 0 : -1
}

function closeDropdown(): void {
  isOpen.value = false
  activeIndex.value = -1
}

function updateValue(value: string): void {
  emit('update:modelValue', value)
  isOpen.value = true
  activeIndex.value = 0
}

function selectOption(option: string): void {
  emit('update:modelValue', option)
  closeDropdown()
}

function handleKeydown(event: KeyboardEvent): void {
  if (props.disabled) {
    return
  }

  if (event.key === 'Escape') {
    closeDropdown()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()

    if (!isOpen.value) {
      openDropdown()
      return
    }

    activeIndex.value = Math.min(activeIndex.value + 1, filteredOptions.value.length - 1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()

    if (!isOpen.value) {
      openDropdown()
      return
    }

    activeIndex.value = Math.max(activeIndex.value - 1, 0)
    return
  }

  if (event.key === 'Enter' && isOpen.value && activeIndex.value >= 0) {
    const option = filteredOptions.value[activeIndex.value]

    if (option) {
      event.preventDefault()
      selectOption(option)
    }
  }
}

function handleDocumentPointerDown(event: PointerEvent): void {
  if (!root.value || !(event.target instanceof Node)) {
    return
  }

  if (!root.value.contains(event.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', handleDocumentPointerDown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleDocumentPointerDown)
})
</script>

<template>
  <div ref="root" class="relative">
    <input
      :id="id"
      :name="name"
      type="text"
      autocomplete="off"
      :disabled="disabled"
      :value="inputValue"
      :placeholder="placeholder"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-controls="listboxId"
      :aria-activedescendant="activeOptionId"
      aria-autocomplete="list"
      class="nusa-input mt-2 pr-10"
      @focus="openDropdown"
      @input="updateValue(($event.target as HTMLInputElement).value)"
      @keydown="handleKeydown"
    />

    <button
      type="button"
      :disabled="disabled"
      class="absolute right-3 top-1/2 mt-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-800 hover:text-neutral-200 disabled:opacity-40"
      aria-hidden="true"
      tabindex="-1"
      @click="isOpen ? closeDropdown() : openDropdown()"
    >
      <span class="text-xs" aria-hidden="true">⌄</span>
    </button>

    <div
      v-if="isOpen"
      :id="listboxId"
      role="listbox"
      class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-2xl border border-neutral-700/80 bg-neutral-950/95 p-1 shadow-2xl shadow-black/40 backdrop-blur"
    >
      <button
        v-for="(option, index) in filteredOptions"
        :id="optionId(index)"
        :key="option"
        type="button"
        role="option"
        :aria-selected="index === activeIndex"
        class="block w-full rounded-xl px-3 py-2.5 text-left text-sm text-neutral-200 transition hover:bg-neutral-800 focus:bg-neutral-800 focus:outline-none"
        :class="index === activeIndex ? 'bg-neutral-800 text-white' : ''"
        @mouseenter="activeIndex = index"
        @mousedown.prevent="selectOption(option)"
      >
        {{ option }}
      </button>

      <p v-if="filteredOptions.length === 0" class="px-3 py-3 text-sm text-neutral-500">
        No matching suggestions. Keep typing to use your own category.
      </p>
    </div>
  </div>
</template>
