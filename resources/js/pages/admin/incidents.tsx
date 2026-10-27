import IncidentList from '@/components/incident-list';
import { index } from '@/routes/admin/incidents';
import type { IncidentListProps } from '@/types/incident';

export default function IncidentsPage(props: IncidentListProps) {
    return (
        <IncidentList key={JSON.stringify(props.filters)} {...props} admin />
    );
}
IncidentsPage.layout = {
    breadcrumbs: [{ title: 'Ocorrências', href: index() }],
};
