import { incidentDate } from '@/components/incident-fields';
import type { MaintenanceHistoryEvent } from '@/types/maintenance';
export function maintenanceMoney(value: string | null) {
    return value === null
        ? 'Não informado'
        : new Intl.NumberFormat('pt-BR', {
              style: 'currency',
              currency: 'BRL',
          }).format(Number(value));
}
const labels: Record<string, string> = {
    description: 'Descrição',
    admin_id: 'Responsável',
    service_provider_id: 'Prestador',
    scheduled_at: 'Agendamento',
    cost: 'Custo',
};
export default function MaintenanceHistory({
    events,
}: {
    events: MaintenanceHistoryEvent[];
}) {
    function value(field: string, raw: string | number | null) {
        if (field === 'cost') {
            return maintenanceMoney(raw === null ? null : String(raw));
        }

        if (field === 'scheduled_at') {
            return incidentDate(raw === null ? null : String(raw));
        }

        return raw ?? 'Não definido';
    }

    return events.length === 0 ? (
        <p className="text-sm text-muted-foreground">
            Nenhum evento registrado.
        </p>
    ) : (
        <ol className="grid gap-4">
            {events.map((event) => (
                <li
                    key={event.key}
                    className="grid gap-1 border-l-2 pl-3 text-sm break-words"
                >
                    <time className="text-muted-foreground">
                        {incidentDate(event.at)}
                    </time>
                    <p className="font-medium">{event.text}</p>
                    <p>{event.actor}</p>
                    {event.reason && <p>{event.reason}</p>}
                    {Object.entries(event.changes).map(([field, change]) => (
                        <p key={field}>
                            <strong>{labels[field] || field}:</strong>{' '}
                            {value(field, change.old_label ?? change.old)} →{' '}
                            {value(field, change.new_label ?? change.new)}
                        </p>
                    ))}
                </li>
            ))}
        </ol>
    );
}
