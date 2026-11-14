import { SlidersHorizontal } from 'lucide-react';
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
}: {
    title: string;
    activeCount: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children: ReactNode;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    <SlidersHorizontal className="size-4" />
                    Filtros{activeCount > 0 ? ` (${activeCount})` : ''}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Filtros de {title}</SheetTitle>
                    <SheetDescription>
                        Selecione os critérios e aplique os filtros ao
                        relatório.
                    </SheetDescription>
                </SheetHeader>
                {children}
            </SheetContent>
        </Sheet>
    );
}
