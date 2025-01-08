<script setup>
import { txt } from '@/Utils/helpers'

defineProps({
	fields: Array,
	allFields: Object,
	addressData: Object
})
</script>

<template>
	<div class="dataTable-wrapper line">
		<table class="infoTable">
			<thead>
				<tr>
					<th>{{ txt('Field') }}</th>
					<th>{{ txt('Value') }}</th>
				</tr>
			</thead>
			<tbody>
				<template v-for="f in fields">
					<tr v-if="addressData[f]">
						<td>{{ allFields[f] || f }}</td>
						<td>{{ addressData[f] }}</td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>
</template>