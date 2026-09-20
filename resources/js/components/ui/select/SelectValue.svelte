<script lang="ts">
	import { Select as SelectPrimitive } from 'bits-ui';
	import type { ComponentProps } from 'svelte';

	let {
		ref = $bindable(null),
		placeholder,
		...restProps
	}: ComponentProps<typeof SelectPrimitive.Value> = $props();
</script>

<SelectPrimitive.Value bind:ref {placeholder} {...restProps} />
