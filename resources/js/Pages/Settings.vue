<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { txt, systemFields, slugify } from '@/Utils/helpers'
import { toast } from '@/Utils/toaster'
import { dialog } from '@/Utils/dialog'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import Card from '@/Components/Elements/Card.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import TableInfo from '@/Components/Table/TableInfo.vue'
import Column from '@/Components/Table/Column.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import IcoButtonCopy from '@/Components/Elements/IcoButtonCopy.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import Modal from '@/Components/Modals/Modal.vue'

const props = defineProps({
	customFields: Object
})

const allFields = computed(() => {
	let fields = {...systemFields, ...props.customFields}

	return Object.entries(fields).reduce((acc, [id, val]) => {
		acc.push({
			id: id,
			val: val,
			removable: !['firma', 'meno', 'priezvisko', 'ico', 'dic', 'icdph', 'adresa', 'mesto', 'psc', 'okres', 'kraj', 'krajina'].includes(id)
		})
		return acc
	}, [])
})

const newFieldModal = ref(false)
const newFieldForm = useForm({
	id: '',
	title: ''
})
function showNewForm() {
	newFieldForm.reset()
	newFieldForm.clearErrors()
	newFieldModal.value = true
}
function submitNewFieldForm() {
	newFieldForm.clearErrors()

	if (allFields.value.some(f => f.id == newFieldForm.id)) {
		newFieldForm.errors.id = txt('fieldExist', 'Field with this ID already exists')
		return
	}

	newFieldForm.post('/settings/addField', {
		preserveScroll: true,
		onSuccess: () => {
			newFieldForm.reset()
			toast.success(txt('Field added'))
		},
		onFinish: () => newFieldModal.value = false
	})
}
function onIDChange() {
	newFieldForm.id = slugify(newFieldForm.id)
	if (allFields.value.some(f => f.id == newFieldForm.id)) newFieldForm.errors.id = txt('fieldExist', 'Field with this ID already exists')
}

const deletingFieldForm = useForm({
	id: null
})
function deleteField(data) {
	if (!data.id) return

	dialog.delete(txt('Delete field'), txt('removeFieldQuestion', 'Are you sure you want to delete the <strong>#0#</strong> field?', [data.val]), {
		onConfirm: () => {
			deletingFieldForm.id = data.id
			deletingFieldForm.post('/settings/deleteField', {
				preserveScroll: true,
				onSuccess: () => {
					deletingFieldForm.reset()
					toast.success(txt('Field deleted'))
				}
			})
		}
	})
}
</script>

<template>
	<AuthenticatedLayout :header="txt('Settings')">
		<Card>
			<TableInfo>
				<template #buttons>
					<Button icon="plus" @click="showNewForm">{{ txt('Add field') }}</Button>
				</template>
				<h3>{{ txt('Fields') }}</h3>
			</TableInfo>
			<DataTable :items="allFields">
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButtonCopy :copyableText="'${'+data.id+'}'"/>
					</template>
				</Column>
				<Column :header="txt('Field')" field="val" />
				<Column header="ID" field="id" />
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton v-if="data.removable" icon="trash" color="danger" v-tooltip="txt('Delete')" :loading="data.id == deletingFieldForm.id" @click.prevent="deleteField(data)" />
					</template>
				</Column>
			</DataTable>
		</Card>
		<Modal v-model:open="newFieldModal" :header="txt('New field')" width="narrow" as="form" @submit.prevent="submitNewFieldForm" :closeable="!newFieldForm.processing">
			<TextInput
				:label="txt('Title')"
				:placeholder="txt('User age')"
				v-model="newFieldForm.title"
				:error="newFieldForm.errors.title"
				trim
				required
				autofocus
			/>
			<TextInput
				label="ID"
				:placeholder="txt('userage')"
				v-model="newFieldForm.id"
				:error="newFieldForm.errors.id"
				trim
				@change="onIDChange"
				required
			/>
			<p>
				<Button type="submit" full :loading="newFieldForm.processing" icon="save">{{ txt('Add field') }}</Button>
			</p>
		</Modal>
	</AuthenticatedLayout>
</template>