import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

const indexPath = join(process.cwd(), 'index.html')
const publicPath = join(process.cwd(), 'public')

function indexHtml(): string {
  return readFileSync(indexPath, 'utf-8')
}

describe('browser metadata', () => {
  it('ships the Nusa title and mobile app metadata', () => {
    const html = indexHtml()

    expect(html).toContain('<title>Nusa Creator Studio</title>')
    expect(html).toContain('name="description"')
    expect(html).toContain('mobile-first AI content studio')
    expect(html).toContain('name="theme-color" content="#050817"')
    expect(html).toContain('name="application-name" content="Nusa Creator Studio"')
  })

  it('links SVG favicon and app icon placeholders', () => {
    const html = indexHtml()

    expect(html).toContain('rel="icon" type="image/svg+xml" href="/favicon.svg"')
    expect(html).toContain('rel="apple-touch-icon" href="/apple-touch-icon.svg"')
    expect(html).toContain('href="/app-icon.svg"')
    expect(existsSync(join(publicPath, 'favicon.svg'))).toBe(true)
    expect(existsSync(join(publicPath, 'app-icon.svg'))).toBe(true)
    expect(existsSync(join(publicPath, 'app-icon-light.svg'))).toBe(true)
    expect(existsSync(join(publicPath, 'apple-touch-icon.svg'))).toBe(true)
  })
})
