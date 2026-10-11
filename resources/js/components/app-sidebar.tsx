import { Link } from '@inertiajs/react';
import {
    ArrowRightLeft,
    BarChart3,
    Building2,
    CalendarDays,
    FileSpreadsheet,
    FileText,
    Images,
    Map as MapIcon,
    Inbox,
    Megaphone,
    MessageSquareWarning,
    Newspaper,
    ClipboardCheck,
    ClipboardList,
    Construction,
    Crown,
    FolderKanban,
    Copy,
    FilePenLine,
    FileUp,
    GitMerge,
    Home,
    Smartphone,
    TabletSmartphone,
    Globe,
    LayoutGrid,
    MapPinned,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard, field, home } from '@/routes';
import { index as conflictsIndex } from '@/routes/admin/sync/conflicts';
import { index as devicesIndex } from '@/routes/admin/sync/devices';
import { population } from '@/routes/admin/census';
import { index as campaignsIndex } from '@/routes/admin/census/campaigns';
import { index as correctionsIndex } from '@/routes/admin/census/corrections';
import { index as duplicatesIndex } from '@/routes/admin/census/duplicates';
import { index as householdsIndex } from '@/routes/admin/census/households';
import { index as complaintsIndex } from '@/routes/admin/complaints';
import { index as announcementsIndex } from '@/routes/admin/content/announcements';
import { index as contentEventsIndex } from '@/routes/admin/content/events';
import { index as inboxIndex } from '@/routes/admin/content/inbox';
import { index as mediaIndex } from '@/routes/admin/content/media';
import { index as pagesIndex } from '@/routes/admin/content/pages';
import { index as postsIndex } from '@/routes/admin/content/posts';
import { index as facilitiesIndex } from '@/routes/admin/facilities';
import { index as infrastructureIndex } from '@/routes/admin/infrastructure';
import { index as leadershipIndex } from '@/routes/admin/leadership';
import { index as needsIndex } from '@/routes/admin/needs-assessment';
import { index as projectsIndex } from '@/routes/admin/projects';
import { index as mapIndex } from '@/routes/admin/map';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as usersIndex } from '@/routes/admin/users';
import { index as geographyIndex } from '@/routes/admin/geography';
import { index as importsIndex } from '@/routes/admin/geography/imports';
import { index as reassignmentsIndex } from '@/routes/admin/geography/reassignments';
import type { NavItem } from '@/types';

type NavGroup = { label: string; items: NavItem[] };

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            {
                title: 'Reports',
                href: reportsIndex(),
                match: '/admin/reports',
                icon: FileSpreadsheet,
                permissions: [
                    'reports.view',
                    'census.view',
                    'facilities.view',
                    'leadership.view',
                    'complaints.view',
                ],
            },
            {
                title: 'Map',
                href: mapIndex(),
                icon: MapIcon,
                permissions: [
                    'geography.view',
                    'facilities.view',
                    'infrastructure.view',
                ],
            },
        ],
    },
    {
        label: 'Census',
        items: [
            {
                title: 'Campaigns',
                href: campaignsIndex(),
                icon: ClipboardList,
                permissions: ['campaigns.view'],
            },
            {
                title: 'Households',
                href: householdsIndex(),
                match: '/admin/census/households',
                icon: Home,
                permissions: ['census.view'],
            },
            {
                title: 'Corrections',
                href: correctionsIndex(),
                icon: FilePenLine,
                permissions: ['census.correct', 'census.approve-correction'],
            },
            {
                title: 'Duplicates',
                href: duplicatesIndex(),
                icon: Copy,
                permissions: ['census.review-duplicates'],
            },
            {
                title: 'Population',
                href: population(),
                icon: BarChart3,
                permissions: ['census.view', 'reports.view'],
            },
        ],
    },
    {
        label: 'Community services',
        items: [
            {
                title: 'Facilities',
                href: facilitiesIndex(),
                match: '/admin/facilities',
                icon: Building2,
                permissions: ['facilities.view'],
            },
            {
                title: 'Needs assessment',
                href: needsIndex(),
                icon: ClipboardCheck,
                permissions: ['facilities.view', 'infrastructure.view'],
            },
            {
                title: 'Leadership',
                href: leadershipIndex(),
                match: '/admin/leadership',
                icon: Crown,
                permissions: ['leadership.view'],
            },
            {
                title: 'Infrastructure',
                href: infrastructureIndex(),
                match: '/admin/infrastructure',
                icon: Construction,
                permissions: ['infrastructure.view'],
            },
            {
                title: 'Projects',
                href: projectsIndex(),
                match: '/admin/projects',
                icon: FolderKanban,
                permissions: ['infrastructure.view'],
            },
        ],
    },
    {
        label: 'Field operations',
        items: [
            {
                title: 'Field app',
                href: field(),
                icon: Smartphone,
                permissions: ['sync.use'],
            },
            {
                title: 'Sync conflicts',
                href: conflictsIndex(),
                icon: GitMerge,
                permissions: ['sync.resolve-conflicts'],
            },
            {
                title: 'Devices',
                href: devicesIndex(),
                icon: TabletSmartphone,
                permissions: ['devices.manage'],
            },
        ],
    },
    {
        label: 'Geographic registry',
        items: [
            {
                title: 'Areas',
                href: geographyIndex('county'),
                match: '/admin/geography/',
                icon: MapPinned,
                permissions: ['geography.view'],
            },
            {
                title: 'Reassignments',
                href: reassignmentsIndex(),
                icon: ArrowRightLeft,
                permissions: [
                    'geography.reassign',
                    'geography.approve-reassignment',
                ],
            },
            {
                title: 'Imports',
                href: importsIndex(),
                icon: FileUp,
                permissions: ['geography.import'],
            },
        ],
    },
    {
        label: 'Complaints',
        items: [
            {
                title: 'Complaints',
                href: complaintsIndex(),
                match: '/admin/complaints',
                icon: MessageSquareWarning,
                permissions: ['complaints.view'],
            },
        ],
    },
    {
        label: 'Public website',
        items: [
            {
                title: 'News',
                href: postsIndex(),
                match: '/admin/content/posts',
                icon: Newspaper,
                permissions: ['content.manage'],
            },
            {
                title: 'Events',
                href: contentEventsIndex(),
                match: '/admin/content/events',
                icon: CalendarDays,
                permissions: ['content.manage'],
            },
            {
                title: 'Announcements',
                href: announcementsIndex(),
                icon: Megaphone,
                permissions: ['content.manage'],
            },
            {
                title: 'Pages',
                href: pagesIndex(),
                icon: FileText,
                permissions: ['content.manage'],
            },
            {
                title: 'Photo approvals',
                href: mediaIndex(),
                icon: Images,
                permissions: ['media.approve', 'content.manage'],
            },
            {
                title: 'Contact inbox',
                href: inboxIndex(),
                icon: Inbox,
                permissions: ['contact-messages.view'],
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                title: 'Staff accounts',
                href: usersIndex(),
                icon: Users,
                permissions: ['users.view'],
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Public website',
        href: home(),
        icon: Globe,
    },
];

export function AppSidebar() {
    const can = useCan();

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permissions || can(...item.permissions),
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {visibleGroups.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
