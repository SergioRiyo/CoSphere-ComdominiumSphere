import { Badge } from '@/components/ui/badge';
import type { PortariaVisitorAccessSituation } from '@/types';

const statusClasses: Record<PortariaVisitorAccessSituation, string> = {
    present:
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
    finished:
        'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300',
    denied: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300',
    pending:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300',
    validated:
        'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
};

export function VisitorAccessStatusBadge({
    situation,
    label,
}: {
    situation: PortariaVisitorAccessSituation;
    label: string;
}) {
    return (
        <Badge variant="outline" className={statusClasses[situation]}>
            {label}
        </Badge>
    );
}
