<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { txt, formatDate, getAddressName, allFields } from '@/Utils/helpers'
import { toast } from '@/Utils/toaster'
import { dialog } from '@/Utils/dialog'
import { useDeleteForm } from '@/Composables/DeleteForm'
import { useStorage } from '@/Composables/BrowserStorage'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import Card from '@/Components/Elements/Card.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import TableInfo from '@/Components/Table/TableInfo.vue'
import Column from '@/Components/Table/Column.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import TextareaInput from '@/Components/Inputs/TextareaInput.vue'
import Modal from '@/Components/Modals/Modal.vue'
import SimpleToggle from '@/Components/Inputs/SimpleToggle.vue'
import Message from '@/Components/Elements/Message.vue'

import FillDocumentModal from './FillDocumentModal.vue'
import FillDetailsModal from './FillDetailsModal.vue'
import FillNoteModal from './FillNoteModal.vue'

const props = defineProps({
	document: Object,
	fills: Array,
	customFields: Object
})

const allFormFields = allFields(props.customFields)

const fillModal = ref(null)
const detailsModal = ref(null)
const fillNoteModal = ref(null)

const showEditModal = ref(false)
const editForm = useForm({
	title: props.document.title,
	description: props.document.description,
	deprecated: props.document.deprecated
})
function submitEditForm() {
	editForm.clearErrors()

	editForm.patch(`/documents/${props.document.id}`, {
		preserveScroll: true,
		onSuccess: () => {
			toast.success(txt('Document updated'))
			showEditModal.value = false
		}
	})
}

const { deletingIDs: deletingFills, deleteItem } = useDeleteForm()
function deleteFill(id) {
	if (!id) return

	deleteItem(id, `/fill/${id}`, {
		onSuccess: () => router.reload({ preserveScroll: true, only: ['fills'] })
	})
}

const isDeleting = ref(false)
function onDeleteError(data) {
	toast.error(txt('Operation failed'))
	console.log(data)
	isDeleting.value = false
}
function deleteDocument() {
	dialog.delete(txt('Delete document'), txt('removeDocumentQuestion', 'Are you sure you want to remove the document <strong>#0#</strong> and all of associated fills from the system?', [props.document.title]), {
		onConfirm: () => {
			isDeleting.value = true
			axios.delete(`/documents/${props.document.id}`).then(res => {
				if (res?.data?.success) {
					toast.success(txt('Document deleted'))
					router.visit('/')
				} else onDeleteError(res)
			}).catch(error => onDeleteError(error))
		}
	})
}

async function copyRow(id) {
	if (!id) return

	await navigator.clipboard.writeText(id).then(() => {
		toast.success(txt('Copied to clipboard'))
	})
}

// fields order
const availableFieldsModal = ref(false)
let tempDocOrder = useStorage(`doc-fields-${props.document.id}`, JSON.parse(JSON.stringify(props.document.fields)))
function moveField(up = false, index) {
	const newIndex = up ? index-1 : index+1
    tempDocOrder.value.splice(newIndex, 0, tempDocOrder.value.splice(index, 1)[0])
}
function resetOrder() {
	tempDocOrder.value = JSON.parse(JSON.stringify(props.document.fields))
}

const updatingOrder = ref(false)
function saveNewOrder() {
	updatingOrder.value = true
	axios.post(`/documents/${props.document.id}/updateFields`, {
		fields: tempDocOrder.value
	}).then((res) => {
		if (res?.data?.success) {
			toast.success(txt('Order saved'))
			props.document.fields = JSON.parse(JSON.stringify(tempDocOrder.value))
			localStorage.removeItem(`doc-fields-${props.document.id}`)
			tempDocOrder = useStorage(`doc-fields-${props.document.id}`, JSON.parse(JSON.stringify(props.document.fields)))
			availableFieldsModal.value = false
		} else onUpdateOrderError(res)
	}).catch(error => onUpdateOrderError(error)).finally(() => {
		updatingOrder.value = false
	})
}
function onUpdateOrderError(data) {
	console.log(data)
	toast.error(txt('Operation failed'))
}

