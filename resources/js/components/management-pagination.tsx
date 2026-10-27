import { Button } from '@/components/ui/button';
export default function ManagementPagination({
    page,
    last,
    busy,
    onChange,
}: {
    page: number;
    last: number;
    busy: boolean;
    onChange: (page: number) => void;
}) {
    return (
        <nav
            aria-label="Paginação"
            className="flex flex-wrap items-center gap-3"
        >
            <Button
                variant="outline"
                disabled={busy || page <= 1}
                onClick={() => onChange(page - 1)}
            >
                Anterior
            </Button>
            <span className="text-sm">
                Página {page} de {last}
            </span>
            <Button
                variant="outline"
                disabled={busy || page >= last}
                onClick={() => onChange(page + 1)}
            >
                Próxima
            </Button>
        </nav>
    );
}
