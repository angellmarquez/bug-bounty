<script module lang="ts">
    export const layout = {
        title: 'Registra tu empresa',
        description: 'Verificamos cada solicitud antes de activarla. Te avisaremos por correo.',
        portal: 'empresa',
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
    import { AYUDA_NOMBRE, PATRON_NOMBRE } from '@/lib/validacion';

    let { passwordRules }: { passwordRules: string } = $props();
</script>

<AppHead title="Registrar empresa" />

<Form method="post" action="/empresa/registro" class="flex flex-col gap-6">
    {#snippet children({ errors, processing })}
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="razon_social">Razón social</Label>
                <Input id="razon_social" name="razon_social" required autocomplete="organization" placeholder="Ej: Acme Corporation S.A." />
                <p class="text-xs text-muted-foreground">Nombre legal oficial de la entidad jurídica registrada.</p>
                <InputError message={errors.razon_social} />
            </div>

            <div class="grid gap-2">
                <Label for="nombre_comercial">Nombre comercial</Label>
                <Input id="nombre_comercial" name="nombre_comercial" autocomplete="organization" placeholder="Ej: Acme Labs (opcional)" />
                <p class="text-xs text-muted-foreground">Marca comercial o nombre visible para la comunidad.</p>
                <InputError message={errors.nombre_comercial} />
            </div>

            <div class="grid gap-2">
                <Label for="identificador_fiscal">Identificador fiscal</Label>
                <Input id="identificador_fiscal" name="identificador_fiscal" required placeholder="Ej: J-12345678-0 o B-12345678" />
                <p class="text-xs text-muted-foreground">Código de identificación tributaria (RUC, CIF, RFC, NIF o NIT).</p>
                <InputError message={errors.identificador_fiscal} />
            </div>

            <div class="grid gap-2">
                <Label for="empresa_email">Correo de la empresa</Label>
                <Input id="empresa_email" type="email" name="empresa_email" required autocomplete="organization" placeholder="contacto@empresa.com" />
                <p class="text-xs text-muted-foreground">Correo institucional o corporativo para notificaciones.</p>
                <InputError message={errors.empresa_email} />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nombre del responsable</Label>
                <Input id="name" name="name" required autocomplete="name" minlength={2} maxlength={100} pattern={PATRON_NOMBRE} title={AYUDA_NOMBRE} placeholder="Ej: María Pérez" />
                <p class="text-xs text-muted-foreground">Nombre y apellido del representante (solo letras y espacios).</p>
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo del responsable</Label>
                <Input id="email" type="email" name="email" required autocomplete="email" placeholder="tu-correo@empresa.com" />
                <p class="text-xs text-muted-foreground">Email con el que la persona iniciará sesión.</p>
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput id="password" name="password" required autocomplete="new-password" passwordrules={passwordRules} />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput id="password_confirmation" name="password_confirmation" required autocomplete="new-password" passwordrules={passwordRules} />
                <InputError message={errors.password_confirmation} />
            </div>

            <AceptarTerminos error={errors.terminos} empresa />

            <Button type="submit" class="mt-1 w-full" disabled={processing} data-test="register-empresa-button">
                {#if processing}<Spinner />{/if}
                Enviar solicitud
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            ¿Tu empresa ya tiene cuenta?
            <TextLink href="/empresa/login">Inicia sesión</TextLink>
        </p>
    {/snippet}
</Form>