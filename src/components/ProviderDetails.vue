<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="provider-details">
		<!-- contact header -->
		<DetailsHeader>
			<!-- avatar and upload photo -->
			<template #avatar="{avatarSize}">
				<Avatar :disable-tooltip="true"
					:display-name="provider.displayName"
					:is-no-user="true"
					:size="avatarSize" />
			</template>

			<!-- display name -->
			<template #title>
				<div v-if="loadingName" class="provider-name__loader icon-loading-small" />
				<h2>
					{{ provider.displayName }}
				</h2>
			</template>

			<!-- org, title -->
			<template v-if="!provider.isOwner" #subtitle>
				{{ t('contacts', 'Team owned by {owner}', { owner: provider.owner.displayName}) }}
			</template>

			<template #actions>
				<!-- copy provider link -->
				<Button type="tertiary"
					:href="providerUrl"
					:title="copyButtonText"
					:class="copyLinkIcon"
					@click.stop.prevent="copyToClipboard(providerUrl)" />

				<!-- Team settings modal -->
				<Button v-if="(provider.isOwner || provider.isAdmin) && !provider.isPersonal" @click="showSettingsModal = true">
					<template #icon>
						<Cog :size="20" />
					</template>
					{{ t('contacts', 'Team settings') }}
				</Button>

				<!-- Only show the join button if the provider is accepting requests -->
				<Button v-if="!provider.isPendingMember && !provider.isMember && provider.canJoin"
					:disabled="loadingJoin"
					class="primary"
					@click="joinProvider">
					<template #icon>
						<Login :size="16" />
					</template>
					{{ t('contacts', 'Request to join') }}
				</Button>
			</template>
		</DetailsHeader>

		<section v-if="showDescription" class="provider-details-section">
			<ContentHeading :loading="loadingDescription">
				{{ t('contacts', 'Description') }}
			</ContentHeading>

			<RichContenteditable :value.sync="provider.description"
				:auto-complete="onAutocomplete"
				:maxlength="1024"
				:multiline="true"
				:contenteditable="false"
				:placeholder="descriptionPlaceholder"
				class="provider-details-section__description"
				@update:value="onDescriptionChangeDebounce" />
		</section>

		<!-- not a member -->
		<template v-if="!provider.isMember">
			<!-- Pending request validation -->
			<NcEmptyContent v-if="provider.isPendingMember"
				:name="t('contacts', 'Your request to join this team is pending approval')">
				<template #icon>
					<NcLoadingIcon :size="20" />
				</template>
			</NcEmptyContent>

			<NcEmptyContent v-else
				:name="t('contacts', 'You are not a member of {provider}', { provider: provider.displayName})">
				<template #icon>
					<IconAccountGroup :size="20" />
				</template>
			</NcEmptyContent>
		</template>

		<section v-else>
			<ContentHeading>
				{{ t('contacts', 'Team resources') }}
			</ContentHeading>
			<p>{{ t('contacts', 'Anything shared with this team will show up here') }}</p>
			<div v-for="provider in resourceProviders" :key="provider.id">
				<ContentHeading>
					<span v-show="false" class="provider__icon" v-html="provider.icon" /> {{ provider.name }}
				</ContentHeading>

				<ul>
					<ListItem v-for="resource in resourcesForProvider(provider.id)"
						:key="resource.url"
						class="resource"
						:name="resource.label"
						:href="resource.url">
						<template #icon>
							<span v-if="resource.iconEmoji" class="resource__icon">
								{{ resource.iconEmoji }}
							</span>
							<span v-else-if="resource.iconSvg" class="resource__icon" v-html="resource.iconSvg" />
							<span v-else-if="resource.iconURL" class="resource__icon">
								<img :src="resource.iconURL" alt="">
							</span>
						</template>
					</ListItem>
				</ul>
			</div>
		</section>

		<MemberList v-if="members.length" :list="members" />

		<Modal v-if="(provider.isOwner || provider.isAdmin) && !provider.isPersonal && showSettingsModal" @close="showSettingsModal=false">
			<div class="provider-settings">
				<h2>{{ t('contacts', 'Team settings') }}</h2>

				<h3>{{ t('contacts', 'Team name') }}</h3>
				<input v-model="provider.displayName"
					:readonly="!provider.isOwner"
					:placeholder="t('contacts', 'Team name')"
					type="text"
					autocomplete="off"
					autocorrect="off"
					spellcheck="false"
					name="displayname"
					@input="onNameChangeDebounce">

				<h3>{{ t('contacts', 'Description') }}</h3>
				<RichContenteditable :value.sync="provider.description"
					:auto-complete="onAutocomplete"
					:maxlength="1024"
					:multiline="true"
					:contenteditable="provider.isOwner"
					:placeholder="descriptionPlaceholder"
					class="provider-details-section__description"
					@update:value="onDescriptionChangeDebounce" />

				<h3>{{ t('contacts', 'Actions') }}</h3>
				<!-- leave provider -->
				<Button v-if="provider.canLeave"
					type="warning"
					@click="confirmLeaveProvider">
					<template #icon>
						<Logout :size="16" />
					</template>
					{{ t('contacts', 'Leave team') }}
				</Button>

				<!-- delete provider -->
				<Button v-if="provider.canDelete"
					type="error"
					href="#"
					@click.prevent.stop="confirmDeleteProvider">
					<template #icon>
						<IconDelete :size="20" />
					</template>
					{{ t('contacts', 'Delete team') }}
				</Button>
			</div>
		</Modal>
	</div>
</template>

<script>
import { ref } from 'vue'
import { useElementSize } from '@vueuse/core'
import debounce from 'debounce'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import {
	NcAvatar as Avatar,
	NcButton as Button,
	NcEmptyContent,
	NcListItem as ListItem,
	NcLoadingIcon,
	NcModal as Modal,
	NcRichContenteditable as RichContenteditable,
} from '@nextcloud/vue'

