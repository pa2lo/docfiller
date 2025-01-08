import { ref } from "vue"

import { getAddressName } from "./helpers"

let addressesLoaded = false
const addressList = ref([])
const addressesData = ref([])
export async function getAddressData() {
	if (!addressesLoaded) {
		try {
			const res = await axios.get('/address-book/getData')
			if (res.data?.addresses?.length) {
				addressesData.value = res.data.addresses
				addressList.value = res.data.addresses.reduce((acc, item) => {
					acc.push({
						value: item.id,
						title: getAddressName(item),
						ico: item?.addressData?.ico,
						note: item.description
					})
					return acc
				}, [])
			}
		} catch (error) {
			console.log(error)
		}
		addressesLoaded = true
	}
	return {
		addressList,
		addressesData
	}
}
export function flushAddressData() {
	addressesLoaded = false
}

export const someGLobalVar = ref(null)