<script module lang="ts">
    import { edit } from '@/routes/profile';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Perfil',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import AppHead from '@/components/AppHead.svelte';
    import DeleteUser from '@/components/DeleteUser.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import RolesUsuario from '@/components/RolesUsuario.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { send } from '@/routes/verification';
    import { AYUDA_NOMBRE, PATRON_NOMBRE } from '@/lib/validacion';

    const user = $derived(page.props.auth.user);
</script>

<AppHead title="Perfil" />

<h1 class="sr-only">Perfil</h1>

<div class="flex flex-col space-y-6">
    <div class="space-y-3">
        <Heading
            variant="small"
            title="Tus roles"
            description="Lo que puedes hacer en la plataforma"
        />
        <RolesUsuario descripciones />
    </div>

    <Heading
        variant="small"
        title="Perfil"
        description="Actualiza tu nombre y tu correo"
    />

    <Form
        {...ProfileController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    name="name"
                    class="mt-1 block w-full"
                    value={user.name}
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                    minlength={2}
                    maxlength={100}
                    pattern={PATRON_NOMBRE}
                    title={AYUDA_NOMBRE}
                />
                <InputError class="mt-2" message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    class="mt-1 block w-full"
                    value={user.email}
                    required
                    autocomplete="username"
                    placeholder="Correo electrónico"
                />
                <InputError class="mt-2" message={errors.email} />
            </div>

            {#if Boolean(page.props.mustVerifyEmail) && !user.email_verified_at}
                <div>
                    <p class="-mt-4 text-sm text-muted-foreground">
                        Tu correo todavía no está verificado.
                        <TextLink href={send()} as="button">
                            Reenviar el correo de verificación.
                        </TextLink>
                    </p>

                    {#if page.props.status === 'verification-link-sent'}
                        <div class="mt-2 text-sm font-medium text-exito">
                            Te enviamos un nuevo enlace de verificación.
                        </div>
                    {/if}
                </div>
            {/if}

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="update-profile-button">Guardar</Button
                >
            </div>
        {/snippet}
    </Form>
</div>

<DeleteUser />
