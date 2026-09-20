<script lang="ts">
	import { Select as SelectPrimitive } from 'bits-ui';

type SelectProps = Omit<SelectPrimitive.RootProps, 'type' | 'value'> & {
	type?: 'single';
	value?: string;
	onValueChange?: (value: string) => void;
};

	let {
		open = $bindable(false),
		value = $bindable(''),
		type = 'single',
		onValueChange,
		...restProps
	}: SelectProps = $props();

	function handleValueChange(v: string) {
		value = v;
		onValueChange?.(v);
	}
</script>

<SelectPrimitive.Root {type} bind:open value={value as never} onValueChange={handleValueChange as never} {...restProps} />
