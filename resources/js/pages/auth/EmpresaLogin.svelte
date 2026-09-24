<script module lang="ts">
    export const layout = {
        title: 'Acceso empresarial',
        description: 'Inicia sesión para consultar y gestionar tu empresa',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
</script>

<AppHead title="Acceso empresarial" />

<Form method="post" action="/login" resetOnSuccess={['password']} class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Correo del responsable</Label>
                <Input id="email" type="email" name="email" required autocomplete="email" />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput id="password" name="password" required autocomplete="current-password" />
                <InputError message={errors.password} />
            </div>

            <Button type="submit" class="w-full" disabled={processing}>
                {#if processing}<Spinner />{/if}
                Iniciar sesión
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            ¿Tu empresa aún no está registrada?
            <a href="/empresa/registro" class="underline underline-offset-4">Crear solicitud</a>
        </div>
    {/snippet}
</Form>