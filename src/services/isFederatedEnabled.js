/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

const isFederatedEnabled = loadState('contacts', 'isFederatedEnabled', false)
export default isFederatedEnabled
