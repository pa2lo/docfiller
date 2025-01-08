<script setup>
import { ref, onMounted, onBeforeMount } from 'vue'
import { getAddressData } from '@/Utils/store'
import { txt } from '@/Utils/helpers'

import TextInput from '@/Components/Inputs/TextInput.vue'
import SelectInput from '@/Components/Inputs/SelectInput.vue'
import RadioButtons from '@/Components/Inputs/RadioButtons.vue'
import Loader from '@/Components/Elements/Loader.vue'
import Message from '@/Components/Elements/Message.vue'

const model = defineModel()

const props = defineProps({
	doc: Object,
	hasError: Boolean,
	formAllFields: Object,
	availableSystemFields: Array,
	availableOtherFields: Array
})

const sourceTypeOptions = [
	{
		value: 'address-book',
		title: txt('Address book')
	}, {
		value: 'ruz',
		title: 'Register ÚZ'
	}
]

const emit = defineEmits(['clearError'])

const sourceType = ref('address-book')

const addressList = ref(null)
let addressesData = null

onBeforeMount(() => {
	sourceType.value = 'address-book'
	addressSelectModel.value = ''
	ruzModel.value = ''
	ruzOptions.value = []
})

onMounted(() => {
	getAddressData().then(res => {
		addressList.value = res?.addressList.value
		addressesData = res?.addressesData
	})
})

const addressSelectModel = ref('')
function setFromAddressBook() {
	if (!addressSelectModel.value) return

	let foundAddress = addressesData.value.find(a => a.id == addressSelectModel.value)
	if (!foundAddress) return

	Object.keys(model.value).forEach(f => model.value[f] = foundAddress.addressData[f] || '')
	if (props.hasError) emit('clearError')
}

const ruzOptions = ref([])
const ruzModel = ref('')
const ruzOptionsLoading = ref(false)
const ruzDataLoading = ref(false)
function loadRuzOptions(val) {
	if (!val) return ruzOptions.value = []

	ruzOptionsLoading.value = true

	axios.post('/ruzSuggestions', {
		q: val
	}).then((data) => {
		if (data?.data?.success) {
			if (data?.data?.items?.length) {
				ruzOptions.value = data?.data?.items.reduce((acc, item) => {
					acc.push({
						title: item.entityName.replaceAll('<b>', '').replaceAll('</b>', ''),
						value: item.id,
						ico: item.entNumber.replaceAll('<b>', '').replaceAll('</b>', '')
					})
					return acc
				}, [])
			} else ruzOptions.value = []
		} else {
			toast.warning('Loading data failed')
			console.log(data)
		}
	}).catch(error => {
		console.log(error)
	}).finally(() => {
		ruzOptionsLoading.value = false
	})
}

const ruzFillMap = {
	firma: 'nazovUJ',
	ico: 'ico',
	adresa: 'ulica',
	mesto: 'mesto',
	psc: 'psc',
	dic: 'dic',
}

function loadDataFromRuz(id) {
	if (!id) return

	ruzDataLoading.value = true
	axios.post('/loadRuzData', {
		id: id
	}).then((data) => {
		if (data?.data?.success) {
			if (model.value.hasOwnProperty('krajina')) model.value.krajina = 'Slovensko';

			['meno', 'priezvisko', 'icdph'].forEach(f => {
				if (model.value.hasOwnProperty(f)) model.value[f] = ''
			})
			Object.entries(ruzFillMap).forEach(([key, ruz]) => {
				if (model.value.hasOwnProperty(key)) model.value[key] = data.data?.info?.[ruz] ?? ''
			})
			if (model.value.hasOwnProperty('kraj')) model.value.kraj = ruzKrajMap[data?.data?.info?.kraj] ?? ''
			if (model.value.hasOwnProperty('okres')) model.value.okres = ruzOkresMap[data?.data?.info?.okres] ?? ''
			if (model.value.hasOwnProperty('icdph') && data?.data?.info?.dic) {
				axios.post('/checkRuzVat', {
					dic: data.data.info.dic
				}).then(res => {
					if (res.data?.success && res.data?.valid) model.value.icdph = `SK${data.data.info.dic}`
				})
			}

			if (props.hasError) emit('clearError')
		} else {
			toast.warning('Loading data failed')
			console.log(data)
		}
	}).catch(error => {
		console.log(error)
	}).finally(() => ruzDataLoading.value = false)
}

