import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { about, contact, dashboard, home, leaders, login, map } from '@/routes';
import { index as communitiesIndex } from '@/routes/communities';
import { create as createComplaint } from '@/routes/complaints';
import { index as eventsIndex } from '@/routes/events';
import { index as newsIndex } from '@/routes/news';

const navigation = [
    { title: 'Communities', href: communitiesIndex(), match: '/communities' },
    { title: 'Leaders', href: leaders(), match: '/leaders' },
    { title: 'Map', href: map(), match: '/map' },
    { title: 'News', href: newsIndex(), match: '/news' },
    { title: 'Events', href: eventsIndex(), match: '/events' },
    { title: 'About', href: about(), match: '/about' },
    { title: 'Contact', href: contact(), match: '/contact' },
    { title: 'Complaints', href: createComplaint(), match: '/complaints' },
];

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth, name } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    const staffLink = auth.user ? (
        <Button asChild size="sm" variant="outline">
            <Link href={dashboard()}>Staff dashboard</Link>
        </Button>
    ) : (
        <Button asChild size="sm" variant="outline">
            <Link href={login()}>Staff sign in</Link>
        </Button>
    );

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-background focus:px-3 focus:py-2"
            >
                Skip to content
            </a>
            <header className="border-b">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <Link href={home()} className="flex items-center gap-2">
                        <AppLogoIcon className="size-8 fill-current" />
                        <span className="leading-tight">
                            <span className="block text-sm font-semibold">
                                {name}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Community census and services
                            </span>
                        </span>
                    </Link>
                    <nav
                        aria-label="Main"
                        className="hidden items-center gap-1 lg:flex"
                    >
                        {navigation.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-foreground',
                                    isCurrentUrl(item.match, undefined, true) &&
                                        'text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                        <span className="ml-2">{staffLink}</span>
                    </nav>
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                                aria-label="Open menu"
                            >
                                <Menu />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right">
                            <SheetHeader>
                                <SheetTitle>Menu</SheetTitle>
                            </SheetHeader>
                            <nav
                                aria-label="Main"
                                className="flex flex-col gap-1 px-4"
                            >
                                {navigation.map((item) => (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        className="rounded-md px-3 py-2 hover:bg-accent"
                                    >
                                        {item.title}
                                    </Link>
                                ))}
                                <div className="mt-4">{staffLink}</div>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </header>
            <main id="main" className="flex-1">
                {children}
            </main>
            <footer className="border-t bg-muted/30">
                <div className="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-muted-foreground md:grid-cols-3">
                    <div>
                        <div className="font-medium text-foreground">
                            {name}
                        </div>
                        <p className="mt-1">
                            Approved community statistics, services and news.
                        </p>
                    </div>
                    <div>
                        <div className="font-medium text-foreground">
                            Your privacy
                        </div>
                        <p className="mt-1">
                            This website never shows information about
                            individual people, households or house locations.
                            Figures are aggregated from approved census records.
                        </p>
                    </div>
                    <nav aria-label="Footer" className="flex flex-col gap-1">
                        <Link href={about()} className="hover:text-foreground">
                            About and data protection
                        </Link>
                        <Link
                            href={contact()}
                            className="hover:text-foreground"
                        >
                            Contact us
                        </Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}
