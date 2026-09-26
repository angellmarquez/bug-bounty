<script module lang="ts">
    export const layout = {
        title: 'Bienvenido de nuevo',
        description: 'Entra con tu cuenta de investigador.',
        portal: 'investigador',
        modo: 'login',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { register } from '@/routes';
    import { store } from '@/routes/login';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();
</script>

<AppHead title="Acceso investigadores" />

{#if status}
    <div class="mb-4 rounded-md border border-exito/40 bg-exito/10 px-3 py-2 text-sm font-medium text-exito">
        {status}
    </div>
{/if}

<Form {...store.form()} resetOnSuccess={['password']} class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input id="email" type="email" name="email" required autocomplete="email" placeholder="tu@correo.com" />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput id="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña" />
                <InputError message={errors.password} />
            </div>

            <Label for="remember" class="flex items-center gap-3 font-normal">
                <Checkbox id="remember" name="remember" />
                <span>Mantener la sesión iniciada</span>
            </Label>

            <Button type="submit" class="mt-2 w-full" disabled={processing} data-test="login-button">
                {#if processing}<Spinner />{/if}
                Iniciar sesión
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            ¿Aún no tienes cuenta?
            <TextLink href={register()}>Crea tu cuenta de investigador</TextLink>
        </p>
    {/snippet}
</Form>
