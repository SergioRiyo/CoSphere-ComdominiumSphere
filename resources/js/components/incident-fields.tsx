import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { IncidentOption } from '@/types/incident';

export function IncidentSelect({
    id,
    label,
    value,
    options,
    onChange,
    error,
    all = false,
    disabled = false,
}: {
    id: string;
    label: string;
    value: string;
    options: IncidentOption[];
    onChange: (value: string) => void;
    error?: string;
    all?: boolean;
    disabled?: boolean;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={value || (all ? 'all' : '')}
                onValueChange={(next) => onChange(next === 'all' ? '' : next)}
                disabled={disabled}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                    {all && <SelectItem value="all">Todos</SelectItem>}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

export function incidentDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value.replace(' ', 'T')))
        : 'Não informada';
}
