import IncidentDetails from '@/components/incident-details';
import { index } from '@/routes/admin/incidents';
import type { IncidentDetailsData, IncidentOption } from '@/types/incident';

export default function IncidentDetailsPage({
    incident,
    priorities,
}: {
    incident: IncidentDetailsData;
    priorities: IncidentOption[];
}) {
    return (
        <IncidentDetails incident={incident} priorities={priorities} admin />
    );
}
IncidentDetailsPage.layout = {
    breadcrumbs: [{ title: 'Ocorrências', href: index() }],
};
