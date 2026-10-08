import IncidentList from '@/components/incident-list';
import { index } from '@/routes/morador/incidents';
import type { IncidentListProps } from '@/types/incident';

export default function IncidentsPage(props: IncidentListProps) {
    return <IncidentList key={JSON.stringify(props.filters)} {...props} />;
}
IncidentsPage.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: index() }],
};
