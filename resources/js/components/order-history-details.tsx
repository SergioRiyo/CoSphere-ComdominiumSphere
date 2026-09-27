import { Badge } from '@/components/ui/badge';
import type { OrderHistoryEntry } from '@/types/order-history';

export function OrderHistoryDetails({
    order,
    timezone,
}: {
    order: OrderHistoryEntry;
    timezone: string;
}) {
    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat('pt-BR', {
                  dateStyle: 'short',
                  timeStyle: 'short',
                  timeZone: timezone,
              }).format(new Date(value))
            : 'Não informada';

    return (
        <div className="flex flex-col gap-4 text-sm">
            <Badge
                variant={order.available_for_pickup ? 'default' : 'secondary'}
            >
                {order.status_label}
            </Badge>
            {order.available_for_pickup && (
                <p className="font-semibold text-primary">
                    Disponível para retirada
                </p>
            )}
            <p className="break-words whitespace-pre-wrap">
                {order.description || 'Sem descrição'}
            </p>
            <dl className="grid gap-3 break-words sm:grid-cols-2">
                {[
                    [
                        'Unidade histórica',
                        `${order.unit.block ? `Bloco ${order.unit.block} · ` : ''}Unidade ${order.unit.number}`,
                    ],
                    ['Destinatário', order.resident_name || 'Indisponível'],
                    ['Remetente', order.sender || 'Não informado'],
                    ['Transportadora', order.carrier || 'Não informada'],
                    ['Rastreio', order.tracking_code || 'Não informado'],
                    ['Data do status atual', date(order.status_date)],
                    ['Cadastro', date(order.created_at)],
                    ['Recebimento', date(order.received_at)],
                    ['Recebida por', order.received_by || 'Não informado'],
                    ['Retirada', date(order.picked_up_at)],
                    [
                        'Retirada confirmada por',
                        order.pickup_confirmed_by || 'Não informado',
                    ],
                ].map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
