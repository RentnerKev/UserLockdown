import { JSDOM } from 'jsdom'

const { window } = new JSDOM('<!doctype html><html><body></body></html>', {
  url: 'http://localhost:3000',
  pretendToBeVisual: true,
})

const replacedGlobals = ['window', 'document', 'navigator', 'Event', 'EventTarget', 'MouseEvent']

for (const key of Object.getOwnPropertyNames(window)) {
  if (!(key in globalThis) && !replacedGlobals.includes(key)) {
    Object.defineProperty(globalThis, key, Object.getOwnPropertyDescriptor(window, key)!)
  }
}

for (const key of replacedGlobals) {
  Object.defineProperty(globalThis, key, {
    configurable: true,
    writable: true,
    value: window[key as keyof typeof window],
  })
}

Object.defineProperty(globalThis, 'IS_REACT_ACT_ENVIRONMENT', {
  configurable: true,
  writable: true,
  value: true,
})
