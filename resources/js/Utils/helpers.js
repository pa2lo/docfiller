export function getUUID(prefix) {
	return `${prefix}-${crypto.randomUUID().split('-').slice(0, -1).join('-')}`
}

export function slugify(string) {
	return string.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9\s-]/g, ' ').trim().replace(/[\s-]+/g, '-')
}

export function formatDate(date, seconds = false) {
	if (typeof date == 'number' && date.toString().length == 10) date = date*1000

	let options = {	day: 'numeric',	month: 'numeric', year: 'numeric', hour: '2-digit',	minute: 'numeric' }
	if (seconds) options.second = 'numeric'

	return new Date(date).toLocaleString( 'sk-SK', options)
}

export const roleOptions = [
	{
		title: 'Admin',
		value: 'admin'
	}, {
		title: 'User',
		value: 'user'
	}
]

export function txt(label, def, params) {
	let txt = window?.textLabels?.[label] || (def || label)
	return txt.replace(/\#(\d+)\#/g, (match, index) => params[parseInt(index)] ?? match)
}

export function getHref(link, params) {
	return !params ? link : `${link}/${params}`
}

export const systemFields = {
	firma: 'Firma',
	meno: 'Meno',
	priezvisko: 'Priezvisko',
	ico: 'IČO',
	dic: 'DIČ',
	icdph: 'IČ DPH',
	adresa: 'Adresa',
	mesto: 'Mesto',
	psc: 'PSČ',
	okres: 'Okres',
	kraj: 'Kraj',
	krajina: 'Krajina'
}

export function allFields(customFields = {}) {
	return {...systemFields, ...customFields}
}

export function downloadFile(url, filename) {
	let a = Object.assign(document.createElement('a'), {
		href: url,
		download: filename
	})
	a.click()
}

export function getAddressName(address) {
	if (address.fill_type == 'multiple' && address.note) return address.note
	else if (address.addressData?.firma) return address.addressData.firma
	else if (address.addressData?.meno && address.addressData?.priezvisko) return `${address.addressData.meno} ${address.addressData.priezvisko}`
	else if (address.addressData?.meno || address.addressData?.priezvisko) return address.addressData?.meno || address.addressData?.priezvisko
	else return address.id
}