import { Button, Field, Input } from '@/components/falak';
import { toast } from '@/components/falak/toast';
import { type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Card } from '../../components/space-bits';
import { SpaceLayout } from '../../components/space-layout';

export default function Account({ must_verify }: { must_verify: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const profile = useForm({ name: auth.user?.name ?? '', email: auth.user?.email ?? '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const saveProfile = (e: FormEvent) => {
        e.preventDefault();
        profile.patch('/settings/profile', { preserveScroll: true, onSuccess: () => toast.success('Saved.') });
    };

    const savePassword = (e: FormEvent) => {
        e.preventDefault();
        password.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => {
                password.reset();
                toast.success('Password changed.');
            },
        });
    };

    return (
        <SpaceLayout title="Account">
            <div className="max-w-xl space-y-6">
                <Card>
                    <form onSubmit={saveProfile} className="space-y-4">
                        <h2 className="text-lg font-semibold">Your details</h2>
                        <Field label="Name" error={profile.errors.name}>
                            <Input value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} required autoComplete="name" />
                        </Field>
                        <Field label="Email" error={profile.errors.email}>
                            <Input
                                type="email"
                                value={profile.data.email}
                                onChange={(e) => profile.setData('email', e.target.value)}
                                required
                                autoComplete="email"
                            />
                        </Field>
                        {must_verify && (
                            <p className="text-fg-muted text-sm">
                                Your email is not confirmed yet.{' '}
                                <Link href="/email/verification-notification" method="post" as="button" className="text-primary hover:underline">
                                    Send the link again
                                </Link>
                            </p>
                        )}
                        <Button type="submit" variant="primary" loading={profile.processing}>
                            Save
                        </Button>
                    </form>
                </Card>

                <Card>
                    <form onSubmit={savePassword} className="space-y-4">
                        <h2 className="text-lg font-semibold">Change password</h2>
                        <Field label="Current password" error={password.errors.current_password}>
                            <Input
                                type="password"
                                value={password.data.current_password}
                                onChange={(e) => password.setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                required
                            />
                        </Field>
                        <Field label="New password" error={password.errors.password}>
                            <Input
                                type="password"
                                value={password.data.password}
                                onChange={(e) => password.setData('password', e.target.value)}
                                autoComplete="new-password"
                                required
                            />
                        </Field>
                        <Field label="Repeat new password" error={password.errors.password_confirmation}>
                            <Input
                                type="password"
                                value={password.data.password_confirmation}
                                onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                required
                            />
                        </Field>
                        <Button type="submit" loading={password.processing}>
                            Change password
                        </Button>
                    </form>
                </Card>

                <Button variant="ghost" asChild>
                    <Link href="/logout" method="post" as="button">
                        Log out
                    </Link>
                </Button>
            </div>
        </SpaceLayout>
    );
}
