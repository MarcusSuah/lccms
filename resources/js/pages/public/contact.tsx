import { Form, Head } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { HoneypotFields } from '@/components/public/honeypot-fields';
import type { HoneypotData } from '@/components/public/honeypot-fields';
import { RichText } from '@/components/public/rich-text';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/contact';

export default function Contact({
    contact,
    honeypot,
}: {
    contact: string | null;
    honeypot: HoneypotData;
}) {
    return (
        <>
            <Head title="Contact" />
            <div className="mx-auto grid max-w-5xl gap-10 px-4 py-10 md:grid-cols-[1fr_1.4fr]">
                <div className="space-y-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Contact us
                    </h1>
                    {contact ? (
                        <RichText html={contact} className="text-sm" />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Send the project team a message.
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        To report a problem with a service, use the complaint
                        form instead: it gives you a private tracking link.
                    </p>
                </div>
                <Form {...store.form()} resetOnSuccess className="space-y-4">
                    {({ errors, processing, wasSuccessful }) => (
                        <>
                            <HoneypotFields honeypot={honeypot} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Your name"
                                    htmlFor="name"
                                    error={errors.name}
                                    required
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        autoComplete="name"
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Email"
                                    htmlFor="email"
                                    error={errors.email}
                                    required
                                >
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        autoComplete="email"
                                        required
                                    />
                                </FormField>
                            </div>
                            <FormField
                                label="Phone (optional)"
                                htmlFor="phone"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    autoComplete="tel"
                                />
                            </FormField>
                            <FormField
                                label="Subject"
                                htmlFor="subject"
                                error={errors.subject}
                                required
                            >
                                <Input id="subject" name="subject" required />
                            </FormField>
                            <FormField
                                label="Message"
                                htmlFor="message"
                                error={errors.message}
                                required
                            >
                                <Textarea
                                    id="message"
                                    name="message"
                                    className="min-h-36"
                                    required
                                />
                            </FormField>
                            {wasSuccessful && (
                                <p className="text-sm text-emerald-700 dark:text-emerald-400">
                                    Thank you. Your message has been received.
                                </p>
                            )}
                            <Button type="submit" disabled={processing}>
                                Send message
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
