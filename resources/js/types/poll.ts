export type PollStatus = 'draft' | 'scheduled' | 'open' | 'closed';

export type PollSummary = {
    id: number;
    question: string;
    author: string;
    status: PollStatus;
    published_at: string | null;
    starts_at: string | null;
    ends_at: string | null;
    created_at: string;
};

export type Poll = PollSummary & { options: { id: number; text: string }[] };

export type ResidentPollSummary = {
    id: number;
    question: string;
    status: PollStatus;
    starts_at: string | null;
    ends_at: string | null;
    has_voted: boolean;
};

export type ResidentPoll = ResidentPollSummary & {
    options: { id: number; text: string }[];
};

export type PollResults = {
    total: number;
    options: { id: number; text: string; votes: number; percentage: number }[];
};

export const pollStatusLabels: Record<PollStatus, string> = {
    draft: 'Rascunho',
    scheduled: 'Aguardando início',
    open: 'Aberta',
    closed: 'Encerrada',
};
