import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/morador/announcements';
import { formatAnnouncementDate } from '@/types/announcement';
import type { PublishedAnnouncement } from '@/types/announcement';

export default function AnnouncementDetails({
    announcement,
    timezone,
}: {
    announcement: PublishedAnnouncement;
    timezone: string;
}) {
    return (
        <>
            <Head title={announcement.title} />
            <div className="grid gap-6 p-4 sm:p-6">
                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>Voltar aos comunicados</Link>
                </Button>
                <article className="grid max-w-3xl min-w-0 gap-4 rounded-xl border p-5 break-words">
                    <h1 className="text-2xl font-semibold">
                        {announcement.title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Por {announcement.author} · Publicado em{' '}
                        <time dateTime={announcement.published_at}>
                            {formatAnnouncementDate(
                                announcement.published_at,
                                timezone,
                            )}
                        </time>
                    </p>
                    <p className="whitespace-pre-wrap">
                        {announcement.content}
                    </p>
                </article>
            </div>
        </>
    );
}

AnnouncementDetails.layout = {
    breadcrumbs: [{ title: 'Comunicados', href: index() }],
};
