<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { txt, allFields } from '@/Utils/helpers'
import { useAppForms } from '@/Composables/AppForms'
import { toast } from '@/Utils/toaster'
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
import TextareaInput from '@/Components/Inputs/TextareaInput.vue'
import FileInput from '@/Components/Inputs/FileInput.vue'
import Tag from '@/Components/Elements/Tag.vue'
import Modal from '@/Components/Modals/Modal.vue'
import Icon from '@/Components/Elements/Icon.vue'

import FillDocumentModal from './FillDocumentModal.vue'

const props = defineProps({
	documents: Array,
	customFields: Object
})

const allFormFields = allFields(props.customFields)

const fillModal = ref(null)

const filter = ref('')
const filteredDocuments = computed(() => {
	return props.documents.filter(doc => ['title', 'description'].some(f => doc[f]?.toLocaleLowerCase().includes(filter.value.toLocaleLowerCase())))
})

const { activeForm, showModal, showNewForm } = useAppForms({
	title: '',
	description: '',
	file: null
})

function setActiveFormFile(e) {
	if (!e.target?.files?.length) return

	activeForm.value.form.file = e.target.files[0]
}

function submitNewDocument() {
	activeForm.value.form.clearErrors()
	activeForm.value.form.post('/documents', {
		preserveScroll: true,
		onSuccess: (data) => {
			showModal.value = false
			toast.success(txt('Document created'), {
				onClick: () => {
					if (data?.props?.flash?.id) router.visit(`/documents/${data.props.flash.id}`)
					else return
				}
			})
			activeForm.value.form.reset()
		}
	})
}

const { deletingIDs, deleteItem } = useDeleteForm()
function deleteDocument(doc) {
	deleteItem(doc.id, `/documents/${doc.id}`, {
		dialogTitle: txt('Delete document'),
		dialogText: txt('removeDocumentQuestion', 'Are you sure you want to remove the document <strong>#0#</strong> and all of associated fills from the system?', [doc.title]),
		toastSuccess: txt('Document deleted'),
		onSuccess: () => router.reload({ preserveScroll: true, only: ['documents'] })
	})
}

function getDeprecatedNote(data) {
	return `<strong>${txt('Outdated document')}</strong>${data.description ? `<br>${data.description}` : ''}`
}

const shlowDocumentHint = ref(false)

async function copyRow(id) {
	if (!id) return

	await navigator.clipboard.writeText("${"+id+"}").then(() => {
		toast.success(txt('Copied to clipboard'))
	})
}
</script>

