export type LostFoundItem = {
    id: number;
    type: 'lost' | 'found';
    description: string;
    condition: string;
    location: string;
    event_date: string;
    status: 'open' | 'in_progress' | 'resolved';
    has_photo: boolean;
    is_author: boolean;
    conversations_count?: number;
};
export type Message = {
    id: number;
    body: string;
    sender: string;
    created_at: string;
};
export type Conversation = {
    id: number;
    resident?: string;
    unit?: string | null;
    messages: Message[];
};
export type History = {
    action: string;
    actor: string;
    before: Record<string, string | boolean> | null;
    after: Record<string, string | boolean>;
    created_at: string;
};
export type LostFoundDetails = LostFoundItem & {
    author?: { name: string; unit: string | null };
    history?: History[];
    conversations: Conversation[];
};
export const statusLabels: Record<LostFoundItem['status'], string> = {
    open: 'Aberto',
    in_progress: 'Em atendimento',
    resolved: 'Finalizado',
};
export const dateText = (value: string) => value.split('-').reverse().join('/');
