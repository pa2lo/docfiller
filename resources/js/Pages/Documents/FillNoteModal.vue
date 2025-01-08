<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { txt } from '@/Utils/helpers'
import { toast } from '@/Utils/toaster'

import Modal from '@/Components/Modals/Modal.vue'
import TextareaInput from '@/Components/Inputs/TextareaInput.vue'
import Button from '@/Components/Elements/Button.vue'

const showEditFillModal = ref(false)
const editFillForm = useForm({
	id: '',
	note: ''
})
function openEditFillForm(data) {
	editFillForm.clearErrors()
	editFillForm.id = data.id
	editFillForm.note = data.note
	showEditFillModal.value = true
}
function submitEditFillForm() {
	editFillForm.patch(`/fill/${editFillForm.id}`, {
		preserveScroll: true,
		onSuccess: () => {
			toast.success(txt('Note edited'))
			showEditFillModal.value = false
		}
	})
}

defineExpose({ openEditFillForm })
</script>

<template>
	<Modal v-model:open="showEditFillModal" width="narrow" :header="txt('Edit note')" as="form" @submit.prevent="submitEditFillForm" :closeable="!editFillForm.processing">
		<TextareaInput
			:label="txt('Note')"
			:placeholder="txt('Filled for...')"
			v-model="editFillForm.note"
			:error="editFillForm.errors.note"
			:rows="3"
			autofocus
			autocomplete="off"
		/>
		<p>
			<Button full type="submit" :loading="editFillForm.processing" icon="save">{{ txt('Save') }}</Button>
		</p>
	</Modal>
</template>