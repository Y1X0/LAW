import { Card } from '../../components/ui/primitives'
import { Skeleton } from '../../components/ui/states'

/** هيكل تحميل اللوحة (grid متجاوب مطابق للتخطيط الفعلي). */
export function DashboardSkeleton() {
  return (
    <div className="space-y-4" data-testid="dashboard-skeleton">
      <Skeleton className="h-8 w-48" />
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i} className="h-40">
            <Skeleton className="mb-3 h-4 w-24" />
            <Skeleton className="mb-2 h-3 w-full" />
            <Skeleton className="mb-2 h-3 w-5/6" />
            <Skeleton className="h-3 w-2/3" />
          </Card>
        ))}
      </div>
    </div>
  )
}