const ruzKrajMap = {
	SK010: 'Bratislavský kraj',
	SK021: 'Trnavský kraj',
	SK022: 'Trenčiansky kraj',
	SK023: 'Nitriansky kraj',
	SK031: 'Žilinský kraj',
	SK032: 'Banskobystrický kraj',
	SK041: 'Prešovský kraj',
	SK042: 'Košický kraj',
	SKZZ: 'Zahraničie',
	SKZZZ: 'Zahraničie',
}
const ruzOkresMap = {
  SK0101: "Bratislava I",
  SK0102: "Bratislava II",
  SK0103: "Bratislava III",
  SK0104: "Bratislava IV",
  SK0105: "Bratislava V",
  SK0106: "Malacky",
  SK0107: "Pezinok",
  SK0108: "Senec",
  SK0211: "Dunajská Streda",
  SK0212: "Galanta",
  SK0213: "Hlohovec",
  SK0214: "Piešťany",
  SK0215: "Senica",
  SK0216: "Skalica",
  SK0217: "Trnava",
  SK0221: "Bánovce nad Bebravou",
  SK0222: "Ilava",
  SK0223: "Myjava",
  SK0224: "Nové Mesto nad Váhom",
  SK0225: "Partizánske",
  SK0226: "Považská Bystrica",
  SK0227: "Prievidza",
  SK0228: "Púchov",
  SK0229: "Trenčín",
  SK0231: "Komárno",
  SK0232: "Levice",
  SK0233: "Nitra",
  SK0234: "Nové Zámky",
  SK0235: "Šaľa",
  SK0236: "Topoľčany",
  SK0237: "Zlaté Moravce",
  SK031A: "Tvrdošín",
  SK031B: "Žilina",
  SK0311: "Bytča",
  SK0312: "Čadca",
  SK0313: "Dolný Kubín",
  SK0314: "Kysucké Nové Mesto",
  SK0315: "Liptovský Mikuláš",
  SK0316: "Martin",
  SK0317: "Námestovo",
  SK0318: "Ružomberok",
  SK0319: "Turčianske Teplice",
  SK032A: "Veľký Krtíš",
  SK032B: "Zvolen",
  SK032C: "Žarnovica",
  SK032D: "Žiar nad Hronom",
  SK0321: "Banská Bystrica",
  SK0322: "Banská Štiavnica",
  SK0323: "Brezno",
  SK0324: "Detva",
  SK0325: "Krupina",
  SK0326: "Lučenec",
  SK0327: "Poltár",
  SK0328: "Revúca",
  SK0329: "Rimavská Sobota",
  SK041A: "Stará Ľubovňa",
  SK041B: "Stropkov",
  SK041C: "Svidník",
  SK041D: "Vranov nad Topľou",
  SK0411: "Bardejov",
  SK0412: "Humenné",
  SK0413: "Kežmarok",
  SK0414: "Levoča",
  SK0415: "Medzilaborce",
  SK0416: "Poprad",
  SK0417: "Prešov",
  SK0418: "Sabinov",
  SK0419: "Snina",
  SK042A: "Spišská Nová Ves",
  SK042B: "Trebišov",
  SK0421: "Gelnica",
  SK0422: "Košice I",
  SK0423: "Košice II",
  SK0424: "Košice III",
  SK0425: "Košice IV",
  SK0426: "Košice - okolie",
  SK0427: "Michalovce",
  SK0428: "Rožňava",
  SK0429: "Sobrance",
  SKZZZ: "Zahraničie",
  SKZZZZ: "Zahraničie"
}
</script>

<template>
	<div class="line">
		<div class="inputs-grid">
			<RadioButtons
				:label="txt('Data source')"
				:options="sourceTypeOptions"
				v-model="sourceType"
				sameWidth
			/>
			<SelectInput
				v-if="sourceType == 'address-book' && addressList?.length"
				:label="txt('Address book')"
				:options="addressList"
				v-model="addressSelectModel"
				@change="setFromAddressBook"
				searchable
				:searchableFields="['ico']"
			>
				<template #option="{ option }">
					<strong>{{ option.title }}</strong><span v-if="option.ico" class="input-label-note">{{ option.ico }}</span>
					<template v-if="option.note">
						<br><span class="smaller text-light">{{ option.note }}</span>
					</template>
				</template>
			</SelectInput>
			<SelectInput
				v-if="sourceType == 'ruz'"
				label="Register ÚZ"
				:placeholder="txt('Search...')"
				:options="ruzOptions"
				:loading="ruzOptionsLoading"
				noItemsText="Zadajte IČO alebo názov"
				v-model="ruzModel"
				@search="loadRuzOptions"
				@change="loadDataFromRuz"
				:searchThrottle="750"
			>
				<template #option="{ option }">
					<strong>{{ option.title }}</strong><span v-if="option.ico" class="input-label-note">{{ option.ico }}</span>
				</template>
			</SelectInput>
		</div>
		<Loader :loading="ruzDataLoading" class="line">
			<div class="inputs-grid divided cols3">
				<Message v-if="hasError" class="input-full" type="error">{{ txt('notFilledFields', 'Fill at least one of the fields in the form') }}</Message>
				<template v-for="field in availableSystemFields">
					<TextInput
						:label="formAllFields[field] || field"
						:labelNote="formAllFields[field] ? `- ${field}` : null"
						v-model="model[field]"
						trim
					/>
				</template>
				<div v-if="availableSystemFields.length && availableOtherFields.length" class="divided"></div>
				<template v-for="field in availableOtherFields">
					<TextInput
						:label="formAllFields[field] || field"
						:labelNote="formAllFields[field] ? `- ${field}` : null"
						v-model="model[field]"
						trim
					/>
				</template>
			</div>
		</Loader>
	</div>
</template>