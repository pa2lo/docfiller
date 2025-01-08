<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { txt, allFields, getAddressName } from '@/Utils/helpers'
import { useAppForms } from '@/Composables/AppForms'
import { toast } from '@/Utils/toaster'
import { flushAddressData } from '@/Utils/store'
import { useDeleteForm } from '@/Composables/DeleteForm'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import Card from '@/Components/Elements/Card.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import TableInfo from '@/Components/Table/TableInfo.vue'
import FilterTags from '@/Components/Table/FilterTags.vue'
import Column from '@/Components/Table/Column.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import Modal from '@/Components/Modals/Modal.vue'
import Tag from '@/Components/Elements/Tag.vue'
import Message from '@/Components/Elements/Message.vue'
import Icon from '@/Components/Elements/Icon.vue'

const props = defineProps({
	addresses: Array,
	customFields: Object
})

const formAllFields = allFields(props.customFields)
const hasCustomFields = Object.keys(props.customFields)?.length

const filter = ref('')
const filteredAddresses = computed(() => {
	let fvLowercase = filter.value.toLocaleLowerCase()
	return props.addresses.filter(address => ['ico', 'firma', 'meno', 'priezvisko'].some(ad => address.addressData[ad]?.toLocaleLowerCase().includes(fvLowercase)))
})

const { activeForm, showModal, showNewForm, showEditForm } = useAppForms({
	description: '',
	addressData: Object.keys(formAllFields).reduce((acc, current) => {
		acc[current] = ''
		return acc
	}, {})
})

function showEditFormExt(data) {
	let realKeys = Object.keys(formAllFields)

	realKeys.filter(f => !data.addressData.hasOwnProperty(f)).forEach(key => {
		data.addressData[key] = ''
	})

	Object.keys(data.addressData).filter(f => !realKeys.includes(f) && data.addressData[f] == null).forEach(key => {
		delete data.addressData[key]
	})

	showEditForm(data)
}

function submitAddressForm() {
	if (['firma', 'meno', 'priezvisko'].every(k => !activeForm.value.form.addressData[k])) {
		activeForm.value.form.errors.notFilled = "not filled"
		return
	}

	activeForm.value.form.clearErrors()
	activeForm.value.type == 'newForm' ? submitNewAddress() : submitEditAddress()
}
function submitNewAddress() {
	activeForm.value.form.post('/address-book', {
		preserveScroll: true,
		onSuccess: () => onSubmitSuccess('Address added')
	})
}
function submitEditAddress() {
	activeForm.value.form.patch(`/address-book/${activeForm.value.form.id}`, {
		preserveScroll: true,
		onSuccess: () => onSubmitSuccess('Address updated')
	})
}
function onSubmitSuccess(text) {
	toast.success(txt(text))
	showModal.value = false
	flushAddressData()
}

const { deletingIDs, deleteItem } = useDeleteForm()
function deleteAddress(item) {
	deleteItem(item.id, `/address-book/${item.id}`, {
		dialogTitle: txt('Delete address'),
		dialogText: txt('deleteAddressQuestion', 'Are you sure you want to delete address <strong>#0#</strong>?', getAddressName(item)),
		onSuccess: () => router.reload({ preserveScroll: true, only: ['addresses'] })
	})
}
</script>

<template>
	<AuthenticatedLayout :header="txt('Address book')">
		<Card>
			<TableInfo v-if="addresses.length" :count="filteredAddresses.length" :countWords="[txt('address'), txt('address2', 'addresses'), txt('addresses')]">
				<TextInput placeholder="Filter..." icon="search" v-model="filter" clearable />
				<template #buttons>
					<Button icon="plus" @click.prevent="showNewForm">{{ txt('Add address') }}</Button>
				</template>
			</TableInfo>
			<FilterTags>
				<Tag v-if="filter" @click="filter = ''" clearable>Filter: {{ filter }}</Tag>
			</FilterTags>
			<DataTable :items="filteredAddresses" :itemWord="txt('address2', 'addresses')" modelField="id" :loadingRows="deletingIDs">
				<template #empty>
					<Button v-if="filter" icon="x" variant="outline" @click="filter = ''">{{ txt('Reset filter') }}</Button>
					<Button v-else icon="plus" size="bigger" @click.prevent="showNewForm">{{ txt('Add address') }}</Button>
				</template>
				<Column v-if="filteredAddresses.some(a => a.description)" type="icon">
					<template #default="{ data }">
						<Icon v-if="data.description" name="circle-info" v-tooltip.touch="data.description" />
					</template>
				</Column>
				<Column :header="txt('Name')" :colClick="showEditFormExt" minWidth="7rem">
					<template #default="{ data }">
						<span class="basic-link">{{ getAddressName(data) }}</span>
					</template>
				</Column>
				<Column :header="txt('IČO')">
					<template #default="{ data }">
						<span v-if="data.addressData.ico">{{ data.addressData.ico }}</span>
						<span v-else>-</span>
					</template>
				</Column>
				<Column :header="txt('Created')" field="created_at" type="date" />
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton icon="edit" v-tooltip="txt('Edit')" @click.stop="showEditFormExt(data)" />
						<IcoButton icon="trash" color="danger" v-tooltip="txt('Delete')" @click.stop="deleteAddress(data)" :loading="deletingIDs.includes(data.id)" />
					</template>
				</Column>
			</DataTable>
		</Card>
		<Modal v-model:open="showModal" width="wider" :header="txt(activeForm?.type == 'newForm' ? 'New address' : 'Edit address')" as="form" @submit.prevent="submitAddressForm" :closeable="!activeForm?.form?.processing" :headerNote="activeForm?.type == 'editForm' ? `ID: ${activeForm.form.id}` : null">
			<TextInput
				:label="txt('Note')"
				:placeholder="txt('internal note')"
				v-model="activeForm.form.description"
				:error="activeForm.form.errors.description"
				trim
			/>
			<div class="inputs-grid divided cols3">
				<Message v-if="activeForm.form.errors.notFilled" class="input-full" type="error">{{ txt('notFilledFields2', 'Fill at least one of the fields Firma, Meno or Priezvisko') }}</Message>
				<template v-for="(val, id) in activeForm.form.addressData">
					<TextInput
						:label="formAllFields[id] ?? id"
						:labelNote="formAllFields[id] ? `- ${id}` : null"
						v-model="activeForm.form.addressData[id]"
						trim
					/>
					<div v-if="id == 'krajina' && hasCustomFields" class="divided"></div>
				</template>
			</div>
			<template #buttons>
				<Button :loading="activeForm?.form?.processing" type="submit" icon="save">{{ txt('Save') }}</Button>
			</template>
		</Modal>
	</AuthenticatedLayout>
</template>