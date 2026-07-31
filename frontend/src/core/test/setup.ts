import '@testing-library/jest-dom/vitest'
import { afterEach, beforeEach } from 'vitest'

// عزل localStorage بين الاختبارات.
beforeEach(() => localStorage.clear())
afterEach(() => localStorage.clear())
