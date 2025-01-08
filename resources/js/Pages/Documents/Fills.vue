<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { txt, getAddressName } from '@/Utils/helpers'
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
import SelectInput from '@/Components/Inputs/SelectInput.vue'
import Modal from '@/Components/Modals/Modal.vue'
import Tag from '@/Components/Elements/Tag.vue'
import Icon from '@/Components/Elements/Icon.vue'
import Pagination from '@/Components/Elements/Pagination.vue'
import InputsRow from '@/Components/Inputs/InputsRow.vue'
import Loader from '@/Components/Elements/Loader.vue'
import SlideToggle from '@/Components/Elements/SlideToggle.vue'

import FillDocumentModal from './FillDocumentModal.vue'
import FillDetailsModal from './FillDetailsModal.vue'
import FillNoteModal from './FillNoteModal.vue'

const props = defineProps({
	fills: Object,
	documents: Array,
	users: Array,
	filters: Object
})

const isLoading = ref(false)
const filterValues = ref({
	title: props.filters?.title || '',
	document: props.filters?.document || '',
	by: props.filters?.by || '',
	from: props.filters?.from || '',
	to: props.filters?.to || ''
})
const showFilter = ref(Object.values(filterValues.value).some(f => f))
function setFilter() {
	let params = Object.entries(filterValues.value).reduce((acc, [key, val]) => {
		if (val || val.length) acc[key] = val
		return acc
	}, {})
	router.get(props.fills.path, params, {
		preserveState: true,
		preserveScroll: true,
		onStart: () => isLoading.value = true,
		onFinish: () => isLoading.value = false
	})
}
function clearFilter() {
	Object.keys(filterValues.value).forEach(key => {
		filterValues.value[key] = ''
	})
	setFilter()
}
function clearFilterKey(key) {
	filterValues.value[key] = ''
	setFilter()
}
let filterSearchTimeout = null
function filterSearchHit() {
	clearTimeout(filterSearchTimeout)
	filterSearchTimeout = setTimeout(() => {
		setFilter()
	}, 300)
}

const userFilterOptions = props.users.reduce((acc, item) => {
	acc.push({
		value: item.id,
		title: item.name
	})
	return acc
}, [])

const documentsfilterOptions = props.documents.reduce((acc, item) => {
	acc.push({
		value: item.id,
		title: item.title,
		note: item?.description
	})
	return acc
}, [])

const documentsMap = props.documents.reduce((acc, item) => {
	acc[item.id] = {...item}
	return acc
}, {})
const usersMap = props.users.reduce((acc, item) => {
	acc[item.id] = {...item}
	return acc
}, {})

const fillModal = ref(null)
const detailsModal = ref(null)
const fillNoteModal = ref(null)

const { deletingIDs: deletingFills, deleteItem } = useDeleteForm()
function deleteFill(id) {
	if (!id) return

	deleteItem(id, `/fill/${id}`, {
		onSuccess: () => router.reload({ preserveScroll: true, only: ['fills'] })
	})
}

const selectDocumentOptions = props.documents.filter(d => !d.deprecated).reduce((acc, item) => {
	acc.push({
		value: item.id,
		title: item.title,
		note: item?.description
	})
	return acc
}, [])
const selectDocumentModal = ref(false)
const selectDocumentModel = ref('')
function selectAndFill() {
	if (!selectDocumentModel.value) return

	selectDocumentModal.value = false
	fillModal.value.showFillModal(documentsMap[selectDocumentModel.value])
}

let todayDate = new Date().toLocaleDateString('en-ca')

const hasFilter = computed(() => Object.values(filterValues.value).some(f => f))
</script>

