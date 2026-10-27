import IncidentDetails from '@/components/incident-details';
import { index } from '@/routes/morador/incidents';
import type { IncidentDetailsData } from '@/types/incident';

export default function IncidentDetailsPage({
    incident,
}: {
    incident: IncidentDetailsData;
}) {
    return <IncidentDetails incident={incident} />;
}
IncidentDetailsPage.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: index() }],
};