<template>
	<AuthenticatedLayout :header="txt('Documents')">
		<Card>
			<TableInfo v-if="documents.length" :count="filteredDocuments.length" :countWords="[txt('document'), txt('documents2', 'documents'), txt('documents')]">
				<TextInput placeholder="Filter..." icon="search" v-model="filter" clearable />
				<template #buttons>
					<Button icon="plus" @click.prevent="showNewForm">{{ txt('Add document') }}</Button>
				</template>
			</TableInfo>
			<FilterTags>
				<Tag v-if="filter" @click="filter = ''" clearable>Filter: {{ filter }}</Tag>
			</FilterTags>
			<DataTable :items="filteredDocuments" :itemWord="txt('nodocuments')" modelField="id" :loadingRows="deletingIDs">
				<template #empty>
					<Button v-if="filter" icon="x" variant="outline" @click="filter = ''">{{ txt('Reset filter') }}</Button>
					<Button v-else icon="plus" size="bigger" @click.prevent="showNewForm">{{ txt('Add document') }}</Button>
				</template>
				<Column v-if="documents?.some(d => d.description || d.deprecated)" type="icon">
					<template #default="{ data }">
						<Icon v-if="data.deprecated" :name="data.description ? 'circle-info' : 'circle-alert'" class="color-warn" v-tooltip="getDeprecatedNote(data)" />
						<Icon v-else-if="data.description" name="circle-info" v-tooltip.touch="data.description" />
					</template>
				</Column>
				<Column :header="txt('Title')" field="title" link="/documents" linkParam="id" minWidth="7rem" />
				<Column :header="txt('Filled')" align="center">
					<template #default="{ data }">
						<span v-if="data.fills_count">{{ data.fills_count }}x</span>
						<span v-else>-</span>
					</template>
				</Column>
				<Column :header="txt('Created by')" align="center">
					<template #default="{ data }">
						{{ data.user?.name ?? '-' }}
					</template>
				</Column>
				<Column :header="txt('Created')" field="created_at" type="date" />
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton icon="docfill" v-tooltip="txt(data.deprecated ? 'Outdated document' : 'Fill document')" :disabled="data.deprecated" @click.prevent="fillModal.showFillModal(data)" />
						<IcoButton icon="right" v-tooltip="txt('Detail')" @click.prevent="router.visit(`/documents/${data.id}`)" />
						<IcoButton icon="trash" color="danger" v-tooltip="txt('Delete')" @click.stop="deleteDocument(data)" :loading="deletingIDs.includes(data.id)" />
					</template>
				</Column>
			</DataTable>
			<template #buttons>
				<Button variant="outline" color="link" iconRight="circle-question" @click="shlowDocumentHint = true">{{ txt('How to prepare a document') }}</Button>
			</template>
		</Card>
		<Modal v-model:open="showModal" width="narrow":header="txt('New document')" as="form" @submit.prevent="submitNewDocument" :closeable="!activeForm?.form?.processing">
			<TextInput
				:label="txt('Title')"
				:placeholder="txt('My document')"
				v-model="activeForm.form.title"
				:error="activeForm.form.errors.title"
				autofocus
				required
				autocomplete="off"
			/>
			<TextareaInput
				:label="txt('Note')"
				:placeholder="txt('document used for...')"
				v-model="activeForm.form.description"
				:error="activeForm.form.errors.description"
				:rows="3"
				autocomplete="off"
			/>
			<FileInput
				:label="txt('Document file')"
				accept=".docx"
				@input="setActiveFormFile"
				:error="activeForm.form.errors.file"
				required
			/>
			<div class="line">
				<Button type="submit" full :loading="activeForm.form.processing">{{ txt('Add document') }}</Button>
			</div>
			<div class="mt06">
				<Button class="mt07" full variant="outline" color="link" iconRight="circle-question" @click="shlowDocumentHint = true">{{ txt('How to prepare a document') }}</Button>
			</div>
		</Modal>
		<Modal v-model:open="shlowDocumentHint" width="narrower" :header="txt('How to prepare a document?')" showCloseButton>
			<p>{{ txt('prepareSentence1', 'Prepare a Word (.docx) document with the text where you want to fill in the data.') }}</p>
			<p v-html="txt('prepareSentence2', 'Insert variables into the document that will be replaced with form data. Variables always have the form <strong>${id}</strong>')"></p>
			<p v-html="txt('prepareSentence3', 'Replace the ID in the variable with the ID of the field in the address. For example, to insert the data from the Company field in the form, insert <strong>${company}</strong> into the document. The variables that can be used are:')"></p>
			<table class="infoTable line">
				<thead>
					<tr>
						<th>{{ txt('Field') }}</th>
						<th>ID</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(k, v) in allFormFields" @click="copyRow(v)" class="isClickable">
						<td>{{ k }}</td>
						<td class="infoTable-highlight-onRow"><strong>{{ v }}</strong></td>
					</tr>
				</tbody>
			</table>
			<p>{{ txt('clickToCopyRow2', 'By clicking on a row in the table, the variable will be copied to the clipboard in the required form.') }}</p>
			<template #buttons>
				<Button link="/files/example.docx" download icon="download">{{ txt('Example document') }}</Button>
			</template>
		</Modal>
		<FillDocumentModal ref="fillModal" @filled="() => router.reload({ preserveScroll: true, only: ['documents'] })" />
	</AuthenticatedLayout>
</template>