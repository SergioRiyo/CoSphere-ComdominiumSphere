import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';

type DashboardComingSoonCardProps = {
    icon: LucideIcon;
    title: string;
    description: string;
};

export function DashboardComingSoonCard({
    icon: Icon,
    title,
    description,
}: DashboardComingSoonCardProps) {
    return (
        <Card className="border-dashed border-cosphere-line/80 bg-cosphere-surface/60 shadow-sm dark:border-border dark:bg-card">
            <CardHeader>
                <span className="inline-flex size-11 items-center justify-center rounded-xl bg-cosphere-orange/10 text-cosphere-orange">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div className="space-y-2">
                    <Badge className="bg-cosphere-blue/10 text-cosphere-blue hover:bg-cosphere-blue/10" variant="secondary">
                        Em breve
                    </Badge>
                    <h2 className="break-words text-lg font-semibold text-cosphere-navy dark:text-foreground">
                        {title}
                    </h2>
                    <CardDescription className="break-words leading-relaxed">
                        {description}
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent>
                <p className="text-sm text-muted-foreground">
                    Ainda não há informações disponíveis nesta área.
                </p>
            </CardContent>
        </Card>
    );
}
