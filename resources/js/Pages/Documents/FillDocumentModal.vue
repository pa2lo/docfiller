<script setup>
import { reactive, ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { toast } from '@/Utils/toaster'
import { dialog } from '@/Utils/dialog'
import { txt, systemFields, allFields, downloadFile } from '@/Utils/helpers'
import { useStorage } from '@/Composables/BrowserStorage'

import Modal from '@/Components/Modals/Modal.vue'
import Button from '@/Components/Elements/Button.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import RadioButtons from '@/Components/Inputs/RadioButtons.vue'
import Message from '@/Components/Elements/Message.vue'
import Loader from '@/Components/Elements/Loader.vue'
import FillForm from './FillForm.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import Dropdown from '@/Components/Floating/Dropdown.vue'
import DropdownLink from '@/Components/Floating/DropdownLink.vue'

const page = usePage()
const customFields = page.props.customFields
const formAllFields = allFields(customFields)

const emit = defineEmits(['filled'])

const fillTypeOptions = [
	{
		value: 'single',
		title: txt('Single')
	}, {
		value: 'multiple',
		title: txt('Multiple (zip)')
	}
]

const doc = ref(null)
const fillModal = ref(false)

let tableViewType = null

const availableSystemFields = ref([])
const availableOtherFields = ref([])
let tempDocOrder = null

function showFillModal(newDoc, newData = {}, newTempOrder) {
	doc.value = newDoc

	fillForm.clearErrors()
	fillForm.reset()

	fillForm.addressData = doc.value.fields.reduce((acc, item) => {
		acc[item] = newData?.addressData?.[item] || ''
		return acc
	}, {})
	fillForm.batch = newData?.batch ? JSON.parse(JSON.stringify(newData.batch)) : []

	fillForm.fill_type = newData?.fill_type ?? 'single'

	availableSystemFields.value = Object.keys(systemFields).filter(f => doc.value.fields.includes(f))
	availableOtherFields.value = doc.value.fields.filter(f => !systemFields.hasOwnProperty(f))

	tempDocOrder = newTempOrder ? newTempOrder : useStorage(`doc-fields-${doc.value.id}`, JSON.parse(JSON.stringify(doc.value.fields)))
	tableViewType = useStorage(`doc-rotate-${doc.value.id}`, 'row')

	fillModal.value = true
}

const fillForm = useForm({
	addressData: {},
	batch: [],
	note: '',
	fill_type: 'single',
	source_type: 'address-book'
})

function submitFill() {
	if (fillForm.fill_type == 'single') {
		if (Object.values(fillForm.addressData).every(v => !v)) {
			fillForm.errors.notFilled = "not filled"
			return
		}
		fillForm.batch = []
	} else {
		fillForm.batch = fillForm.batch.filter(row => {
			return doc.value.fields.some(k => row[k] != '')
		})

		if (!fillForm.batch.length) {
			fillForm.errors.noRows = "not filled"
			return
		}

		if (fillForm.batch.length == 1) {
			Object.keys(fillForm.addressData).forEach(k => fillForm.addressData[k] = fillForm.batch[0][k] || '')
			fillForm.fill_type = 'single'
			fillForm.batch = []
		} else Object.keys(fillForm.addressData).forEach(k => fillForm.addressData[k] = '')
	}

	fillForm.clearErrors()

	fillForm.processing = true
	axios.post(`/documents/${doc.value.id}/fill`, {
		addressData: fillForm.addressData,
		note: fillForm.note,
		fill_type: fillForm.fill_type,
		batch: fillForm.batch
	}).then(data => {
		if (data?.data?.fill?.id) {
			fillModal.value = false

			dialog.success(txt('Document filled'), '', {
				showCancel: true,
				confirmText: txt('Download'),
				onConfirm: () => downloadFile(`/fill/${data?.data?.fill?.id}/download`, data?.data?.fill?.generated_file)
			})

			emit('filled')
		} else if (data?.data?.deprecated) toast.warning(txt('deprecatedNote', 'The document is outdated and can no longer be filled out'))
		else toast.error(txt('Document fill failed'))
	}).catch(error => {
		toast.error(txt('Document fill failed'))
		console.log(error)
	}).finally(() => fillForm.processing = false)
}

const dataLoading = ref(false)

const recordModal = reactive({
	show: false,
	type: 'new',
	data: {},
	index: null,
	error: false
})
function showRecordModal(type, index = null) {
	recordModal.type = type
	recordModal.index = index
	recordModal.error = false

	if (type == 'new') {
		recordModal.data = doc.value.fields.reduce((acc, item) => {
			acc[item] = ''
			return acc
		}, {})
	} else recordModal.data = JSON.parse(JSON.stringify(fillForm.batch[index]))

	recordModal.show = true
}
function saveRecordModal() {
	if (Object.values(recordModal.data).every(v => !v)) {
		recordModal.error = true
		return
	}

	if (recordModal.type == 'new') fillForm.batch.push(recordModal.data)
	else if (recordModal.index !== null) fillForm.batch[recordModal.index] = recordModal.data

	recordModal.show = false

	if (fillForm.errors.noRows) fillForm.errors.noRows = ''
}
function cloneRecord(index) {
	fillForm.batch.splice(index+1, 0, JSON.parse(JSON.stringify(fillForm.batch[index])))
}
function deleteRecord(index) {
	dialog.delete(null, txt('deleteRecordQuestion', 'Are you sure you want to delete record?'), {
		onConfirm: () => {
			fillForm.batch.splice(index, 1)
		}
	})
}

const tableField = reactive({
	show: false,
	index: null,
	model: null,
	field: null,
	fieldTitle: null
})
function editTableField(index, field) {
	tableField.field = field
	tableField.index = index
	tableField.model = String(fillForm.batch[index][field] ?? '')
	tableField.fieldTitle = formAllFields[field] || field
	tableField.show = true
}
function saveTableField() {
	fillForm.batch[tableField.index][tableField.field] = String(tableField.model)

	if (Object.values(fillForm.batch[tableField.index]).every(v => !v)) fillForm.batch.splice(tableField.index, 1)

	tableField.show = false
}

function importRows() {
	const input = Object.assign(document.createElement('input'), {
		type: 'file',
		accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
	})

	input.onchange = (e) => {
		if (input.files) {
			const file = input.files[0]

			if (!file) return

			const formData = new FormData();
			formData.append('file', file)
			dataLoading.value = true
			axios.post('/loadFieldsData', formData, {
				headers: {
					'Content-Type': 'multipart/form-data'
				}
			}).then(res => {
				if (res?.data?.length) {
					let importRecords = res.data.map(item => {
						Object.keys(item).forEach(key => {
							if (!doc.value.fields.includes(key)) delete item[key]
						})
						doc.value.fields.forEach(field => {
							if (!item[field]) item[field] = ''
						})
						return item
					})
					if (importRecords.length) {
						fillForm.batch.push(...importRecords)
						toast.success(txt('Data imported successfully'))
					} else toast.error(txt('XLS has no data to import'))
				} else {
					toast.error(txt('Operation failed'))
					console.log(res)
				}
			}).catch(error => {
				toast.error(txt('Operation failed'))
				console.log(error)
			}).finally(() => {
				dataLoading.value = false
			})
		}
	}

	input.click()
}

function downloadXLSXWithData() {
	if (!fillForm.batch.length) return

	axios.post('/getXLSXTemplate', {
		rows: fillForm.batch,
	}, {
		responseType: 'blob'
	}).then(res => {
		if (res.data) {
			const href = URL.createObjectURL(res.data)
			const link = document.createElement('a')
			link.href = href
			link.setAttribute('download', doc.value.filename.replace('.docx', ' data.xlsx'))
			link.click()
			URL.revokeObjectURL(href)
		}
	}).catch(error => {
		console.log(error)
	})
}

// dd
const dragIndex = ref(null)
const dragHoverIndex = ref(null)
function onDragStart(e, i) {
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

function resetOrder() {
	tempDocOrder.value = JSON.parse(JSON.stringify(doc.value.fields))
}
const hasNewOrder = computed(() => doc.value.fields.toString() != tempDocOrder.value.toString())

defineExpose({ showFillModal })
</script>

<template>
	<Modal v-model:open="fillModal" :header="txt('Fill document')" :headerNote="doc?.title" width="wider" :closeable="!fillForm.processing" as="form" @submit.prevent="submitFill">
		<div class="inputs-grid">
			<RadioButtons
				:label="txt('Document format')"
				:options="fillTypeOptions"
				v-model="fillForm.fill_type"
				sameWidth
			/>
			<TextInput
				:label="txt('Note')"
				:placeholder="txt('Filled for ...')"
				v-model="fillForm.note"
			/>
		</div>
		<FillForm
			v-if="fillForm.fill_type == 'single'"
			v-model="fillForm.addressData"
			:formAllFields
			:availableSystemFields
			:availableOtherFields
			:hasError="fillForm.errors.notFilled ? true : false"
			@clearError="fillForm.errors.notFilled = ''"
		/>
		<div v-else class="line divided">
			<Loader :loading="dataLoading">
				<h4>{{ txt('Data') }}</h4>
				<Message v-if="fillForm.errors.noRows" type="error">{{ txt('noRowsError', 'No data available to generate the document. Please add at least one record.') }}</Message>
				<div v-if="fillForm.batch.length" class="dataTable-wrapper line">
					<table v-if="tableViewType == 'row'" class="infoTable">
						<thead>
							<tr>
								<th
									v-for="(field, i) in tempDocOrder"
									class="infoTable-default isMovable"
									:class="{ 'col-drop': i === dragHoverIndex, 'col-dragged': dragHoverIndex && dragIndex == i }"
									draggable="true"
									@dragstart="onDragStart($event, i)"
									@dragenter="onDragEnter($event, i)"
									@dragover="onDragOver($event, i)"
									@dragleave="onDragLeave($event, i)"
									@drop="onDrop($event, i)"
									@dragend="onDragCancel"
								>
									{{ formAllFields[field] || field }}
								</th>
								<th class="infoTable-buttons infoTable-sticky-right">
									<IcoButton v-if="hasNewOrder" icon="refresh" v-tooltip="txt('Reset order')" @click.prevent="resetOrder" />
									<IcoButton icon="table-col" v-tooltip="txt('Rotate table')" @click.prevent="tableViewType = 'col'" />
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="(row, index) in fillForm.batch">
								<td v-for="(field, i) in tempDocOrder" class="infoTable-highlight isClickable" :class="{ 'col-drop': i === dragHoverIndex, 'col-dragged': dragIndex == i }"  @click="editTableField(index, field)">
									{{ row[field] }}
								</td>
								<td class="infoTable-buttons infoTable-sticky-right">
									<IcoButton icon="edit" v-tooltip="txt('Edit')" @click.prevent="showRecordModal('edit', index)" />
									<IcoButton icon="copy" v-tooltip="txt('Clone')" @click.prevent="cloneRecord(index)" />
									<IcoButton icon="trash" color="danger" v-tooltip="txt('Delete')" @click.prevent="deleteRecord(index)" />
								</td>
							</tr>
						</tbody>
					</table>
					<table v-else class="infoTable">
						<tbody>
							<tr	v-for="(field, i) in tempDocOrder" :class="{ 'row-drop': i === dragHoverIndex, 'row-dragged': dragIndex == i }">
								<td
									class="infoTable-th isMovable infoTable-sticky-left"
									draggable="true"
									@dragstart="onDragStart($event, i)"
									@dragenter="onDragEnter($event, i)"
									@dragover="onDragOver($event, i)"
									@dragleave="onDragLeave($event, i)"
									@drop="onDrop($event, i)"
									@dragend="onDragCancel"
								>
									{{ formAllFields[field] || field }}
								</td>
								<td v-for="(row, index) in fillForm.batch" @click="editTableField(index, field)" class="infoTable-highlight isClickable">
									{{ row[field] }}
								</td>
							</tr>
							<tr>
								<td class="infoTable-th infoTable-buttons infoTable-buttons-left infoTable-sticky-left">
									<IcoButton icon="table-row" v-tooltip="txt('Rotate table')" @click.prevent="tableViewType = 'row'" />
									<IcoButton v-if="hasNewOrder" icon="refresh" v-tooltip="txt('Reset order')" @click.prevent="resetOrder" />
								</td>
								<td v-for="(row, index) in fillForm.batch" class="infoTable-buttons infoTable-buttons-left">
									<IcoButton icon="edit" v-tooltip="txt('Edit')" @click.prevent="showRecordModal('edit', index)" />
									<IcoButton icon="copy" v-tooltip="txt('Clone')" @click.prevent="cloneRecord(index)" />
									<IcoButton icon="trash" color="danger" v-tooltip="txt('Delete')" @click.prevent="deleteRecord(index)" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p class="buttons-row">
					<Button icon="plus" @click.prevent="showRecordModal('new')">{{ txt('Add record') }}</Button>
					<Button icon="upload" color="link" variant="outline" @click.prevent="importRows">{{ txt('Import from XLSX') }}</Button>
					<Button v-if="!fillForm.batch.length" class="ml-a" :link="`/documents/${doc.id}/getXLSXTemplate`" color="link" variant="outline" icon="download" download>{{ txt('Download XLSX template') }}</Button>
					<Dropdown v-else class="ml-a" variant="outline" color="link" align="right" :label="txt('More')">
						<DropdownLink :link="`/documents/${doc.id}/getXLSXTemplate`" download icon="download">{{ txt('Download XLSX template') }}</DropdownLink>
						<DropdownLink @click="downloadXLSXWithData" icon="download">{{ txt('Download XLSX with data') }}</DropdownLink>
					</Dropdown>
				</p>
			</Loader>
		</div>
		<template #buttons>
			<Button type="submit" :loading="fillForm.processing" icon="docfill">{{ txt('Fill document') }}</Button>
		</template>
	</Modal>
	<Modal v-model:open="recordModal.show" width="wider" :header="txt(recordModal.type == 'new' ? 'Add record' : 'Edit record')" as="form" @submit.prevent="saveRecordModal">
		<FillForm
			v-model="recordModal.data"
			:formAllFields
			:availableSystemFields
			:availableOtherFields
			:hasError="recordModal.error"
			@clearError="recordModal.error = false"
		/>
		<template #buttons>
			<Button type="submit">{{ txt('Save') }}</Button>
		</template>
	</Modal>
	<Modal v-model:open="tableField.show" width="narrow" :header="txt('Edit field')" as="form" @submit.prevent="saveTableField">
		<TextInput
			:label="tableField.fieldTitle"
			v-model="tableField.model"
			trim
			autofocus
			clearable
		/>
		<p>
			<Button full type="submit">{{ txt('Save') }}</Button>
		</p>
	</Modal>
</template>