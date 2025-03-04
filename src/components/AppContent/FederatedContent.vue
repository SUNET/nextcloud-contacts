<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AppContent>
		<EmptyContent v-if="!federated" :name="t('contacts', 'Please select an OCM provider')">
			<template #icon>
				<AccountGroup :size="20" />
			</template>
		</EmptyContent>

		<EmptyContent v-else-if="loading" class="empty-content" :name="t('contacts', 'Loading OCM provider…')">
			<template #icon>
				<IconLoading :size="20" />
			</template>
		</EmptyContent>

		<CircleDetails v-else :federated="federated" />
	</AppContent>
</template>
<script>
import { showError } from '@nextcloud/dialogs'
import {
	NcAppContent as AppContent,
	NcEmptyContent as EmptyContent,
	NcLoadingIcon as IconLoading,
	isMobile,
} from '@nextcloud/vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import ProviderDetails from '../ProviderDetails.vue'
import RouterMixin from '../../mixins/RouterMixin.js'

export default {
	name: 'FederatedContent',

	components: {
		AppContent,
		ProviderDetails,
		EmptyContent,
		AccountGroup,
		IconLoading,
	},

	mixins: [isMobile, RouterMixin],

	props: {
		loading: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			loadingList: false,
		}
	},

	computed: {
		// store variables
		providers() {
			return this.$store.getters.getProviders
		},
		provider() {
			return this.$store.getters.getProvider(this.selectedProvider)
		},
		members() {
			return Object.values(this.provider?.members || [])
		},

		/**
		 * Is the current provider empty
		 *
		 * @return {boolean}
		 */
		isEmptyProvider() {
			return this.members.length === 0
		},
	},

	watch: {
		provider(newProvider) {
			if (newProvider?.id) {
				this.fetchProviderUsers(newProvider.id)
			}
		},
	},

	beforeMount() {
		if (this.provider?.id) {
			this.fetchProviderUsers(this.provider.id)
		}
	},

	methods: {
		async fetchProviderUsers(providerId) {
			this.loadingList = true
			this.logger.debug('Fetching members for', { circleId })

			try {
				await this.$store.dispatch('getProviderUsers', providerId)
			} catch (error) {
				console.error(error)
				showError(t('contacts', 'There was an error fetching the member list'))
			} finally {
				this.loadingList = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// TODO: replace my button component when available
button {
	height: 44px;
	display: flex;
	justify-content: center;
	align-items: center;
	span {
		margin-right: 10px;
	}
}

.empty-content {
	height: 100%;
}
</style>