<template>
	<AuthenticatedLayout :header="txt('Document fills')">
		<Card>
			<TableInfo v-if="fills.total || hasFilter || isLoading" :count="fills.total">
				<Button icon="filter" variant="outline" color="link" :disabled="isLoading || hasFilter" @click.prevent="showFilter = !showFilter">Filter</Button>
				<template #buttons>
					<Button icon="docfill" @click.prevent="selectDocumentModal = true">{{ txt('Fill document') }}</Button>
				</template>
			</TableInfo>
			<SlideToggle class="line" :show="showFilter || hasFilter">
				<InputsRow wrap>
					<TextInput class="grow" :label="txt('Search')" placeholder="Firma, Meno, IČO..." v-model="filterValues.title" clearable @update:modelValue="filterSearchHit" :chars="16" :readOnly="isLoading" />
					<SelectInput class="grow" :placeholder="txt('all')" allowEmpty :label="txt('Document')" :options="documentsfilterOptions" v-model="filterValues.document" searchable :searchableFields="['note']" @change="setFilter" :readOnly="isLoading">
						<template #option="{ option }">
							<span>{{ option.title }}</span>
							<template v-if="option.note">
								<br><span class="smaller text-light">{{ option.note }}</span>
							</template>
						</template>
					</SelectInput>
					<SelectInput class="grow" :placeholder="txt('anyone')" allowEmpty :label="txt('Created by')" :options="userFilterOptions" v-model="filterValues.by" @change="setFilter" :readOnly="isLoading" />
					<TextInput class="grow" :label="txt('From')" type="date" v-model="filterValues.from" @update:modelValue="setFilter" :max="todayDate" :readOnly="isLoading" clearable />
					<TextInput class="grow" :label="txt('To')" type="date" v-model="filterValues.to" @update:modelValue="setFilter" :max="todayDate" :readOnly="isLoading" clearable />
				</InputsRow>
			</SlideToggle>
			<FilterTags>
				<Tag v-if="filterValues.title" key="fvTitle" clearable @click="clearFilterKey('title')">{{ txt('Search') }}: {{ filterValues.title }}</Tag>
				<Tag v-if="filterValues.document" key="fvDocument" clearable @click="clearFilterKey('document')">{{ txt('Document') }}: {{ documentsMap[filterValues.document]?.title }}</Tag>
				<Tag v-if="filterValues.by" key="fvBy" clearable @click="clearFilterKey('by')">{{ txt('Created by') }}: {{ usersMap[filterValues.by]?.name }}</Tag>
				<Tag v-if="filterValues.from" key="fvFrom" clearable @click="clearFilterKey('from')">{{ txt('From') }}: {{ filterValues.from }}</Tag>
				<Tag v-if="filterValues.to" key="fvTo" clearable @click="clearFilterKey('to')">{{ txt('to') }}: {{ filterValues.to }}</Tag>
			</FilterTags>
			<Loader class="line" :loading="isLoading">
				<DataTable :items="fills.data" modelField="id">
					<template #empty>
						<Button v-if="!documents.length" @click="router.visit('/')">{{ txt('Add document first') }}</Button>
						<Button v-else-if="hasFilter" icon="x" variant="outline" @click="clearFilter">{{ txt('Reset filter') }}</Button>
						<Button v-else icon="docfill" @click.prevent="selectDocumentModal = true">{{ txt('Fill document') }}</Button>
					</template>
					<Column type="buttons">
						<template #default="{ data }">
							<IcoButton icon="download" v-tooltip="txt('Download')" :link="`/fill/${data.id}/download`" download />
						</template>
					</Column>
					<Column :header="txt('Name')" :colClick="(data) => detailsModal.showDetailsModal(data, documentsMap[data.document_id], usersMap[data.user_id])" minWidth="9rem">
						<template #default="{ data }">
							<span class="basic-link">{{ getAddressName(data) }}</span>
							<template v-if="data?.addressData?.ico"><br>{{ data.addressData.ico }}</template>
						</template>
					</Column>
					<Column v-if="fills.data.some(f => f.fill_type == 'single' && f.note)" type="icon">
						<template #default="{ data }">
							<Icon v-if="data.fill_type == 'single' && data.note" name="circle-info" v-tooltip.touch="data.note" />
						</template>
					</Column>
					<Column :header="txt('Document')" link="/documents" linkParam="document_id" minWidth="7rem">
						<template #default="{ data }">
							<span class="basic-link">{{ documentsMap[data.document_id]?.title }}</span>
						</template>
					</Column>
					<Column :header="txt('Created by')" align="center">
						<template #default="{ data }">
							<span v-if="usersMap[data.user_id]?.name">{{ usersMap[data.user_id]?.name }}</span>
							<span v-else>-</span>
						</template>
					</Column>
					<Column :header="txt('Added')" type="date" field="created_at" />
					<Column type="buttons">
						<template #default="{ data }">
							<IcoButton icon="edit" v-tooltip="txt('Edit note')" @click.prevent="fillNoteModal.openEditFillForm(data)" />
							<IcoButton icon="docfill" v-tooltip="txt(documentsMap[data.document_id]?.deprecated ? 'Outdated document' : 'Fill again')" @click.prevent="fillModal.showFillModal(documentsMap[data.document_id], data)" :disabled="documentsMap[data.document_id]?.deprecated" />
							<IcoButton icon="article" v-tooltip="txt('Details')" @click.prevent="detailsModal.showDetailsModal(data, documentsMap[data.document_id], usersMap[data.user_id])" />
							<IcoButton icon="trash" v-tooltip="txt('Delete')" color="danger" @click.prevent="deleteFill(data.id)" :loading="deletingFills.includes(data.id)" />
						</template>
					</Column>
				</DataTable>
				<Pagination
					v-if="fills.links"
					:currentPage="fills.current_page"
					:links="fills.links"
					:prevPage="fills.prev_page_url"
					:nextPage="fills.next_page_url"
					:firstPage="fills.first_page_url"
					:lastPage="fills.last_page_url"
					:pages="fills.last_page"
					:from="fills.from"
					:to="fills.to"
					:total="fills.total"
				/>
			</Loader>
		</Card>
		<Modal v-model:open="selectDocumentModal" :header="txt('Select document')" width="narrow" as="form" @submit.prevent="selectAndFill">
			<SelectInput
				:label="txt('Documents')"
				:options="selectDocumentOptions"
				v-model="selectDocumentModel"
				searchable
				:searchableFields="['note']"
			>
				<template #option="{ option }">
					<strong>{{ option.title }}</strong><span v-if="option.ico" class="input-label-note">{{ option.ico }}</span>
					<template v-if="option.note">
						<br><span class="smaller text-light">{{ option.note }}</span>
					</template>
				</template>
			</SelectInput>
			<p>
				<Button type="submit" full :disabled="!selectDocumentModel">{{ txt('Fill document') }}</Button>
			</p>
		</Modal>
		<FillNoteModal ref="fillNoteModal" />
		<FillDocumentModal ref="fillModal" @filled="router.reload({ preserveScroll: true, only: ['fills'] })" />
		<FillDetailsModal ref="detailsModal" @fillAgain="(f, d) => fillModal.showFillModal(f, d)" />
	</AuthenticatedLayout>
</template>