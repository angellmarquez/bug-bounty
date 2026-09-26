<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Label } from '@/components/ui/label';
</script>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Eliminar cuenta"
        description="Borra tu cuenta y todos sus datos"
    />
    <div
        class="space-y-4 rounded-lg border border-peligro/30 bg-peligro/10 p-4"
    >
        <div class="relative space-y-0.5 text-peligro">
            <p class="font-medium">Atención</p>
            <p class="text-sm">
                Esta acción no se puede deshacer.
            </p>
        </div>
        <Dialog>
            <DialogTrigger asChild>
                {#snippet children(props)}
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                        {...props}
                    >
                        Eliminar cuenta
                    </Button>
                {/snippet}
            </DialogTrigger>
            <DialogContent>
                <Form
                    {...ProfileController.destroy.form()}
                    class="space-y-6"
                    options={{ preserveScroll: true }}
                >
                    {#snippet children({ errors, processing })}
                        <div class="space-y-3">
                            <DialogTitle
                                >¿Seguro que quieres eliminar tu cuenta?</DialogTitle
                            >
                            <DialogDescription>
                                Al eliminarla se borran para siempre tu cuenta y
                                todos sus datos. Escribe tu contraseña para
                                confirmarlo.
                            </DialogDescription>
                        </div>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Contraseña</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Contraseña"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose asChild>
                                {#snippet children(props)}
                                    <Button variant="secondary" {...props}>Cancelar</Button>
                                {/snippet}
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="confirm-delete-user-button"
                            >
                                Eliminar cuenta
                            </Button>
                        </DialogFooter>
                    {/snippet}
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</div>
