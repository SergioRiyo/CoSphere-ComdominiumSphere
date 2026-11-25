export function formatVisitorDateTime(value: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}
