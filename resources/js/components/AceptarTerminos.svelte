<script lang="ts">
    import InputError from '@/components/InputError.svelte';

    // Casilla obligatoria del registro: el servidor exige `terminos` aceptado y guarda la versión.
    let { error = '', empresa = false }: { error?: string; empresa?: boolean } = $props();
</script>

<div class="grid gap-1.5">
    <label for="terminos" class="flex items-start gap-3 text-sm leading-snug">
        <input
            id="terminos"
            name="terminos"
            type="checkbox"
            value="1"
            required
            class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
            data-test="aceptar-terminos"
        />
        <span class="text-muted-foreground">
            He leído y acepto los
            <a href="/terminos" target="_blank" rel="noopener" class="text-foreground underline underline-offset-4">Términos de Servicio</a>{empresa ? ',' : ' y'}
            la <a href="/privacidad" target="_blank" rel="noopener" class="text-foreground underline underline-offset-4">Política de Privacidad</a>{#if empresa}{' '}y la <a href="/politica-de-divulgacion" target="_blank" rel="noopener" class="text-foreground underline underline-offset-4">Política de Divulgación</a>, incluido el compromiso de puerto seguro con los investigadores{/if}.
        </span>
    </label>
    <InputError message={error} />
</div>
