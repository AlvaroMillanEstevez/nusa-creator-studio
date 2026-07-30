import { nextTick } from 'vue'

const INVALID_FIELD_SELECTOR =
  '[aria-invalid="true"], [data-invalid="true"], [data-field-error="true"]'
const FOCUSABLE_SELECTOR =
  'input:not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled]), [role="combobox"]:not([disabled]), [tabindex]:not([tabindex="-1"])'

function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }

  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function isHTMLElement(value: Element | null): value is HTMLElement {
  return value instanceof HTMLElement
}

function focusTargetFor(element: HTMLElement): HTMLElement {
  if (element.matches(FOCUSABLE_SELECTOR)) {
    return element
  }

  return element.querySelector<HTMLElement>(FOCUSABLE_SELECTOR) ?? element
}

export async function focusFirstInvalidField(root: ParentNode | null | undefined): Promise<boolean> {
  await nextTick()

  const searchRoot = root ?? (typeof document === 'undefined' ? null : document)

  if (searchRoot === null) {
    return false
  }

  const invalidElement = searchRoot.querySelector(INVALID_FIELD_SELECTOR)

  if (!isHTMLElement(invalidElement)) {
    return false
  }

  const target = focusTargetFor(invalidElement)
  invalidElement.scrollIntoView({
    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    block: 'center',
    inline: 'nearest',
  })
  target.focus({ preventScroll: true })

  return true
}
