<script module lang="ts">
    export const layout = {
        title: 'Crea tu cuenta de investigador',
        description: 'Gratis y en un minuto. Tus informes se cifran desde el primer envío.',
        portal: 'investigador',
        modo: 'registro',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AceptarTerminos from '@/components/AceptarTerminos.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { store } from '@/routes/register';
    import { AYUDA_NOMBRE, PATRON_NOMBRE } from '@/lib/validacion';

    let { passwordRules }: { passwordRules: string } = $props();
</script>

<AppHead title="Crear cuenta de investigador" />

<Form {...store.form()} resetOnSuccess={['password', 'password_confirmation']} class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autocomplete="name"
                    name="name"
                    placeholder="Nombre y apellido"
                    minlength={2}
                    maxlength={100}
                    pattern={PATRON_NOMBRE}
                    title={AYUDA_NOMBRE}
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input id="email" type="email" required autocomplete="email" name="email" placeholder="tu@correo.com" />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput id="password" required autocomplete="new-password" name="password" placeholder="Mínimo 8 caracteres" passwordrules={passwordRules} />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput id="password_confirmation" required autocomplete="new-password" name="password_confirmation" placeholder="Repite la contraseña" passwordrules={passwordRules} />
                <InputError message={errors.password_confirmation} />
            </div>

            <AceptarTerminos error={errors.terminos} />

            <Button type="submit" class="mt-1 w-full" disabled={processing} data-test="register-user-button">
                {#if processing}<Spinner />{/if}
                Crear cuenta
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            ¿Ya tienes cuenta?
            <TextLink href={login()}>Inicia sesión</TextLink>
        </p>
    {/snippet}
</Form>