// dd
const dragAllowed = ref(false)
const dragIndex = ref(null)
const dragHoverIndex = ref(null)
function onDragStart(e, i) {
	if (!dragAllowed.value) e.preventDefault()
	dragIndex.value = i
}
function onDragEnter(e, i) {
	if (i === dragIndex.value) return
	e.preventDefault()
	dragHoverIndex.value = i
}
function onDragLeave(e, i) {
	if (!e.currentTarget.contains(e.relatedTarget) && i == dragHoverIndex.value) dragHoverIndex.value = null
}
function onDragOver(e, i) {
	if (i != dragIndex.value) e.preventDefault()
}
function onDragCancel() {
	dragHoverIndex.value = null
}
function onDrop(e, i) {
	tempDocOrder.value.splice(i, 0, tempDocOrder.value.splice(dragIndex.value, 1)[0])
	dragHoverIndex.value = null
}

const hasNewOrder = computed(() => props.document.fields.toString() != tempDocOrder.value.toString())

function showFillModal(data) {
	fillModal.value.showFillModal(props.document, data, tempDocOrder)
}
</script>

<template>
	<AuthenticatedLayout :header="document.title" backLink="/">
		<Card :header="txt('Detail')" :headerNote="`${txt('Created by')}: ${document?.user?.name}`">
			<template #actions>
				<Button size="compact" color="link" variant="outline" icon="edit" @click.prevent="showEditModal = true" :disabled="isDeleting">{{ txt('Edit') }}</Button>
			</template>
			<Message v-if="document.deprecated" type="warning">{{ txt('deprecatedNote', 'The document is outdated and can no longer be filled out') }}</Message>
			<p v-if="document.description">{{ document.description }}</p>
			<TableInfo v-if="fills.length">
				<h5>{{ txt('Filled') }}: {{ fills.length }}x</h5>
				<template v-if="!document.deprecated" #buttons>
					<Button icon="docfill" @click.prevent="showFillModal(null)" :disabled="isDeleting">{{ txt('Fill document') }}</Button>
				</template>
			</TableInfo>
			<DataTable :items="fills" :itemWord="txt('records2', 'records')" modelField="id" :loadingRows="deletingFills">
				<template v-if="!document.deprecated" #empty>
					<Button icon="docfill" @click.prevent="showFillModal(null)" :disabled="isDeleting">{{ txt('Fill document') }}</Button>
				</template>
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton icon="download" v-tooltip="txt('Download')" :link="`/fill/${data.id}/download`" download />
					</template>
				</Column>
				<Column :header="txt('Name')" :colClick="(data) => detailsModal.showDetailsModal(data, document, data?.user)">
					<template #default="{ data }">
						<strong class="basic-link">{{ getAddressName(data) }}</strong>
						<span v-if="data.addressData?.ico" class="input-label-note">{{ data.addressData.ico }}</span>
					</template>
				</Column>
				<Column v-if="fills?.some(f => f.fill_type == 'single' && f.note)" :header="txt('Note')">
					<template #default="{ data }">
						<span v-if="data.fill_type == 'single' && data.note">{{ data.note }}</span>
					</template>
				</Column>
				<Column :header="txt('Created by')" align="center">
					<template #default="{ data }">
						<span v-if="data.user?.name">{{ data.user.name }}</span>
						<span v-else>-</span>
					</template>
				</Column>
				<Column :header="txt('Added')" type="date" field="created_at" />
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton icon="edit" v-tooltip="txt('Edit note')" @click.prevent="fillNoteModal.openEditFillForm(data)" />
						<IcoButton icon="docfill" v-tooltip="txt(document.deprecated ? 'Outdated document' : 'Fill again')" @click.prevent="showFillModal(data)" :disabled="document.deprecated" />
						<IcoButton icon="article" v-tooltip="txt('Details')" @click.prevent="detailsModal.showDetailsModal(data, document, data?.user)" />
						<IcoButton icon="trash" v-tooltip="txt('Delete')" color="danger" @click.prevent="deleteFill(data.id)" :loading="deletingFills.includes(data.id)" />
					</template>
				</Column>
			</DataTable>
			<template #footer>
				<div class="smaller">
					<div>{{ txt('Added') }}: <strong class="nowrap">{{ formatDate(document.created_at) }}</strong></div>
					<div>{{ txt('Updated') }}: <strong class="nowrap">{{ formatDate(document.updated_at) }}</strong></div>
				</div>
			</template>
			<template #buttons>
				<Button color="link" variant="outline" icon="circle-info" @click.prevent="availableFieldsModal = true">{{ txt('Available fields') }}</Button>
				<Button color="link" variant="outline" icon="download" :link="`/documents/${document.id}/download`" download :disabled="isDeleting">{{ txt('Download') }}</Button>
				<Button color="danger" variant="outline" icon="trash" @click.prevent="deleteDocument" :loading="isDeleting">{{ txt('Delete') }}</Button>
			</template>
		</Card>
		<Modal v-model:open="showEditModal" width="narrow" :header="txt('Edit document')" :closeable="!editForm.processing" as="form" @submit.prevent="submitEditForm">
			<TextInput
				:label="txt('Title')"
				:placeholder="txt('My document')"
				v-model="editForm.title"
				:error="editForm.errors.title"
				autofocus
				required
				autocomplete="off"
			/>
			<TextareaInput
				:label="txt('Note')"
				:placeholder="txt('document used for...')"
				v-model="editForm.description"
				:error="editForm.errors.description"
				:rows="3"
				autocomplete="off"
			/>
			<SimpleToggle
				:label="txt('Outdated document')"
				v-model="editForm.deprecated"
				:error="editForm.errors.deprecated"
			/>
			<p>
				<Button full type="submit" :loading="editForm.processing" icon="save">{{ txt('Save') }}</Button>
			</p>
		</Modal>
		<Modal v-model:open="availableFieldsModal" width="narrower" :header="txt('Available fields')" showCloseButton :closeable="!updatingOrder">
			<div class="dataTable-wrapper">
				<table class="infoTable line">
					<thead>
						<tr>
							<th>{{ txt('Field') }}</th>
							<th>ID</th>
							<th class="infoTable-buttons infoTable-sticky-right">
								<IcoButton v-if="hasNewOrder" icon="refresh" v-tooltip="txt('Reset order')" @click.prevent="resetOrder" />
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="(k, i) in tempDocOrder"
							:class="{ 'row-drop': i === dragHoverIndex, 'row-dragged': dragIndex == i }"
							draggable="true"
							@dragstart="onDragStart($event, i)"
							@dragenter="onDragEnter($event, i)"
							@dragover="onDragOver($event, i)"
							@dragleave="onDragLeave($event, i)"
							@drop="onDrop($event, i)"
							@dragend="onDragCancel"
						>
							<td class="isClickable" @click="copyRow(k)">{{ allFormFields[k] ?? k }}</td>
							<td class="isClickable infoTable-highlight-onRow" @click="copyRow(k)"><strong>{{ k }}</strong></td>
							<td class="infoTable-buttons infoTable-sticky-right">
								<IcoButton :invisible="i == 0" icon="up" @click="moveField(true, i)" />
								<IcoButton :invisible="i == document.fields.length -1" icon="down" @click="moveField(false, i)" />
								<IcoButton class="isMovable touch-hide" icon="move" @pointerdown="dragAllowed = true" @pointerup="dragAllowed = false" @pointercancel="dragAllowed = false" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p>{{ txt('clickToCopyRow', 'Clicking on a row in the table copies the ID to the clipboard.') }}</p>
			<template v-if="hasNewOrder" #buttons>
				<Button icon="save" @click.prevent="saveNewOrder" :loading="updatingOrder">{{ txt('Save order') }}</Button>
			</template>
		</Modal>
		<FillNoteModal ref="fillNoteModal" />
		<FillDocumentModal ref="fillModal" @filled="router.reload({ preserveScroll: true, only: ['fills'] })" />
		<FillDetailsModal ref="detailsModal" @fillAgain="(d, f) => showFillModal(f)" />
	</AuthenticatedLayout>
</template>