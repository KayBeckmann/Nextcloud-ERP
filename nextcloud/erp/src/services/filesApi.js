import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function ensureErpFolder() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/files/erp-folder'))
	return data.ocs.data
}