import Cog from 'vue-material-design-icons/Cog.vue'
import Login from 'vue-material-design-icons/Login.vue'
import Logout from 'vue-material-design-icons/Logout.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconAccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import { ProviderEdit, editProvider } from '../services/providers.ts'
import DetailsHeader from './DetailsHeader.vue'
import MemberList from './MemberList/MemberList.vue'
import ContentHeading from './ProviderDetails/ContentHeading.vue'

export default {
	name: 'ProviderDetails',

	components: {
		Avatar,
		Button,
		ContentHeading,
		DetailsHeader,
		ListItem,
		Cog,
		IconAccountGroup,
		IconDelete,
		Login,
		Logout,
		MemberList,
		Modal,
		NcEmptyContent,
		NcLoadingIcon,
		RichContenteditable,
	},


	setup() {
		const avatarList = ref()
		const { width } = useElementSize(avatarList)
		return { avatarList, width }
	},

	data() {
		return {
			loadingDescription: false,
			loadingName: false,
			showSettingsModal: false,
			showMembersModal: false,
			resources: null,
		}
	},

	computed: {
		descriptionPlaceholder() {
			if (this.provider.description.trim() === '') {
				return t('contacts', 'There is no description for this team')
			}
			return t('contacts', 'Enter a description for the team')
		},

		isEmptyDescription() {
			return this.provider.description.trim() === ''
		},

		showDescription() {
			if (this.provider.isOwner) {
				return true
			}
			return !this.isEmptyDescription
		},

		members() {
			return Object.values(this.$store.getters.getProvider(this.provider.id)?.members || [])
		},

		maxMembers() {
			// How many avatars (default-clickable-area + 12px gap) fit?
			const avatarWidth = parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area')) + 12
			const maxMembers = Math.floor(this.width / avatarWidth)
			return (this.members.length > maxMembers)
				? maxMembers - 1
				: maxMembers
		},

		memberLimit() {
			return Math.min(this.members.length, this.maxMembers)
		},

		membersLimited() {
			return this.members.slice(0, this.memberLimit)
		},

		hasExtraMembers() {
			return this.members.length > this.maxMembers
		},

		resourceProviders() {
			return this.resources?.reduce((acc, res) => {
				if (!acc.find(p => p.id === res.provider.id)) {
					acc.push(res.provider)
				}
				return acc
			}, []) ?? []
		},

		resourcesForProvider() {
			return (providerId) => {
				return this.resources?.filter(res => res.provider.id === providerId) ?? []
			}
		},
	},

	watch: {
		'provider.id': {
			handler() {
				this.fetchTeamResources()
			},
			immediate: true,
		},
	},

	methods: {
		async fetchTeamResources() {
			const response = await axios.get(generateOcsUrl(`/teams/${this.provider.id}/resources`))
			this.resources = response.data.ocs.data.resources
		},
		/**
		 * Autocomplete @mentions on the description
		 *
		 * @param {string} search the search term
		 * @param {Function} callback callback to be called with results array
		 */
		onAutocomplete(search, callback) {
			// TODO: implement autocompletion. Disabled for now
			// eslint-disable-next-line n/no-callback-literal
			callback([])
		},

		onDescriptionChangeDebounce: debounce(function(...args) {
			this.onDescriptionChange(...args)
		}, 500),
		async onDescriptionChange(description) {
			this.loadingDescription = true
			try {
				await editProvider(this.provider.id, ProviderEdit.Description, description)
			} catch (error) {
				console.error('Unable to edit team description', description, error)
				showError(t('contacts', 'An error happened during description sync'))
			} finally {
				this.loadingDescription = false
			}
		},

		onNameChangeDebounce: debounce(function(event) {
			this.onNameChange(event.target.value)
		}, 500),
		async onNameChange(name) {
			this.loadingName = true
			try {
				await editProvider(this.provider.id, ProviderEdit.Name, name)
			} catch (error) {
				console.error('Unable to edit name', name, error)
				showError(t('contacts', 'An error happened during name sync'))
			} finally {
				this.loadingName = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.app-content-details header,
.app-content-details section {
	max-width: 800px;
	margin: auto;
	margin-bottom: 36px;
	@media screen and (max-width: 1024px) {
		padding: 0 20px;

	}

	&:deep(.contact-header__avatar) {
		width: 75px !important;
	}

	&:deep(.contact-header__no-wrap) {
		flex-grow: 1;
	}

	&:deep(.contact-header__actions) {
		flex-grow: 0;
	}
}

.provider-name__loader {
	margin-left: 8px;
}

.provider-details {
	padding-inline: 20px;
}

.provider-details-section {
	&:not(:first-of-type) {
		margin-top: 24px;
	}

	&__actions {
		display: flex;
		a, button {
			margin-right: 8px;
		}
	}

	&__description {
		max-width: 800px;
	}
}

.avatar-box {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.avatar-list {
	display: flex;
	flex-wrap: wrap;
	flex-grow: 1;
	gap: 12px;
}

:deep(.app-content-list) {
	max-width: 100%;
	border: 0;
}

.provider-settings {
	margin: 12px;
}

.provider__icon {
	display: inline-block;
	width: 24px;
	height: 24px;
}

.resource {
	&__icon {
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		text-align: center;
		svg {
			width: 20px;
			height: 20px;
		}
		img {
			border-radius: var(--border-radius-pill);
			overflow: hidden;
			width: 32px;
			height: 32px;
		}
	}

	&:deep(.line-one__name) {
		font-weight: normal;
	}
}
</style>
