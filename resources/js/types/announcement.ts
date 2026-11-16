export type AnnouncementSummary = {
    id: number;
    title: string;
    author: string;
    published_at: string | null;
    created_at: string;
};

export type Announcement = AnnouncementSummary & { content: string };

export type PublishedAnnouncementSummary = {
    id: number;
    title: string;
    author: string;
    published_at: string;
};

export type PublishedAnnouncement = PublishedAnnouncementSummary & {
    content: string;
};

export function formatAnnouncementDate(
    value: string,
    timezone: string,
): string {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}
