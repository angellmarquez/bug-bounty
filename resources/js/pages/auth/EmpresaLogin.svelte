<script module lang="ts">
    export const layout = {
        title: 'Acceso para empresas',
        description: 'Entra con el correo del responsable de tu empresa.',
        portal: 'empresa',
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
</script>

<AppHead title="Acceso empresas" />

<Form method="post" action="/login" resetOnSuccess={['password']} class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <!-- Indica al servidor que es el acceso de empresas: una cuenta de otro tipo no entra por aquí. -->
        <input type="hidden" name="portal" value="empresa" />
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Correo del responsable</Label>
                <Input id="email" type="email" name="email" required autocomplete="email" placeholder="responsable@empresa.com" />
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
            ¿Tu empresa aún no está registrada?
            <TextLink href="/empresa/registro">Solicita el alta</TextLink>
        </p>
    {/snippet}
</Form>
