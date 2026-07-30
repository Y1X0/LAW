import { describe, expect, it } from 'vitest'
import { attendanceStatusLabel, formatCurrency, formatMinutes, formatPeriod } from './format'

describe('format', () => {
  it('يترجم حالات الحضور', () => {
    expect(attendanceStatusLabel('late')).toBe('متأخّر')
    expect(attendanceStatusLabel('present')).toBe('حاضر')
    expect(attendanceStatusLabel('unknown')).toBe('unknown')
  })

  it('ينسّق الدقائق', () => {
    expect(formatMinutes(0)).toBe('—')
    expect(formatMinutes(45)).toBe('45د')
    expect(formatMinutes(60)).toBe('1س')
    expect(formatMinutes(90)).toBe('1س 30د')
  })

  it('ينسّق الفترة والعملة', () => {
    expect(formatPeriod(2027, 3)).toBe('03/2027')
    expect(formatCurrency(3400, 'SAR')).toBe('3,400.00 SAR')
  })
})
