import { Search, SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

export default function ReportFilterSheet({
    title,
    activeCount,
    open,
    onOpenChange,
    children,
    mode = 'filter',
}: {
    title: string;
    activeCount: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children: ReactNode;
    mode?: 'filter' | 'search';
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    {mode === 'search' ? (
                        <Search className="size-4" />
                    ) : (
                        <SlidersHorizontal className="size-4" />
                    )}
                    {mode === 'search' ? 'Buscar encomenda' : 'Filtros'}
                    {activeCount > 0 ? ` (${activeCount})` : ''}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {mode === 'search'
                            ? 'Buscar encomenda'
                            : `Filtros de ${title}`}
                    </SheetTitle>
                    <SheetDescription>
                        {mode === 'search'
                            ? 'Selecione a unidade ou informe o rastreio ou remetente para buscar.'
                            : 'Selecione os critérios e aplique os filtros à listagem.'}
                    </SheetDescription>
                </SheetHeader>
                {children}
            </SheetContent>
        </Sheet>
    );
}
