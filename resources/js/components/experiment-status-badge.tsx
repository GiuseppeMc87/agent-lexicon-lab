import type { ExperimentStatus } from '@/types';
import { cn } from '@/lib/utils';

const styles: Record<ExperimentStatus, string> = {
    pending:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    running:
        'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
    completed:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    failed: 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
};

export function ExperimentStatusBadge({
    status,
    className,
}: {
    status: ExperimentStatus;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold capitalize',
                styles[status],
                className,
            )}
        >
            {status === 'running' && (
                <span
                    className="mr-1.5 size-1.5 animate-pulse rounded-full bg-current"
                    aria-hidden="true"
                />
            )}
            {status}
        </span>
    );
}
