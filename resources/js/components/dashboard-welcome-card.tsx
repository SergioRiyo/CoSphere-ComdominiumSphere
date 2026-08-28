import { Link } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type DashboardWelcomeCardProps = {
    user: User;
    roleLabel: string;
    description: string;
};

export function DashboardWelcomeCard({
    user,
    roleLabel,
    description,
}: DashboardWelcomeCardProps) {
    return (
        <Card className="border-cosphere-line/80 shadow-cosphere-soft dark:border-border">
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 flex-1 space-y-2">
                    <span className="inline-flex w-fit rounded-full bg-cosphere-blue/10 px-3 py-1 text-xs font-semibold tracking-[0.12em] text-cosphere-blue uppercase">
                        {roleLabel}
                    </span>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight break-words text-cosphere-navy dark:text-foreground">
                            Olá, {user.name}
                        </h1>
                        <CardDescription className="mt-2 max-w-2xl leading-relaxed break-words">
                            {description}
                        </CardDescription>
                    </div>
                </div>

                <Button
                    className="w-full sm:w-auto sm:shrink-0"
                    variant="outline"
                    asChild
                >
                    <Link href={edit()} prefetch>
                        <Settings aria-hidden="true" />
                        Ver perfil
                    </Link>
                </Button>
            </CardHeader>

            <CardContent>
                <p className="text-sm break-all text-muted-foreground">
                    {user.email}
                </p>
            </CardContent>
        </Card>
    );
}
