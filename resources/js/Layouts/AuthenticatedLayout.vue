<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { txt } from '@/Utils/helpers'

import { useAppInstaller } from '@/Composables/AppInstaller'

import ApplicationLogo from '@/Components/Elements/ApplicationLogo.vue'
import Icon from '@/Components/Elements/Icon.vue'
import MenuGroup from '@/Components/Elements/MenuGroup.vue'
import MenuLink from '@/Components/Elements/MenuLink.vue'
import MobileMenuLink from '@/Components/Elements/MobileMenuLink.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'

defineProps({
	header: String,
	headerMeta: String,
	subtitle: String,
	backLink: String,
	width: {
		type: String,
		default: 'normal'
	}
})

const { installPrompt, installApp } = useAppInstaller()

let menuOpen = ref(false)
let isDark = ref(document.cookie.includes('theme=dark') || (window.matchMedia('(prefers-color-scheme: dark)').matches && !document.cookie.includes('theme=light')))

function switchTheme() {
	isDark.value = !isDark.value

	document.cookie = `theme=${isDark.value ? 'dark' : 'light'}; path=/`
	document.querySelector('meta[name="theme-color"]').setAttribute('content', isDark.value ? 'hsl(215, 71%, 6%)' : 'hsl(213, 39%, 95%)')
	window.darkTheme = isDark.value
	document.documentElement.classList.toggle('theme-dark', isDark.value)
	document.documentElement.classList.toggle('theme-light', !isDark.value)
}

const isiOS = /iPhone|iPad|iPod/.test(window.navigator?.userAgentData?.platform || window.navigator.platform)
const isDemo = import.meta.env.VITE_DEMO == "true"
</script>

<template>
	<Head :title="headerMeta ?? header" />
	<aside class="sidemenu-cont" :class="{menuOpen: menuOpen}">
		<div class="sidemenu-backdrop l-hide isClickable" @click="menuOpen = !menuOpen"></div>
		<nav class="sidemenu-menu flex">
			<div class="sidemenu-logo flex ai-c">
				<ApplicationLogo />
				<Icon class="sidemenu-hide-x isClickable ml-a l-hide" name="x" @click="menuOpen = !menuOpen" />
			</div>
			<div class="sidemenu-group divided">
				<MenuLink link="/" activeRoute="/documents" icon="docstack">{{ txt('Documents') }}</MenuLink>
				<MenuLink link="/fills" icon="docfill">{{ txt('Document fills') }}</MenuLink>
				<MenuLink link="/address-book" icon="address-book">{{ txt('Address book') }}</MenuLink>
				<MenuLink link="/settings" icon="settings">{{ txt('Settings') }}</MenuLink>
			</div>
			<div class="sidemenu-group divided">
				<MenuLink v-if="$page.props?.auth?.user?.role == 'admin'" activeRoute="/users" link="/users" icon="users">{{ txt('User accounts') }}</MenuLink>
				<MenuLink link="/profile" icon="user-edit">{{ txt('Profile') }}</MenuLink>
			</div>
			<div v-if="installPrompt || isDemo" class="sidemenu-group divided">
				<MenuLink v-if="installPrompt" isButton icon="download" @click.prevent="installApp">{{ txt('Download app') }}</MenuLink>
				<MenuLink v-if="isDemo" link="https://github.com/pa2lo/docfiller" icon="github" external>GitHub</MenuLink>
			</div>
			<div class="sidemenu-footer divided flex ai-c">
				<Button color="heading" size="compact" variant="outline" :icon="isDark ? 'sun' : 'moon'" bigIcon :title="isDark ? 'Light mode' : 'Dark mode'" @click="switchTheme" />
				<Button color="link" size="compact" variant="outline" class="grow" link="/logout" icon="logout" method="post" as="button">{{ txt('Log Out') }}</Button>
			</div>
		</nav>
	</aside>
	<div class="authenticated-layout" :class="[`authenticated-layout-${width}`]" :tabindex="isiOS ? '-1' : null">
		<header class="authenticated-header flex ai-c">
			<IcoButton v-if="backLink" icon="left" class="authenticated-header-back" :link="backLink" transparent />
			<h1 class="page-title">{{ header ?? $page.component }}</h1>
			<button class="menu-toggler flex aj-c isClickable l-hide" @click="menuOpen = !menuOpen"><Icon name="menu" /></button>
		</header>
		<div v-if="$slots.subtitle || subtitle" class="page-subtitle line"><slot name="subtitle"><p class="text-light">{{ subtitle }}</p></slot></div>
		<main class="page-main section">
			<div class="page-content">
				<slot />
			</div>
		</main>
		<aside class="mobile-nav flex l-hide">
			<MobileMenuLink link="/" icon="docstack" activeRoute="/documents">{{ txt('Documents') }}</MobileMenuLink>
			<MobileMenuLink link="/fills" icon="docfill">{{ txt('Filled') }}</MobileMenuLink>
			<MobileMenuLink link="/address-book" icon="address-book">{{ txt('Address book') }}</MobileMenuLink>
		</aside>
	</div>
</template>