import { useContext } from 'react'
import { CapabilitiesContext, type CapabilitiesValue } from './capabilitiesContext'

export function useCapabilities(): CapabilitiesValue {
  const ctx = useContext(CapabilitiesContext)
  if (!ctx) throw new Error('useCapabilities must be used within <CapabilitiesProvider>')
  return ctx
}
