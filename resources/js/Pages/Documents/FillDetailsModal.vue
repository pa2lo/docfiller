<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { txt, allFields, formatDate } from '@/Utils/helpers'
import { useStorage } from '@/Composables/BrowserStorage'

import Modal from '@/Components/Modals/Modal.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'

import FillInfoTable from './FillInfoTable.vue'

const page = usePage()
const formAllFields = allFields(page.props.customFields)

const details = ref(null)
const doc = ref(null)
const user = ref(null)
const detailsModal = ref(false)

const modalWidth = ref('narrower')

const documentFields = ref([])

let tableViewType = null

function showDetailsModal(newDetails, newDocument, newUser = null) {
	details.value = newDetails
	doc.value = newDocument
	user.value = newUser

	documentFields.value = localStorage.getItem(`doc-fields-${doc.value.id}`) ? JSON.parse(localStorage.getItem(`doc-fields-${newDocument.id}`)) : newDocument.fields

	modalWidth.value = newDetails.fill_type == 'multiple' ? 'wider' : 'narrower'

	tableViewType = useStorage(`doc-rotate-${doc.value.id}`, 'row')

	detailsModal.value = true
}

const emit = defineEmits(['fillAgain'])
function fillAgain() {
	detailsModal.value = false
	emit('fillAgain', doc.value, details.value)
}

const showRecordDetail = ref(false)
const recordData = ref(null)
function openRecordDetail(data) {
	recordData.value = data
	showRecordDetail.value = true
}

defineExpose({ showDetailsModal })
</script>

<template>
	<Modal v-model:open="detailsModal" :header="txt('Details')" :width="modalWidth" :headerNote="`ID: ${details?.id}`">
		<template v-if="details">
			<div v-if="details?.note">{{ txt('Note') }}: <strong>{{ details.note }}</strong></div>
			<div>{{ txt('Document') }}: <strong>{{ doc.title }}</strong></div>
			<div>{{ txt('Created') }}: <strong>{{ formatDate(details.created_at) }}</strong></div>
			<div v-if="user?.name">{{ txt('Created by') }}: <strong>{{ user.name }}</strong></div>
			<div>{{ txt('File') }}: <a :href="`/fill/${details.id}/download`" :download="details.generated_file"><strong>{{ details.generated_file }}</strong></a></div>

			<div v-if="details?.batch?.length" class="dataTable-wrapper line">
				<table v-if="tableViewType == 'row'" class="infoTable">
					<thead>
						<tr>
							<th v-for="field in documentFields" class="infoTable-default">
								{{ formAllFields[field] || field }}
							</th>
							<th class="infoTable-buttons infoTable-sticky-right">
								<IcoButton icon="table-col" v-tooltip="txt('Rotate table')" @click.prevent="tableViewType = 'col'" />
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="data in details.batch">
							<td v-for="field in documentFields">
								{{ data[field] ?? '' }}
							</td>
							<td class="infoTable-buttons infoTable-sticky-right">
								<IcoButton icon="circle-info" v-tooltip="txt('Record detail')" @click.prevent="openRecordDetail(data)" />
							</td>
						</tr>
					</tbody>
				</table>
				<table v-else class="infoTable">
					<tbody>
						<tr v-for="field in documentFields">
							<td class="infoTable-th infoTable-sticky-left">{{ formAllFields[field] || field }}</td>
							<td v-for="data in details.batch">{{ data[field] ?? '' }}</td>
						</tr>
						<tr>
							<td class="infoTable-th infoTable-buttons infoTable-sticky-left infoTable-buttons-left">
								<IcoButton icon="table-row" v-tooltip="txt('Rotate table')" @click.prevent="tableViewType = 'row'" />
							</td>
							<td v-for="data in details.batch" class="infoTable-buttons infoTable-buttons-left">
								<IcoButton icon="circle-info" v-tooltip="txt('Record detail')" @click.prevent="openRecordDetail(data)" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<FillInfoTable v-else-if="details.addressData" :fields="documentFields" :allFields="formAllFields" :addressData="details.addressData" />
		</template>
		<template #buttons>
			<Button v-if="!doc.deprecated" icon="docfill" @click.prevent="fillAgain">{{ txt('Fill again') }}</Button>
			<Button icon="download" :link="`/fill/${details?.id}/download`" download>{{ txt('Download') }}</Button>
		</template>
	</Modal>
	<Modal v-model:open="showRecordDetail" width="narrower" :header="txt('Record detail')" showCloseButton>
		<FillInfoTable v-if="recordData" :fields="documentFields" :allFields="formAllFields" :addressData="recordData" />
	</Modal>
</template>